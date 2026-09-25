<?php

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// Custom tables — no WP core API exists. $wpdb is the only correct approach.

namespace LinkNacional\Filebrowser\Admin;

use LinkNacional\Filebrowser\Includes\LinkNacionalFilebrowserFiles;

class LinkNacionalFilebrowserAdmin {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	public function enqueue_styles( $hook_suffix ) {
		if ( 'toplevel_page_linknacional-filebrowser' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_script( 'linknacional-filebrowser-fontawesome', LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'assets/js/compiled/fontawesome.compiled.js', array(), LINKNACIONAL_FILEBROWSER_VERSION, false );
		wp_enqueue_style( $this->plugin_name, LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'admin/css/linknacional-filebrowser-admin.css', array(), LINKNACIONAL_FILEBROWSER_VERSION, 'all' );
	}

	public function enqueue_scripts( $hook_suffix ) {
		if ( 'toplevel_page_linknacional-filebrowser' !== $hook_suffix ) {
			return;
		}
		wp_enqueue_script( $this->plugin_name, LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'admin/js/linknacional-filebrowser-admin.js', array( 'jquery' ), LINKNACIONAL_FILEBROWSER_VERSION, false );
		wp_localize_script( $this->plugin_name, 'linknacional_ajax', array(
			'ajax_url'          => admin_url( 'admin-ajax.php' ),
			'home'              => esc_html__( 'Home', 'linknacional-file-browser' ),
			'folder_text'       => esc_html__( 'Folder', 'linknacional-file-browser' ),
			'items_text'        => esc_html__( 'items', 'linknacional-file-browser' ),
			'loading_text'      => esc_html__( 'Loading…', 'linknacional-file-browser' ),
			'copied_text'       => esc_html__( 'Copied!', 'linknacional-file-browser' ),
			'saved_text'        => esc_html__( 'Saved', 'linknacional-file-browser' ),
			'uploading_text'    => esc_html__( 'Uploading files…', 'linknacional-file-browser' ),
			'empty_folder_text' => esc_html__( 'This folder is empty', 'linknacional-file-browser' ),
			'empty_folder_hint' => esc_html__( 'Drag files here or use the Upload button to add content.', 'linknacional-file-browser' ),
			'no_results_text'   => esc_html__( 'No matching items', 'linknacional-file-browser' ),
			'error_loading_text' => esc_html__( 'Could not load contents', 'linknacional-file-browser' ),
			'error_generic'     => esc_html__( 'Something went wrong. Please try again.', 'linknacional-file-browser' ),
			'name_empty_error'  => esc_html__( 'Name cannot be empty', 'linknacional-file-browser' ),
			'name_exists_error' => esc_html__( 'An item with this name already exists', 'linknacional-file-browser' ),
			'create_folder_error' => esc_html__( 'Error creating folder', 'linknacional-file-browser' ),
			'update_error'      => esc_html__( 'Error updating name', 'linknacional-file-browser' ),
			'unknown_error'     => esc_html__( 'Unknown error', 'linknacional-file-browser' ),
			'expand_collapse'   => esc_html__( 'Expand/Collapse', 'linknacional-file-browser' ),
			'hide_subfolders'   => esc_html__( 'Hide Subfolders', 'linknacional-file-browser' ),
			'show_subfolders'   => esc_html__( 'Show Subfolders', 'linknacional-file-browser' ),
			'show_folders'      => esc_html__( 'Show folders', 'linknacional-file-browser' ),
			'hide_folders'      => esc_html__( 'Hide folders', 'linknacional-file-browser' ),
			'collapse'          => esc_html__( 'Collapse', 'linknacional-file-browser' ),
			'confirm_edit'      => esc_html__( 'Confirm', 'linknacional-file-browser' ),
			'cancel'            => esc_html__( 'Cancel', 'linknacional-file-browser' ),
			'save'              => esc_html__( 'Save', 'linknacional-file-browser' ),
			'create'            => esc_html__( 'Create folder', 'linknacional-file-browser' ),
			'edit_name'         => esc_html__( 'Rename', 'linknacional-file-browser' ),
			'delete_folder'     => esc_html__( 'Delete folder', 'linknacional-file-browser' ),
			'delete_file'       => esc_html__( 'Delete file', 'linknacional-file-browser' ),
			'open_file'         => esc_html__( 'Open', 'linknacional-file-browser' ),
			'download'          => esc_html__( 'Download', 'linknacional-file-browser' ),
			'copy_link'         => esc_html__( 'Copy link', 'linknacional-file-browser' ),
			'move_to'           => esc_html__( 'Move to…', 'linknacional-file-browser' ),
			'preview'           => esc_html__( 'Preview', 'linknacional-file-browser' ),
			'more_actions'      => esc_html__( 'More actions', 'linknacional-file-browser' ),
			'rename_title'      => esc_html__( 'Rename', 'linknacional-file-browser' ),
			'rename_folder_lbl' => esc_html__( 'Folder name', 'linknacional-file-browser' ),
			'rename_file_lbl'   => esc_html__( 'File name', 'linknacional-file-browser' ),
			'delete_title'      => esc_html__( 'Delete item', 'linknacional-file-browser' ),
			'delete_selected_title' => esc_html__( 'Delete selected items', 'linknacional-file-browser' ),
			'delete_one_msg'    => esc_html__( '“%s” will be permanently deleted. This cannot be undone.', 'linknacional-file-browser' ),
			'delete_folder_warn' => esc_html__( 'Everything inside this folder will be deleted too.', 'linknacional-file-browser' ),
			'delete_many_msg'   => esc_html__( '%d items will be permanently deleted. This cannot be undone.', 'linknacional-file-browser' ),
			'delete'            => esc_html__( 'Delete', 'linknacional-file-browser' ),
			'link_copied'       => esc_html__( 'Link copied to clipboard', 'linknacional-file-browser' ),
			'copied_fallback'   => esc_html__( 'Copy this link:', 'linknacional-file-browser' ),
			'selected_items'    => esc_html__( '%d selected', 'linknacional-file-browser' ),
			'move_here'         => esc_html__( 'Move here', 'linknacional-file-browser' ),
			'moved_ok'          => esc_html__( '“%s” moved', 'linknacional-file-browser' ),
			'move_error'        => esc_html__( 'Could not move the item', 'linknacional-file-browser' ),
			'drop_upload'       => esc_html__( 'Drop files to upload', 'linknacional-file-browser' ),
			'uploaded_ok'       => esc_html__( 'Upload complete', 'linknacional-file-browser' ),
			'uploaded_partial'  => esc_html__( 'Some files could not be uploaded', 'linknacional-file-browser' ),
			'upload_error'      => esc_html__( 'Error uploading files', 'linknacional-file-browser' ),
			'upload_failed'     => esc_html__( 'Upload failed', 'linknacional-file-browser' ),
			'retry'             => esc_html__( 'Retry', 'linknacional-file-browser' ),
			'clear'             => esc_html__( 'Clear', 'linknacional-file-browser' ),
			'close'             => esc_html__( 'Close', 'linknacional-file-browser' ),
			'folder_created'    => esc_html__( 'Folder “%s” created', 'linknacional-file-browser' ),
			'folder_renamed'    => esc_html__( 'Folder renamed', 'linknacional-file-browser' ),
			'file_renamed'      => esc_html__( 'File renamed', 'linknacional-file-browser' ),
			'deleted_ok'        => esc_html__( 'Deleted', 'linknacional-file-browser' ),
			'deleted_partial'   => esc_html__( 'Some items could not be deleted', 'linknacional-file-browser' ),
			'delete_error'      => esc_html__( 'Could not delete the items', 'linknacional-file-browser' ),
			'already_there'     => esc_html__( 'It is already in this folder', 'linknacional-file-browser' ),
			'file_type_warn'    => esc_html__( 'File type not allowed', 'linknacional-file-browser' ),
			'migrating_text'    => esc_html__( 'Migrating…', 'linknacional-file-browser' ),
			'migrate_text'      => esc_html__( 'Migrate Now', 'linknacional-file-browser' ),
			'migrate_error'     => esc_html__( 'Migration failed. Please try again.', 'linknacional-file-browser' ),
			'just_now'          => esc_html__( 'just now', 'linknacional-file-browser' ),
			'details'           => esc_html__( 'Details', 'linknacional-file-browser' ),
			'detail_name'       => esc_html__( 'Name', 'linknacional-file-browser' ),
			'detail_type'       => esc_html__( 'Type', 'linknacional-file-browser' ),
			'detail_size'       => esc_html__( 'Size', 'linknacional-file-browser' ),
			'detail_location'   => esc_html__( 'Location', 'linknacional-file-browser' ),
			'detail_modified'   => esc_html__( 'Modified', 'linknacional-file-browser' ),
			'copy_url'          => esc_html__( 'Copy URL', 'linknacional-file-browser' ),
			'quick_actions'     => esc_html__( 'Quick actions', 'linknacional-file-browser' ),
			'col_home'          => esc_html__( 'Home', 'linknacional-file-browser' ),
			'col_favorites'     => esc_html__( 'Favorites', 'linknacional-file-browser' ),
			'col_recent'        => esc_html__( 'Recent', 'linknacional-file-browser' ),
			'col_trash'         => esc_html__( 'Trash', 'linknacional-file-browser' ),
			'section_folders'   => esc_html__( 'Folders', 'linknacional-file-browser' ),
			'new_folder'        => esc_html__( 'New folder', 'linknacional-file-browser' ),
			'upload_tile_title' => esc_html__( 'Upload files', 'linknacional-file-browser' ),
			'upload_tile_hint'  => esc_html__( 'Click to browse or drag files here', 'linknacional-file-browser' ),
			'favorites_empty'   => esc_html__( 'No favorites yet', 'linknacional-file-browser' ),
			'recent_empty'      => esc_html__( 'No recent files', 'linknacional-file-browser' ),
			'trash_empty'       => esc_html__( 'Trash is empty', 'linknacional-file-browser' ),
			'move_to_trash'     => esc_html__( 'Move to trash', 'linknacional-file-browser' ),
			'restore'           => esc_html__( 'Restore', 'linknacional-file-browser' ),
			'delete_forever'    => esc_html__( 'Delete permanently', 'linknacional-file-browser' ),
			'trashed_ok'        => esc_html__( 'Moved to trash', 'linknacional-file-browser' ),
			'restored_ok'       => esc_html__( 'Restored', 'linknacional-file-browser' ),
			'purged_ok'         => esc_html__( 'Deleted permanently', 'linknacional-file-browser' ),
			'favorite_on'       => esc_html__( 'Added to favorites', 'linknacional-file-browser' ),
			'favorite_off'      => esc_html__( 'Removed from favorites', 'linknacional-file-browser' ),
			'add_favorite'      => esc_html__( 'Add to favorites', 'linknacional-file-browser' ),
			'remove_favorite'   => esc_html__( 'Remove from favorites', 'linknacional-file-browser' ),
			'open_new_tab'      => esc_html__( 'Open in new tab', 'linknacional-file-browser' ),
			'copy_to'           => esc_html__( 'Copy to…', 'linknacional-file-browser' ),
			'copied_ok'         => esc_html__( 'Copied', 'linknacional-file-browser' ),
			'copy_error'        => esc_html__( 'Could not copy the item', 'linknacional-file-browser' ),
			'trash_confirm'     => esc_html__( '“%s” will be moved to the trash.', 'linknacional-file-browser' ),
			'purge_confirm'     => esc_html__( '“%s” will be permanently deleted. This cannot be undone.', 'linknacional-file-browser' ),
			'type_image'        => esc_html__( 'Image', 'linknacional-file-browser' ),
			'type_pdf'          => esc_html__( 'PDF', 'linknacional-file-browser' ),
			'type_word'         => esc_html__( 'Document', 'linknacional-file-browser' ),
			'type_excel'        => esc_html__( 'Spreadsheet', 'linknacional-file-browser' ),
			'type_powerpoint'   => esc_html__( 'Presentation', 'linknacional-file-browser' ),
			'type_text'         => esc_html__( 'Text', 'linknacional-file-browser' ),
			'type_audio'        => esc_html__( 'Audio', 'linknacional-file-browser' ),
			'type_video'        => esc_html__( 'Video', 'linknacional-file-browser' ),
			'type_archive'      => esc_html__( 'Archive', 'linknacional-file-browser' ),
			'type_file'         => esc_html__( 'File', 'linknacional-file-browser' ),
			'copy_ai'           => esc_html__( 'Copy for AI', 'linknacional-file-browser' ),
			'ai_copied'         => esc_html__( 'Copied for AI', 'linknacional-file-browser' ),
			'ai_nothing'        => esc_html__( 'Nothing to copy', 'linknacional-file-browser' ),
			'ai_title'          => esc_html__( 'File Browser', 'linknacional-file-browser' ),
			'ai_url'            => esc_html__( 'URL', 'linknacional-file-browser' ),
			'ai_description'    => esc_html__( 'Description', 'linknacional-file-browser' ),
			'restrict_download' => esc_html__( 'Restrict download', 'linknacional-file-browser' ),
			'allow_download'    => esc_html__( 'Allow download', 'linknacional-file-browser' ),
			'download_locked'   => esc_html__( 'Download restricted', 'linknacional-file-browser' ),
			'download_unlocked' => esc_html__( 'Download allowed', 'linknacional-file-browser' ),
		));
	}

