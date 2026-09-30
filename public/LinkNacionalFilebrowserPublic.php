<?php

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// Custom tables — no WP core API exists. $wpdb is the only correct approach.

namespace LinkNacional\Filebrowser\Public;

use LinkNacional\Filebrowser\Includes\LinkNacionalFilebrowserFiles;

class LinkNacionalFilebrowserPublic {

	private $plugin_name;
	private $version;

	public function __construct( $plugin_name, $version ) {
		$this->plugin_name = $plugin_name;
		$this->version = $version;
	}

	public function linknacional_get_public_nonce() {
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html__( 'Invalid request method.', 'linknacional-file-browser' ) );
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

	/**
	 * File delivery endpoint.
	 *
	 * Modes: `info` (JSON capability probe), `pdf` (PDF/Office-PDF rendition for
	 * the client-side PDF.js viewer), `image` (view-only image), `text`
	 * (plain-text body), and the default raw stream. The download is the only
	 * action gated by `allow_download`.
	 */
	public function serve_file_ajax() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$file_id  = isset( $_GET['file_id'] ) ? intval( wp_unslash( $_GET['file_id'] ) ) : 0;
		$mode     = isset( $_GET['mode'] ) ? sanitize_key( wp_unslash( $_GET['mode'] ) ) : '';
		$token    = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		$nonce    = isset( $_GET['nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['nonce'] ) ) : '';
		$download = isset( $_GET['dl'] );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( ! $this->authorize_file_request( $file_id, $token, $nonce ) ) {
			wp_die( esc_html__( 'Download restricted', 'linknacional-file-browser' ), '', array( 'response' => 403 ) );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$file = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_files()} WHERE id = %d", $file_id ) );

		if ( ! $file || (int) $file->is_trashed === 1 ) {
			wp_die( esc_html__( 'File not found', 'linknacional-file-browser' ), '', array( 'response' => 404 ) );
		}

		$path = $file->file_path;
		if ( ! $path || ! file_exists( $path ) || ! LinkNacionalFilebrowserFiles::is_within_storage_dir( $path ) ) {
			wp_die( esc_html__( 'File not found', 'linknacional-file-browser' ), '', array( 'response' => 404 ) );
		}

		$ext = strtolower( (string) $file->file_type );

		// Capability probe for the viewer.
		if ( 'info' === $mode ) {
			$kind     = LinkNacionalFilebrowserFiles::preview_kind( $ext );
			$viewable = in_array( $kind, array( 'image', 'pdf', 'text' ), true )
				|| ( 'office' === $kind && false !== LinkNacionalFilebrowserFiles::converted_pdf( $path, $ext ) );
			wp_send_json_success(
				array(
					'kind'           => $kind,
					'viewable'       => $viewable,
					'allow_download' => (int) $file->allow_download,
				)
			);
		}

		// PDF rendition for the client-side PDF.js viewer: PDFs are served as-is,
		// Office documents as their cached LibreOffice→PDF conversion.
		if ( 'pdf' === $mode ) {
			$pdf = ( 'pdf' === $ext ) ? $path : LinkNacionalFilebrowserFiles::converted_pdf( $path, $ext );
			if ( ! $pdf ) {
				wp_die( esc_html__( 'Preview unavailable', 'linknacional-file-browser' ), '', array( 'response' => 415 ) );
			}
			$this->stream_file( $pdf, 'application/pdf', 'inline', $file->original_name );
		}

		// Image rendition for the viewer (view-only, no download attachment).
		if ( 'image' === $mode ) {
			if ( 'image' !== LinkNacionalFilebrowserFiles::preview_kind( $ext ) ) {
				wp_die( esc_html__( 'Preview unavailable', 'linknacional-file-browser' ), '', array( 'response' => 415 ) );
			}
			$type = wp_check_filetype( $file->original_name );
			$mime = ! empty( $type['type'] ) ? $type['type'] : 'application/octet-stream';
			$this->stream_file( $path, $mime, 'inline', $file->original_name );
		}

		// Plain-text body for the viewer.
		if ( 'text' === $mode ) {
			if ( ! LinkNacionalFilebrowserFiles::is_text( $ext ) ) {
				wp_die( esc_html__( 'Preview unavailable', 'linknacional-file-browser' ), '', array( 'response' => 415 ) );
			}
			$this->stream_file( $path, 'text/plain; charset=UTF-8', 'inline', $file->original_name );
		}

		// Raw stream — the download path. A restricted file is never streamed.
		if ( empty( $file->allow_download ) ) {
			wp_die( esc_html__( 'Download restricted', 'linknacional-file-browser' ), '', array( 'response' => 403 ) );
		}

		$type = wp_check_filetype( $file->original_name );
		$mime = ! empty( $type['type'] ) ? $type['type'] : 'application/octet-stream';
		$this->stream_file( $path, $mime, $download ? 'attachment' : 'inline', $file->original_name );
	}

	/**
	 * Stream a file to the browser and stop.
	 *
	 * @param string $path        Absolute path.
	 * @param string $mime        Content type.
	 * @param string $disposition 'inline' or 'attachment'.
	 * @param string $name        Download file name.
	 * @return void
	 */
	private function stream_file( $path, $mime, $disposition, $name ) {
		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}
		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Length: ' . filesize( $path ) );
		header( 'Content-Disposition: ' . $disposition . '; filename="' . rawurlencode( $name ) . '"' );
		header( 'X-Content-Type-Options: nosniff' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming the file to the browser; WP_Filesystem would load it fully into memory.
		readfile( $path );
		exit;
	}

	/**
	 * Whether the request may read the given file — via a session nonce or a
	 * valid, unexpired share token bound to that same file.
	 *
	 * @param int    $file_id
	 * @param string $token
	 * @param string $nonce
	 * @return bool
	 */
	private function authorize_file_request( $file_id, $token, $nonce ) {
		if ( '' !== $token ) {
			return $file_id > 0 && LinkNacionalFilebrowserFiles::validate_share_token( $token ) === (int) $file_id;
		}
		if ( '' !== $nonce ) {
			return (bool) wp_verify_nonce( $nonce, LinkNacionalFilebrowserFiles::NONCE_ACTION );
		}
		return false;
	}

	/**
	 * Register the standalone viewer query var.
	 *
	 * @param array $vars
	 * @return array
	 */
	public function register_viewer_query_vars( $vars ) {
		$vars[] = 'linknacional_viewer';
		return $vars;
	}

	/**
	 * Render the standalone viewer page for a token-protected file.
	 *
	 * The page mounts the fullscreen viewer regardless of the download flag —
	 * the flag only controls whether the download button is offered.
	 */
	public function render_viewer_page() {
		$file_id = (int) get_query_var( 'linknacional_viewer' );
		if ( $file_id <= 0 ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- The token is validated right below via validate_share_token().
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		if ( LinkNacionalFilebrowserFiles::validate_share_token( $token ) !== $file_id ) {
			wp_die( esc_html__( 'This link is invalid or has expired.', 'linknacional-file-browser' ), '', array( 'response' => 403 ) );
		}

		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$file = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->table_files()} WHERE id = %d", $file_id ) );
		if ( ! $file || (int) $file->is_trashed === 1 ) {
			wp_die( esc_html__( 'File not found', 'linknacional-file-browser' ), '', array( 'response' => 404 ) );
		}

		$path = $file->file_path;
		if ( ! $path || ! file_exists( $path ) || ! LinkNacionalFilebrowserFiles::is_within_storage_dir( $path ) ) {
			wp_die( esc_html__( 'File not found', 'linknacional-file-browser' ), '', array( 'response' => 404 ) );
		}

		$data = array(
			'name'           => $file->original_name,
			'ext'            => strtolower( (string) $file->file_type ),
			'allow_download' => ! empty( $file->allow_download ),
			'serve_url'      => LinkNacionalFilebrowserFiles::token_serve_url( $file_id, $token ),
			'size'           => (int) $file->file_size,
		);

		wp_enqueue_script( 'linknacional-filebrowser-fontawesome', LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'assets/js/compiled/fontawesome.compiled.js', array(), LINKNACIONAL_FILEBROWSER_VERSION, false );
		wp_enqueue_style( $this->plugin_name, LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'public/css/linknacional-filebrowser-public.css', array(), LINKNACIONAL_FILEBROWSER_VERSION, 'all' );
		wp_enqueue_script( 'linknacional-filebrowser-pdfjs', LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'assets/js/compiled/pdfjs.compiled.js', array(), LINKNACIONAL_FILEBROWSER_VERSION, false );
		wp_enqueue_script( $this->plugin_name, LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'public/js/linknacional-filebrowser-public.js', array( 'jquery' ), LINKNACIONAL_FILEBROWSER_VERSION, false );
		wp_localize_script( $this->plugin_name, 'linknacional_public_ajax', $this->viewer_strings() );

		require LINKNACIONAL_FILEBROWSER_PLUGIN_PATH . 'public/partials/linknacional-filebrowser-viewer.php';
		exit;
	}

	/**
	 * Translated strings needed by the standalone viewer page.
	 *
	 * @return array
	 */
	private function viewer_strings() {
		return array(
			'pdfjs_worker'        => LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'assets/js/compiled/pdf.worker.min.js',
			'close'               => esc_html__( 'Close', 'linknacional-file-browser' ),
			'download'            => esc_html__( 'Download', 'linknacional-file-browser' ),
			'download_locked'     => esc_html__( 'Download restricted', 'linknacional-file-browser' ),
			'preview_unavailable' => esc_html__( 'Preview unavailable', 'linknacional-file-browser' ),
			'view'                => esc_html__( 'View', 'linknacional-file-browser' ),
			'zoom_in'             => esc_html__( 'Zoom in', 'linknacional-file-browser' ),
			'zoom_out'            => esc_html__( 'Zoom out', 'linknacional-file-browser' ),
			'zoom_fit'            => esc_html__( 'Fit to screen', 'linknacional-file-browser' ),
			'prev_page'           => esc_html__( 'Previous page', 'linknacional-file-browser' ),
			'next_page'           => esc_html__( 'Next page', 'linknacional-file-browser' ),
			'type_image'          => esc_html__( 'Image', 'linknacional-file-browser' ),
			'type_pdf'            => esc_html__( 'PDF', 'linknacional-file-browser' ),
			'type_file'           => esc_html__( 'File', 'linknacional-file-browser' ),
		);
	}

	public function register_shortcode() {
		add_shortcode( 'linkn_filebrowser', array( $this, 'render_filebrowser_shortcode' ) );
	}

	public function render_filebrowser_shortcode( $atts ) {
		wp_enqueue_script( 'linknacional-filebrowser-fontawesome', LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'assets/js/compiled/fontawesome.compiled.js', array(), LINKNACIONAL_FILEBROWSER_VERSION, false );
		wp_enqueue_style( $this->plugin_name, LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'public/css/linknacional-filebrowser-public.css', array(), LINKNACIONAL_FILEBROWSER_VERSION, 'all' );
		wp_enqueue_script( 'linknacional-filebrowser-pdfjs', LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'assets/js/compiled/pdfjs.compiled.js', array(), LINKNACIONAL_FILEBROWSER_VERSION, false );
		wp_enqueue_script( $this->plugin_name, LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'public/js/linknacional-filebrowser-public.js', array( 'jquery' ), LINKNACIONAL_FILEBROWSER_VERSION, false );

		wp_localize_script( $this->plugin_name, 'linknacional_public_ajax', array(
			'ajax_url'            => admin_url( 'admin-ajax.php' ),
			'pdfjs_worker'        => LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'assets/js/compiled/pdf.worker.min.js',
			'home'                => esc_html__( 'Home', 'linknacional-file-browser' ),
			'folder_text'         => esc_html__( 'Folder', 'linknacional-file-browser' ),
			'searching_text'      => esc_html__( 'Searching…', 'linknacional-file-browser' ),
			'error_loading_text'  => esc_html__( 'Could not load contents', 'linknacional-file-browser' ),
			'error_search_text'   => esc_html__( 'Error performing search', 'linknacional-file-browser' ),
			'error_folders_text'  => esc_html__( 'Error loading folders', 'linknacional-file-browser' ),
			'empty_folder_text'   => esc_html__( 'This folder is empty', 'linknacional-file-browser' ),
			'empty_folder_desc'   => esc_html__( 'No files or folders found in this location.', 'linknacional-file-browser' ),
			'no_results_text'     => esc_html__( 'No results found', 'linknacional-file-browser' ),
			'no_results_desc'     => esc_html__( 'Try adjusting your search terms.', 'linknacional-file-browser' ),
			'search_results_text' => esc_html__( 'Results for', 'linknacional-file-browser' ),
			'found_items_text'    => esc_html__( 'Found', 'linknacional-file-browser' ),
			'items_text'          => esc_html__( 'items', 'linknacional-file-browser' ),
			'open_file'           => esc_html__( 'Open', 'linknacional-file-browser' ),
			'download'            => esc_html__( 'Download', 'linknacional-file-browser' ),
			'download_locked'      => esc_html__( 'Download restricted', 'linknacional-file-browser' ),
			'preview_unavailable'  => esc_html__( 'Preview unavailable', 'linknacional-file-browser' ),
			'prev_page'           => esc_html__( 'Previous page', 'linknacional-file-browser' ),
			'next_page'           => esc_html__( 'Next page', 'linknacional-file-browser' ),
			'view'                => esc_html__( 'View', 'linknacional-file-browser' ),
			'zoom_in'             => esc_html__( 'Zoom in', 'linknacional-file-browser' ),
			'zoom_out'            => esc_html__( 'Zoom out', 'linknacional-file-browser' ),
			'zoom_fit'            => esc_html__( 'Fit to screen', 'linknacional-file-browser' ),
			'preview'             => esc_html__( 'Preview', 'linknacional-file-browser' ),
			'copy_link'           => esc_html__( 'Copy link', 'linknacional-file-browser' ),
			'more_actions'        => esc_html__( 'More actions', 'linknacional-file-browser' ),
			'close'               => esc_html__( 'Close', 'linknacional-file-browser' ),
			'link_copied'         => esc_html__( 'Link copied to clipboard', 'linknacional-file-browser' ),
			'copied_fallback'     => esc_html__( 'Copy this link:', 'linknacional-file-browser' ),
			'details'             => esc_html__( 'Details', 'linknacional-file-browser' ),
			'detail_name'         => esc_html__( 'Name', 'linknacional-file-browser' ),
			'detail_type'         => esc_html__( 'Type', 'linknacional-file-browser' ),
			'detail_size'         => esc_html__( 'Size', 'linknacional-file-browser' ),
			'detail_location'     => esc_html__( 'Location', 'linknacional-file-browser' ),
			'detail_modified'     => esc_html__( 'Modified', 'linknacional-file-browser' ),
			'copy_url'            => esc_html__( 'Copy URL', 'linknacional-file-browser' ),
			'quick_actions'       => esc_html__( 'Quick actions', 'linknacional-file-browser' ),
			'col_home'            => esc_html__( 'Home', 'linknacional-file-browser' ),
			'col_favorites'       => esc_html__( 'Favorites', 'linknacional-file-browser' ),
			'col_recent'          => esc_html__( 'Recent', 'linknacional-file-browser' ),
			'col_trash'           => esc_html__( 'Trash', 'linknacional-file-browser' ),
			'show_folders'        => esc_html__( 'Show folders', 'linknacional-file-browser' ),
			'hide_folders'        => esc_html__( 'Hide folders', 'linknacional-file-browser' ),
			'section_folders'     => esc_html__( 'Folders', 'linknacional-file-browser' ),
			'favorites_empty'     => esc_html__( 'No favorites yet', 'linknacional-file-browser' ),
			'recent_empty'        => esc_html__( 'No recent files', 'linknacional-file-browser' ),
			'trash_empty'         => esc_html__( 'Trash is empty', 'linknacional-file-browser' ),
			'type_image'          => esc_html__( 'Image', 'linknacional-file-browser' ),
			'type_pdf'            => esc_html__( 'PDF', 'linknacional-file-browser' ),
			'type_word'           => esc_html__( 'Document', 'linknacional-file-browser' ),
			'type_excel'          => esc_html__( 'Spreadsheet', 'linknacional-file-browser' ),
			'type_powerpoint'     => esc_html__( 'Presentation', 'linknacional-file-browser' ),
			'type_text'           => esc_html__( 'Text', 'linknacional-file-browser' ),
			'type_audio'          => esc_html__( 'Audio', 'linknacional-file-browser' ),
			'type_video'          => esc_html__( 'Video', 'linknacional-file-browser' ),
			'type_archive'        => esc_html__( 'Archive', 'linknacional-file-browser' ),
			'type_file'           => esc_html__( 'File', 'linknacional-file-browser' ),
			'copy_ai'             => esc_html__( 'Copy for AI', 'linknacional-file-browser' ),
			'ai_copied'           => esc_html__( 'Copied for AI', 'linknacional-file-browser' ),
			'ai_nothing'          => esc_html__( 'Nothing to copy', 'linknacional-file-browser' ),
			'ai_title'            => esc_html__( 'File Browser', 'linknacional-file-browser' ),
			'ai_url'              => esc_html__( 'URL', 'linknacional-file-browser' ),
			'ai_description'      => esc_html__( 'Description', 'linknacional-file-browser' ),
		));

		$atts = shortcode_atts( array(
			'folder_id' => 0,
			'root' => '',
			// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Shortcode attribute name, not a WP_Query/get_posts() parameter.
			'exclude' => '',
			'show_search' => 'true',
			'show_breadcrumb' => 'true',
			'show_folder_tree' => 'true',
			'layout' => 'grid',
		), $atts );

		$folder_id = intval( $atts['folder_id'] );
		$root_id = $this->resolve_root_folder( $atts['root'] );
		// With a root, the root folder becomes the starting point instead of the top-level Home.
		if ( $folder_id <= 0 && $root_id > 0 ) {
			$folder_id = $root_id;
		}
		$exclude_ids = $this->resolve_excluded_ids( $atts['exclude'], $root_id );
		$show_search = $atts['show_search'] === 'true';
		$show_breadcrumb = $atts['show_breadcrumb'] === 'true';
		$show_folder_tree = $atts['show_folder_tree'] === 'true';
		$layout = in_array( $atts['layout'], array( 'grid', 'list' ) ) ? $atts['layout'] : 'grid';

		ob_start();
		?>
		<div class="linknacional-filebrowser-public lnfb"
			data-folder-id="<?php echo esc_attr( $folder_id ); ?>"
			data-root-id="<?php echo esc_attr( $root_id ); ?>"
			data-exclude-ids="<?php echo esc_attr( implode( ',', $exclude_ids ) ); ?>"
			data-layout="<?php echo esc_attr( $layout ); ?>"
			data-show-tree="<?php echo $show_folder_tree ? '1' : '0'; ?>">

			<div class="lnfb-body">
				<?php if ( $show_folder_tree ) : ?>
				<aside class="lnfb-sidebar">
					<nav class="lnfb-collections" id="lnfb-collections" role="tablist" aria-label="<?php esc_attr_e( 'Collections', 'linknacional-file-browser' ); ?>">
						<button type="button" class="lnfb-collection is-active" role="tab" data-collection="files" aria-selected="true">
							<i class="fas fa-house"></i> <?php esc_html_e( 'Home', 'linknacional-file-browser' ); ?>
						</button>
						<button type="button" class="lnfb-collection" role="tab" data-collection="favorites" aria-selected="false">
							<i class="fas fa-star"></i> <?php esc_html_e( 'Favorites', 'linknacional-file-browser' ); ?>
						</button>
						<button type="button" class="lnfb-collection" role="tab" data-collection="recent" aria-selected="false">
							<i class="fas fa-clock-rotate-left"></i> <?php esc_html_e( 'Recent', 'linknacional-file-browser' ); ?>
						</button>
					</nav>
					<hr class="lnfb-nav-sep">
					<div class="lnfb-section-head">
						<span class="lnfb-section-title"><i class="fas fa-sitemap"></i> <?php esc_html_e( 'Folders', 'linknacional-file-browser' ); ?></span>
					</div>
					<div id="linknacional-folder-tree-public" class="lnfb-tree">
						<div class="lnfb-loading"><i class="fas fa-spinner fa-spin"></i> <?php esc_html_e( 'Loading folders…', 'linknacional-file-browser' ); ?></div>
					</div>
				</aside>
				<?php endif; ?>

				<div class="lnfb-content">
					<?php if ( $show_folder_tree ) : ?>
					<button type="button" class="lnfb-sidebar-handle" aria-label="<?php esc_attr_e( 'Toggle folder panel', 'linknacional-file-browser' ); ?>" title="<?php esc_attr_e( 'Toggle folder panel', 'linknacional-file-browser' ); ?>"><i class="fas fa-chevron-left"></i></button>
					<?php endif; ?>
					<div class="lnfb-workspace">
						<div class="lnfb-workspace-body">
							<div class="lnfb-toolbar">
								<?php if ( $show_search ) : ?>
								<div class="lnfb-search">
									<i class="fas fa-magnifying-glass"></i>
									<input type="search" id="linknacional-search-input" placeholder="<?php esc_attr_e( 'Search files and folders…', 'linknacional-file-browser' ); ?>" aria-label="<?php esc_attr_e( 'Search files and folders…', 'linknacional-file-browser' ); ?>">
									<button type="button" id="linknacional-clear-search" class="lnfb-search-clear" aria-label="<?php esc_attr_e( 'Clear search', 'linknacional-file-browser' ); ?>"><i class="fas fa-xmark"></i></button>
								</div>
								<?php endif; ?>
								<div class="lnfb-toolbar-row">
									<div class="lnfb-toolbar-left">
										<label class="screen-reader-text" for="linknacional-sort"><?php esc_html_e( 'Sort by', 'linknacional-file-browser' ); ?></label>
										<select id="linknacional-sort" class="lnfb-sort">
											<option value="name-asc"><?php esc_html_e( 'Name (A–Z)', 'linknacional-file-browser' ); ?></option>
											<option value="name-desc"><?php esc_html_e( 'Name (Z–A)', 'linknacional-file-browser' ); ?></option>
											<option value="newest"><?php esc_html_e( 'Newest first', 'linknacional-file-browser' ); ?></option>
											<option value="oldest"><?php esc_html_e( 'Oldest first', 'linknacional-file-browser' ); ?></option>
											<option value="size"><?php esc_html_e( 'Largest first', 'linknacional-file-browser' ); ?></option>
										</select>
										<button type="button" class="lnfb-copy-ai" id="lnfb-copy-ai">
											<i class="fas fa-robot"></i> <?php esc_html_e( 'Copy for AI', 'linknacional-file-browser' ); ?>
										</button>
									</div>
									<div class="lnfb-viewtoggle" role="group" aria-label="<?php esc_attr_e( 'View', 'linknacional-file-browser' ); ?>">
										<button type="button" class="lnfb-view-btn <?php echo $layout === 'grid' ? 'is-active' : ''; ?>" data-layout="grid" title="<?php esc_attr_e( 'Grid view', 'linknacional-file-browser' ); ?>"><i class="fas fa-table-cells-large"></i></button>
										<button type="button" class="lnfb-view-btn <?php echo $layout === 'list' ? 'is-active' : ''; ?>" data-layout="list" title="<?php esc_attr_e( 'List view', 'linknacional-file-browser' ); ?>"><i class="fas fa-list"></i></button>
									</div>
								</div>
							</div>
							<hr class="lnfb-main-sep">

							<div class="lnfb-content-area">
							<?php if ( $show_breadcrumb ) : ?>
							<div class="lnfb-breadcrumb" id="linknacional-current-path"></div>
							<?php endif; ?>

							<div id="linknacional-contents" class="lnfb-contents is-<?php echo esc_attr( $layout ); ?>" aria-live="polite">
								<div class="lnfb-skeleton">
									<div class="lnfb-sk-card"></div><div class="lnfb-sk-card"></div>
									<div class="lnfb-sk-card"></div><div class="lnfb-sk-card"></div>
									<div class="lnfb-sk-card"></div><div class="lnfb-sk-card"></div>
								</div>
							</div>
							<aside class="lnfb-drawer" id="lnfb-drawer"></aside>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	public function get_all_folders_frontend() {
		check_ajax_referer( 'linknacional_filebrowser_public_nonce', 'nonce' );
		$root_id     = isset( $_POST['root_id'] ) ? intval( wp_unslash( $_POST['root_id'] ) ) : 0;
		$exclude_ids = $this->parse_id_list( isset( $_POST['exclude_ids'] ) ? map_deep( wp_unslash( $_POST['exclude_ids'] ), 'sanitize_text_field' ) : '' );
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$folders = $wpdb->get_results("SELECT * FROM {$this->table_folders()} WHERE is_trashed = 0 ORDER BY parent_id ASC, name ASC");
		$files = $wpdb->get_results("SELECT * FROM {$this->table_files()} WHERE is_trashed = 0 ORDER BY folder_id ASC, original_name ASC");
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $root_id > 0 || ! empty( $exclude_ids ) ) {
			list( $folders, $files ) = $this->scope_items( $folders, $files, $root_id, $exclude_ids );
		}
		wp_send_json_success( array( 'folders' => $folders, 'files' => LinkNacionalFilebrowserFiles::prepare_rows( $files ) ) );
	}

	/**
	 * Frontend collections: favorites | recent (read-only, never includes trash).
	 */
	public function get_collection_frontend() {
		check_ajax_referer( 'linknacional_filebrowser_public_nonce', 'nonce' );
		$collection = isset( $_POST['collection'] ) ? sanitize_key( wp_unslash( $_POST['collection'] ) ) : '';
		$root_id    = isset( $_POST['root_id'] ) ? intval( wp_unslash( $_POST['root_id'] ) ) : 0;
		$exclude_ids = $this->parse_id_list( isset( $_POST['exclude_ids'] ) ? map_deep( wp_unslash( $_POST['exclude_ids'] ), 'sanitize_text_field' ) : '' );
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
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $root_id > 0 || ! empty( $exclude_ids ) ) {
			list( $folders, $files ) = $this->scope_items( $folders, $files, $root_id, $exclude_ids );
		}
		$folders = $this->attach_folder_item_counts( $folders );
		$files   = LinkNacionalFilebrowserFiles::prepare_rows( $files );
		wp_send_json_success( array( 'folders' => $folders, 'files' => $files ) );
	}

