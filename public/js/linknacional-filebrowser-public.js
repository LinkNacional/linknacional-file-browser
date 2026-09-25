/**
 * Link Nacional File Browser — frontend.
 *
 * Read-only browser mirroring the admin UX: folder tree, breadcrumb,
 * grid/list cards, contextual menu (preview / download / copy link / open),
 * image & PDF preview lightbox and toast feedback.
 */
(function ($) {
	'use strict';

	var L = window.linknacional_public_ajax || {};
	function t(key, fallback) { return L[key] || fallback; }

	var IMG_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

	var state = {
		nonce: '',
		currentFolderId: 0,
		rootId: 0,
		excludeIds: [],
		folders: [],
		files: [],
		view: 'grid',
		sort: 'name-asc',
		collection: 'files',
		contents: null,
		searchData: null,
		searchActive: false,
		drawerItem: null,
		drawerFloorH: 0,
		ready: false
	};

	/* ------------------------------------------------------------------ *
	 *  Helpers
	 * ------------------------------------------------------------------ */

	function esc(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function extOf(name) {
		var dot = String(name).lastIndexOf('.');
		return dot > -1 ? String(name).slice(dot + 1).toLowerCase() : '';
	}

	function formatSize(bytes) {
		bytes = Number(bytes) || 0;
		if (bytes <= 0) { return '0 B'; }
		var units = ['B', 'KB', 'MB', 'GB', 'TB'];
		var i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
		return (bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
	}

	function typeIcon(ext) {
		var map = {
			pdf: 'fas fa-file-pdf', doc: 'fas fa-file-word', docx: 'fas fa-file-word',
			xls: 'fas fa-file-excel', xlsx: 'fas fa-file-excel',
			ppt: 'fas fa-file-powerpoint', pptx: 'fas fa-file-powerpoint',
			txt: 'fas fa-file-lines', jpg: 'fas fa-file-image', jpeg: 'fas fa-file-image',
			png: 'fas fa-file-image', gif: 'fas fa-file-image', webp: 'fas fa-file-image',
			zip: 'fas fa-file-archive', rar: 'fas fa-file-archive', '7z': 'fas fa-file-archive',
			mp3: 'fas fa-file-audio', wav: 'fas fa-file-audio', ogg: 'fas fa-file-audio',
			mp4: 'fas fa-file-video', mov: 'fas fa-file-video', avi: 'fas fa-file-video'
		};
		return map[ext] || 'fas fa-file';
	}

	function typeCategory(ext) {
		ext = String(ext || '').toLowerCase();
		if (IMG_EXT.indexOf(ext) !== -1) { return t('type_image', 'Image'); }
		var map = {
			pdf: 'type_pdf', doc: 'type_word', docx: 'type_word',
			xls: 'type_excel', xlsx: 'type_excel', ppt: 'type_powerpoint', pptx: 'type_powerpoint',
			txt: 'type_text', mp3: 'type_audio', wav: 'type_audio', ogg: 'type_audio',
			mp4: 'type_video', mov: 'type_video', avi: 'type_video',
			zip: 'type_archive', rar: 'type_archive', '7z': 'type_archive'
		};
		return map[ext] ? t(map[ext], 'File') : t('type_file', 'File');
	}

	function formatDate(value) {
		if (!value) { return ''; }
		var d = new Date(String(value).replace(' ', 'T'));
		if (isNaN(d.getTime())) { return ''; }
		var pad = function (n) { return (n < 10 ? '0' : '') + n; };
		return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear();
	}

	function itemLocation(item) {
		if (item.folder_name) { return item.folder_name; }
		var fid = Number(item.folder_id || 0);
		if (!fid) { return t('home', 'Home'); }
		var folder = null;
		state.folders.forEach(function (f) { if (Number(f.id) === fid) { folder = f; } });
		return folder ? folder.name : t('home', 'Home');
	}

	function container() { return $('.linknacional-filebrowser-public').first(); }

	function api(action, data) {
		return $.ajax({
			url: L.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: $.extend({ action: action, nonce: state.nonce }, data || {})
		});
	}

	function toast(message, type) {
		type = type || 'info';
		var icon = type === 'success' ? 'fas fa-circle-check'
			: type === 'error' ? 'fas fa-circle-exclamation'
				: 'fas fa-circle-info';
		var $t = $('<div class="lnfb-toast ' + type + '">'
			+ '<i class="lnfb-toast-icn ' + icon + '"></i>'
			+ '<span class="lnfb-toast-msg">' + esc(message) + '</span>'
			+ '<button type="button" class="lnfb-toast-close" aria-label="' + esc(t('close', 'Close')) + '"><i class="fas fa-xmark"></i></button>'
			+ '<span class="lnfb-toast-bar"></span>'
			+ '</div>');
		$('#lnfb-toasts').append($t);
		window.requestAnimationFrame(function () { $t.addClass('show'); });

		var timer = null;
		var dismiss = function () {
			if ($t.hasClass('is-hiding')) { return; }
			window.clearTimeout(timer);
			$t.removeClass('show').addClass('is-hiding');
			window.setTimeout(function () { $t.remove(); }, 340);
		};
		timer = window.setTimeout(dismiss, 10000);
		$t.find('.lnfb-toast-close').on('click', dismiss);
	}

	/* ------------------------------------------------------------------ *
	 *  Context menu
	 * ------------------------------------------------------------------ */

	function closeMenu() { $('.lnfb-menu').remove(); }

	function openMenu($trigger, item) {
		closeMenu();
		var actions = buildActions(item);
		var $menu = $('<div class="lnfb-menu" role="menu"></div>');
		actions.forEach(function (a) {
			$('<button type="button" role="menuitem" class="lnfb-menu-item"><i class="' + a.icon + '"></i> ' + esc(a.label) + '</button>')
				.on('click', function () { closeMenu(); a.run(); })
				.appendTo($menu);
		});
		$('body').append($menu);
		var rect = $trigger[0].getBoundingClientRect();
		var w = $menu.outerWidth();
		var h = $menu.outerHeight();
		var left = Math.max(8, rect.right - w);
		var top = rect.bottom + 6;
		if (top + h > window.innerHeight - 8) { top = rect.top - h - 6; }
		$menu.css({ left: left + 'px', top: top + 'px' });
		window.setTimeout(function () {
			$(document).one('click', closeMenu);
			$(window).one('resize', closeMenu);
		}, 0);
	}

	function buildActions(item) {
		if (item.type === 'folder') {
			return [
				{ label: t('open_file', 'Open'), icon: 'fas fa-folder-open', run: function () { navigateTo(item.id); } }
			];
		}
		var actions = [
			{ label: t('preview', 'Preview'), icon: 'fas fa-eye', run: function () { openDrawer(item); } }
		];
		if (item.allow_download) {
			actions.push({ label: t('download', 'Download'), icon: 'fas fa-download', run: function () { downloadFile(item); } });
			actions.push({ label: t('copy_link', 'Copy link'), icon: 'fas fa-link', run: function () { copyLink(item); } });
			actions.push({ label: t('open_new_tab', 'Open in new tab'), icon: 'fas fa-arrow-up-right-from-square', run: function () { window.open(item.url, '_blank', 'noopener'); } });
		}
		return actions;
	}

	/* ------------------------------------------------------------------ *
	 *  Preview modal
	 * ------------------------------------------------------------------ */

	/* ------------------------------------------------------------------ *
	 *  Detail drawer (read-only)
	 * ------------------------------------------------------------------ */

	function ensureDrawer() {
		var $d = $('#lnfb-drawer');
		if (!$d.length || $d.data('built')) { return; }
		$d.html(
			'<div class="lnfb-drawer-panel" role="dialog" aria-modal="true">'
			+ '<header class="lnfb-drawer-head">'
			+ '<span class="lnfb-drawer-icn" id="lnfb-drawer-icn"><i class="fas fa-file"></i></span>'
			+ '<div class="lnfb-drawer-titles"><h2 class="lnfb-drawer-name" id="lnfb-drawer-name"></h2><p class="lnfb-drawer-meta" id="lnfb-drawer-meta"></p></div>'
			+ '<button type="button" class="lnfb-drawer-close" data-drawer-close aria-label="' + esc(t('close', 'Close')) + '"><i class="fas fa-xmark"></i></button>'
			+ '</header>'
			+ '<div class="lnfb-drawer-scroll">'
			+ '<div class="lnfb-drawer-preview" id="lnfb-drawer-preview"></div>'
			+ '<div class="lnfb-drawer-block"><h3 class="lnfb-drawer-h3">' + esc(t('details', 'Details')) + '</h3><dl class="lnfb-drawer-details" id="lnfb-drawer-details"></dl></div>'
			+ '<div class="lnfb-drawer-block"><h3 class="lnfb-drawer-h3">' + esc(t('quick_actions', 'Quick actions')) + '</h3><div class="lnfb-drawer-quick" id="lnfb-drawer-quick"></div></div>'
			+ '</div>'
		);
		$d.data('built', true);
	}

	function bindDrawerEvents() {
		if ($(document).data('lnfb-drawer-bound')) { return; }
		$(document).data('lnfb-drawer-bound', true);
		$(document).on('click', '.linknacional-filebrowser-public .lnfb-drawer [data-drawer-close]', function () { closeDrawer(); });
		// Clicking empty space in the content area (outside the panel) closes it.
		$(document).on('click', '.linknacional-filebrowser-public .lnfb-content-area', function (e) {
			if ($(e.target).closest('.lnfb-drawer-panel, .lnfb-item, .lnfb-kebab').length) { return; }
			if ($('#lnfb-drawer').hasClass('is-open')) { closeDrawer(); }
		});
		$(document).on('keydown', function (e) {
			if (e.key === 'Escape') { closeMenu(); closeDrawer(); }
		});
	}

	function openDrawer(item) {
		ensureDrawer();
		bindDrawerEvents();
		state.drawerItem = item;
		var ext = (item.filetype || extOf(item.name) || '').toLowerCase();

		$('#lnfb-drawer-icn').html('<i class="' + typeIcon(ext) + '"></i>');
		$('#lnfb-drawer-name').text(item.name);
		var meta = [formatSize(item.size), ext ? ext.toUpperCase() : '', formatDate(item.updated_at || item.created_at)].filter(Boolean);
		$('#lnfb-drawer-meta').text(meta.join(' - '));

		var $p = $('#lnfb-drawer-preview').empty();
		var isImg = IMG_EXT.indexOf(ext) !== -1 && item.url && item.allow_download;
		$p.toggleClass('is-image', isImg);
		if (isImg) {
			$p.append($('<img>').attr('src', item.url).attr('alt', item.name));
		} else {
			// PDFs and text files are not embedded (mini viewer looks odd) — show just the icon.
			$p.append('<div class="lnfb-drawer-nopreview"><i class="' + typeIcon(ext) + '"></i></div>');
		}
		// Read-only favourite indicator (public never toggles favourites).
		if (Number(item.is_favorite)) {
			$p.append('<span class="lnfb-fav-badge" title="' + esc(t('col_favorites', 'Favorites')) + '"><i class="fas fa-star"></i></span>');
		}

		var typeLabel = ext ? ext.toUpperCase() + ' (' + typeCategory(ext) + ')' : typeCategory('');
		var rows = [
			[t('detail_name', 'Name'), item.name],
			[t('detail_type', 'Type'), typeLabel],
			[t('detail_size', 'Size'), formatSize(item.size)],
			[t('detail_location', 'Location'), itemLocation(item)],
			[t('detail_modified', 'Modified'), formatDate(item.updated_at || item.created_at)]
		];
		var details = '';
		rows.forEach(function (r) {
			details += '<div class="lnfb-drawer-row"><dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd></div>';
		});
		$('#lnfb-drawer-details').html(details);

		// Public is read-only: quick actions only, hidden when download is restricted.
		var quick = [];
		if (item.allow_download) {
			quick.push({ label: t('open_new_tab', 'Open in new tab'), icon: 'fas fa-arrow-up-right-from-square', run: function () { window.open(item.url, '_blank', 'noopener'); } });
			quick.push({ label: t('download', 'Download'), icon: 'fas fa-download', run: function () { downloadFile(item); } });
			quick.push({ label: t('copy_url', 'Copy URL'), icon: 'fas fa-link', run: function () { copyLink(item); } });
		}
		var $q = $('#lnfb-drawer-quick').empty();
		quick.forEach(function (a) {
			$('<button type="button" class="lnfb-quick-item">')
				.html('<i class="' + a.icon + '"></i> <span>' + esc(a.label) + '</span>')
				.on('click', a.run)
				.appendTo($q);
		});

		$('#lnfb-drawer').addClass('is-open');
		window.requestAnimationFrame(sizeDrawerToContent);
		var img = $('#lnfb-drawer-preview img')[0];
		if (img && !img.complete) { img.addEventListener('load', sizeDrawerToContent); }
	}

	function sizeDrawerToContent() {
		if (window.matchMedia('(max-width: 782px)').matches) { return; }
		var $c = container();
		var panelH = $c.find('.lnfb-drawer-panel').outerHeight();
		var $body = $c.find('.lnfb-body');
		if (!panelH || !$body.length) { return; }
		// Remember the resting height (card default / grid) as a floor.
		if (!state.drawerFloorH) { state.drawerFloorH = Math.ceil($body.outerHeight()); }
		var extra = $body.outerHeight() - $c.find('.lnfb-content-area').outerHeight();
		if (isNaN(extra) || extra < 0) { extra = 0; }
		// Fit the panel exactly (grow OR shrink), but never below the resting height.
		var desired = Math.max(state.drawerFloorH, Math.ceil(panelH + extra));
		$body.css('min-height', desired + 'px');
	}

	function closeDrawer() {
		$('#lnfb-drawer').removeClass('is-open');
		// Keep the height set by the last opened item; it only re-fits when a new file opens.
		state.drawerItem = null;
	}

	/* ------------------------------------------------------------------ *
	 *  File actions
	 * ------------------------------------------------------------------ */

	function downloadFile(item) {
		var a = document.createElement('a');
		a.href = item.url + (item.url.indexOf('?') === -1 ? '?' : '&') + 'dl=1';
		a.download = item.name;
		document.body.appendChild(a);
		a.click();
		a.remove();
	}

	function copyLink(item) {
		var done = function () { toast(t('link_copied', 'Link copied to clipboard'), 'success'); };
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(item.url).then(done, function () { fallbackCopy(item.url, done); });
		} else {
			fallbackCopy(item.url, done);
		}
	}

	function fallbackCopy(text, done) {
		var $tmp = $('<textarea>').val(text).css({ position: 'fixed', opacity: 0 }).appendTo('body');
		$tmp[0].select();
		try { document.execCommand('copy'); done(); }
		catch (err) { window.prompt(t('copied_fallback', 'Copy this link:'), text); }
		$tmp.remove();
	}

	/* ------------------------------------------------------------------ *
	 *  Data loading
	 * ------------------------------------------------------------------ */

	function fetchAndSetNonce(onReady) {
		$.ajax({
			url: L.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: { action: 'linknacional_get_public_nonce', action_name: 'linknacional_filebrowser_public_nonce' },
			success: function (response) {
				if (response && response.success && response.data && response.data.nonce) {
					state.nonce = response.data.nonce;
				} else {
					console.error('Failed to obtain nonce.');
				}
				onReady();
			},
			error: function () {
				console.error('Failed to obtain nonce.');
				onReady();
			}
		});
	}

	function loadTree() {
		var $tree = container().find('#linknacional-folder-tree-public');
		if (!$tree.length) { return; }
		api('linknacional_frontend_get_all_folders', { root_id: state.rootId, exclude_ids: state.excludeIds.join(',') }).then(function (response) {
			if (response && response.success) {
				state.folders = response.data.folders || [];
				state.files = response.data.files || [];
				renderTree();
			} else {
				$tree.html('<div class="lnfb-empty error"><p>' + esc(t('error_folders_text', 'Error loading folders')) + '</p></div>');
			}
		});
	}

	function applyCollectionUI(collection) {
		state.collection = collection;
		var $c = container();
		$c.find('.lnfb-collection').removeClass('is-active').attr('aria-selected', 'false');
		$c.find('.lnfb-collection[data-collection="' + collection + '"]').addClass('is-active').attr('aria-selected', 'true');
		$c.toggleClass('is-collection', collection !== 'files');
		renderCollectionBreadcrumb();
	}

	function renderCollectionBreadcrumb() {
		var $bc = container().find('#linknacional-current-path');
		if (!$bc.length) { return; }
		$bc.empty();
		if (state.collection === 'files') { return; }
		var labels = { favorites: t('col_favorites', 'Favorites'), recent: t('col_recent', 'Recent') };
		var icons = { favorites: 'fas fa-star', recent: 'fas fa-clock-rotate-left' };
		$bc.append('<span class="lnfb-crumb current"><i class="' + (icons[state.collection] || '') + '"></i> ' + esc(labels[state.collection] || '') + '</span>');
	}

	function loadContents(folderId) {
		state.currentFolderId = Number(folderId) || 0;
		state.searchActive = false;
		applyCollectionUI('files');
		container().find('#linknacional-contents').html('<div class="lnfb-skeleton">'
			+ '<div class="lnfb-sk-card"></div><div class="lnfb-sk-card"></div>'
			+ '<div class="lnfb-sk-card"></div><div class="lnfb-sk-card"></div>'
			+ '<div class="lnfb-sk-card"></div><div class="lnfb-sk-card"></div></div>');

		api('linknacional_frontend_get_contents', { folder_id: state.currentFolderId }).then(function (response) {
			if (response && response.success) {
				renderContents(response.data);
				renderBreadcrumb(response.data.breadcrumb);
				highlightTreeActive();
			} else {
				container().find('#linknacional-contents').html('<div class="lnfb-empty error"><i class="fas fa-triangle-exclamation"></i><p>' + esc(t('error_loading_text', 'Could not load contents')) + '</p></div>');
			}
		});
	}

	function loadCollection(collection) {
		if (collection === 'files') {
			navigateTo(baseFolderId());
			return;
		}
		applyCollectionUI(collection);
		state.searchActive = false;
		clearSearchUI();
		container().find('#linknacional-contents').html('<div class="lnfb-skeleton">'
			+ '<div class="lnfb-sk-card"></div><div class="lnfb-sk-card"></div>'
			+ '<div class="lnfb-sk-card"></div><div class="lnfb-sk-card"></div>'
			+ '<div class="lnfb-sk-card"></div><div class="lnfb-sk-card"></div></div>');
		api('linknacional_frontend_get_collection', { collection: collection, root_id: state.rootId, exclude_ids: state.excludeIds.join(',') }).then(function (response) {
			if (response && response.success) {
				renderContents(response.data);
			} else {
				container().find('#linknacional-contents').html('<div class="lnfb-empty error"><i class="fas fa-triangle-exclamation"></i><p>' + esc(t('error_loading_text', 'Could not load contents')) + '</p></div>');
			}
		});
	}

	function navigateTo(folderId) {
		state.searchActive = false;
		clearSearchUI();
		closeDrawer();
		loadContents(folderId);
	}

	/* ------------------------------------------------------------------ *
	 *  Folder tree
	 * ------------------------------------------------------------------ */

	function isExcluded(id) {
		return state.excludeIds.indexOf(Number(id)) !== -1;
	}

	function folderChildren(parentId) {
		return state.folders
			.filter(function (f) { return Number(f.parent_id) === Number(parentId) && !isExcluded(f.id); })
			.sort(function (a, b) { return compareItems(a, b, 'name'); });
	}

	function folderFiles(parentId) {
		return state.files
			.filter(function (f) { return Number(f.folder_id) === Number(parentId); })
			.sort(function (a, b) { return compareItems(a, b, 'original_name'); });
	}

	function hasChildren(folderId) {
		return folderChildren(folderId).length > 0 || folderFiles(folderId).length > 0;
	}

	function buildTree(parentId, level) {
		var kids = folderChildren(parentId);
		var files = folderFiles(parentId);
		if (!kids.length && !files.length) { return ''; }
		var html = '<div class="lnfb-tree-children' + (level === 0 ? ' is-open' : '') + '" data-parent-id="' + parentId + '">';
		kids.forEach(function (f) {
			var children = hasChildren(f.id);
			html += '<div class="lnfb-tree-item folder" data-folder-id="' + f.id + '" data-folder-name="' + esc(f.name) + '" data-parent-id="' + f.parent_id + '">'
				+ '<button type="button" class="lnfb-tree-toggle"' + (children ? '' : ' hidden') + '><i class="fas fa-caret-right"></i></button>'
				+ '<span class="lnfb-tree-icn"><i class="fas fa-folder"></i></span>'
				+ '<span class="lnfb-tree-label">' + esc(f.name) + '</span>'
				+ '</div>';
			html += buildTree(f.id, level + 1);
		});
		files.forEach(function (f) {
			var ext = extOf(f.original_name);
			html += '<div class="lnfb-tree-item file" data-file-id="' + f.id + '" data-file-name="' + esc(f.original_name) + '" data-file-url="' + esc(f.file_url) + '" data-filetype="' + esc(ext) + '" data-size="' + (Number(f.file_size) || 0) + '" data-allow-download="' + (Number(f.allow_download) === 0 ? 0 : 1) + '" data-parent-folder-id="' + parentId + '">'
				+ '<span class="lnfb-tree-spacer"></span>'
				+ '<span class="lnfb-tree-icn"><i class="' + typeIcon(ext) + '"></i></span>'
				+ '<span class="lnfb-tree-label">' + esc(f.original_name) + '</span>'
				+ '</div>';
		});
		return html + '</div>';
	}

	function renderTree() {
		var $tree = container().find('#linknacional-folder-tree-public');
		if (!$tree.length) { return; }
		var baseId = Number(state.rootId) || 0;
		var baseLabel = t('home', 'Home');
		var baseIcon = 'fas fa-house';
		if (baseId) {
			var base = null;
			state.folders.forEach(function (f) { if (Number(f.id) === baseId) { base = f; } });
			if (base) { baseLabel = base.name; baseIcon = 'fas fa-folder-open'; }
		}
		var html = '<div class="lnfb-tree-item folder root" data-folder-id="' + baseId + '" data-folder-name="' + esc(baseLabel) + '" data-parent-id="-1">'
			+ '<button type="button" class="lnfb-tree-toggle"><i class="fas fa-caret-down"></i></button>'
			+ '<span class="lnfb-tree-icn"><i class="' + baseIcon + '"></i></span>'
			+ '<span class="lnfb-tree-label">' + esc(baseLabel) + '</span>'
			+ '</div>';
		html += buildTree(baseId, 0);
		$tree.html(html);
		highlightTreeActive();
	}

	function highlightTreeActive() {
		var $c = container();
		$c.find('.lnfb-tree-item').removeClass('is-active');
		var $item = $c.find('.lnfb-tree-item[data-folder-id="' + state.currentFolderId + '"]');
		if ($item.length) {
			$item.addClass('is-active');
			$item.parents('.lnfb-tree-children').each(function () {
				$(this).addClass('is-open');
				$(this).prev('.lnfb-tree-item').addClass('is-open')
					.find('.fas.fa-caret-right').removeClass('fa-caret-right').addClass('fa-caret-down');
			});
		}
	}

	/* ------------------------------------------------------------------ *
	 *  Content rendering
	 * ------------------------------------------------------------------ */

	function baseFolderId() { return Number(state.rootId) || 0; }

	function baseFolderName() {
		var id = baseFolderId();
		if (!id) { return t('home', 'Home'); }
		var name = null;
		state.folders.forEach(function (f) { if (Number(f.id) === id) { name = f.name; } });
		return name || t('home', 'Home');
	}

	function renderBreadcrumb(breadcrumb) {
		var $bc = container().find('#linknacional-current-path');
		if (!$bc.length) { return; }
		$bc.empty();
		var crumbs = (breadcrumb && breadcrumb.length) ? breadcrumb.slice() : [{ id: baseFolderId(), name: baseFolderName() }];
		var base = baseFolderId();
		if (base) {
			// Keep only the trail from the root folder downwards — that folder is the new start.
			for (var i = 0; i < crumbs.length; i++) {
				if (Number(crumbs[i].id) === base) { crumbs = crumbs.slice(i); break; }
			}
		}
		crumbs.forEach(function (crumb, index) {
			var last = index === crumbs.length - 1;
			if (index > 0) { $bc.append('<i class="fas fa-chevron-right lnfb-crumb-sep"></i>'); }
			var firstIcon = index === 0 ? (Number(crumb.id) === 0 ? '<i class="fas fa-house"></i> ' : '<i class="fas fa-folder-open"></i> ') : '';
			var $c = $('<button type="button" class="lnfb-crumb' + (last ? ' current' : '') + '" data-folder-id="' + crumb.id + '">'
				+ firstIcon + esc(crumb.name) + '</button>');
			if (!last) { $c.on('click', function () { navigateTo(crumb.id); }); }
			$bc.append($c);
		});
	}

	function compareItems(a, b, nameKey) {
		var sort = state.sort;
		if (sort === 'newest' || sort === 'oldest') {
			var da = a.created_at ? Date.parse(String(a.created_at).replace(' ', 'T')) : 0;
			var db = b.created_at ? Date.parse(String(b.created_at).replace(' ', 'T')) : 0;
			if (da !== db) { return sort === 'newest' ? db - da : da - db; }
		} else if (sort === 'size') {
			var sizeA = Number(a.file_size !== undefined ? a.file_size : a.size) || 0;
			var sizeB = Number(b.file_size !== undefined ? b.file_size : b.size) || 0;
			if (sizeA !== sizeB) { return sizeB - sizeA; }
		}
		var cmp = String(a[nameKey]).localeCompare(String(b[nameKey]));
		return sort === 'name-desc' ? -cmp : cmp;
	}

	function sortItems(items, nameKey) {
		return items.sort(function (a, b) { return compareItems(a, b, nameKey); });
	}

	function renderContents(data) {
		state.contents = { folders: data.folders || [], files: data.files || [] };
		var $c = container().find('#linknacional-contents');
		var folders = sortItems(state.contents.folders.slice().filter(function (f) { return !isExcluded(f.id); }), 'name');
		var allFiles = state.contents.files.slice();
		var favFiles = sortItems(allFiles.filter(function (f) { return Number(f.is_favorite); }), 'original_name');
		var otherFiles = sortItems(allFiles.filter(function (f) { return !Number(f.is_favorite); }), 'original_name');
		var files = favFiles.concat(otherFiles);

		if (!folders.length && !files.length) {
			$c.html(emptyStateHtml());
			return;
		}

		var html = '';
		folders.forEach(function (f) { html += cardHtml({ type: 'folder', id: f.id, name: f.name, is_favorite: Number(f.is_favorite) || 0, item_count: Number(f.item_count) || 0, created_at: f.created_at, updated_at: f.updated_at }); });
		files.forEach(function (f) {
			html += cardHtml({
				type: 'file', id: f.id, name: f.original_name, url: f.file_url,
				filetype: f.file_type || extOf(f.original_name), size: f.file_size,
				is_favorite: Number(f.is_favorite) || 0, created_at: f.created_at, updated_at: f.updated_at,
				allow_download: Number(f.allow_download) === 0 ? 0 : 1,
				folder_id: f.folder_id, folder_name: f.folder_name || ''
			});
		});
		$c.html(html);
	}

	function emptyStateHtml() {
		if (state.collection === 'favorites') {
			return '<div class="lnfb-empty"><i class="fas fa-star"></i><p>' + esc(t('favorites_empty', 'No favorites yet')) + '</p></div>';
		}
		if (state.collection === 'recent') {
			return '<div class="lnfb-empty"><i class="fas fa-clock-rotate-left"></i><p>' + esc(t('recent_empty', 'No recent files')) + '</p></div>';
		}
		return '<div class="lnfb-empty"><i class="fas fa-folder-open"></i><p>' + esc(t('empty_folder_text', 'This folder is empty')) + '</p><span>' + esc(t('empty_folder_desc', '')) + '</span></div>';
	}

	function renderSearchResults(data) {
		state.searchData = data;
		var $c = container().find('#linknacional-contents');
		var folders = sortItems((data.folders || []).slice().filter(function (f) { return !isExcluded(f.id); }), 'name');
		var files = sortItems((data.files || []).slice(), 'original_name');
		var count = folders.length + files.length;

		var html = '<div class="lnfb-results-head"><i class="fas fa-magnifying-glass"></i> '
			+ esc(t('search_results_text', 'Results for')) + ' “' + esc(data.search_term) + '” — '
			+ esc(t('found_items_text', 'Found')) + ' ' + count + ' ' + esc(t('items_text', 'items')) + '</div>';

		if (!count) {
			html += '<div class="lnfb-empty"><i class="fas fa-magnifying-glass"></i><p>' + esc(t('no_results_text', 'No results found')) + '</p><span>' + esc(t('no_results_desc', '')) + '</span></div>';
			$c.html(html);
			return;
		}

		folders.forEach(function (f) {
			html += cardHtml({ type: 'folder', id: f.id, name: f.name, path: f.full_path || '', is_favorite: Number(f.is_favorite) || 0, item_count: Number(f.item_count) || 0, updated_at: f.updated_at });
		});
		files.forEach(function (f) {
			html += cardHtml({ type: 'file', id: f.id, name: f.original_name, url: f.file_url, filetype: f.file_type || extOf(f.original_name), size: f.file_size, path: f.folder_name || '', is_favorite: Number(f.is_favorite) || 0, allow_download: Number(f.allow_download) === 0 ? 0 : 1, updated_at: f.updated_at, folder_id: f.folder_id, folder_name: f.folder_name || '' });
		});
		$c.html(html);
	}

	function cardHtml(item) {
		var isFolder = item.type === 'folder';
		var ext = isFolder ? '' : (item.filetype || '');
		var locked = !isFolder && item.allow_download === 0;
		var thumb;
		if (isFolder) {
			thumb = '<i class="fas fa-folder"></i>';
		} else if (!locked && IMG_EXT.indexOf(ext) !== -1 && item.url) {
			thumb = '<img src="' + esc(item.url) + '" alt="" draggable="false" loading="lazy">';
		} else {
			thumb = '<i class="' + typeIcon(ext) + '"></i>';
		}
		var meta;
		if (isFolder) {
			meta = (Number(item.item_count) || 0) + ' ' + t('items_text', 'items');
		} else {
			var date = formatDate(item.created_at);
			meta = formatSize(item.size) + (date ? ' - ' + date : '');
		}
		if (item.path) { meta = esc(item.path); }
		var data = 'data-type="' + item.type + '" data-id="' + item.id + '" data-name="' + esc(item.name) + '"'
			+ ' data-favorite="' + (item.is_favorite ? 1 : 0) + '"'
			+ ' data-allow-download="' + (locked ? 0 : 1) + '"'
			+ ' data-created="' + esc(item.created_at || '') + '" data-updated="' + esc(item.updated_at || '') + '"';
		if (!isFolder) {
			data += ' data-url="' + esc(item.url) + '" data-filetype="' + esc(ext) + '" data-size="' + (Number(item.size) || 0) + '"'
				+ ' data-folder-id="' + (Number(item.folder_id) || 0) + '" data-folder-name="' + esc(item.folder_name || '') + '"';
		}
		var star = Number(item.is_favorite) ? '<span class="lnfb-fav-badge" title="' + esc(t('col_favorites', 'Favorites')) + '"><i class="fas fa-star"></i></span>' : '';
		var lock = locked ? '<span class="lnfb-lock-badge" title="' + esc(t('download_locked', 'Download restricted')) + '"><i class="fas fa-lock"></i></span>' : '';
		return '<div class="lnfb-item ' + item.type + (ext ? ' ext-' + esc(ext) : '') + '" ' + data + '>'
			+ star
			+ lock
			+ '<button type="button" class="lnfb-kebab" aria-label="' + esc(t('more_actions', 'More actions')) + '"><i class="fas fa-ellipsis-vertical"></i></button>'
			+ '<div class="lnfb-thumb">' + thumb + '</div>'
			+ '<div class="lnfb-name" title="' + esc(item.name) + '">' + esc(item.name) + '</div>'
			+ '<div class="lnfb-meta">' + meta + '</div>'
			+ '</div>';
	}

	function itemFromEl($el) {
		var type = $el.attr('data-type');
		var base = {
			id: Number($el.attr('data-id')), name: String($el.attr('data-name')),
			is_favorite: Number($el.attr('data-favorite')) || 0,
			created_at: String($el.attr('data-created') || ''),
			updated_at: String($el.attr('data-updated') || '')
		};
		if (type === 'folder') {
			base.type = 'folder';
			return base;
		}
		base.type = 'file';
		base.url = String($el.attr('data-url'));
		base.filetype = String($el.attr('data-filetype'));
		base.size = Number($el.attr('data-size'));
		base.allow_download = Number($el.attr('data-allow-download')) === 0 ? 0 : 1;
		base.folder_id = Number($el.attr('data-folder-id')) || 0;
		base.folder_name = String($el.attr('data-folder-name') || '');
		return base;
	}

	/* ------------------------------------------------------------------ *
	 *  Search
	 * ------------------------------------------------------------------ */

	var searchTimer = null;
	var searchSeq = 0;

	function clearSearchUI() {
		container().find('#linknacional-search-input').val('');
		container().find('#linknacional-clear-search').removeClass('is-visible');
	}

	function performSearch(term) {
		if (term.length < 2) { return; }
		state.searchActive = true;
		var seq = ++searchSeq;
		container().find('#linknacional-clear-search').addClass('is-visible');
		container().find('#linknacional-contents').html('<div class="lnfb-loading"><i class="fas fa-spinner fa-spin"></i> ' + esc(t('searching_text', 'Searching…')) + '</div>');

		api('linknacional_frontend_search', { search_term: term, folder_id: state.currentFolderId, root_id: state.rootId, exclude_ids: state.excludeIds.join(',') }).then(function (response) {
			if (seq !== searchSeq) { return; } // a newer search superseded this one
			if (response && response.success) {
				renderSearchResults(response.data);
			} else {
				container().find('#linknacional-contents').html('<div class="lnfb-empty error"><p>' + esc(t('error_search_text', 'Error performing search')) + '</p></div>');
			}
		});
	}

	function applyLayout(layout) {
		state.view = layout === 'list' ? 'list' : 'grid';
		container().attr('data-layout', state.view);
		container().find('.lnfb-view-btn').removeClass('is-active').filter('[data-layout="' + state.view + '"]').addClass('is-active');
		container().find('#linknacional-contents').removeClass('is-grid is-list').addClass('is-' + state.view);
	}

	/* ------------------------------------------------------------------ *
	 *  Events
	 * ------------------------------------------------------------------ */

	$(function () {

		ensureDrawer();

		/* Toasts container (public markup has none). */
		if (!$('#lnfb-toasts').length) {
			$('body').append('<div id="lnfb-toasts" class="lnfb-toasts" aria-live="polite"></div>');
		}

		/* Keep the contextual menu anchored — close it when the page scrolls. */
		document.addEventListener('scroll', function () {
			if ($('.lnfb-menu').length) { closeMenu(); }
		}, true);

		/* Collections */
		$(document).on('click', '.linknacional-filebrowser-public .lnfb-collection', function () {
			var collection = $(this).data('collection');
			if (collection === state.collection && collection === 'files') { return; }
			loadCollection(collection);
		});

		/* Layout */
		$(document).on('click', '.linknacional-filebrowser-public .lnfb-view-btn', function () {
			applyLayout($(this).data('layout'));
		});

		/* Sort */
		$(document).on('change', '.linknacional-filebrowser-public #linknacional-sort', function () {
			state.sort = $(this).val();
			if (state.searchActive && state.searchData) {
				renderSearchResults(state.searchData);
			} else if (state.contents) {
				renderContents(state.contents);
			}
			renderTree();
		});

		/* Search */
		$(document).on('input', '.linknacional-filebrowser-public #linknacional-search-input', function () {
			var term = $(this).val().trim();
			window.clearTimeout(searchTimer);
			if (term.length >= 2) {
				searchTimer = window.setTimeout(function () { performSearch(term); }, 300);
			} else {
				container().find('#linknacional-clear-search').removeClass('is-visible');
				if (state.searchActive) { loadContents(state.currentFolderId); }
			}
		});

		$(document).on('click', '.linknacional-filebrowser-public #linknacional-clear-search', function () {
			clearSearchUI();
			loadContents(state.currentFolderId);
		});

		/* Tree navigation */
		$(document).on('click', '.linknacional-filebrowser-public .lnfb-tree-item.folder', function (e) {
			if ($(e.target).closest('.lnfb-tree-toggle').length) { return; }
			navigateTo(Number($(this).data('folder-id')));
		});
		$(document).on('click', '.linknacional-filebrowser-public .lnfb-tree-item.file', function (e) {
			e.stopPropagation();
			openDrawer({
				type: 'file',
				id: Number($(this).attr('data-file-id')) || 0,
				name: String($(this).attr('data-file-name')),
				url: String($(this).attr('data-file-url')),
				filetype: String($(this).attr('data-filetype')),
				size: Number($(this).attr('data-size')) || 0,
				allow_download: Number($(this).attr('data-allow-download')) === 0 ? 0 : 1
			});
		});
		$(document).on('click', '.linknacional-filebrowser-public .lnfb-tree-toggle', function (e) {
			e.stopPropagation();
			var $item = $(this).closest('.lnfb-tree-item');
			var $children = $item.next('.lnfb-tree-children');
			if (!$children.length) { return; }
			var open = $children.toggleClass('is-open').hasClass('is-open');
			$item.toggleClass('is-open', open);
			$(this).find('i').toggleClass('fa-caret-right', !open).toggleClass('fa-caret-down', open);
		});

		/* Sidebar collapse (single centred handle) */
		$(document).on('click', '.linknacional-filebrowser-public .lnfb-sidebar-handle', function () {
			var $body = $(this).closest('.lnfb-body');
			var collapsed = $body.toggleClass('sidebar-collapsed').hasClass('sidebar-collapsed');
			$(this).find('i').toggleClass('fa-chevron-left', !collapsed).toggleClass('fa-chevron-right', collapsed);
		});

		/* Item click → folder navigates, file opens the detail drawer */
		$(document).on('click', '.linknacional-filebrowser-public .lnfb-item', function (e) {
			if ($(e.target).closest('.lnfb-kebab').length) { return; }
			var item = itemFromEl($(this));
			if (item.type === 'folder') { navigateTo(item.id); }
			else { openDrawer(item); }
		});

		/* Kebab */
		$(document).on('click', '.linknacional-filebrowser-public .lnfb-kebab', function (e) {
			e.stopPropagation();
			openMenu($(this), itemFromEl($(this).closest('.lnfb-item')));
		});

		/* Boot */
		fetchAndSetNonce(function () {
			var $root = container();
			if (!$root.length) { return; }
			state.rootId = parseInt($root.data('root-id'), 10) || 0;
			state.excludeIds = String($root.data('exclude-ids') || '').split(',').map(function (n) { return parseInt(n, 10) || 0; }).filter(function (n) { return n > 0; });
			var initialFolder = parseInt($root.data('folder-id'), 10) || 0;
			if (!initialFolder && state.rootId) { initialFolder = state.rootId; }
			applyLayout($root.data('layout') || 'grid');
			loadTree();
			loadContents(initialFolder);
		});
	});

})(jQuery);
