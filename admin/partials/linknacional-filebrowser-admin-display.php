<?php
/**
 * Admin display — presentational template.
 *
 * Pure view: no data logic, no query, no inline <style>/<script>.
 * All decisions are made in LinkNacionalFilebrowserAdmin::admin_page() and passed via $data.
 *
 * @var array $data Prepared view data. Keys: title, shortcode, migration_available,
 *                   allowed_extensions, max_upload_size_label.
 *
 * @package LinkNacional_Filebrowser
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$lkn_extensions = isset( $data['allowed_extensions'] ) ? $data['allowed_extensions'] : '';
?>
<div class="wrap lkn-fb-admin">

	<?php // Marks the end of the page header so core places admin notices here (top of page) instead of after the first heading inside the config block. ?>
	<hr class="wp-header-end">

	<div class="lkn-fb-header">
		<div class="lkn-fb-header-title">
			<span class="lkn-fb-logo"><i class="fas fa-folder-tree"></i></span>
			<div>
				<h1><?php echo esc_html( $data['title'] ); ?></h1>
				<p class="lkn-fb-subtitle"><?php esc_html_e( 'Organize your files and share them anywhere with a shortcode.', 'linknacional-file-browser' ); ?></p>
			</div>
		</div>
		<nav class="lkn-fb-tabs" role="tablist">
			<button type="button" class="lkn-fb-tab is-active" role="tab" data-tab="files" aria-selected="true">
				<i class="fas fa-folder-open"></i> <?php esc_html_e( 'Files', 'linknacional-file-browser' ); ?>
			</button>
			<button type="button" class="lkn-fb-tab" role="tab" data-tab="help" aria-selected="false">
				<i class="fas fa-circle-question"></i> <?php esc_html_e( 'Help', 'linknacional-file-browser' ); ?>
			</button>
		</nav>
	</div>

	<?php if ( ! empty( $data['migration_available'] ) ) : ?>
	<div id="linknacional-migration-banner" class="lkn-fb-migration">
		<div class="lkn-fb-migration-text">
			<strong><i class="fas fa-triangle-exclamation"></i> <?php esc_html_e( 'Data migration required', 'linknacional-file-browser' ); ?></strong>
			<p><?php esc_html_e( 'We detected data from a previous version. Migrate your folders, files, and uploads to the new format.', 'linknacional-file-browser' ); ?></p>
		</div>
		<div class="lkn-fb-migration-actions">
			<span id="linknacional-migration-status" class="lkn-fb-migration-status"></span>
			<button type="button" id="linknacional-migrate-btn" class="button button-primary">
				<i class="fas fa-rotate"></i> <?php esc_html_e( 'Migrate Now', 'linknacional-file-browser' ); ?>
			</button>
		</div>
	</div>
	<?php endif; ?>

	<!-- ============================ TAB: FILES ============================ -->
	<section class="lkn-fb-panel" data-panel="files" role="tabpanel">

		<div class="lkn-fb-body">
			<aside class="lkn-fb-sidebar" id="lkn-fb-sidebar">

				<nav class="lkn-fb-collections" id="lkn-fb-collections" role="tablist" aria-label="<?php esc_attr_e( 'Collections', 'linknacional-file-browser' ); ?>">
					<button type="button" class="lkn-fb-collection is-active" role="tab" data-collection="files" aria-selected="true">
						<i class="fas fa-house"></i> <?php esc_html_e( 'Home', 'linknacional-file-browser' ); ?>
					</button>
					<button type="button" class="lkn-fb-collection" role="tab" data-collection="favorites" aria-selected="false">
						<i class="fas fa-star"></i> <?php esc_html_e( 'Favorites', 'linknacional-file-browser' ); ?>
					</button>
					<button type="button" class="lkn-fb-collection" role="tab" data-collection="recent" aria-selected="false">
						<i class="fas fa-clock-rotate-left"></i> <?php esc_html_e( 'Recent', 'linknacional-file-browser' ); ?>
					</button>
					<button type="button" class="lkn-fb-collection" role="tab" data-collection="trash" aria-selected="false">
						<i class="fas fa-trash-can"></i> <?php esc_html_e( 'Trash', 'linknacional-file-browser' ); ?>
					</button>
				</nav>
				<hr class="lkn-fb-nav-sep">

				<div class="lkn-fb-section-head">
					<span class="lkn-fb-section-title"><i class="fas fa-sitemap"></i> <?php esc_html_e( 'Folders', 'linknacional-file-browser' ); ?></span>
					<button type="button" class="lkn-fb-icon-btn lkn-fb-icon-btn-accent" id="lkn-fb-new-folder" aria-label="<?php esc_attr_e( 'New folder', 'linknacional-file-browser' ); ?>" title="<?php esc_attr_e( 'New folder', 'linknacional-file-browser' ); ?>"><i class="fas fa-plus"></i></button>
				</div>
				<div id="lkn-fb-tree" class="lkn-fb-tree"></div>
			</aside>

			<main class="lkn-fb-main" id="lkn-fb-main">
				<button type="button" class="lkn-fb-sidebar-handle" id="lkn-fb-sidebar-handle" aria-label="<?php esc_attr_e( 'Toggle folder panel', 'linknacional-file-browser' ); ?>" title="<?php esc_attr_e( 'Toggle folder panel', 'linknacional-file-browser' ); ?>">
					<i class="fas fa-chevron-left"></i>
				</button>

				<div class="lkn-fb-workspace">
					<div class="lkn-fb-workspace-body">
						<div class="lkn-fb-toolbar">
							<div class="lkn-fb-search">
								<i class="fas fa-magnifying-glass"></i>
								<input type="search" id="lkn-fb-search" placeholder="<?php esc_attr_e( 'Filter this folder…', 'linknacional-file-browser' ); ?>" aria-label="<?php esc_attr_e( 'Filter current folder', 'linknacional-file-browser' ); ?>">
								<button type="button" id="lkn-fb-search-clear" class="lkn-fb-search-clear" hidden aria-label="<?php esc_attr_e( 'Clear filter', 'linknacional-file-browser' ); ?>"><i class="fas fa-xmark"></i></button>
							</div>
							<div class="lkn-fb-toolbar-row">
								<div class="lkn-fb-toolbar-left">
									<button type="button" class="button lkn-fb-bulk-btn" id="lkn-fb-bulk-delete" hidden>
										<i class="fas fa-trash"></i> <span id="lkn-fb-bulk-count">0</span>
									</button>
									<label class="screen-reader-text" for="lkn-fb-sort"><?php esc_html_e( 'Sort by', 'linknacional-file-browser' ); ?></label>
									<select id="lkn-fb-sort" class="lkn-fb-sort">
										<option value="name-asc"><?php esc_html_e( 'Name (A–Z)', 'linknacional-file-browser' ); ?></option>
										<option value="name-desc"><?php esc_html_e( 'Name (Z–A)', 'linknacional-file-browser' ); ?></option>
										<option value="newest"><?php esc_html_e( 'Newest first', 'linknacional-file-browser' ); ?></option>
										<option value="oldest"><?php esc_html_e( 'Oldest first', 'linknacional-file-browser' ); ?></option>
										<option value="size"><?php esc_html_e( 'Largest first', 'linknacional-file-browser' ); ?></option>
									</select>
									<button type="button" class="button" id="lkn-fb-copy-ai">
										<i class="fas fa-robot"></i> <?php esc_html_e( 'Copy for AI', 'linknacional-file-browser' ); ?>
									</button>
								</div>
								<div class="lkn-fb-viewtoggle" role="group" aria-label="<?php esc_attr_e( 'View', 'linknacional-file-browser' ); ?>">
									<button type="button" class="lkn-fb-view-btn is-active" data-view="grid" title="<?php esc_attr_e( 'Grid view', 'linknacional-file-browser' ); ?>"><i class="fas fa-table-cells-large"></i></button>
									<button type="button" class="lkn-fb-view-btn" data-view="list" title="<?php esc_attr_e( 'List view', 'linknacional-file-browser' ); ?>"><i class="fas fa-list"></i></button>
								</div>
							</div>
						</div>
						<hr class="lkn-fb-main-sep">

						<div class="lkn-fb-content-area">
							<div class="lkn-fb-breadcrumb" id="lkn-fb-breadcrumb"></div>

							<button type="button" class="lkn-fb-uploadtile" id="lkn-fb-upload-tile">
								<span class="lkn-fb-uploadtile-icn"><i class="fas fa-cloud-arrow-up"></i></span>
								<span class="lkn-fb-uploadtile-title"><?php esc_html_e( 'Upload files', 'linknacional-file-browser' ); ?></span>
								<span class="lkn-fb-uploadtile-hint" id="lkn-fb-upload-tile-hint"><?php esc_html_e( 'Click to browse or drag files here', 'linknacional-file-browser' ); ?></span>
							</button>

							<div class="lkn-fb-dropzone" id="lkn-fb-dropzone">
								<div id="lkn-fb-contents" class="lkn-fb-contents is-grid" aria-live="polite"></div>
							</div>

							<!-- ===================== UPLOAD SURFACE (fills the content area) ===================== -->
							<div class="lkn-fb-overlay" id="lkn-fb-dropzone-overlay">
								<div class="lkn-fb-uploadbar-card" role="status" aria-live="polite">
									<div class="lkn-fb-uploadbar-head">
										<span class="lkn-fb-upstate"><i class="fas fa-cloud-arrow-up"></i></span>
										<span id="lkn-fb-upload-title"><?php esc_html_e( 'Uploading files…', 'linknacional-file-browser' ); ?></span>
										<button type="button" class="lkn-fb-upload-clear" id="lkn-fb-upload-clear" aria-label="<?php esc_attr_e( 'Dismiss', 'linknacional-file-browser' ); ?>"><i class="fas fa-xmark"></i></button>
									</div>
									<ul id="lkn-fb-upload-list" class="lkn-fb-upload-list"></ul>
									<span class="lkn-fb-uploadbar-timer"></span>
								</div>
							</div>

							<!-- ===================== DETAIL PANEL (overlays the content area) ===================== -->
							<aside class="lkn-fb-drawer" id="lkn-fb-drawer">
								<button type="button" class="lkn-fb-drawer-scrim" data-drawer-close tabindex="-1" aria-hidden="true"></button>
								<div class="lkn-fb-drawer-panel" role="dialog" aria-modal="true" aria-labelledby="lkn-fb-drawer-name">
									<header class="lkn-fb-drawer-head">
										<span class="lkn-fb-drawer-icn" id="lkn-fb-drawer-icn"><i class="fas fa-file"></i></span>
										<div class="lkn-fb-drawer-titles">
											<h2 class="lkn-fb-drawer-name" id="lkn-fb-drawer-name"></h2>
											<p class="lkn-fb-drawer-meta" id="lkn-fb-drawer-meta"></p>
										</div>
										<button type="button" class="lkn-fb-drawer-close" data-drawer-close aria-label="<?php esc_attr_e( 'Close', 'linknacional-file-browser' ); ?>"><i class="fas fa-xmark"></i></button>
									</header>
									<div class="lkn-fb-drawer-scroll">
										<div class="lkn-fb-drawer-preview" id="lkn-fb-drawer-preview"></div>

										<div class="lkn-fb-drawer-block">
											<h3 class="lkn-fb-drawer-h3"><?php esc_html_e( 'Details', 'linknacional-file-browser' ); ?></h3>
											<dl class="lkn-fb-drawer-details" id="lkn-fb-drawer-details"></dl>
										</div>

										<button type="button" class="button button-primary lkn-fb-copyurl" id="lkn-fb-drawer-copyurl">
											<i class="fas fa-link"></i> <?php esc_html_e( 'Copy URL', 'linknacional-file-browser' ); ?>
										</button>

										<div class="lkn-fb-drawer-actions" id="lkn-fb-drawer-actions"></div>

										<div class="lkn-fb-drawer-block">
											<h3 class="lkn-fb-drawer-h3"><?php esc_html_e( 'Quick actions', 'linknacional-file-browser' ); ?></h3>
											<div class="lkn-fb-drawer-quick" id="lkn-fb-drawer-quick"></div>
										</div>
									</div>
								</div>
							</aside>
						</div>
					</div>
				</div>
			</main>
		</div>
	</section>

	<!-- ============================ TAB: HELP ============================ -->
	<section class="lkn-fb-panel" data-panel="help" role="tabpanel" hidden>
		<div class="lkn-fb-help-grid">
			<div class="lkn-fb-card">
				<h2><i class="fas fa-rocket"></i> <?php esc_html_e( 'Display it on your site', 'linknacional-file-browser' ); ?></h2>
				<ol>
					<li><?php esc_html_e( 'Copy the shortcode below', 'linknacional-file-browser' ); ?></li>
					<li><?php esc_html_e( 'Open the page or post where you want the browser', 'linknacional-file-browser' ); ?></li>
					<li><?php esc_html_e( 'Add a shortcode block in your editor', 'linknacional-file-browser' ); ?></li>
					<li><?php esc_html_e( 'Paste the shortcode and publish', 'linknacional-file-browser' ); ?></li>
				</ol>
				<div class="lkn-fb-shortcode-box">
					<code><?php echo esc_html( $data['shortcode'] ); ?></code>
					<button type="button" class="button button-primary copy-shortcode" data-shortcode="<?php echo esc_attr( $data['shortcode'] ); ?>">
						<i class="fas fa-copy"></i> <?php esc_html_e( 'Copy', 'linknacional-file-browser' ); ?>
					</button>
				</div>
				<p class="lkn-fb-muted"><?php esc_html_e( 'The browser displays every folder and file you create here. Visitors can browse and download files — read-only.', 'linknacional-file-browser' ); ?></p>
				<p class="lkn-fb-muted"><?php printf( esc_html__( 'Maximum upload size: %s', 'linknacional-file-browser' ), esc_html( $data['max_upload_size_label'] ) ); ?></p>
			</div>

			<div class="lkn-fb-card">
				<h2><i class="fas fa-sliders"></i> <?php esc_html_e( 'Shortcode attributes', 'linknacional-file-browser' ); ?></h2>
				<table class="lkn-fb-table">
					<thead><tr><th><?php esc_html_e( 'Attribute', 'linknacional-file-browser' ); ?></th><th><?php esc_html_e( 'Default', 'linknacional-file-browser' ); ?></th><th><?php esc_html_e( 'Description', 'linknacional-file-browser' ); ?></th></tr></thead>
					<tbody>
						<tr><td><code>folder_id</code></td><td><code>0</code></td><td><?php esc_html_e( 'Folder to open on load.', 'linknacional-file-browser' ); ?></td></tr>
						<tr><td><code>root</code></td><td><code>0</code></td><td><?php esc_html_e( 'Folder id, name, or path used as the tree root (e.g. root="Projects"). Everything outside it is hidden.', 'linknacional-file-browser' ); ?></td></tr>
						<tr><td><code>exclude</code></td><td><code>[]</code></td><td><?php esc_html_e( 'Comma-separated folder names to hide (case-insensitive), e.g. exclude="Drafts,Archive".', 'linknacional-file-browser' ); ?></td></tr>
						<tr><td><code>show_search</code></td><td><code>true</code></td><td><?php esc_html_e( 'Show the search box.', 'linknacional-file-browser' ); ?></td></tr>
						<tr><td><code>show_breadcrumb</code></td><td><code>true</code></td><td><?php esc_html_e( 'Show the breadcrumb trail.', 'linknacional-file-browser' ); ?></td></tr>
						<tr><td><code>show_folder_tree</code></td><td><code>true</code></td><td><?php esc_html_e( 'Show the folder sidebar.', 'linknacional-file-browser' ); ?></td></tr>
						<tr><td><code>layout</code></td><td><code>grid</code></td><td><?php esc_html_e( 'grid or list.', 'linknacional-file-browser' ); ?></td></tr>
					</tbody>
				</table>
			</div>

			<div class="lkn-fb-card">
				<h2><i class="fas fa-lightbulb"></i> <?php esc_html_e( 'Tips', 'linknacional-file-browser' ); ?></h2>
				<ul class="lkn-fb-tips">
					<li><i class="fas fa-cloud-arrow-up"></i> <?php esc_html_e( 'Drag & drop several files onto the upload area to upload them at once.', 'linknacional-file-browser' ); ?></li>
					<li><i class="fas fa-arrows-up-down-left-right"></i> <?php esc_html_e( 'Drag a file or folder onto another folder to move it.', 'linknacional-file-browser' ); ?></li>
					<li><i class="fas fa-ellipsis"></i> <?php esc_html_e( 'Use the ⋯ menu on each item to open, download, rename, copy the link, or delete.', 'linknacional-file-browser' ); ?></li>
					<li><i class="fas fa-square-check"></i> <?php esc_html_e( 'Tick the checkboxes to run bulk actions.', 'linknacional-file-browser' ); ?></li>
				</ul>
			</div>
		</div>
	</section>

	<input type="file" id="lkn-fb-file-input" multiple accept="<?php echo esc_attr( $lkn_extensions ); ?>" hidden>
</div>

<!-- ===================== CREATE FOLDER MODAL ===================== -->
<div id="create-folder-modal" class="lkn-fb-modal" hidden>
	<div class="lkn-fb-modal-box" role="dialog" aria-modal="true" aria-labelledby="lkn-fb-folder-title">
		<button type="button" class="lkn-fb-modal-close" data-close aria-label="<?php esc_attr_e( 'Close', 'linknacional-file-browser' ); ?>">&times;</button>
		<h2 id="lkn-fb-folder-title"><i class="fas fa-folder-plus"></i> <?php esc_html_e( 'Create New Folder', 'linknacional-file-browser' ); ?></h2>
		<form id="create-folder-form">
			<label for="lkn-fb-folder-name"><?php esc_html_e( 'Folder name', 'linknacional-file-browser' ); ?></label>
			<input type="text" id="lkn-fb-folder-name" name="folder_name" required autocomplete="off">
			<input type="hidden" id="parent-folder-id" name="parent_id" value="0">
			<div class="lkn-fb-modal-actions">
				<button type="button" class="button" data-close><?php esc_html_e( 'Cancel', 'linknacional-file-browser' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Create folder', 'linknacional-file-browser' ); ?></button>
			</div>
		</form>
	</div>
</div>

<!-- ===================== RENAME MODAL ===================== -->
<div id="lkn-fb-rename-modal" class="lkn-fb-modal" hidden>
	<div class="lkn-fb-modal-box" role="dialog" aria-modal="true" aria-labelledby="lkn-fb-rename-title">
		<button type="button" class="lkn-fb-modal-close" data-close aria-label="<?php esc_attr_e( 'Close', 'linknacional-file-browser' ); ?>">&times;</button>
		<h2 id="lkn-fb-rename-title"><i class="fas fa-pen"></i> <?php esc_html_e( 'Rename', 'linknacional-file-browser' ); ?></h2>
		<form id="lkn-fb-rename-form">
			<label for="lkn-fb-rename-input" id="lkn-fb-rename-label"><?php esc_html_e( 'Name', 'linknacional-file-browser' ); ?></label>
			<div class="lkn-fb-rename-field">
				<input type="text" id="lkn-fb-rename-input" autocomplete="off" required>
				<span class="lkn-fb-ext" id="lkn-fb-rename-ext" hidden></span>
			</div>
			<p class="lkn-fb-field-error" id="lkn-fb-rename-error" hidden></p>
			<div class="lkn-fb-modal-actions">
				<button type="button" class="button" data-close><?php esc_html_e( 'Cancel', 'linknacional-file-browser' ); ?></button>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save', 'linknacional-file-browser' ); ?></button>
			</div>
		</form>
	</div>
</div>

<!-- ===================== MOVE MODAL ===================== -->
<div id="lkn-fb-move-modal" class="lkn-fb-modal" hidden>
	<div class="lkn-fb-modal-box" role="dialog" aria-modal="true" aria-labelledby="lkn-fb-move-title">
		<button type="button" class="lkn-fb-modal-close" data-close aria-label="<?php esc_attr_e( 'Close', 'linknacional-file-browser' ); ?>">&times;</button>
		<h2 id="lkn-fb-move-title"><i class="fas fa-arrow-right-arrow-left"></i> <?php esc_html_e( 'Move to…', 'linknacional-file-browser' ); ?></h2>
		<p class="lkn-fb-muted" id="lkn-fb-move-subject"></p>
		<label for="lkn-fb-move-select"><?php esc_html_e( 'Destination folder', 'linknacional-file-browser' ); ?></label>
		<select id="lkn-fb-move-select"></select>
		<div class="lkn-fb-modal-actions">
			<button type="button" class="button" data-close><?php esc_html_e( 'Cancel', 'linknacional-file-browser' ); ?></button>
			<button type="button" class="button button-primary" id="lkn-fb-move-confirm"><?php esc_html_e( 'Move', 'linknacional-file-browser' ); ?></button>
		</div>
	</div>
</div>

<!-- ===================== CONFIRM MODAL ===================== -->
<div id="lkn-fb-confirm-modal" class="lkn-fb-modal" hidden>
	<div class="lkn-fb-modal-box lkn-fb-modal-sm" role="dialog" aria-modal="true" aria-labelledby="lkn-fb-confirm-title">
		<h2 id="lkn-fb-confirm-title"><i class="fas fa-triangle-exclamation"></i> <span id="lkn-fb-confirm-heading"></span></h2>
		<p id="lkn-fb-confirm-message" class="lkn-fb-muted"></p>
		<div class="lkn-fb-modal-actions">
			<button type="button" class="button" data-close><?php esc_html_e( 'Cancel', 'linknacional-file-browser' ); ?></button>
			<button type="button" class="button lkn-fb-danger" id="lkn-fb-confirm-ok"></button>
		</div>
	</div>
</div>

<div id="lkn-fb-toasts" class="lkn-fb-toasts" aria-live="polite"></div>
