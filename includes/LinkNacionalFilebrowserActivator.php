<?php

namespace LinkNacional\Filebrowser\Includes;

use LinkNacional\Filebrowser\Includes\LinkNacionalFilebrowserFiles;

class LinkNacionalFilebrowserActivator {

	/**
	 * Current schema version for the plugin tables.
	 */
	const DB_VERSION = '1.3.0';

	public static function activate() {
		self::create_tables();
		\update_option( 'linknacional_filebrowser_db_version', self::DB_VERSION );
		self::maybe_migrate_storage();
		LinkNacionalFilebrowserFiles::schedule_cleanup();
	}

	/**
	 * Runs dbDelta when the stored schema version is behind the current one.
	 * Hooked on `plugins_loaded` so upgrades apply without reactivation.
	 */
	public static function maybe_upgrade() {
		if ( \get_option( 'linknacional_filebrowser_db_version' ) !== self::DB_VERSION ) {
			self::create_tables();
			\update_option( 'linknacional_filebrowser_db_version', self::DB_VERSION );
		}
		self::maybe_migrate_storage();
		LinkNacionalFilebrowserFiles::schedule_cleanup();
	}

	private static function create_tables() {
		global $wpdb;

		$folders_table = $wpdb->prefix . 'linknacional_filebrowser_folders';
		$files_table   = $wpdb->prefix . 'linknacional_filebrowser_files';
		$tokens_table  = $wpdb->prefix . 'linknacional_filebrowser_tokens';

		$charset_collate = $wpdb->get_charset_collate();

		$sql_folders = "CREATE TABLE $folders_table (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			parent_id mediumint(9) DEFAULT 0,
			path text NOT NULL,
			is_favorite tinyint(1) NOT NULL DEFAULT 0,
			is_trashed tinyint(1) NOT NULL DEFAULT 0,
			trashed_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY parent_id (parent_id),
			KEY is_favorite (is_favorite),
			KEY is_trashed (is_trashed)
		) $charset_collate;";

