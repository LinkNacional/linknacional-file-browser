<?php

namespace LinkNacional\Filebrowser\Includes;

use LinkNacional\Filebrowser\Includes\LinkNacionalFilebrowserLoader;
use LinkNacional\Filebrowser\Includes\LinkNacionalFilebrowserActivator;
use LinkNacional\Filebrowser\Admin\LinkNacionalFilebrowserAdmin;
use LinkNacional\Filebrowser\Public\LinkNacionalFilebrowserPublic;

class LinkNacionalFilebrowser {

	protected $loader;
	protected $plugin_name;
	protected $version;

	public function __construct() {
		if ( defined( 'LINKNACIONAL_FILEBROWSER_VERSION' ) ) {
			$this->version = LINKNACIONAL_FILEBROWSER_VERSION;
		} else {
			$this->version = '1.1.0';
		}
		$this->plugin_name = 'linknacional-file-browser';

		$this->load_dependencies();
		$this->define_core_hooks();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	private function load_dependencies() {
		$this->loader = new LinkNacionalFilebrowserLoader();
	}

	private function define_core_hooks() {
		$this->loader->add_action( 'plugins_loaded', LinkNacionalFilebrowserActivator::class, 'maybe_upgrade' );
	}

	private function define_admin_hooks() {
		$plugin_admin = new LinkNacionalFilebrowserAdmin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'add_admin_menu' );
		$this->loader->add_action( 'wp_ajax_linknacional_create_folder', $plugin_admin, 'create_folder_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_upload_file', $plugin_admin, 'upload_file_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_delete_folder', $plugin_admin, 'delete_folder_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_delete_file', $plugin_admin, 'delete_file_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_update_folder_name', $plugin_admin, 'update_folder_name_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_update_file_name', $plugin_admin, 'update_file_name_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_move_file', $plugin_admin, 'move_file_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_move_folder', $plugin_admin, 'move_folder_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_toggle_favorite', $plugin_admin, 'toggle_favorite_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_toggle_download', $plugin_admin, 'toggle_download_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_trash_items', $plugin_admin, 'trash_items_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_restore_items', $plugin_admin, 'restore_items_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_purge_items', $plugin_admin, 'purge_items_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_empty_trash', $plugin_admin, 'empty_trash_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_copy_item', $plugin_admin, 'copy_item_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_get_collection', $plugin_admin, 'get_collection_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_get_folder_contents', $plugin_admin, 'get_folder_contents_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_get_all_folders', $plugin_admin, 'get_all_folders_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_get_folder_files', $plugin_admin, 'get_folder_files_ajax' );
		$this->loader->add_action( 'wp_ajax_linknacional_get_all_folders_admin_frontend', $plugin_admin, 'get_all_folders_admin_frontend' );
		$this->loader->add_action( 'wp_ajax_nopriv_linknacional_get_admin_nonce', $plugin_admin, 'linknacional_get_admin_nonce');
		$this->loader->add_action( 'wp_ajax_linknacional_get_admin_nonce', $plugin_admin, 'linknacional_get_admin_nonce');
		$this->loader->add_action( 'wp_ajax_linknacional_migrate', $plugin_admin, 'migrate_ajax' );
	}

	private function define_public_hooks() {
		$plugin_public = new LinkNacionalFilebrowserPublic( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'init', $plugin_public, 'register_shortcode' );
		$this->loader->add_action( 'wp_ajax_linknacional_frontend_get_contents', $plugin_public, 'get_folder_contents_frontend' );
		$this->loader->add_action( 'wp_ajax_nopriv_linknacional_frontend_get_contents', $plugin_public, 'get_folder_contents_frontend' );
		$this->loader->add_action( 'wp_ajax_linknacional_frontend_get_all_folders', $plugin_public, 'get_all_folders_frontend' );
		$this->loader->add_action( 'wp_ajax_nopriv_linknacional_frontend_get_all_folders', $plugin_public, 'get_all_folders_frontend' );
		$this->loader->add_action( 'wp_ajax_linknacional_frontend_get_collection', $plugin_public, 'get_collection_frontend' );
		$this->loader->add_action( 'wp_ajax_nopriv_linknacional_frontend_get_collection', $plugin_public, 'get_collection_frontend' );
		$this->loader->add_action( 'wp_ajax_linknacional_frontend_search', $plugin_public, 'search_files_frontend' );
		$this->loader->add_action( 'wp_ajax_nopriv_linknacional_frontend_search', $plugin_public, 'search_files_frontend' );
		$this->loader->add_action( 'wp_ajax_linknacional_frontend_get_folder_files', $plugin_public, 'get_folder_files_frontend' );
		$this->loader->add_action( 'wp_ajax_nopriv_linknacional_frontend_get_folder_files', $plugin_public, 'get_folder_files_frontend' );
		$this->loader->add_action( 'wp_ajax_nopriv_linknacional_get_public_nonce', $plugin_public, 'linknacional_get_public_nonce');
		$this->loader->add_action( 'wp_ajax_linknacional_get_public_nonce', $plugin_public, 'linknacional_get_public_nonce');

		// File delivery endpoint — enforces the per-file download restriction for visitors.
		$this->loader->add_action( 'wp_ajax_linknacional_serve_file', $plugin_public, 'serve_file_ajax' );
		$this->loader->add_action( 'wp_ajax_nopriv_linknacional_serve_file', $plugin_public, 'serve_file_ajax' );

		// Prevent LiteSpeed Cache from combining/minifying FontAwesome bundle
		$this->loader->add_filter( 'script_loader_tag', $this, 'add_no_optimize_attr', 10, 3 );
	}

	public function run() {
		$this->loader->run();
	}

	public function get_plugin_name() {
		return $this->plugin_name;
	}

	public function get_loader() {
		return $this->loader;
	}

	public function get_version() {
		return $this->version;
	}

	public function add_no_optimize_attr( $tag, $handle, $src ) {
		if ( 'linknacional-filebrowser-fontawesome' === $handle ) {
			$tag = str_replace( '<script ', '<script data-no-optimize="1" data-no-defer="1" ', $tag );
		}
		return $tag;
	}
}