	/**
	 * Keep only the folders/files that live inside the given root subtree and
	 * drop anything that belongs to an excluded folder (or its subtree).
	 *
	 * @param array $folders
	 * @param array $files
	 * @param int   $root_id
	 * @param int[] $exclude_ids
	 * @return array [ $folders, $files ]
	 */
	private function scope_items( $folders, $files, $root_id, $exclude_ids ) {
		if ( $root_id > 0 ) {
			$scope   = $this->folder_subtree_ids( $root_id );
			$folders = array_values( array_filter( $folders, function ( $f ) use ( $scope ) {
				return in_array( (int) $f->id, $scope, true );
			} ) );
			$files = array_values( array_filter( $files, function ( $f ) use ( $scope ) {
				return in_array( (int) $f->folder_id, $scope, true );
			} ) );
		}
		if ( ! empty( $exclude_ids ) ) {
			$excl    = array_map( 'intval', $exclude_ids );
			$folders = array_values( array_filter( $folders, function ( $f ) use ( $excl ) {
				return ! in_array( (int) $f->id, $excl, true );
			} ) );
			$files = array_values( array_filter( $files, function ( $f ) use ( $excl ) {
				return ! in_array( (int) $f->folder_id, $excl, true );
			} ) );
		}
		return array( $folders, $files );
	}

