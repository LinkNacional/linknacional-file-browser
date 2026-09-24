<?php

namespace LinkNacional\Filebrowser\Includes;

class LinkNacionalFilebrowserActivator {

	/**
	 * Current schema version for the plugin tables.
	 */
	const DB_VERSION = '1.1.0';

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
	}
}
