<?php

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
// Custom tables — no WP core API exists. $wpdb is the only correct approach.

namespace LinkNacional\Filebrowser\Public;

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

	public function register_shortcode() {
		add_shortcode( 'linkn_filebrowser', array( $this, 'render_filebrowser_shortcode' ) );
	}

	public function render_filebrowser_shortcode( $atts ) {
		wp_enqueue_script( 'linknacional-filebrowser-fontawesome', LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'assets/js/compiled/fontawesome.compiled.js', array(), LINKNACIONAL_FILEBROWSER_VERSION, false );
		wp_enqueue_style( $this->plugin_name, LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'public/css/linknacional-filebrowser-public.css', array(), LINKNACIONAL_FILEBROWSER_VERSION, 'all' );
		wp_enqueue_script( $this->plugin_name, LINKNACIONAL_FILEBROWSER_PLUGIN_URL . 'public/js/linknacional-filebrowser-public.js', array( 'jquery' ), LINKNACIONAL_FILEBROWSER_VERSION, false );

		wp_localize_script( $this->plugin_name, 'linknacional_public_ajax', array(
			'ajax_url'            => admin_url( 'admin-ajax.php' ),
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
			'open_new_tab'        => esc_html__( 'Open in new tab', 'linknacional-file-browser' ),
			'download'            => esc_html__( 'Download', 'linknacional-file-browser' ),
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
		));

		$atts = shortcode_atts( array(
			'folder_id' => 0,
			'root' => '',
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
		$exclude_ids = $this->parse_id_list( isset( $_POST['exclude_ids'] ) ? wp_unslash( $_POST['exclude_ids'] ) : '' );
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$folders = $wpdb->get_results("SELECT * FROM {$this->table_folders()} WHERE is_trashed = 0 ORDER BY parent_id ASC, name ASC");
		$files = $wpdb->get_results("SELECT * FROM {$this->table_files()} WHERE is_trashed = 0 ORDER BY folder_id ASC, original_name ASC");
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $root_id > 0 || ! empty( $exclude_ids ) ) {
			list( $folders, $files ) = $this->scope_items( $folders, $files, $root_id, $exclude_ids );
		}
		wp_send_json_success( array( 'folders' => $folders, 'files' => $files ) );
	}

	/**
	 * Frontend collections: favorites | recent (read-only, never includes trash).
	 */
	public function get_collection_frontend() {
		check_ajax_referer( 'linknacional_filebrowser_public_nonce', 'nonce' );
		$collection = isset( $_POST['collection'] ) ? sanitize_key( wp_unslash( $_POST['collection'] ) ) : '';
		$root_id    = isset( $_POST['root_id'] ) ? intval( wp_unslash( $_POST['root_id'] ) ) : 0;
		$exclude_ids = $this->parse_id_list( isset( $_POST['exclude_ids'] ) ? wp_unslash( $_POST['exclude_ids'] ) : '' );
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
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$children = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$this->table_folders()} WHERE parent_id = %d AND is_trashed = 0", $pid ) );
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
		$exclude_ids = $this->parse_id_list( isset( $_POST['exclude_ids'] ) ? wp_unslash( $_POST['exclude_ids'] ) : '' );
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
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$this->table_folders()} WHERE id = %d AND is_trashed = 0", (int) $root ) );
			return $exists ? (int) $root : 0;
		}
		$parent = 0;
		foreach ( explode( '/', $root ) as $segment ) {
			$segment = $this->str_lower( trim( $segment ) );
			if ( '' === $segment ) {
				continue;
			}
			$found = 0;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$children = $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM {$this->table_folders()} WHERE parent_id = %d AND is_trashed = 0", $parent ) );
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
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT id, name FROM {$this->table_folders()} WHERE is_trashed = 0" );
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
		return array('folders' => $this->attach_folder_item_counts( $folders ), 'files' => $files, 'current_folder' => $current_folder, 'breadcrumb' => $this->build_breadcrumb( $folder_id ));
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
		wp_send_json_success( $files );
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