		$sql_files = "CREATE TABLE $files_table (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL,
			original_name varchar(255) NOT NULL,
			folder_id mediumint(9) NOT NULL,
			file_type varchar(50) NOT NULL,
			file_size bigint(20) NOT NULL,
			file_path text NOT NULL,
			file_url text NOT NULL,
			description text,
			is_favorite tinyint(1) NOT NULL DEFAULT 0,
			allow_download tinyint(1) NOT NULL DEFAULT 1,
			is_trashed tinyint(1) NOT NULL DEFAULT 0,
			trashed_at datetime DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY folder_id (folder_id),
			KEY is_favorite (is_favorite),
			KEY is_trashed (is_trashed)
		) $charset_collate;";

		$sql_tokens = "CREATE TABLE $tokens_table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			file_id mediumint(9) NOT NULL,
			token_hash char(64) NOT NULL,
			expires_at datetime NOT NULL,
			created_by bigint(20) unsigned NOT NULL DEFAULT 0,
			revoked tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY token_hash (token_hash),
			KEY file_id (file_id),
			KEY expires_at (expires_at)
		) $charset_collate;";

		require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta( $sql_folders );
		\dbDelta( $sql_files );
		\dbDelta( $sql_tokens );
	}

	/**
	 * One-time move of stored files out of the public uploads folder and onto
	 * unguessable file names. Runs on every load until done, then is flagged.
	 */
	private static function maybe_migrate_storage() {
		if ( '2' === \get_option( 'linknacional_filebrowser_storage_migrated' ) ) {
			return;
		}

		$dir = self::resolve_storage_dir();
		\update_option( LinkNacionalFilebrowserFiles::STORAGE_OPTION, $dir );
		LinkNacionalFilebrowserFiles::ensure_dir( $dir );
		LinkNacionalFilebrowserFiles::ensure_dir( LinkNacionalFilebrowserFiles::cache_dir() );

		// Always drop the server guards. They are harmless when the folder lives
		// outside the webroot, and they protect Apache installs where it does not
		// (nginx ignores .htaccess — there the hashed names are the fallback).
		self::protect_upload_dir( $dir );

		$complete = self::relocate_files( $dir );
		if ( $complete ) {
			\update_option( 'linknacional_filebrowser_storage_migrated', '2' );
		}
	}

	/**
	 * Resolve where files should live: prefer a folder outside the webroot,
	 * falling back to the legacy uploads folder when that is not writable.
	 *
	 * @return string
	 */
	private static function resolve_storage_dir() {
		$preferred = untrailingslashit( LinkNacionalFilebrowserFiles::default_storage_dir() );
		if ( ( \is_dir( $preferred ) || wp_mkdir_p( $preferred ) ) && is_writable( $preferred ) ) {
			return $preferred;
		}
		$uploads  = wp_upload_dir();
		$fallback = untrailingslashit( $uploads['basedir'] . '/linknacional-filebrowser' );
		wp_mkdir_p( $fallback );
		return $fallback;
	}

	/**
	 * Move every file row's payload into the storage dir under a hashed name.
	 *
	 * @param string $dir Destination storage directory.
	 * @return bool True when nothing that still exists on disk was left behind.
	 */
	private static function relocate_files( $dir ) {
		global $wpdb;

		$table   = $wpdb->prefix . 'linknacional_filebrowser_files';
		$uploads = wp_upload_dir();
		$legacy  = untrailingslashit( $uploads['basedir'] . '/linknacional-filebrowser' );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results( "SELECT id, name, file_path FROM {$table}" );
		if ( ! is_array( $rows ) ) {
			return true;
		}

		$complete = true;
		foreach ( $rows as $row ) {
			// Already stored where it belongs?
			if ( ! empty( $row->file_path ) && 0 === strpos( wp_normalize_path( $row->file_path ), wp_normalize_path( $dir ) . '/' ) && \file_exists( $row->file_path ) ) {
				continue;
			}

			$current = ! empty( $row->file_path ) ? $row->file_path : $legacy . '/' . $row->name;
			if ( ! \file_exists( $current ) ) {
				$current = $legacy . '/' . $row->name;
			}
			// Source already gone (pre-existing missing file) — nothing to move.
			if ( ! \file_exists( $current ) ) {
				continue;
			}

			$base = pathinfo( $row->name, PATHINFO_FILENAME );
			if ( preg_match( '/^[A-Za-z0-9]{24}$/', $base ) ) {
				$target_name = $row->name;
			} else {
				$target_name = LinkNacionalFilebrowserFiles::hashed_filename( pathinfo( $row->name, PATHINFO_EXTENSION ), $dir );
			}
			$target = $dir . '/' . $target_name;

			if ( wp_normalize_path( $current ) !== wp_normalize_path( $target ) ) {
				if ( ! @rename( $current, $target ) ) {
					// A real file exists but could not be moved: retry later.
					$complete = false;
					continue;
				}
			}

			$wpdb->update(
				$table,
				array(
					'name'      => $target_name,
					'file_path' => $target,
				),
				array( 'id' => (int) $row->id ),
				array( '%s', '%s' ),
				array( '%d' )
			);
		}

		return $complete;
	}

	/**
	 * Drop server guards into a public storage folder (used only when the files
	 * could not be placed outside the webroot).
	 *
	 * @param string $dir Absolute directory.
	 */
	private static function protect_upload_dir( $dir ) {
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) {
			return;
		}

		$htaccess = $dir . '/.htaccess';
		$rules    = "# Link Nacional File Browser: block direct access to stored files.\n"
			. "<IfModule mod_authz_core.c>\n"
			. "Require all denied\n"
			. "</IfModule>\n"
			. "<IfModule !mod_authz_core.c>\n"
			. "Order allow,deny\n"
			. "Deny from all\n"
			. "</IfModule>\n";
		if ( ! \file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $htaccess, $rules );
		} else {
			$existing = (string) \file_get_contents( $htaccess );
			if ( false === strpos( $existing, 'Link Nacional File Browser' ) ) {
				// Preserve existing rules; just append our block.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
				@file_put_contents( $htaccess, rtrim( $existing ) . "\n\n" . $rules );
			}
		}

		$index = $dir . '/index.php';
		if ( ! \file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}
	}
}
