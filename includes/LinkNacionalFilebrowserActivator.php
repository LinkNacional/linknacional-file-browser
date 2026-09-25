<?php

namespace LinkNacional\Filebrowser\Includes;

class LinkNacionalFilebrowserActivator {

	/**
	 * Current schema version for the plugin tables.
	 */
	const DB_VERSION = '1.2.0';

	public static function activate() {
		self::create_tables();
		\update_option( 'linknacional_filebrowser_db_version', self::DB_VERSION );
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
		// One-time: give legacy files unguessable names (matters on servers that
		// ignore .htaccess, e.g. nginx).
		if ( ! \get_option( 'linknacional_filebrowser_files_hashed' ) ) {
			self::hash_legacy_files();
			\update_option( 'linknacional_filebrowser_files_hashed', 1 );
		}
	}

	private static function create_tables() {
		global $wpdb;

		$folders_table = $wpdb->prefix . 'linknacional_filebrowser_folders';
		$files_table   = $wpdb->prefix . 'linknacional_filebrowser_files';

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

		require_once \ABSPATH . 'wp-admin/includes/upgrade.php';
		\dbDelta( $sql_folders );
		\dbDelta( $sql_files );

		$upload_dir      = wp_upload_dir();
		$filebrowser_dir = $upload_dir['basedir'] . '/linknacional-filebrowser';

		if ( ! \file_exists( $filebrowser_dir ) ) {
			wp_mkdir_p( $filebrowser_dir );
		}

		self::protect_upload_dir( $filebrowser_dir );
	}

	/**
	 * Drop server guards into the upload directory so files cannot be fetched
	 * directly. Files are delivered only through the plugin's proxy endpoint.
	 *
	 * Apache honours .htaccess; servers that ignore it (e.g. nginx) fall back to
	 * the unguessable stored file names.
	 *
	 * @param string $dir Absolute upload directory.
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

	/**
	 * Best-effort, one-time rename of stored files to unguessable names.
	 * Skips files already hashed (24 alphanumeric chars before the extension)
	 * and any file missing on disk.
	 */
	private static function hash_legacy_files() {
		global $wpdb;

		$table   = $wpdb->prefix . 'linknacional_filebrowser_files';
		$uploads = wp_upload_dir();
		$dir     = untrailingslashit( $uploads['basedir'] . '/linknacional-filebrowser' );
		$url     = untrailingslashit( $uploads['baseurl'] . '/linknacional-filebrowser' );

		if ( ! is_dir( $dir ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results( "SELECT id, name, file_path FROM {$table}" );
		if ( ! is_array( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$current = ! empty( $row->file_path ) ? $row->file_path : $dir . '/' . $row->name;
			if ( ! \file_exists( $current ) ) {
				continue;
			}
			$base = pathinfo( $row->name, PATHINFO_FILENAME );
			if ( preg_match( '/^[A-Za-z0-9]{24}$/', $base ) ) {
				continue;
			}
			$stored  = LinkNacionalFilebrowserFiles::hashed_filename( pathinfo( $row->name, PATHINFO_EXTENSION ), $dir );
			$newpath = $dir . '/' . $stored;
			if ( ! @rename( $current, $newpath ) ) {
				continue;
			}
			$wpdb->update(
				$table,
				array(
					'name'      => $stored,
					'file_path' => $newpath,
					'file_url'  => $url . '/' . $stored,
				),
				array( 'id' => (int) $row->id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);
		}
	}
}