	/**
	 * Ids of a folder and every descendant (only non-trashed folders).
	 *
	 * @param int $root_id
	 * @return int[]
	 */
	private function folder_subtree_ids( $root_id ) {
		global $wpdb;
		$ids      = array( (int) $root_id );
		$frontier = array( (int) $root_id );
		$guard    = 0;
		while ( ! empty( $frontier ) && $guard < 10000 ) {
			$next = array();
			foreach ( $frontier as $pid ) {
				// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from $wpdb->prefix; not user input.
				$children = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$this->table_folders()} WHERE parent_id = %d AND is_trashed = 0", $pid ) );
				// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				foreach ( $children as $cid ) {
					$cid = (int) $cid;
					if ( ! in_array( $cid, $ids, true ) ) {
						$ids[]  = $cid;
						$next[] = $cid;
					}
					++$guard;
				}
			}
			$frontier = $next;
		}
		return $ids;
	}

	public function get_folder_contents_frontend() {
		check_ajax_referer( 'linknacional_filebrowser_public_nonce', 'nonce' );
		$folder_id = isset( $_POST['folder_id'] ) ? intval( wp_unslash( $_POST['folder_id'] ) ) : 0;
		$contents = $this->get_folder_contents( $folder_id );
		wp_send_json_success( $contents );
	}

	public function search_files_frontend() {
		check_ajax_referer( 'linknacional_filebrowser_public_nonce', 'nonce' );
		$search_term = isset( $_POST['search_term'] ) ? sanitize_text_field( wp_unslash( $_POST['search_term'] ) ) : '';
		$folder_id = isset( $_POST['folder_id'] ) ? intval( wp_unslash( $_POST['folder_id'] ) ) : 0;
		$root_id   = isset( $_POST['root_id'] ) ? intval( wp_unslash( $_POST['root_id'] ) ) : 0;
		$exclude_ids = $this->parse_id_list( isset( $_POST['exclude_ids'] ) ? map_deep( wp_unslash( $_POST['exclude_ids'] ), 'sanitize_text_field' ) : '' );
		if ( empty( $search_term ) ) {
			wp_send_json_error( __( 'Search term is required', 'linknacional-file-browser' ) );
		}
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$folders = $wpdb->get_results( $wpdb->prepare(
			"SELECT f.*, p.name as parent_name, p.path as parent_path FROM {$this->table_folders()} f LEFT JOIN {$this->table_folders()} p ON f.parent_id = p.id WHERE f.name LIKE %s AND f.is_trashed = 0 ORDER BY f.name ASC",
			'%' . $wpdb->esc_like( $search_term ) . '%'
		));
		foreach ($folders as $folder) {
			$folder->full_path = $this->build_folder_path( $folder->id );
		}
		$files = $wpdb->get_results( $wpdb->prepare(
			"SELECT f.*, folder.name as folder_name, folder.path as folder_path FROM {$this->table_files()} f LEFT JOIN {$this->table_folders()} folder ON f.folder_id = folder.id WHERE f.original_name LIKE %s AND f.is_trashed = 0 ORDER BY f.original_name ASC",
			'%' . $wpdb->esc_like( $search_term ) . '%'
		));
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $root_id > 0 || ! empty( $exclude_ids ) ) {
			list( $folders, $files ) = $this->scope_items( $folders, $files, $root_id, $exclude_ids );
		}
		$folders = $this->attach_folder_item_counts( $folders );
		$files   = LinkNacionalFilebrowserFiles::prepare_rows( $files );
		wp_send_json_success( array( 'folders' => $folders, 'files' => $files, 'search_term' => $search_term ) );
	}

	/**
	 * Resolve the `root` shortcode attribute to a folder id.
	 *
	 * Accepts a numeric id, a folder name, or a slash-separated path
	 * (e.g. "parent/child"). Returns 0 when it cannot be resolved.
	 *
	 * @param string $root
	 * @return int
	 */
	private function resolve_root_folder( $root ) {
		$root = trim( (string) $root );
		if ( '' === $root ) {
			return 0;
		}
		global $wpdb;
		if ( ctype_digit( $root ) ) {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from $wpdb->prefix; not user input.
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table_folders()} WHERE id = %d AND is_trashed = 0", (int) $root ) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return $exists ? (int) $root : 0;
		}
		$parent = 0;
		foreach ( explode( '/', $root ) as $segment ) {
			$segment = $this->str_lower( trim( $segment ) );
			if ( '' === $segment ) {
				continue;
			}
			$found = 0;
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from $wpdb->prefix; not user input.
			$children = $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM {$this->table_folders()} WHERE parent_id = %d AND is_trashed = 0", $parent ) );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			foreach ( $children as $child ) {
				if ( $this->str_lower( $child->name ) === $segment ) {
					$found = (int) $child->id;
					break;
				}
			}
			if ( ! $found ) {
				return 0;
			}
			$parent = $found;
		}
		return $parent;
	}

	/**
	 * Resolve the `exclude` attribute (comma-separated folder names/paths) into
	 * folder ids — each matched folder plus its whole subtree. Matching is
	 * case-insensitive. Ids are restricted to the root subtree when a root is set.
	 *
	 * @param string $exclude
	 * @param int    $root_id
	 * @return int[]
	 */
	private function resolve_excluded_ids( $exclude, $root_id ) {
		$terms = array();
		foreach ( explode( ',', (string) $exclude ) as $term ) {
			$term = $this->str_lower( trim( $term ) );
			if ( '' !== $term ) {
				$terms[] = $term;
			}
		}
		if ( empty( $terms ) ) {
			return array();
		}
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is built from $wpdb->prefix; not user input.
		$rows = $wpdb->get_results( "SELECT id, name FROM {$this->table_folders()} WHERE is_trashed = 0" );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$scope = $root_id > 0 ? $this->folder_subtree_ids( $root_id ) : null;
		$ids = array();
		foreach ( $rows as $row ) {
			if ( null !== $scope && ! in_array( (int) $row->id, $scope, true ) ) {
				continue;
			}
			if ( in_array( $this->str_lower( $row->name ), $terms, true ) ) {
				foreach ( $this->folder_subtree_ids( (int) $row->id ) as $sid ) {
					$ids[] = $sid;
				}
			}
		}
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Multibyte-safe lowercase.
	 *
	 * @param string $value
	 * @return string
	 */
	private function str_lower( $value ) {
		return \function_exists( 'mb_strtolower' ) ? \mb_strtolower( (string) $value, 'UTF-8' ) : \strtolower( (string) $value );
	}

	/**
	 * Parse a comma-separated string of ids into a list of positive ints.
	 *
	 * @param mixed $raw
	 * @return int[]
	 */
	private function parse_id_list( $raw ) {
		$parts = is_array( $raw ) ? $raw : explode( ',', (string) $raw );
		$out   = array();
		foreach ( $parts as $part ) {
			$part = intval( $part );
			if ( $part > 0 ) {
				$out[] = $part;
			}
		}
		return $out;
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
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name built from $wpdb->prefix; not user input.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are built dynamically ($placeholders).
		$sub_counts  = $wpdb->get_results( $wpdb->prepare(
			"SELECT parent_id AS fid, COUNT(*) AS total FROM {$this->table_folders()} WHERE parent_id IN ($placeholders) AND is_trashed = 0 GROUP BY parent_id",
			$ids
		) );
		$file_counts = $wpdb->get_results( $wpdb->prepare(
			"SELECT folder_id AS fid, COUNT(*) AS total FROM {$this->table_files()} WHERE folder_id IN ($placeholders) AND is_trashed = 0 GROUP BY folder_id",
			$ids
		) );
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
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

	private function get_folder_contents( $folder_id ) {
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$folders = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$this->table_folders()} WHERE parent_id = %d AND is_trashed = 0 ORDER BY name ASC", $folder_id));
		$files = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$this->table_files()} WHERE folder_id = %d AND is_trashed = 0 ORDER BY original_name ASC", $folder_id));
		$current_folder = null;
		if ( $folder_id > 0 ) {
			$current_folder = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$this->table_folders()} WHERE id = %d", $folder_id));
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return array('folders' => $this->attach_folder_item_counts( $folders ), 'files' => LinkNacionalFilebrowserFiles::prepare_rows( $files ), 'current_folder' => $current_folder, 'breadcrumb' => $this->build_breadcrumb( $folder_id ));
	}

	private function build_breadcrumb( $folder_id ) {
		if ( $folder_id == 0 ) {
			return array( array( 'id' => 0, 'name' => __( 'Home', 'linknacional-file-browser' ), 'path' => '' ) );
		}
		global $wpdb;
		$breadcrumb = array();
		$current_id = $folder_id;
		$path_parts = array();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		while ( $current_id > 0 ) {
			$folder = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$this->table_folders()} WHERE id = %d", $current_id));
			if ( $folder ) { array_unshift( $path_parts, $folder->name ); $current_id = $folder->parent_id; } else { break; }
		}
		$current_id = $folder_id;
		$partial_path = '';
		while ( $current_id > 0 ) {
			$folder = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$this->table_folders()} WHERE id = %d", $current_id));
			if ( $folder ) {
				$folder_index = array_search( $folder->name, $path_parts );
				if ( $folder_index !== false ) { $partial_path = implode( '/', array_slice( $path_parts, 0, $folder_index + 1 ) ); }
				array_unshift( $breadcrumb, array( 'id' => $folder->id, 'name' => $folder->name, 'path' => $partial_path ));
				$current_id = $folder->parent_id;
			} else { break; }
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		array_unshift( $breadcrumb, array( 'id' => 0, 'name' => __( 'Home', 'linknacional-file-browser' ), 'path' => '' ) );
		return $breadcrumb;
	}

	private function build_folder_path( $folder_id ) {
		if ( $folder_id == 0 ) return 'Home';
		global $wpdb;
		$path_parts = array();
		$current_id = $folder_id;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		while ( $current_id != 0 ) {
			$folder = $wpdb->get_row( $wpdb->prepare("SELECT id, name, parent_id FROM {$this->table_folders()} WHERE id = %d", $current_id));
			if ( $folder ) { array_unshift( $path_parts, $folder->name ); $current_id = $folder->parent_id; } else { break; }
		}
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return 'Home' . ( !empty( $path_parts ) ? ' / ' . implode( ' / ', $path_parts ) : '' );
	}

	public function get_folder_files_frontend() {
		check_ajax_referer( 'linknacional_filebrowser_public_nonce', 'nonce' );
		$folder_id = isset( $_POST['folder_id'] ) ? intval( wp_unslash( $_POST['folder_id'] ) ) : 0;
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$files = $wpdb->get_results( $wpdb->prepare("SELECT * FROM {$this->table_files()} WHERE folder_id = %d AND is_trashed = 0 ORDER BY original_name ASC", $folder_id));
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		wp_send_json_success( LinkNacionalFilebrowserFiles::prepare_rows( $files ) );
	}

	/**
	 * Issue temporary share links for files, for the frontend "Copy for AI".
	 *
	 * Mirrors the admin handler but is gated by the public nonce (this browser
	 * is already public/read-only), returning { links: { id: viewer_url } }.
	 */
	public function share_files_frontend() {
		check_ajax_referer( 'linknacional_filebrowser_public_nonce', 'nonce' );

		$raw = isset( $_POST['file_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['file_ids'] ) ) : '';
		$ids = array();
		foreach ( explode( ',', (string) $raw ) as $part ) {
			$part = intval( $part );
			if ( $part > 0 ) {
				$ids[] = $part;
			}
		}
		$ids = array_values( array_unique( $ids ) );
		if ( empty( $ids ) ) {
			wp_send_json_error( esc_html__( 'No files selected', 'linknacional-file-browser' ) );
		}

		global $wpdb;
		$placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name built from $wpdb->prefix; not user input.
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Placeholders are built dynamically ($placeholders).
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id FROM {$this->table_files()} WHERE id IN ($placeholders) AND is_trashed = 0",
			$ids
		) );
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$links = array();
		foreach ( $rows as $row ) {
			$token = LinkNacionalFilebrowserFiles::create_share_token( (int) $row->id );
			if ( $token ) {
				$links[ (int) $row->id ] = LinkNacionalFilebrowserFiles::viewer_url( (int) $row->id, $token );
			}
		}
		if ( empty( $links ) ) {
			wp_send_json_error( esc_html__( 'Could not create the link', 'linknacional-file-browser' ) );
		}
		wp_send_json_success( array(
			'links'      => $links,
			'expires_in' => LinkNacionalFilebrowserFiles::TOKEN_TTL,
		) );
	}

	private function table_folders() {
		global $wpdb;
		return $wpdb->prefix . 'linknacional_filebrowser_folders';
	}

	private function table_files() {
		global $wpdb;
		return $wpdb->prefix . 'linknacional_filebrowser_files';
	}
}