	public function add_admin_menu() {
		add_menu_page(
			esc_html__('File Browser', 'linknacional-file-browser'),
			esc_html__('File Browser', 'linknacional-file-browser'),
			'manage_options',
			'linknacional-filebrowser',
			array($this, 'admin_page'),
			'dashicons-portfolio',
			30
		);
	}

	/**
	 * Controller for the admin page.
	 *
	 * Prepares the view data and delegates rendering to the partial (data/template split).
	 */
	public function admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'linknacional-file-browser' ) );
		}

		$data = $this->get_admin_page_data();

		require LINKNACIONAL_FILEBROWSER_PLUGIN_PATH . 'admin/partials/linknacional-filebrowser-admin-display.php';
	}

	/**
	 * Build the data consumed by the admin view.
	 *
	 * @return array
	 */
	private function get_admin_page_data() {
		return array(
			'title'              => __( 'File Browser Manager', 'linknacional-file-browser' ),
			'shortcode'          => '[linkn_filebrowser]',
			'migration_available' => $this->old_tables_exist(),
			'allowed_extensions' => $this->get_allowed_extensions(),
			'max_upload_size_label' => size_format( wp_max_upload_size() ),
		);
	}

	/**
	 * Allowed upload mime map (single source of truth for upload validation).
	 *
	 * @return array
	 */
	private function get_allowed_mimes() {
		return array(
			'pdf'          => 'application/pdf',
			'doc'          => 'application/msword',
			'docx'         => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xls'          => 'application/vnd.ms-excel',
			'xlsx'         => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'ppt'          => 'application/vnd.ms-powerpoint',
			'pptx'         => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
			'txt'          => 'text/plain',
			'jpg|jpeg|jpe' => 'image/jpeg',
			'png'          => 'image/png',
			'gif'          => 'image/gif',
			'webp'         => 'image/webp',
		);
	}

	/**
	 * Accepted extensions for the file input's `accept` attribute.
	 *
	 * @return string
	 */
	private function get_allowed_extensions() {
		return '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.gif,.webp';
	}

	public function linknacional_get_admin_nonce() {
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html__( 'Invalid request method.', 'linknacional-file-browser' ) );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$action_name = isset( $_POST['action_name'] ) ? sanitize_text_field( wp_unslash( $_POST['action_name'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $action_name ) {
			wp_send_json_error( esc_html__( 'Action name required', 'linknacional-file-browser' ) );
		}
		$nonce = wp_create_nonce( $action_name );
		wp_send_json_success( array( 'nonce' => $nonce ) );
	}

	public function create_folder_ajax() {
		check_ajax_referer('linknacional_filebrowser_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions', 'linknacional-file-browser'));
		}
		$folder_name = isset( $_POST['folder_name'] ) ? sanitize_text_field( wp_unslash( $_POST['folder_name'] ) ) : '';
		$parent_id = isset( $_POST['parent_id'] ) ? intval( wp_unslash( $_POST['parent_id'] ) ) : 0;
		if (empty($folder_name)) {
			wp_send_json_error(esc_html__('Folder name is required', 'linknacional-file-browser'));
		}
		global $wpdb;
		if ( $this->folder_name_exists( $folder_name, $parent_id ) ) {
			wp_send_json_error( esc_html__( 'A folder with this name already exists in this location', 'linknacional-file-browser' ) );
		}
		$path = $this->build_folder_path($parent_id) . '/' . $folder_name;
		$result = $wpdb->insert(
			$this->table_folders(),
			array('name' => $folder_name, 'parent_id' => $parent_id, 'path' => $path),
			array('%s', '%d', '%s')
		);
		if ($result === false) {
			wp_send_json_error(esc_html__('Failed to create folder', 'linknacional-file-browser'));
		}
		wp_send_json_success(array(
			'message' => sprintf(
				/* translators: %s: folder name */
				esc_html__('Folder “%s” created', 'linknacional-file-browser'),
				$folder_name
			),
			'folder_id' => $wpdb->insert_id,
			'name' => $folder_name,
		));
	}

	public function upload_file_ajax() {
		check_ajax_referer('linknacional_filebrowser_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions', 'linknacional-file-browser'));
		}
		$folder_id = isset( $_POST['folder_id'] ) ? intval( wp_unslash( $_POST['folder_id'] ) ) : 0;
		if (empty($_FILES['files'])) {
			wp_send_json_error(esc_html__('No files uploaded', 'linknacional-file-browser'));
		}
		$upload_dir = wp_upload_dir();
		$filebrowser_dir = $upload_dir['basedir'] . '/linknacional-filebrowser';
		$filebrowser_url = $upload_dir['baseurl'] . '/linknacional-filebrowser';

		if ( ! file_exists( $filebrowser_dir ) ) {
			wp_mkdir_p( $filebrowser_dir );
		}

		$filter_upload_dir = function ( $dirs ) use ( $filebrowser_dir, $filebrowser_url ) {
			return array(
				'path'    => $filebrowser_dir,
				'url'     => $filebrowser_url,
				'subdir'  => '',
				'basedir' => $filebrowser_dir,
				'baseurl' => $filebrowser_url,
				'error'   => false,
			);
		};
		add_filter( 'upload_dir', $filter_upload_dir );

		$overrides = array(
			'test_form' => false,
			'mimes'     => $this->get_allowed_mimes(),
		);

		$uploaded_files = array();
		$failed_files   = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$files = $_FILES['files'];
		for ( $i = 0; $i < count( $files['name'] ); $i++ ) {
			if ( UPLOAD_ERR_OK !== $files['error'][$i] ) {
				$failed_files[] = sanitize_file_name( $files['name'][$i] );
				continue;
			}

			$original_name = sanitize_file_name( $files['name'][$i] );
			$file_size     = $files['size'][$i];

			$single_file = array(
				'name'     => $files['name'][$i],
				'type'     => $files['type'][$i],
				'tmp_name' => $files['tmp_name'][$i],
				'error'    => $files['error'][$i],
				'size'     => $files['size'][$i],
			);

			$movefile = wp_handle_upload( $single_file, $overrides );

			if ( isset( $movefile['error'] ) ) {
				$failed_files[] = $original_name;
				continue;
			}

			// Store under an unguessable name; the display name stays in original_name.
			$stored   = LinkNacionalFilebrowserFiles::hashed_filename( pathinfo( $movefile['file'], PATHINFO_EXTENSION ), $filebrowser_dir );
			$new_path = $filebrowser_dir . '/' . $stored;
			if ( @rename( $movefile['file'], $new_path ) ) {
				$filename  = $stored;
				$file_path = $new_path;
			} else {
				$filename  = basename( $movefile['file'] );
				$file_path = $movefile['file'];
			}
			$file_url = $filebrowser_url . '/' . $filename;
			$file_ext = pathinfo( $filename, PATHINFO_EXTENSION );

			// Keep display names unique within the folder: "logo.png", "logo (1).png", …
			$original_name = $this->unique_file_name_in_folder( $original_name, $folder_id );

			global $wpdb;
			$result = $wpdb->insert(
				$this->table_files(),
				array(
					'name'          => $filename,
					'original_name' => $original_name,
					'folder_id'     => $folder_id,
					'file_type'     => $file_ext,
					'file_size'     => $file_size,
					'file_path'     => $file_path,
					'file_url'      => $file_url,
				),
				array( '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
			);

			if ( false !== $result ) {
				$uploaded_files[] = array(
					'id'   => $wpdb->insert_id,
					'name' => $original_name,
					'size' => $file_size,
					'type' => $file_ext,
				);
			} else {
				$failed_files[] = $original_name;
			}
		}

		remove_filter( 'upload_dir', $filter_upload_dir );
		if (empty($uploaded_files)) {
			wp_send_json_error(esc_html__('Failed to upload files', 'linknacional-file-browser'));
		}
		wp_send_json_success(array(
			'message' => esc_html__('Files uploaded successfully', 'linknacional-file-browser'),
			'files'   => $uploaded_files,
			'failed'  => $failed_files,
		));
	}

	public function delete_folder_ajax() {
		check_ajax_referer('linknacional_filebrowser_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions', 'linknacional-file-browser'));
		}
		$folder_id = isset( $_POST['folder_id'] ) ? intval( wp_unslash( $_POST['folder_id'] ) ) : 0;
		global $wpdb;
		$this->delete_folder_recursive($folder_id);
		wp_send_json_success(esc_html__('Folder deleted successfully', 'linknacional-file-browser'));
	}

	public function delete_file_ajax() {
		check_ajax_referer('linknacional_filebrowser_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions', 'linknacional-file-browser'));
		}
		$file_id = isset( $_POST['file_id'] ) ? intval( wp_unslash( $_POST['file_id'] ) ) : 0;
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$file = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_files()} WHERE id = %d", $file_id));
		if ($file) {
			if ( file_exists( $file->file_path ) ) {
				wp_delete_file( $file->file_path );
			}
			$wpdb->delete($this->table_files(), array('id' => $file_id), array('%d'));
		}
		wp_send_json_success(esc_html__('File deleted successfully', 'linknacional-file-browser'));
	}

	/* ------------------------------------------------------------------ *
	 *  Collections, favorites, trash & copy
	 * ------------------------------------------------------------------ */

	/**
	 * Toggle the favorite flag of a file or folder.
	 */
	public function toggle_favorite_ajax() {
		check_ajax_referer( 'linknacional_filebrowser_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		$type = isset( $_POST['item_type'] ) ? sanitize_key( wp_unslash( $_POST['item_type'] ) ) : '';
		$id   = isset( $_POST['id'] ) ? intval( wp_unslash( $_POST['id'] ) ) : 0;
		if ( $id <= 0 || ! in_array( $type, array( 'file', 'folder' ), true ) ) {
			wp_send_json_error( esc_html__( 'Invalid item', 'linknacional-file-browser' ) );
		}
		global $wpdb;
		$table = $type === 'folder' ? $this->table_folders() : $this->table_files();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT is_favorite FROM {$table} WHERE id = %d", $id ) );
		if ( null === $current ) {
			wp_send_json_error( esc_html__( 'Item not found', 'linknacional-file-browser' ) );
		}
		$new_value = $current ? 0 : 1;
		$wpdb->update( $table, array( 'is_favorite' => $new_value ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
		wp_send_json_success( array(
			'is_favorite' => (bool) $new_value,
			'message'     => $new_value
				? esc_html__( 'Added to favorites', 'linknacional-file-browser' )
				: esc_html__( 'Removed from favorites', 'linknacional-file-browser' ),
		) );
	}

	/**
	 * Toggle the download restriction flag of a file. When restricted (0), the
	 * file is only downloadable by managers; visitors are blocked server-side.
	 */
	public function toggle_download_ajax() {
		check_ajax_referer( 'linknacional_filebrowser_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		$id = isset( $_POST['id'] ) ? intval( wp_unslash( $_POST['id'] ) ) : 0;
		if ( $id <= 0 ) {
			wp_send_json_error( esc_html__( 'Invalid item', 'linknacional-file-browser' ) );
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$current = $wpdb->get_var( $wpdb->prepare( "SELECT allow_download FROM {$this->table_files()} WHERE id = %d", $id ) );
		if ( null === $current ) {
			wp_send_json_error( esc_html__( 'Item not found', 'linknacional-file-browser' ) );
		}
		$new_value = $current ? 0 : 1;
		$wpdb->update( $this->table_files(), array( 'allow_download' => $new_value ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
		wp_send_json_success( array(
			'allow_download' => (bool) $new_value,
			'message'        => $new_value
				? esc_html__( 'Download allowed', 'linknacional-file-browser' )
				: esc_html__( 'Download restricted', 'linknacional-file-browser' ),
		) );
	}

	/**
	 * Return the items that belong to a collection: favorites | recent | trash.
	 */
	public function get_collection_ajax() {
		check_ajax_referer( 'linknacional_filebrowser_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		$collection = isset( $_POST['collection'] ) ? sanitize_key( wp_unslash( $_POST['collection'] ) ) : '';
		global $wpdb;
		$folders = array();
		$files   = array();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		switch ( $collection ) {
			case 'favorites':
				$folders = $wpdb->get_results( "SELECT * FROM {$this->table_folders()} WHERE is_favorite = 1 AND is_trashed = 0 ORDER BY name ASC" );
				$files   = $wpdb->get_results( "SELECT f.*, folder.name AS folder_name FROM {$this->table_files()} f LEFT JOIN {$this->table_folders()} folder ON f.folder_id = folder.id WHERE f.is_favorite = 1 AND f.is_trashed = 0 ORDER BY f.original_name ASC" );
				break;
			case 'recent':
				$files = $wpdb->get_results( "SELECT f.*, folder.name AS folder_name FROM {$this->table_files()} f LEFT JOIN {$this->table_folders()} folder ON f.folder_id = folder.id WHERE f.is_trashed = 0 ORDER BY f.created_at DESC, f.id DESC LIMIT 50" );
				break;
			case 'trash':
				$folders = $wpdb->get_results( "SELECT * FROM {$this->table_folders()} WHERE is_trashed = 1 ORDER BY name ASC" );
				$files   = $wpdb->get_results( "SELECT f.*, folder.name AS folder_name FROM {$this->table_files()} f LEFT JOIN {$this->table_folders()} folder ON f.folder_id = folder.id WHERE f.is_trashed = 1 ORDER BY f.original_name ASC" );
				break;
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$folders = $this->attach_folder_item_counts( $folders );
		$files   = LinkNacionalFilebrowserFiles::prepare_rows( $files );
		wp_send_json_success( array( 'folders' => $folders, 'files' => $files ) );
	}

	/**
	 * Move items to the trash (soft delete). Trashing a folder also trashes its subtree.
	 */
	public function trash_items_ajax() {
		check_ajax_referer( 'linknacional_filebrowser_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		$items = $this->parse_items_param();
		if ( empty( $items ) ) {
			wp_send_json_error( esc_html__( 'No items provided', 'linknacional-file-browser' ) );
		}
		global $wpdb;
		$now = current_time( 'mysql' );
		foreach ( $items as $item ) {
			if ( $item['type'] === 'folder' ) {
				$this->set_folder_trashed_recursive( $item['id'], 1, $now );
			} else {
				$wpdb->update( $this->table_files(), array( 'is_trashed' => 1, 'trashed_at' => $now ), array( 'id' => $item['id'] ), array( '%d', '%s' ), array( '%d' ) );
			}
		}
		wp_send_json_success( array( 'message' => esc_html__( 'Moved to trash', 'linknacional-file-browser' ) ) );
	}

	/**
	 * Restore items from the trash.
	 */
	public function restore_items_ajax() {
		check_ajax_referer( 'linknacional_filebrowser_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		$items = $this->parse_items_param();
		if ( empty( $items ) ) {
			wp_send_json_error( esc_html__( 'No items provided', 'linknacional-file-browser' ) );
		}
		global $wpdb;
		foreach ( $items as $item ) {
			if ( $item['type'] === 'folder' ) {
				$this->set_folder_trashed_recursive( $item['id'], 0, null );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$parent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT parent_id FROM {$this->table_folders()} WHERE id = %d", $item['id'] ) );
				if ( $parent > 0 ) {
					$this->restore_ancestors( $parent );
				}
			} else {
				$wpdb->update( $this->table_files(), array( 'is_trashed' => 0, 'trashed_at' => null ), array( 'id' => $item['id'] ), array( '%d', '%s' ), array( '%d' ) );
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$folder_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT folder_id FROM {$this->table_files()} WHERE id = %d", $item['id'] ) );
				if ( $folder_id > 0 ) {
					$this->restore_ancestors( $folder_id );
				}
			}
		}
		wp_send_json_success( array( 'message' => esc_html__( 'Items restored', 'linknacional-file-browser' ) ) );
	}

	/**
	 * Permanently delete items (files/folders and their subtree).
	 */
	public function purge_items_ajax() {
		check_ajax_referer( 'linknacional_filebrowser_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		$items = $this->parse_items_param();
		if ( empty( $items ) ) {
			wp_send_json_error( esc_html__( 'No items provided', 'linknacional-file-browser' ) );
		}
		global $wpdb;
		foreach ( $items as $item ) {
			if ( $item['type'] === 'folder' ) {
				$this->delete_folder_recursive( $item['id'] );
			} else {
				$this->purge_file( $item['id'] );
			}
		}
		wp_send_json_success( array( 'message' => esc_html__( 'Items permanently deleted', 'linknacional-file-browser' ) ) );
	}

	/**
	 * Permanently delete every trashed folder and file.
	 */
	public function empty_trash_ajax() {
		check_ajax_referer( 'linknacional_filebrowser_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$folder_ids = $wpdb->get_col( "SELECT id FROM {$this->table_folders()} WHERE is_trashed = 1" );
		foreach ( $folder_ids as $fid ) {
			$this->delete_folder_recursive( (int) $fid );
		}
		$file_ids = $wpdb->get_col( "SELECT id FROM {$this->table_files()} WHERE is_trashed = 1" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $file_ids as $fid ) {
			$this->purge_file( (int) $fid );
		}
		wp_send_json_success( array( 'message' => esc_html__( 'Trash emptied', 'linknacional-file-browser' ) ) );
	}

	/**
	 * Copy a file (or folder subtree) into a target folder.
	 */
	public function copy_item_ajax() {
		check_ajax_referer( 'linknacional_filebrowser_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		$type   = isset( $_POST['item_type'] ) ? sanitize_key( wp_unslash( $_POST['item_type'] ) ) : '';
		$id     = isset( $_POST['id'] ) ? intval( wp_unslash( $_POST['id'] ) ) : 0;
		$target = isset( $_POST['target_folder_id'] ) ? intval( wp_unslash( $_POST['target_folder_id'] ) ) : 0;
		if ( $id <= 0 || ! in_array( $type, array( 'file', 'folder' ), true ) ) {
			wp_send_json_error( esc_html__( 'Invalid item', 'linknacional-file-browser' ) );
		}
		if ( $target > 0 && ! $this->folder_is_active( $target ) ) {
			wp_send_json_error( esc_html__( 'Destination folder not found', 'linknacional-file-browser' ) );
		}
		if ( $type === 'folder' ) {
			if ( $id === $target || $this->is_folder_descendant( $target, $id ) ) {
				wp_send_json_error( esc_html__( 'A folder cannot be copied into itself or its subfolders', 'linknacional-file-browser' ) );
			}
			$this->copy_folder_recursive( $id, $target );
		} else {
			$this->copy_file( $id, $target );
		}
		wp_send_json_success( array( 'message' => esc_html__( 'Copied successfully', 'linknacional-file-browser' ) ) );
	}

	/**
	 * Copy a single file row + its physical file into a target folder.
	 */
	private function copy_file( $file_id, $target_folder_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$file = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_files()} WHERE id = %d", $file_id ) );
		if ( ! $file ) {
			return false;
		}
		$new_name = $this->unique_file_name_in_folder( $file->original_name, $target_folder_id );
		$upload   = wp_upload_dir();
		$dir      = $upload['basedir'] . '/linknacional-filebrowser';
		$url      = $upload['baseurl'] . '/linknacional-filebrowser';

		$stored   = LinkNacionalFilebrowserFiles::hashed_filename( pathinfo( $new_name, PATHINFO_EXTENSION ), $dir );

		$dest_path = $dir . '/' . $stored;
		if ( ! file_exists( $file->file_path ) ) {
			return false;
		}
		if ( ! copy( $file->file_path, $dest_path ) ) {
			return false;
		}

		return $wpdb->insert(
			$this->table_files(),
			array(
				'name'          => $stored,
				'original_name' => $new_name,
				'folder_id'     => $target_folder_id,
				'file_type'     => $file->file_type,
				'file_size'     => $file->file_size,
				'file_path'     => $dest_path,
				'file_url'      => $url . '/' . $stored,
			),
			array( '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Copy a folder subtree into another folder.
	 */
	private function copy_folder_recursive( $folder_id, $target_parent_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$folder = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_folders()} WHERE id = %d", $folder_id ) );
		if ( ! $folder ) {
			return;
		}
		$new_name = $this->unique_folder_name( $folder->name, $target_parent_id );
		$path     = $this->build_folder_path( $target_parent_id ) . '/' . $new_name;
		$inserted = $wpdb->insert(
			$this->table_folders(),
			array( 'name' => $new_name, 'parent_id' => $target_parent_id, 'path' => $path ),
			array( '%s', '%d', '%s' )
		);
		if ( false === $inserted ) {
			return;
		}
		$new_id = $wpdb->insert_id;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$subfolders = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$this->table_folders()} WHERE parent_id = %d", $folder_id ) );
		$files      = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$this->table_files()} WHERE folder_id = %d", $folder_id ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $subfolders as $sub ) {
			$this->copy_folder_recursive( (int) $sub, $new_id );
		}
		foreach ( $files as $fid ) {
			$this->copy_file( (int) $fid, $new_id );
		}
	}

	/**
	 * Permanently delete one file (physical + row).
	 */
	private function purge_file( $file_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$file = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_files()} WHERE id = %d", $file_id ) );
		if ( $file && file_exists( $file->file_path ) ) {
			wp_delete_file( $file->file_path );
		}
		$wpdb->delete( $this->table_files(), array( 'id' => $file_id ), array( '%d' ) );
	}

	/**
	 * Set the trashed state for a folder and its whole subtree.
	 *
	 * @param int         $folder_id
	 * @param int         $state  1 = trashed, 0 = restored.
	 * @param string|null $now    Timestamp for trashed_at (null when restoring).
	 */
	private function set_folder_trashed_recursive( $folder_id, $state, $now ) {
		global $wpdb;
		$wpdb->update(
			$this->table_folders(),
			array( 'is_trashed' => $state, 'trashed_at' => $state ? $now : null ),
			array( 'id' => $folder_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		$wpdb->update(
			$this->table_files(),
			array( 'is_trashed' => $state, 'trashed_at' => $state ? $now : null ),
			array( 'folder_id' => $folder_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$children = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$this->table_folders()} WHERE parent_id = %d", $folder_id ) );
		foreach ( $children as $child_id ) {
			$this->set_folder_trashed_recursive( (int) $child_id, $state, $now );
		}
	}

	/**
	 * Parse the `items` POST param (JSON array of {type,id}).
	 *
	 * @return array
	 */
	private function parse_items_param() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$raw = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return array();
		}
		$out = array();
		foreach ( $decoded as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$type = isset( $entry['type'] ) ? sanitize_key( $entry['type'] ) : '';
			$id   = isset( $entry['id'] ) ? intval( $entry['id'] ) : 0;
			if ( $id > 0 && in_array( $type, array( 'file', 'folder' ), true ) ) {
				$out[] = array( 'type' => $type, 'id' => $id );
			}
		}
		return $out;
	}

	public function get_folder_contents_ajax() {
		check_ajax_referer('linknacional_filebrowser_nonce', 'nonce');		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		$folder_id = isset( $_POST['folder_id'] ) ? intval( wp_unslash( $_POST['folder_id'] ) ) : 0;
		$contents = $this->get_folder_contents($folder_id);
		$contents['breadcrumb'] = $this->build_breadcrumb( $folder_id );
		$contents['folder_id']  = $folder_id;
		wp_send_json_success($contents);
	}

	public function get_all_folders_ajax() {
		check_ajax_referer('linknacional_filebrowser_nonce', 'nonce');
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$folders = $wpdb->get_results("SELECT * FROM {$this->table_folders()} WHERE is_trashed = 0 ORDER BY parent_id ASC, name ASC");
		wp_send_json_success($folders);
	}

	/**
	 * Attach an item count (non-trashed subfolders + files) to each folder row.
	 *
	 * @param array $folders Folder rows.
	 * @return array
	 */
	private function attach_folder_item_counts( $folders ) {
		if ( empty( $folders ) || ! is_array( $folders ) ) {
			return $folders;
		}
		global $wpdb;
		$ids = array();
		foreach ( $folders as $folder ) {
			if ( isset( $folder->id ) && (int) $folder->id > 0 ) {
				$ids[] = (int) $folder->id;
			}
		}
		$ids = array_values( array_unique( $ids ) );
		if ( empty( $ids ) ) {
			return $folders;
		}
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sub_counts  = $wpdb->get_results( $wpdb->prepare(
			"SELECT parent_id AS fid, COUNT(*) AS total FROM {$this->table_folders()} WHERE parent_id IN ($placeholders) AND is_trashed = 0 GROUP BY parent_id",
			$ids
		) );
		$file_counts = $wpdb->get_results( $wpdb->prepare(
			"SELECT folder_id AS fid, COUNT(*) AS total FROM {$this->table_files()} WHERE folder_id IN ($placeholders) AND is_trashed = 0 GROUP BY folder_id",
			$ids
		) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$totals = array();
		foreach ( $sub_counts as $row ) {
			$key = (int) $row->fid;
			$totals[ $key ] = ( isset( $totals[ $key ] ) ? $totals[ $key ] : 0 ) + (int) $row->total;
		}
		foreach ( $file_counts as $row ) {
			$key = (int) $row->fid;
			$totals[ $key ] = ( isset( $totals[ $key ] ) ? $totals[ $key ] : 0 ) + (int) $row->total;
		}
		foreach ( $folders as $folder ) {
			if ( isset( $folder->id ) ) {
				$folder->item_count = isset( $totals[ (int) $folder->id ] ) ? $totals[ (int) $folder->id ] : 0;
			}
		}
		return $folders;
	}

	private function get_folder_contents($folder_id) {
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$folders = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table_folders()} WHERE parent_id = %d AND is_trashed = 0 ORDER BY name ASC", $folder_id));
		$files = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table_files()} WHERE folder_id = %d AND is_trashed = 0 ORDER BY original_name ASC", $folder_id));
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array('folders' => $this->attach_folder_item_counts( $folders ), 'files' => LinkNacionalFilebrowserFiles::prepare_rows( $files ));
	}

	private function build_folder_path($folder_id) {
		if ($folder_id == 0) return '';
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$folder = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_folders()} WHERE id = %d", $folder_id));
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ($folder && $folder->parent_id > 0) {
			return $this->build_folder_path($folder->parent_id) . '/' . $folder->name;
		} elseif ($folder) {
			return $folder->name;
		}
		return '';
	}

	/**
	 * Build a breadcrumb trail (Home → … → current) as an array of {id, name}.
	 *
	 * @param int $folder_id Current folder id.
	 * @return array
	 */
	private function build_breadcrumb( $folder_id ) {
		global $wpdb;
		$crumbs     = array();
		$current_id = (int) $folder_id;
		$guard      = 0;
		while ( $current_id > 0 && $guard < 1000 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$folder = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, parent_id FROM {$this->table_folders()} WHERE id = %d", $current_id ) );
			if ( ! $folder ) {
				break;
			}
			array_unshift( $crumbs, array( 'id' => (int) $folder->id, 'name' => $folder->name ) );
			$current_id = (int) $folder->parent_id;
			++$guard;
		}
		array_unshift( $crumbs, array( 'id' => 0, 'name' => __( 'Home', 'linknacional-file-browser' ) ) );
		return $crumbs;
	}

	private function delete_folder_recursive($folder_id) {
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$subfolders = $wpdb->get_results($wpdb->prepare("SELECT id FROM {$this->table_folders()} WHERE parent_id = %d", $folder_id));
		foreach ($subfolders as $subfolder) {
			$this->delete_folder_recursive($subfolder->id);
		}
		$files = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$this->table_files()} WHERE folder_id = %d", $folder_id));
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ($files as $file) {
			if ( file_exists( $file->file_path ) ) {
				wp_delete_file( $file->file_path );
			}
		}
		$wpdb->delete($this->table_files(), array('folder_id' => $folder_id), array('%d'));
		$wpdb->delete($this->table_folders(), array('id' => $folder_id), array('%d'));
	}

	public function update_folder_name_ajax() {
		check_ajax_referer('linknacional_filebrowser_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions', 'linknacional-file-browser'));
		}
		$folder_id = isset( $_POST['id'] ) ? intval( wp_unslash( $_POST['id'] ) ) : 0;
		$new_name = isset( $_POST['new_name'] ) ? sanitize_text_field( wp_unslash( $_POST['new_name'] ) ) : '';
		if (empty($new_name)) {
			wp_send_json_error(esc_html__('Folder name cannot be empty', 'linknacional-file-browser'));
		}
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$folder = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_folders()} WHERE id = %d", $folder_id));
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if (!$folder) {
			wp_send_json_error(esc_html__('Folder not found', 'linknacional-file-browser'));
		}
		if ( $this->folder_name_exists( $new_name, $folder->parent_id, $folder_id ) ) {
			wp_send_json_error(esc_html__('A folder with this name already exists', 'linknacional-file-browser'));
		}
		$result = $wpdb->update($this->table_folders(), array('name' => $new_name), array('id' => $folder_id), array('%s'), array('%d'));
		if ($result !== false) {
			$this->rebuild_folder_paths( $folder_id );
			wp_send_json_success( array( 'message' => esc_html__('Folder name updated successfully', 'linknacional-file-browser' ), 'name' => $new_name ) );
		} else {
			wp_send_json_error(esc_html__('Error updating folder name', 'linknacional-file-browser'));
		}
	}

	public function update_file_name_ajax() {
		check_ajax_referer('linknacional_filebrowser_nonce', 'nonce');
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('Insufficient permissions', 'linknacional-file-browser'));
		}
		$file_id = isset( $_POST['id'] ) ? intval( wp_unslash( $_POST['id'] ) ) : 0;
		$new_name = isset( $_POST['new_name'] ) ? sanitize_file_name( wp_unslash( $_POST['new_name'] ) ) : '';
		if (empty($new_name)) {
			wp_send_json_error(esc_html__('File name cannot be empty', 'linknacional-file-browser'));
		}
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$file = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table_files()} WHERE id = %d", $file_id));
		if (!$file) {
			wp_send_json_error(esc_html__('File not found', 'linknacional-file-browser'));
		}
		$existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$this->table_files()} WHERE original_name = %s AND folder_id = %d AND id != %d", $new_name, $file->folder_id, $file_id));
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ($existing) {
			wp_send_json_error(esc_html__('A file with this name already exists', 'linknacional-file-browser'));
		}
		$file_info = \pathinfo($new_name);
		$old_file_info = \pathinfo($file->original_name);
		if (isset($file_info['extension']) && isset($old_file_info['extension'])) {
			if (\strtolower($file_info['extension']) !== \strtolower($old_file_info['extension'])) {
				wp_send_json_error(esc_html__('Cannot change the file extension', 'linknacional-file-browser'));
			}
		}
		$result = $wpdb->update($this->table_files(), array('original_name' => $new_name), array('id' => $file_id), array('%s'), array('%d'));
		if ($result !== false) {
			wp_send_json_success( array( 'message' => esc_html__('File name updated successfully', 'linknacional-file-browser' ), 'name' => $new_name ) );
		} else {
			wp_send_json_error(esc_html__('Error updating file name', 'linknacional-file-browser'));
		}
	}

	/**
	 * Move a single file into another folder.
	 */
	public function move_file_ajax() {
		check_ajax_referer( 'linknacional_filebrowser_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		$file_id = isset( $_POST['file_id'] ) ? intval( wp_unslash( $_POST['file_id'] ) ) : 0;
		$target  = isset( $_POST['target_folder_id'] ) ? intval( wp_unslash( $_POST['target_folder_id'] ) ) : 0;

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$file = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_files()} WHERE id = %d", $file_id ) );
		if ( ! $file ) {
			wp_send_json_error( esc_html__( 'File not found', 'linknacional-file-browser' ) );
		}
		if ( (int) $file->folder_id === $target ) {
			wp_send_json_success( array( 'message' => esc_html__( 'File is already in this folder', 'linknacional-file-browser' ), 'unchanged' => true ) );
		}
		if ( $target > 0 && ! $this->folder_is_active( $target ) ) {
			wp_send_json_error( esc_html__( 'Destination folder not found', 'linknacional-file-browser' ) );
		}

		$new_name = $this->unique_file_name_in_folder( $file->original_name, $target, $file_id );
		$updated  = $wpdb->update(
			$this->table_files(),
			array( 'folder_id' => $target, 'original_name' => $new_name ),
			array( 'id' => $file_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			wp_send_json_error( esc_html__( 'Error moving the file', 'linknacional-file-browser' ) );
		}
		wp_send_json_success( array( 'message' => esc_html__( 'File moved successfully', 'linknacional-file-browser' ), 'original_name' => $new_name ) );
	}

	/**
	 * Move a folder (and its subtree) into another folder.
	 */
	public function move_folder_ajax() {
		check_ajax_referer( 'linknacional_filebrowser_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		$folder_id = isset( $_POST['folder_id'] ) ? intval( wp_unslash( $_POST['folder_id'] ) ) : 0;
		$target    = isset( $_POST['target_folder_id'] ) ? intval( wp_unslash( $_POST['target_folder_id'] ) ) : 0;

		if ( $folder_id <= 0 ) {
			wp_send_json_error( esc_html__( 'Folder not found', 'linknacional-file-browser' ) );
		}
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$folder = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_folders()} WHERE id = %d", $folder_id ) );
		if ( ! $folder ) {
			wp_send_json_error( esc_html__( 'Folder not found', 'linknacional-file-browser' ) );
		}
		if ( (int) $folder->parent_id === $target ) {
			wp_send_json_success( array( 'message' => esc_html__( 'Folder is already in this location', 'linknacional-file-browser' ), 'unchanged' => true ) );
		}
		if ( $folder_id === $target ) {
			wp_send_json_error( esc_html__( 'A folder cannot be moved into itself', 'linknacional-file-browser' ) );
		}
		if ( $target > 0 ) {
			if ( ! $this->folder_is_active( $target ) ) {
				wp_send_json_error( esc_html__( 'Destination folder not found', 'linknacional-file-browser' ) );
			}
			if ( $this->is_folder_descendant( $target, $folder_id ) ) {
				wp_send_json_error( esc_html__( 'A folder cannot be moved into one of its own subfolders', 'linknacional-file-browser' ) );
			}
		}

		$new_name = $this->unique_folder_name( $folder->name, $target, $folder_id );
		$updated  = $wpdb->update(
			$this->table_folders(),
			array( 'parent_id' => $target, 'name' => $new_name ),
			array( 'id' => $folder_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);
		if ( false === $updated ) {
			wp_send_json_error( esc_html__( 'Error moving the folder', 'linknacional-file-browser' ) );
		}
		$this->rebuild_folder_paths( $folder_id );
		wp_send_json_success( array( 'message' => esc_html__( 'Folder moved successfully', 'linknacional-file-browser' ), 'name' => $new_name ) );
	}

	/**
	 * Recursively recompute the `path` column for a folder and its subtree.
	 *
	 * @param int $folder_id
	 */
	private function rebuild_folder_paths( $folder_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$folder = $wpdb->get_row( $wpdb->prepare( "SELECT id, name, parent_id FROM {$this->table_folders()} WHERE id = %d", $folder_id ) );
		if ( ! $folder ) {
			return;
		}
		$path = $this->build_folder_path( (int) $folder->parent_id ) . '/' . $folder->name;
		$wpdb->update( $this->table_folders(), array( 'path' => $path ), array( 'id' => $folder_id ), array( '%s' ), array( '%d' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$children = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM {$this->table_folders()} WHERE parent_id = %d", $folder_id ) );
		foreach ( $children as $child ) {
			$this->rebuild_folder_paths( (int) $child->id );
		}
	}

	/**
	 * Whether a folder id exists.
	 *
	 * @param int $folder_id
	 * @return bool
	 */
	/**
	 * Whether a folder exists and is NOT in the trash (valid move/copy destination).
	 *
	 * @param int $folder_id
	 * @return bool
	 */
	private function folder_is_active( $folder_id ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table_folders()} WHERE id = %d AND is_trashed = 0", $folder_id ) );
	}

	/**
	 * Bring a folder and all of its ancestors out of the trash so a restored
	 * descendant is actually reachable in the tree.
	 *
	 * @param int $folder_id
	 */
	private function restore_ancestors( $folder_id ) {
		global $wpdb;
		$current = (int) $folder_id;
		$guard   = 0;
		while ( $current > 0 && $guard < 1000 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$parent = (int) $wpdb->get_var( $wpdb->prepare( "SELECT parent_id FROM {$this->table_folders()} WHERE id = %d", $current ) );
			$wpdb->update( $this->table_folders(), array( 'is_trashed' => 0, 'trashed_at' => null ), array( 'id' => $current ), array( '%d', '%s' ), array( '%d' ) );
			$current = $parent;
			++$guard;
		}
	}

	/**
	 * Whether $candidate_id is $ancestor_id itself or one of its descendants.
	 *
	 * @param int $candidate_id
	 * @param int $ancestor_id
	 * @return bool
	 */
	private function is_folder_descendant( $candidate_id, $ancestor_id ) {
		global $wpdb;
		$current = (int) $candidate_id;
		$guard   = 0;
		while ( $current > 0 && $guard < 1000 ) {
			if ( $current === (int) $ancestor_id ) {
				return true;
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$current = (int) $wpdb->get_var( $wpdb->prepare( "SELECT parent_id FROM {$this->table_folders()} WHERE id = %d", $current ) );
			++$guard;
		}
		return false;
	}

	/**
	 * Ensure a folder name is unique within a parent, appending " (2)", " (3)"… if needed.
	 *
	 * @param string $name
	 * @param int    $parent_id
	 * @param int    $exclude_id
	 * @return string
	 */
	private function unique_folder_name( $name, $parent_id, $exclude_id = 0 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table_folders()} WHERE name = %s AND parent_id = %d AND id != %d", $name, $parent_id, $exclude_id ) );
		if ( ! $exists ) {
			return $name;
		}
		$i = 2;
		do {
			$candidate = $name . ' (' . $i . ')';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table_folders()} WHERE name = %s AND parent_id = %d AND id != %d", $candidate, $parent_id, $exclude_id ) );
			++$i;
		} while ( $exists );
		return $candidate;
	}

	/**
	 * Case-insensitive check for an existing folder name within a parent.
	 *
	 * @param string $name
	 * @param int    $parent_id
	 * @param int    $exclude_id
	 * @return bool
	 */
	private function folder_name_exists( $name, $parent_id, $exclude_id = 0 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$names = $wpdb->get_col( $wpdb->prepare( "SELECT name FROM {$this->table_folders()} WHERE parent_id = %d AND id != %d", $parent_id, $exclude_id ) );
		$target = \function_exists( 'mb_strtolower' ) ? \mb_strtolower( $name, 'UTF-8' ) : \strtolower( $name );
		foreach ( $names as $existing ) {
			$existing = \function_exists( 'mb_strtolower' ) ? \mb_strtolower( $existing, 'UTF-8' ) : \strtolower( $existing );
			if ( $existing === $target ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Ensure a file name is unique within a folder, appending " (1)", " (2)"… if needed.
	 *
	 * @param string $name
	 * @param int    $folder_id
	 * @param int    $exclude_id
	 * @return string
	 */
	private function unique_file_name_in_folder( $name, $folder_id, $exclude_id = 0 ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table_files()} WHERE original_name = %s AND folder_id = %d AND id != %d", $name, $folder_id, $exclude_id ) );
		if ( ! $exists ) {
			return $name;
		}
		$pathinfo = pathinfo( $name );
		$ext      = isset( $pathinfo['extension'] ) ? '.' . $pathinfo['extension'] : '';
		$base     = isset( $pathinfo['filename'] ) ? $pathinfo['filename'] : $name;
		$i        = 1;
		do {
			$candidate = $base . ' (' . $i . ')' . $ext;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table_files()} WHERE original_name = %s AND folder_id = %d AND id != %d", $candidate, $folder_id, $exclude_id ) );
			++$i;
		} while ( $exists );
		return $candidate;
	}

	public function get_folder_files_ajax() {
		check_ajax_referer( 'linknacional_filebrowser_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		$folder_id = isset( $_POST['folder_id'] ) ? intval( wp_unslash( $_POST['folder_id'] ) ) : 0;
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$files = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$this->table_files()} WHERE folder_id = %d AND is_trashed = 0 ORDER BY original_name ASC", $folder_id));
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		wp_send_json_success( LinkNacionalFilebrowserFiles::prepare_rows( $files ) );
	}

	public function get_all_folders_admin_frontend() {
		check_ajax_referer( 'linknacional_filebrowser_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$folders = $wpdb->get_results("SELECT * FROM {$this->table_folders()} WHERE is_trashed = 0 ORDER BY parent_id ASC, name ASC");
		$files = $wpdb->get_results("SELECT * FROM {$this->table_files()} WHERE is_trashed = 0 ORDER BY folder_id ASC, original_name ASC");
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		wp_send_json_success(array('folders' => $folders, 'files' => LinkNacionalFilebrowserFiles::prepare_rows( $files )));
	}

	private function table_folders() {
		global $wpdb;
		return $wpdb->prefix . 'linknacional_filebrowser_folders';
	}

	private function table_files() {
		global $wpdb;
		return $wpdb->prefix . 'linknacional_filebrowser_files';
	}

	private function old_table_folders() {
		global $wpdb;
		return $wpdb->prefix . 'lknwp_filebrowser_folders';
	}

	private function old_table_files() {
		global $wpdb;
		return $wpdb->prefix . 'lknwp_filebrowser_files';
	}

	public function old_tables_exist() {
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$folders_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->old_table_folders() ) ) === $this->old_table_folders();
		$files_exists   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->old_table_files() ) ) === $this->old_table_files();
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $folders_exists || $files_exists;
	}

	public function migrate_ajax() {
		check_ajax_referer( 'linknacional_filebrowser_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Insufficient permissions', 'linknacional-file-browser' ) );
		}

		global $wpdb;

		$upload_dir       = wp_upload_dir();
		$old_dir          = $upload_dir['basedir'] . '/lknwp-filebrowser';
		$new_dir          = $upload_dir['basedir'] . '/linknacional-filebrowser';
		$old_url          = $upload_dir['baseurl'] . '/lknwp-filebrowser';
		$new_url          = $upload_dir['baseurl'] . '/linknacional-filebrowser';

		if ( ! file_exists( $new_dir ) ) {
			wp_mkdir_p( $new_dir );
		}

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter

		// --- Migrate folders ---
		$old_folders = $wpdb->get_results( "SELECT * FROM {$this->old_table_folders()} ORDER BY id ASC" );
		$id_map      = array(); // old_id → new_id
		$migrated_folders = 0;

		if ( is_array( $old_folders ) ) {
			foreach ( $old_folders as $folder ) {
				$new_parent_id = isset( $id_map[ $folder->parent_id ] ) ? $id_map[ $folder->parent_id ] : 0;
				$folder_name   = $folder->name;

				// Resolve name conflicts
				$unique_name = $folder_name;
				$suffix_num  = 1;
				while ( $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table_folders()} WHERE name = %s AND parent_id = %d",
					$unique_name, $new_parent_id
				) ) > 0 ) {
					$unique_name = $folder_name . ' (copy)';
					if ( $suffix_num > 1 ) {
						$unique_name = $folder_name . ' (copy)' . $suffix_num;
					}
					++$suffix_num;
				}

				$new_path = '/' . $unique_name;
				if ( $new_parent_id > 0 ) {
					$parent_path = $wpdb->get_var( $wpdb->prepare(
						"SELECT path FROM {$this->table_folders()} WHERE id = %d", $new_parent_id
					) );
					$new_path = $parent_path . '/' . $unique_name;
				}

				$inserted = $wpdb->insert(
					$this->table_folders(),
					array(
						'name'      => $unique_name,
						'parent_id' => $new_parent_id,
						'path'      => $new_path,
					),
					array( '%s', '%d', '%s' )
				);

				if ( false !== $inserted ) {
					$id_map[ $folder->id ] = $wpdb->insert_id;
					++$migrated_folders;
				}
			}
		}

		// --- Migrate files ---
		$old_files       = $wpdb->get_results( "SELECT * FROM {$this->old_table_files()} ORDER BY id ASC" );
		$migrated_files  = 0;

		if ( is_array( $old_files ) ) {
			foreach ( $old_files as $file ) {
				$new_folder_id = isset( $id_map[ $file->folder_id ] ) ? $id_map[ $file->folder_id ] : 0;
				$original_name = $file->original_name;

				// Resolve name conflicts
				$unique_name = $original_name;
				$suffix_num  = 1;
				while ( $wpdb->get_var( $wpdb->prepare(
					"SELECT COUNT(*) FROM {$this->table_files()} WHERE original_name = %s AND folder_id = %d",
					$unique_name, $new_folder_id
				) ) > 0 ) {
					$ext         = pathinfo( $original_name, PATHINFO_EXTENSION );
					$base        = $ext ? substr( $original_name, 0, - ( strlen( $ext ) + 1 ) ) : $original_name;
					$unique_name = $base . ' (copy).' . $ext;
					if ( $suffix_num > 1 ) {
						$unique_name = $base . ' (copy)' . $suffix_num . '.' . $ext;
					}
					++$suffix_num;
				}

				$stored_name   = LinkNacionalFilebrowserFiles::hashed_filename( pathinfo( $unique_name, PATHINFO_EXTENSION ), $new_dir );
				$new_file_path = $new_dir . '/' . $stored_name;
				$new_file_url  = $new_url . '/' . $stored_name;

				// Copy physical file
				$old_file_path = $old_dir . '/' . $file->name;
				if ( file_exists( $old_file_path ) && ! file_exists( $new_file_path ) ) {
					copy( $old_file_path, $new_file_path );
				}

				$inserted = $wpdb->insert(
					$this->table_files(),
					array(
						'name'          => $stored_name,
						'original_name' => $unique_name,
						'folder_id'     => $new_folder_id,
						'file_type'     => $file->file_type,
						'file_size'     => $file->file_size,
						'file_path'     => $new_file_path,
						'file_url'      => $new_file_url,
					),
					array( '%s', '%s', '%d', '%s', '%d', '%s', '%s' )
				);

				if ( false !== $inserted ) {
					++$migrated_files;
				}
			}
		}

		// --- Drop old tables ---
		$wpdb->query( "DROP TABLE IF EXISTS {$this->old_table_folders()}" );
		$wpdb->query( "DROP TABLE IF EXISTS {$this->old_table_files()}" );

		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.DirectDatabaseQuery.SchemaChange,PluginCheck.Security.DirectDB.UnescapedDBParameter

		wp_send_json_success( array(
			'message'          => sprintf(
				/* translators: 1: folders count, 2: files count */
				esc_html__( 'Migration complete: %1$d folders and %2$d files migrated.', 'linknacional-file-browser' ),
				$migrated_folders,
				$migrated_files
			),
			'folders_migrated' => $migrated_folders,
			'files_migrated'   => $migrated_files,
		) );
	}
}
