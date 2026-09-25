/**
 * Link Nacional File Browser — admin.
 *
 * Grid/list file manager with drag & drop upload, drag-to-move,
 * contextual menu, preview, bulk actions and toast feedback.
 */
(function ($) {
	'use strict';

	var L = window.linknacional_ajax || {};
	function t(key, fallback) { return L[key] || fallback; }

	var state = {
		nonce: '',
		currentFolderId: 0,
		folders: [],
		files: [],
		contents: { folders: [], files: [] },
		breadcrumb: [],
		collection: 'files',
		view: 'grid',
		sort: 'name-asc',
		filter: '',
		dragging: null,
		renameItem: null,
		moveSubject: null,
		moveMode: 'move',
		drawerItem: null,
		drawerFloorH: 0
	};

	var IMG_EXT = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
	var uploadHideTimer = null;

	/* ------------------------------------------------------------------ *
	 *  Small helpers
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
		var i = Math.floor(Math.log(bytes) / Math.log(1024));
		i = Math.min(i, units.length - 1);
		return (bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1) + ' ' + units[i];
	}

	function typeIcon(ext) {
		var map = {
			pdf: 'fas fa-file-pdf', doc: 'fas fa-file-word', docx: 'fas fa-file-word',
			xls: 'fas fa-file-excel', xlsx: 'fas fa-file-excel',
			ppt: 'fas fa-file-powerpoint', pptx: 'fas fa-file-powerpoint',
			txt: 'fas fa-file-lines', jpg: 'fas fa-file-image', jpeg: 'fas fa-file-image',
			png: 'fas fa-file-image', gif: 'fas fa-file-image', webp: 'fas fa-file-image'
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
		var $t = $('<div class="lkn-fb-toast ' + type + '">'
			+ '<i class="lkn-fb-toast-icn ' + icon + '"></i>'
			+ '<span class="lkn-fb-toast-msg">' + esc(message) + '</span>'
			+ '<button type="button" class="lkn-fb-toast-close" aria-label="' + esc(t('close', 'Close')) + '"><i class="fas fa-xmark"></i></button>'
			+ '<span class="lkn-fb-toast-bar"></span>'
			+ '</div>');
		$('#lkn-fb-toasts').append($t);
		window.requestAnimationFrame(function () { $t.addClass('show'); });

		var timer = null;
		var dismiss = function () {
			if ($t.hasClass('is-hiding')) { return; }
			window.clearTimeout(timer);
			$t.removeClass('show').addClass('is-hiding');
			window.setTimeout(function () { $t.remove(); }, 340);
		};
		timer = window.setTimeout(dismiss, 10000);
		$t.find('.lkn-fb-toast-close').on('click', dismiss);
	}

	/* ------------------------------------------------------------------ *
	 *  Modals
	 * ------------------------------------------------------------------ */

	var confirmResolver = null;

	function openModal(sel) {
		$(sel).prop('hidden', false);
		$('body').addClass('lkn-fb-modal-open');
	}

	function closeModal($m) {
		$m.prop('hidden', true);
		if (!$('.lkn-fb-modal').filter(function () { return !this.hidden; }).length) {
			$('body').removeClass('lkn-fb-modal-open');
		}
	}

	function confirmDialog(opts) {
		$('#lkn-fb-confirm-heading').text(opts.title || t('delete_title', 'Delete'));
		$('#lkn-fb-confirm-message').html(opts.message || '');
		$('#lkn-fb-confirm-ok').text(opts.confirmText || t('delete', 'Delete'));
		openModal('#lkn-fb-confirm-modal');
		return new Promise(function (resolve) { confirmResolver = resolve; });
	}

	function resolveConfirm(value) {
		$('#lkn-fb-confirm-modal').prop('hidden', true);
		if (!$('.lkn-fb-modal').filter(function () { return !this.hidden; }).length) {
			$('body').removeClass('lkn-fb-modal-open');
		}
		if (confirmResolver) { confirmResolver(value); confirmResolver = null; }
	}

	/* ------------------------------------------------------------------ *
	 *  Contextual menu
	 * ------------------------------------------------------------------ */

	function closeMenu() { $('.lkn-fb-menu').remove(); }

	function openMenu($trigger, item) {
		closeMenu();
		var actions = buildActions(item);
		var $menu = $('<div class="lkn-fb-menu" role="menu"></div>');
		actions.forEach(function (a) {
			$('<button type="button" role="menuitem" class="lkn-fb-menu-item' + (a.danger ? ' danger' : '') + '">'
				+ '<i class="' + a.icon + '"></i> ' + esc(a.label) + '</button>')
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
			$(window).one('scroll', closeMenu);
			$(window).one('resize', closeMenu);
		}, 0);
	}

	function buildActions(item) {
		var actions = [];
		var inTrash = state.collection === 'trash';

		if (inTrash) {
			actions.push({ label: t('restore', 'Restore'), icon: 'fas fa-rotate-left', run: function () { restoreItems([item]); } });
			actions.push({ label: t('delete_forever', 'Delete permanently'), icon: 'fas fa-trash', danger: true, run: function () { purgeItems([item]); } });
			return actions;
		}

		var favLabel = item.is_favorite ? t('remove_favorite', 'Remove from favorites') : t('add_favorite', 'Add to favorites');
		var favIcon  = item.is_favorite ? 'fas fa-star' : 'far fa-star';

		if (item.type === 'folder') {
			actions.push({ label: t('open_file', 'Open'), icon: 'fas fa-folder-open', run: function () { navigateTo(item.id); } });
			actions.push({ label: favLabel, icon: favIcon, run: function () { toggleFavorite(item); } });
			actions.push({ label: t('move_to', 'Move to…'), icon: 'fas fa-arrow-right-arrow-left', run: function () { openMoveModal(item, 'move'); } });
			actions.push({ label: t('copy_to', 'Copy to…'), icon: 'fas fa-copy', run: function () { openMoveModal(item, 'copy'); } });
			actions.push({ label: t('edit_name', 'Rename'), icon: 'fas fa-pen', run: function () { openRename(item); } });
			actions.push({ label: t('copy_ai', 'Copy for AI'), icon: 'fas fa-robot', run: function () { copyForAI(item); } });
			actions.push({ label: t('delete_folder', 'Delete folder'), icon: 'fas fa-trash', danger: true, run: function () { trashItems([item]); } });
		} else {
			actions.push({ label: t('preview', 'Preview'), icon: 'fas fa-eye', run: function () { openDrawer(item); } });
			actions.push({ label: t('download', 'Download'), icon: 'fas fa-download', run: function () { downloadFile(item); } });
			actions.push({ label: t('copy_link', 'Copy link'), icon: 'fas fa-link', run: function () { copyLink(item); } });
			actions.push({ label: favLabel, icon: favIcon, run: function () { toggleFavorite(item); } });
			actions.push({ label: t('move_to', 'Move to…'), icon: 'fas fa-arrow-right-arrow-left', run: function () { openMoveModal(item, 'move'); } });
			actions.push({ label: t('copy_to', 'Copy to…'), icon: 'fas fa-copy', run: function () { openMoveModal(item, 'copy'); } });
			actions.push({ label: t('edit_name', 'Rename'), icon: 'fas fa-pen', run: function () { openRename(item); } });
			actions.push({ label: t('copy_ai', 'Copy for AI'), icon: 'fas fa-robot', run: function () { copyForAI(item); } });
			actions.push({ label: item.allow_download ? t('restrict_download', 'Restrict download') : t('allow_download', 'Allow download'), icon: item.allow_download ? 'fas fa-lock' : 'fas fa-lock-open', run: function () { toggleDownload(item); } });
			actions.push({ label: t('move_to_trash', 'Move to trash'), icon: 'fas fa-trash', danger: true, run: function () { trashItems([item]); } });
		}
		return actions;
	}

	/* ------------------------------------------------------------------ *
	 *  Data loading
	 * ------------------------------------------------------------------ */

	function fetchAndSetNonce(onReady) {
		$.ajax({
			url: L.ajax_url,
			type: 'POST',
			dataType: 'json',
			data: { action: 'linknacional_get_admin_nonce', action_name: 'linknacional_filebrowser_nonce' },
			success: function (response) {
				if (response && response.success && response.data && response.data.nonce) {
					state.nonce = response.data.nonce;
				} else {
					console.error('Failed to obtain admin nonce.');
				}
				onReady();
			},
			error: function () {
				console.error('Failed to obtain admin nonce.');
				onReady();
			}
		});
	}

	function loadTree() {
		api('linknacional_get_all_folders_admin_frontend', {}).then(function (response) {
			if (response && response.success) {
				state.folders = response.data.folders || [];
				state.files = response.data.files || [];
				renderTree();
			}
		});
	}

	function applyCollectionUI(collection) {
		state.collection = collection;
		$('.lkn-fb-collection').removeClass('is-active').attr('aria-selected', 'false');
		$('.lkn-fb-collection[data-collection="' + collection + '"]').addClass('is-active').attr('aria-selected', 'true');
		$('.lkn-fb-admin').toggleClass('is-collection', collection !== 'files');
		renderBreadcrumb();
		if (collection === 'trash') { closeDrawer(); }
	}

	function loadContents(folderId) {
		state.currentFolderId = Number(folderId) || 0;
		applyCollectionUI('files');
		$('#lkn-fb-contents').html('<div class="lkn-fb-skeleton">'
			+ '<div class="lkn-fb-sk-card"></div><div class="lkn-fb-sk-card"></div>'
			+ '<div class="lkn-fb-sk-card"></div><div class="lkn-fb-sk-card"></div>'
			+ '<div class="lkn-fb-sk-card"></div><div class="lkn-fb-sk-card"></div></div>');

		api('linknacional_get_folder_contents', { folder_id: state.currentFolderId }).then(function (response) {
			if (response && response.success) {
				state.contents = { folders: response.data.folders || [], files: response.data.files || [] };
				state.breadcrumb = response.data.breadcrumb || [];
				renderBreadcrumb();
				renderContents();
			} else {
				$('#lkn-fb-contents').html('<div class="lkn-fb-empty error"><i class="fas fa-triangle-exclamation"></i><p>' + esc(t('error_loading_text', 'Could not load contents')) + '</p></div>');
			}
			highlightTreeActive();
		});
	}

	function loadCollection(collection) {
		if (collection === 'files') {
			navigateTo(state.currentFolderId);
			return;
		}
		applyCollectionUI(collection);
		clearSelection();
		state.filter = '';
		$('#lkn-fb-search').val('');
		$('#lkn-fb-search-clear').prop('hidden', true);
		$('#lkn-fb-contents').html('<div class="lkn-fb-skeleton">'
			+ '<div class="lkn-fb-sk-card"></div><div class="lkn-fb-sk-card"></div>'
			+ '<div class="lkn-fb-sk-card"></div><div class="lkn-fb-sk-card"></div>'
			+ '<div class="lkn-fb-sk-card"></div><div class="lkn-fb-sk-card"></div></div>');
		api('linknacional_get_collection', { collection: collection }).then(function (response) {
			if (response && response.success) {
				state.contents = { folders: response.data.folders || [], files: response.data.files || [] };
				state.breadcrumb = [];
				renderContents();
			} else {
				$('#lkn-fb-contents').html('<div class="lkn-fb-empty error"><i class="fas fa-triangle-exclamation"></i><p>' + esc(t('error_loading_text', 'Could not load contents')) + '</p></div>');
			}
		});
	}

	function navigateTo(folderId) {
		state.filter = '';
		$('#lkn-fb-search').val('');
		$('#lkn-fb-search-clear').prop('hidden', true);
		clearSelection();
		closeDrawer();
		loadContents(folderId);
	}

	/* ------------------------------------------------------------------ *
	 *  Folder tree (sidebar)
	 * ------------------------------------------------------------------ */

	function folderChildren(parentId) {
		return state.folders
			.filter(function (f) { return Number(f.parent_id) === Number(parentId); })
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
		var html = '<div class="lkn-fb-tree-children' + (level === 0 ? ' is-open' : '') + '" data-parent-id="' + parentId + '">';
		kids.forEach(function (f) {
			html += '<div class="lkn-fb-tree-item folder" data-folder-id="' + f.id + '" data-folder-name="' + esc(f.name) + '" data-parent-id="' + f.parent_id + '" draggable="false">'
				+ '<button type="button" class="lkn-fb-tree-toggle"' + (hasChildren(f.id) ? '' : ' hidden') + '><i class="fas fa-caret-right"></i></button>'
				+ '<span class="lkn-fb-tree-icn"><i class="fas fa-folder"></i></span>'
				+ '<span class="lkn-fb-tree-label">' + esc(f.name) + '</span>'
				+ '</div>';
			html += buildTree(f.id, level + 1);
		});
		files.forEach(function (f) {
			var ext = extOf(f.original_name);
			html += '<div class="lkn-fb-tree-item file" data-file-id="' + f.id + '" data-file-name="' + esc(f.original_name) + '" data-file-url="' + esc(f.file_url) + '" data-filetype="' + esc(ext) + '" data-size="' + (Number(f.file_size) || 0) + '" data-allow-download="' + (Number(f.allow_download) === 0 ? 0 : 1) + '" data-parent-folder-id="' + parentId + '" draggable="false">'
				+ '<span class="lkn-fb-tree-spacer"></span>'
				+ '<span class="lkn-fb-tree-icn"><i class="' + typeIcon(ext) + '"></i></span>'
				+ '<span class="lkn-fb-tree-label">' + esc(f.original_name) + '</span>'
				+ '</div>';
		});
		return html + '</div>';
	}

	function renderTree() {
		var html = '<div class="lkn-fb-tree-item folder root" data-folder-id="0" data-folder-name="' + esc(t('home', 'Home')) + '" data-parent-id="-1">'
			+ '<button type="button" class="lkn-fb-tree-toggle"><i class="fas fa-caret-down"></i></button>'
			+ '<span class="lkn-fb-tree-icn"><i class="fas fa-house"></i></span>'
			+ '<span class="lkn-fb-tree-label">' + esc(t('home', 'Home')) + '</span>'
			+ '</div>';
		html += buildTree(0, 0);
		$('#lkn-fb-tree').html(html);
		highlightTreeActive();
	}

	function highlightTreeActive() {
		$('.lkn-fb-tree-item').removeClass('is-active');
		var $item = $('.lkn-fb-tree-item[data-folder-id="' + state.currentFolderId + '"]');
		if ($item.length) {
			$item.addClass('is-active');
			$item.parents('.lkn-fb-tree-children').each(function () {
				$(this).addClass('is-open');
				$(this).prev('.lkn-fb-tree-item').addClass('is-open').find('.fas.fa-caret-right').removeClass('fa-caret-right').addClass('fa-caret-down');
			});
		}
	}

	/* ------------------------------------------------------------------ *
	 *  Content grid / list
	 * ------------------------------------------------------------------ */

	function renderBreadcrumb() {
		var $bc = $('#lkn-fb-breadcrumb').empty();
		if (state.collection !== 'files') {
			var labels = { favorites: t('col_favorites', 'Favorites'), recent: t('col_recent', 'Recent'), trash: t('col_trash', 'Trash') };
			var icons = { favorites: 'fas fa-star', recent: 'fas fa-clock-rotate-left', trash: 'fas fa-trash-can' };
			$bc.append('<span class="lkn-fb-crumb current"><i class="' + (icons[state.collection] || '') + '"></i> ' + esc(labels[state.collection] || '') + '</span>');
			return;
		}
		var crumbs = state.breadcrumb.length ? state.breadcrumb : [{ id: 0, name: t('home', 'Home') }];
		crumbs.forEach(function (crumb, index) {
			var last = index === crumbs.length - 1;
			if (index > 0) {
				$bc.append('<i class="fas fa-chevron-right lkn-fb-crumb-sep"></i>');
			}
			var $c = $('<button type="button" class="lkn-fb-crumb' + (last ? ' current' : '') + '" data-folder-id="' + crumb.id + '">'
				+ (index === 0 ? '<i class="fas fa-house"></i> ' : '') + esc(crumb.name) + '</button>');
			if (!last) {
				$c.on('click', function () { navigateTo(crumb.id); });
			}
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
			var sizeA = Number(a.size !== undefined ? a.size : a.file_size) || 0;
			var sizeB = Number(b.size !== undefined ? b.size : b.file_size) || 0;
			if (sizeA !== sizeB) { return sizeB - sizeA; }
		}
		var cmp = String(a[nameKey]).localeCompare(String(b[nameKey]));
		return sort === 'name-desc' ? -cmp : cmp;
	}

	function sortItems(items) {
		return items.sort(function (a, b) { return compareItems(a, b, '_name'); });
	}

	function currentItems() {
		var items = [];
		state.contents.folders.forEach(function (f) {
			items.push({
				type: 'folder', id: f.id, name: f.name, _name: f.name,
				is_favorite: Number(f.is_favorite) || 0,
				item_count: Number(f.item_count) || 0,
				created_at: f.created_at, updated_at: f.updated_at
			});
		});
		state.contents.files.forEach(function (f) {
			items.push({
				type: 'file', id: f.id, name: f.original_name, url: f.file_url,
				filetype: f.file_type || extOf(f.original_name), size: f.file_size,
				created_at: f.created_at, updated_at: f.updated_at, _name: f.original_name,
				is_favorite: Number(f.is_favorite) || 0,
				allow_download: Number(f.allow_download) === 0 ? 0 : 1,
				folder_id: f.folder_id, folder_name: f.folder_name || ''
			});
		});
		// Folders first, then files.
		items.sort(function (a, b) {
			if (a.type !== b.type) { return a.type === 'folder' ? -1 : 1; }
			return 0;
		});
		return items;
	}

	function renderContents() {
		var $c = $('#lkn-fb-contents');
		var items = currentItems();

		// Folders first, then favourite files, then the rest; each group sorted.
		var folders = sortItems(items.filter(function (i) { return i.type === 'folder'; }));
		var files = items.filter(function (i) { return i.type === 'file'; });
		var favFiles = sortItems(files.filter(function (i) { return Number(i.is_favorite); }));
		var otherFiles = sortItems(files.filter(function (i) { return !Number(i.is_favorite); }));
		var ordered = folders.concat(favFiles, otherFiles);

		if (state.filter) {
			var q = state.filter.toLowerCase();
			ordered = ordered.filter(function (i) { return i.name.toLowerCase().indexOf(q) !== -1; });
		}

		if (!ordered.length) {
			$c.html(emptyStateHtml());
			return;
		}

		var html = '';
		ordered.forEach(function (item) { html += cardHtml(item); });
		$c.html(html);
		syncSelection();
	}

	function emptyStateHtml() {
		if (state.filter) {
			return '<div class="lkn-fb-empty"><i class="fas fa-magnifying-glass"></i><p>' + esc(t('no_results_text', 'No matching items')) + '</p></div>';
		}
		if (state.collection === 'favorites') {
			return '<div class="lkn-fb-empty"><i class="fas fa-star"></i><p>' + esc(t('favorites_empty', 'No favorites yet')) + '</p></div>';
		}
		if (state.collection === 'recent') {
			return '<div class="lkn-fb-empty"><i class="fas fa-clock-rotate-left"></i><p>' + esc(t('recent_empty', 'No recent files')) + '</p></div>';
		}
		if (state.collection === 'trash') {
			return '<div class="lkn-fb-empty"><i class="fas fa-trash-can"></i><p>' + esc(t('trash_empty', 'Trash is empty')) + '</p></div>';
		}
		return '<div class="lkn-fb-empty"><i class="fas fa-folder-open"></i><p>' + esc(t('empty_folder_text', 'This folder is empty')) + '</p><span>' + esc(t('empty_folder_hint', '')) + '</span></div>';
	}

	function cardHtml(item) {
		var isFolder = item.type === 'folder';
		var ext = isFolder ? '' : (item.filetype || '');
		var locked = !isFolder && Number(item.allow_download) === 0;
		var thumb;
		if (isFolder) {
			thumb = '<i class="fas fa-folder"></i>';
		} else if (!locked && IMG_EXT.indexOf(ext) !== -1) {
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
		var data = 'data-type="' + item.type + '" data-id="' + item.id + '" data-name="' + esc(item.name) + '"'
			+ ' data-favorite="' + (item.is_favorite ? 1 : 0) + '"'
			+ ' data-allow-download="' + (locked ? 0 : 1) + '"'
			+ ' data-created="' + esc(item.created_at || '') + '" data-updated="' + esc(item.updated_at || '') + '"';
		if (!isFolder) {
			data += ' data-url="' + esc(item.url) + '" data-filetype="' + esc(ext) + '" data-size="' + (Number(item.size) || 0) + '"'
				+ ' data-folder-id="' + (Number(item.folder_id) || 0) + '" data-folder-name="' + esc(item.folder_name || '') + '"';
		}
		var fav = favStarButtonHtml(item);
		var lock = locked ? '<span class="lkn-fb-lock-badge" title="' + esc(t('download_locked', 'Download restricted')) + '"><i class="fas fa-lock"></i></span>' : '';
		return '<div class="lkn-fb-item ' + item.type + (ext ? ' ext-' + esc(ext) : '') + '" ' + data + ' draggable="true">'
			+ fav
			+ '<label class="lkn-fb-check"><input type="checkbox" class="lkn-fb-select" aria-label="Select"><span></span></label>'
			+ '<button type="button" class="lkn-fb-kebab" aria-label="' + esc(t('more_actions', 'More actions')) + '"><i class="fas fa-ellipsis-vertical"></i></button>'
			+ '<div class="lkn-fb-thumb">' + thumb + '</div>'
			+ '<div class="lkn-fb-name" title="' + esc(item.name) + '">' + esc(item.name) + '</div>'
			+ '<div class="lkn-fb-meta">' + esc(meta) + '</div>'
			+ lock
			+ '</div>';
	}

	function itemFromEl($el) {
		// Read raw attributes (not jQuery .data()) so numeric-looking names are preserved verbatim.
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
	 *  Selection & bulk actions
	 * ------------------------------------------------------------------ */

	function getSelectedItems() {
		var items = [];
		$('.lkn-fb-item.is-selected').each(function () { items.push(itemFromEl($(this))); });
		return items;
	}

	function clearSelection() {
		$('.lkn-fb-item').removeClass('is-selected').find('.lkn-fb-select').prop('checked', false);
		syncSelection();
	}

	function syncSelection() {
		var count = getSelectedItems().length;
		var $btn = $('#lkn-fb-bulk-delete');
		$('#lkn-fb-bulk-count').text(count);
		$btn.prop('hidden', count === 0);
	}

	/* ------------------------------------------------------------------ *
	 *  Upload (drag & drop + picker) with a concurrency-limited queue
	 * ------------------------------------------------------------------ */

	function overlayEl() { return $('#lkn-fb-dropzone-overlay'); }
	function tileEl() { return $('#lkn-fb-upload-tile'); }
	function uploadActive() { return overlayEl().hasClass('is-uploading'); }
	var tileHintDefault = null;

	function showUploadPanel() {
		var $o = overlayEl();
		window.clearTimeout(uploadHideTimer);
		$o.removeClass('is-success is-error').addClass('is-uploading is-visible');
	}

	function hideUploadPanel() {
		window.clearTimeout(uploadHideTimer);
		var $o = overlayEl();
		$o.removeClass('is-visible');
		window.setTimeout(function () {
			$o.removeClass('is-uploading is-success is-error');
			$('#lkn-fb-upload-list').empty();
		}, 260);
	}

	function scheduleUploadAutoHide() {
		window.clearTimeout(uploadHideTimer);
		uploadHideTimer = window.setTimeout(function () { hideUploadPanel(); }, 10000);
	}

	function setUploadState(state, title) {
		var $o = overlayEl();
		$o.removeClass('is-uploading is-success is-error').addClass('is-' + state).addClass('is-visible');
		var icon = state === 'success' ? 'fa-circle-check'
			: state === 'error' ? 'fa-circle-exclamation'
				: 'fa-cloud-arrow-up';
		$o.find('.lkn-fb-upstate i').attr('class', 'fas ' + icon);
		if (title != null) { $('#lkn-fb-upload-title').text(title); }
	}

	function beginDragHint() {
		if (uploadActive()) { return; }
		var $tile = tileEl();
		if (!$tile.length) { return; }
		if (tileHintDefault === null) { tileHintDefault = $tile.find('.lkn-fb-uploadtile-hint').text(); }
		$tile.addClass('is-dragover');
		$tile.find('.lkn-fb-uploadtile-hint').text(t('drop_upload', 'Drop files to upload'));
	}

	function endDragHint() {
		var $tile = tileEl();
		$tile.removeClass('is-dragover');
		if (tileHintDefault !== null) { $tile.find('.lkn-fb-uploadtile-hint').text(tileHintDefault); }
	}

	function addUploadRow(file, folderId) {
		var $row = $('<li class="lkn-fb-up-row">'
			+ '<span class="lkn-fb-up-icn"><i class="fas ' + typeIcon(extOf(file.name)) + '"></i></span>'
			+ '<span class="lkn-fb-up-name">' + esc(file.name) + '</span>'
			+ '<span class="lkn-fb-up-status"></span>'
			+ '<div class="lkn-fb-up-track"><div class="lkn-fb-up-fill"></div></div>'
			+ '</li>');
		$('#lkn-fb-upload-list').append($row);
		return {
			file: file, folderId: folderId, $row: $row,
			$fill: $row.find('.lkn-fb-up-fill'),
			$status: $row.find('.lkn-fb-up-status')
		};
	}

	function uploadOne(file, entry, folderId) {
		return new Promise(function (resolve) {
			var fd = new FormData();
			fd.append('action', 'linknacional_upload_file');
			fd.append('nonce', state.nonce);
			fd.append('folder_id', folderId);
			fd.append('files[]', file);

			$.ajax({
				url: L.ajax_url,
				type: 'POST',
				data: fd,
				processData: false,
				contentType: false,
				xhr: function () {
					var xhr = $.ajaxSettings.xhr();
					if (xhr.upload) {
						xhr.upload.addEventListener('progress', function (e) {
							if (e.lengthComputable) {
								entry.$fill.css('width', Math.round(e.loaded / e.total * 100) + '%');
							}
						});
					}
					return xhr;
				}
			}).done(function (response) {
				if (response && response.success) {
					entry.$row.addClass('is-done');
					entry.$fill.css('width', '100%');
					entry.$status.html('<i class="fas fa-circle-check"></i>');
					resolve(true);
				} else {
					markRowError(entry, (response && response.data) || t('upload_failed', 'Upload failed'));
					resolve(false);
				}
			}).fail(function () {
				markRowError(entry, t('upload_failed', 'Upload failed'));
				resolve(false);
			});
		});
	}

	function markRowError(entry, message) {
		entry.$row.addClass('is-error');
		var $retry = $('<button type="button" class="lkn-fb-up-retry" title="' + esc(message) + '">' + esc(t('retry', 'Retry')) + '</button>');
		$retry.on('click', function () { retryUpload(entry); });
		entry.$status.empty().append('<i class="fas fa-circle-exclamation"></i> ').append($retry);
	}

	function retryUpload(entry) {
		window.clearTimeout(uploadHideTimer);
		showUploadPanel();
		setUploadState('uploading', t('uploading_text', 'Uploading files…'));
		entry.$row.removeClass('is-error');
		entry.$status.empty();
		entry.$fill.css('width', '0');
		uploadOne(entry.file, entry, entry.folderId).then(function (ok) {
			if (!ok) {
				setUploadState('error', t('upload_error', 'Error uploading files'));
			} else if ($('.lkn-fb-up-row.is-error').length) {
				setUploadState('error', t('uploaded_partial', 'Some files could not be uploaded'));
			} else {
				setUploadState('success', t('uploaded_ok', 'Upload complete'));
			}
			scheduleUploadAutoHide();
			loadTree();
			loadContents(state.currentFolderId);
		});
	}

	function uploadBatch(files, targetFolderId) {
		if (!files || !files.length) { return; }
		targetFolderId = (targetFolderId == null) ? state.currentFolderId : targetFolderId;

		// A fresh batch replaces any finished/error panel still on screen.
		window.clearTimeout(uploadHideTimer);
		$('#lkn-fb-upload-list').empty();
		showUploadPanel();
		setUploadState('uploading', t('uploading_text', 'Uploading files…'));

		var entries = files.map(function (f) { return addUploadRow(f, targetFolderId); });
		var total = files.length;
		var finished = 0;
		var failed = 0;
		var index = 0;
		var active = 0;
		var MAX = 3;
		var done = false;

		function pump() {
			while (active < MAX && index < total && !done) {
				(function (i) {
					active++;
					uploadOne(files[i], entries[i], targetFolderId).then(function (ok) {
						active--;
						finished++;
						if (!ok) { failed++; }
						if (finished === total && !done) {
							done = true;
							finish();
						}
						pump();
					});
				})(index++);
			}
		}

		function finish() {
			if (failed === 0) {
				setUploadState('success', t('uploaded_ok', 'Upload complete'));
			} else if (failed < total) {
				setUploadState('error', t('uploaded_partial', 'Some files could not be uploaded'));
			} else {
				setUploadState('error', t('upload_error', 'Error uploading files'));
			}
			scheduleUploadAutoHide();
			loadTree();
			loadContents(state.currentFolderId);
		}

		pump();
	}

	function hasFiles(e) {
		var dt = e.originalEvent && e.originalEvent.dataTransfer;
		if (!dt || !dt.types) { return false; }
		return Array.prototype.indexOf.call(dt.types, 'Files') !== -1;
	}

	/* ------------------------------------------------------------------ *
	 *  File actions
	 * ------------------------------------------------------------------ */

	/* ------------------------------------------------------------------ *
	 *  Detail drawer
	 * ------------------------------------------------------------------ */

	function favStarButtonHtml(item) {
		var on = item.is_favorite ? ' is-fav' : '';
		return '<button type="button" class="lkn-fb-fav-btn' + on + '"'
			+ ' aria-pressed="' + (item.is_favorite ? 'true' : 'false') + '"'
			+ ' data-id="' + item.id + '" data-type="' + item.type + '"'
			+ ' aria-label="' + esc(item.is_favorite ? t('remove_favorite', 'Remove from favorites') : t('add_favorite', 'Add to favorites')) + '">'
			+ '<i class="' + (item.is_favorite ? 'fas' : 'far') + ' fa-star"></i></button>';
	}

	function openDrawer(item) {
		state.drawerItem = item;
		var ext = (item.filetype || extOf(item.name) || '').toLowerCase();

		$('#lkn-fb-drawer-icn').html('<i class="' + typeIcon(ext) + '"></i>');
		$('#lkn-fb-drawer-name').text(item.name);
		var meta = [formatSize(item.size), ext ? ext.toUpperCase() : '', formatDate(item.updated_at || item.created_at)].filter(Boolean);
		$('#lkn-fb-drawer-meta').text(meta.join(' - '));

		var $p = $('#lkn-fb-drawer-preview').empty();
		var isImg = IMG_EXT.indexOf(ext) !== -1 && item.url;
		$p.toggleClass('is-image', isImg);
		if (isImg) {
			$p.append($('<img>').attr('src', item.url).attr('alt', item.name));
		} else {
			// PDFs and text files are not embedded (mini viewer looks odd) — show just the icon.
			$p.append('<div class="lkn-fb-drawer-nopreview"><i class="' + typeIcon(ext) + '"></i></div>');
		}
		$p.append(favStarButtonHtml(item));

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
			details += '<div class="lkn-fb-drawer-row"><dt>' + esc(r[0]) + '</dt><dd>' + esc(r[1]) + '</dd></div>';
		});
		$('#lkn-fb-drawer-details').html(details);

		buildDrawerActions(item);
		buildDrawerQuick(item);

		$('#lkn-fb-drawer').addClass('is-open');
		$('body').addClass('lkn-fb-drawer-open');
		window.requestAnimationFrame(sizeDrawerToContent);

		// Re-fit once a lazy image finishes decoding (the frame is square, but be safe).
		var img = $('#lkn-fb-drawer-preview img')[0];
		if (img && !img.complete) { img.addEventListener('load', sizeDrawerToContent); }
	}

	function sizeDrawerToContent() {
		if (window.matchMedia('(max-width: 782px)').matches) { return; }
		var panelH = $('.lkn-fb-drawer-panel').outerHeight();
		var $body = $('.lkn-fb-body');
		if (!panelH || !$body.length) { return; }
		// Remember the resting height (card default / grid) as a floor.
		if (!state.drawerFloorH) { state.drawerFloorH = Math.ceil($body.outerHeight()); }
		var extra = $body.outerHeight() - $('.lkn-fb-content-area').outerHeight();
		if (isNaN(extra) || extra < 0) { extra = 0; }
		// Fit the panel exactly (grow OR shrink), but never below the resting height.
		var desired = Math.max(state.drawerFloorH, Math.ceil(panelH + extra));
		$body.css('min-height', desired + 'px');
	}

	function buildDrawerActions(item) {
		var $a = $('#lkn-fb-drawer-actions').empty();
		var btns = [];
		if (state.collection === 'trash') {
			btns.push({ label: t('restore', 'Restore'), icon: 'fas fa-rotate-left', cls: '', run: function () { restoreItems([item]); } });
			btns.push({ label: t('delete_forever', 'Delete permanently'), icon: 'fas fa-trash', cls: 'lkn-fb-danger', run: function () { purgeItems([item]); } });
		} else {
			btns.push({ label: t('download', 'Download'), icon: 'fas fa-download', cls: '', run: function () { downloadFile(item); } });
			btns.push({ label: t('edit_name', 'Rename'), icon: 'fas fa-pen', cls: '', run: function () { closeDrawer(); openRename(item); } });
			btns.push({ label: t('delete', 'Delete'), icon: 'fas fa-trash', cls: 'lkn-fb-danger', run: function () { trashItems([item]); } });
		}
		btns.forEach(function (b) {
			$('<button type="button" class="button ' + b.cls + '">')
				.html('<i class="' + b.icon + '"></i> ' + esc(b.label))
				.on('click', b.run)
				.appendTo($a);
		});
	}

	function buildDrawerQuick(item) {
		var $q = $('#lkn-fb-drawer-quick').empty();
		var acts = [];
		acts.push({ label: t('open_new_tab', 'Open in new tab'), icon: 'fas fa-arrow-up-right-from-square', run: function () { window.open(item.url, '_blank', 'noopener'); } });
		if (state.collection !== 'trash') {
			acts.push({ label: t('move_to', 'Move to…'), icon: 'fas fa-arrow-right-arrow-left', run: function () { openMoveModal(item, 'move'); } });
			acts.push({ label: t('copy_to', 'Copy to…'), icon: 'fas fa-copy', run: function () { openMoveModal(item, 'copy'); } });
			acts.push({
				label: item.is_favorite ? t('remove_favorite', 'Remove from favorites') : t('add_favorite', 'Add to favorites'),
				icon: item.is_favorite ? 'fas fa-star' : 'far fa-star',
				run: function () { toggleFavorite(item); }
			});
			acts.push({ label: t('copy_ai', 'Copy for AI'), icon: 'fas fa-robot', run: function () { copyForAI(item); } });
		}
		acts.forEach(function (a) {
			$('<button type="button" class="lkn-fb-quick-item">')
				.html('<i class="' + a.icon + '"></i> <span>' + esc(a.label) + '</span>')
				.on('click', a.run)
				.appendTo($q);
		});
	}

	function closeDrawer() {
		$('#lkn-fb-drawer').removeClass('is-open');
		// Keep the height set by the last opened item; it only re-fits when a new file opens.
		$('body').removeClass('lkn-fb-drawer-open');
		state.drawerItem = null;
	}

	function downloadFile(item) {
		var a = document.createElement('a');
		a.href = item.url + (item.url.indexOf('?') === -1 ? '?' : '&') + 'dl=1';
		a.download = item.name;
		document.body.appendChild(a);
		a.click();
		a.remove();
	}

	function copyLink(item) {
		var url = item.url;
		var done = function () { toast(t('link_copied', 'Link copied to clipboard'), 'success'); };
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(url).then(done, function () { fallbackCopy(url, done); });
		} else {
			fallbackCopy(url, done);
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
	 *  Copy for AI (Markdown export for LLMs)
	 * ------------------------------------------------------------------ */

	function mdEscape(text) {
		return String(text == null ? '' : text).replace(/[\\[\]]/g, '\\$&');
	}

	function mdLink(text, url) {
		return '[' + mdEscape(text) + '](' + String(url == null ? '' : url).replace(/\)/g, '%29') + ')';
	}

	function folderNameOf(id) {
		var hit = (state.folders || []).filter(function (x) { return Number(x.id) === Number(id); })[0];
		return hit ? hit.name : '';
	}

	function descriptionOf(id) {
		var hit = (state.files || []).filter(function (x) { return Number(x.id) === Number(id); })[0];
		return hit ? hit.description : '';
	}

	function groupBy(list, key, sortKey) {
		var out = {};
		list.forEach(function (item) {
			var k = Number(item[key]) || 0;
			(out[k] = out[k] || []).push(item);
		});
		Object.keys(out).forEach(function (k) {
			out[k].sort(function (a, b) { return String(a[sortKey]).localeCompare(String(b[sortKey])); });
		});
		return out;
	}

	function appendFolderMarkdown(out, byParent, byFolder, folderId, level) {
		var heading = new Array(Math.min(level, 6) + 1).join('#');
		(byFolder[Number(folderId)] || []).forEach(function (f) {
			out.push('- ' + mdLink(f.original_name, f.file_url) + ' (' + formatSize(f.file_size) + ')');
		});
		(byParent[Number(folderId)] || []).forEach(function (sf) {
			out.push('');
			out.push(heading + ' ' + mdEscape(sf.name));
			out.push('');
			appendFolderMarkdown(out, byParent, byFolder, sf.id, level + 1);
		});
	}

	function buildFileMarkdown(item) {
		var name = item.name || item.original_name || '';
		var ext = (item.filetype || extOf(name) || '').toLowerCase();
		var size = item.size !== undefined ? item.size : item.file_size;
		var url = item.url || item.file_url || '';
		var folder = item.folder_name || folderNameOf(item.folder_id);
		var lines = ['# ' + mdEscape(name), ''];
		lines.push('- **' + t('detail_type', 'Type') + ':** ' + (ext ? ext.toUpperCase() + ' (' + typeCategory(ext) + ')' : typeCategory('')));
		lines.push('- **' + t('detail_size', 'Size') + ':** ' + formatSize(size));
		var date = formatDate(item.updated_at || item.created_at);
		if (date) { lines.push('- **' + t('detail_modified', 'Modified') + ':** ' + date); }
		if (url) { lines.push('- **' + t('ai_url', 'URL') + ':** ' + url); }
		if (folder) { lines.push('- **' + t('folder_text', 'Folder') + ':** ' + folder); }
		var desc = item.description || descriptionOf(item.id);
		if (desc) { lines.push('- **' + t('ai_description', 'Description') + ':** ' + String(desc).replace(/\s+/g, ' ').trim()); }
		return lines.join('\n');
	}

	function buildFolderMarkdown(folder) {
		var byParent = groupBy(state.folders || [], 'parent_id', 'name');
		var byFolder = groupBy(state.files || [], 'folder_id', 'original_name');
		var out = ['# ' + mdEscape(folder ? folder.name : t('ai_title', 'File Browser')), ''];
		appendFolderMarkdown(out, byParent, byFolder, folder ? folder.id : 0, 2);
		return out.join('\n').replace(/\n{3,}/g, '\n\n').trim();
	}

	function buildAIMarkdown(item) {
		if (item && item.type === 'file') { return buildFileMarkdown(item); }
		return buildFolderMarkdown(item && item.type === 'folder' ? item : null);
	}

	function copyForAI(item) {
		var text = buildAIMarkdown(item);
		if (!text) { toast(t('ai_nothing', 'Nothing to copy'), 'error'); return; }
		var done = function () { toast(t('ai_copied', 'Copied for AI'), 'success'); };
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text, done); });
		} else {
			fallbackCopy(text, done);
		}
	}

	/* ------------------------------------------------------------------ *
	 *  Rename
	 * ------------------------------------------------------------------ */

	function openRename(item) {
		state.renameItem = item;
		var isFile = item.type === 'file';
		$('#lkn-fb-rename-label').text(isFile ? t('rename_file_lbl', 'File name') : t('rename_folder_lbl', 'Folder name'));
		var base = item.name;
		var ext = '';
		if (isFile) {
			var dot = item.name.lastIndexOf('.');
			if (dot > 0) { base = item.name.slice(0, dot); ext = item.name.slice(dot); }
		}
		var $ext = $('#lkn-fb-rename-ext');
		$('#lkn-fb-rename-input').val(base);
		if (ext) { $ext.text(ext).prop('hidden', false); } else { $ext.text('').prop('hidden', true); }
		$('#lkn-fb-rename-error').prop('hidden', true).text('');
		openModal('#lkn-fb-rename-modal');
		window.setTimeout(function () { $('#lkn-fb-rename-input').trigger('focus').select(); }, 60);
	}

	function submitRename() {
		var item = state.renameItem;
		if (!item) { return; }
		var base = $('#lkn-fb-rename-input').val().trim();
		var ext = $('#lkn-fb-rename-ext').text();
		var $error = $('#lkn-fb-rename-error');
		if (!base) {
			$error.text(t('name_empty_error', 'Name cannot be empty')).prop('hidden', false);
			return;
		}
		var finalName = item.type === 'file' ? base + ext : base;
		var action = item.type === 'folder' ? 'linknacional_update_folder_name' : 'linknacional_update_file_name';

		api(action, { id: item.id, new_name: finalName }).then(function (response) {
			if (response && response.success) {
				closeModal($('#lkn-fb-rename-modal'));
				toast(item.type === 'folder' ? t('folder_renamed', 'Folder renamed') : t('file_renamed', 'File renamed'), 'success');
				loadTree();
				loadContents(state.currentFolderId);
			} else {
				$error.text((response && response.data) || t('update_error', 'Error updating name')).prop('hidden', false);
			}
		});
	}

	/* ------------------------------------------------------------------ *
	 *  Move
	 * ------------------------------------------------------------------ */

	function openMoveModal(item, mode) {
		state.moveSubject = item;
		state.moveMode = mode === 'copy' ? 'copy' : 'move';
		var isCopy = state.moveMode === 'copy';
		$('#lkn-fb-move-title').html('<i class="fas ' + (isCopy ? 'fa-copy' : 'fa-arrow-right-arrow-left') + '"></i> '
			+ esc(isCopy ? t('copy_to', 'Copy to…') : t('move_to', 'Move to…')));
		$('#lkn-fb-move-confirm').text(isCopy ? t('copied_ok', 'Copy') : t('move_here', 'Move'));
		$('#lkn-fb-move-subject').text('“' + item.name + '”');
		var $sel = $('#lkn-fb-move-select').empty();
		$sel.append($('<option>').val(0).text('🏠 ' + t('home', 'Home')));
		buildFolderOptions(item).forEach(function (o) {
			$sel.append($('<option>').val(o.id).text(new Array(o.depth + 1).join('— ') + o.name));
		});
		openModal('#lkn-fb-move-modal');
	}

	function buildFolderOptions(item) {
		var out = [];
		var skip = {};
		if (item.type === 'folder') {
			(function mark(id) {
				skip[String(id)] = true;
				state.folders.forEach(function (f) { if (String(f.parent_id) === String(id)) { mark(f.id); } });
			})(item.id);
		}
		(function walk(parentId, depth) {
			state.folders
				.filter(function (f) { return String(f.parent_id) === String(parentId); })
				.sort(function (a, b) { return a.name.localeCompare(b.name); })
				.forEach(function (f) {
					if (skip[String(f.id)]) { return; }
					out.push({ id: f.id, name: f.name, depth: depth });
					walk(f.id, depth + 1);
				});
		})(0, 0);
		return out;
	}

	function moveItems(items, targetFolderId) {
		targetFolderId = Number(targetFolderId) || 0;
		var calls = items.map(function (it) {
			if (it.type === 'folder') {
				return api('linknacional_move_folder', { folder_id: it.id, target_folder_id: targetFolderId });
			}
			return api('linknacional_move_file', { file_id: it.id, target_folder_id: targetFolderId });
		});
		return Promise.all(calls).then(function (responses) {
			var firstError = responses.filter(function (r) { return !(r && r.success); })[0];
			var allUnchanged = responses.length > 0 && responses.every(function (r) {
				return r && r.success && r.data && r.data.unchanged;
			});
			if (firstError) {
				toast((firstError && firstError.data) || t('move_error', 'Could not move the item'), 'error');
			} else if (allUnchanged) {
				toast(t('already_there', 'It is already in this folder'), 'info');
			} else {
				var label = items.length === 1 ? items[0].name : items.length + ' ' + t('items_text', 'items');
				toast(t('moved_ok', '“%s” moved').replace('%s', label), 'success');
			}
			clearSelection();
			loadTree();
			loadContents(state.currentFolderId);
		});
	}

	function moveDraggedTo(targetFolderId) {
		var d = state.dragging;
		state.dragging = null;
		if (!d) { return; }
		var items;
		if (d.selected && getSelectedItems().length > 1) {
			items = getSelectedItems();
		} else {
			items = [d.item];
		}
		items = items.filter(function (it) {
			return !(it.type === 'folder' && Number(it.id) === Number(targetFolderId));
		});
		if (!items.length) { return; }
		moveItems(items, targetFolderId);
	}

	function copyItem(item, targetFolderId) {
		api('linknacional_copy_item', { item_type: item.type, id: item.id, target_folder_id: Number(targetFolderId) || 0 }).then(function (response) {
			if (response && response.success) {
				toast(t('copied_ok', 'Copied'), 'success');
			} else {
				toast((response && response.data) || t('copy_error', 'Could not copy the item'), 'error');
			}
			refreshView();
		});
	}

	/* ------------------------------------------------------------------ *
	 *  Delete
	 * ------------------------------------------------------------------ */

	function elForItem(item) {
		var type = item.type === 'folder' ? 'folder' : 'file';
		return $('.lkn-fb-item.' + type + '[data-id="' + item.id + '"]');
	}

	function burstItem($el) {
		if ($el.hasClass('is-exploding')) { return; }
		$el.addClass('is-exploding');
		var count = 8;
		for (var i = 0; i < count; i++) {
			var angle = (Math.PI * 2 * i) / count + (Math.random() - 0.5) * 0.6;
			var dist = 36 + Math.random() * 30;
			var size = 5 + Math.round(Math.random() * 5);
			$('<span class="lkn-fb-burst"></span>').css({
				'--dx': Math.round(Math.cos(angle) * dist) + 'px',
				'--dy': Math.round(Math.sin(angle) * dist) + 'px',
				width: size + 'px',
				height: size + 'px',
				animationDelay: Math.round(Math.random() * 60) + 'ms'
			}).appendTo($el);
		}
		window.setTimeout(function () { $el.remove(); }, 560);
	}

	function explodeItems(items) {
		return new Promise(function (resolve) {
			var $els = [];
			items.forEach(function (it) {
				var $el = elForItem(it);
				if ($el.length) { $els.push($el); }
			});
			if (!$els.length) { resolve(); return; }
			$els.forEach(function ($el) { burstItem($el); });
			window.setTimeout(resolve, 460);
		});
	}

	function refreshView() {
		closeDrawer();
		clearSelection();
		loadTree();
		if (state.collection === 'files') {
			loadContents(state.currentFolderId);
		} else {
			loadCollection(state.collection);
		}
	}

	function itemsParam(items) {
		return JSON.stringify(items.map(function (it) { return { type: it.type, id: it.id }; }));
	}

	function trashItems(items) {
		if (!items.length) { return; }
		var one = items.length === 1;
		var hasFolder = items.some(function (i) { return i.type === 'folder'; });
		var message;
		if (one) {
			message = esc(t('trash_confirm', '“%s” will be moved to the trash.').replace('%s', items[0].name));
			if (hasFolder) { message += '<br><strong>' + esc(t('delete_folder_warn', 'Everything inside this folder will be deleted too.')) + '</strong>'; }
		} else {
			message = esc(t('delete_many_msg', '%d items will be moved to the trash.').replace('%d', items.length));
		}
		var title = one ? t('delete_title', 'Delete item') : t('delete_selected_title', 'Delete selected items');
		confirmDialog({ title: title, message: message, confirmText: t('move_to_trash', 'Move to trash') }).then(function (ok) {
			if (!ok) { return; }
			explodeItems(items).then(function () {
				api('linknacional_trash_items', { items: itemsParam(items) }).then(function (response) {
					toast((response && response.success) ? t('trashed_ok', 'Moved to trash') : t('delete_error', 'Could not delete the items'), (response && response.success) ? 'success' : 'error');
					refreshView();
				});
			});
		});
	}

	function purgeItems(items) {
		if (!items.length) { return; }
		var one = items.length === 1;
		var message = one
			? esc(t('purge_confirm', '“%s” will be permanently deleted. This cannot be undone.').replace('%s', items[0].name))
			: esc(t('delete_many_msg', '%d items will be permanently deleted. This cannot be undone.').replace('%d', items.length));
		confirmDialog({ title: t('delete_forever', 'Delete permanently'), message: message, confirmText: t('delete_forever', 'Delete permanently') }).then(function (ok) {
			if (!ok) { return; }
			explodeItems(items).then(function () {
				api('linknacional_purge_items', { items: itemsParam(items) }).then(function (response) {
					toast((response && response.success) ? t('purged_ok', 'Deleted permanently') : t('delete_error', 'Could not delete the items'), (response && response.success) ? 'success' : 'error');
					refreshView();
				});
			});
		});
	}

	function restoreItems(items) {
		if (!items.length) { return; }
		api('linknacional_restore_items', { items: itemsParam(items) }).then(function (response) {
			toast((response && response.success) ? t('restored_ok', 'Restored') : t('error_generic', 'Something went wrong. Please try again.'), (response && response.success) ? 'success' : 'error');
			refreshView();
		});
	}

	function toggleDownload(item) {
		api('linknacional_toggle_download', { id: item.id }).then(function (response) {
			if (response && response.success) {
				toast(response.data.message, 'success');
				var allow = response.data.allow_download ? 1 : 0;
				if (state.drawerItem && state.drawerItem.id === item.id) { state.drawerItem.allow_download = allow; }
				state.contents.files.forEach(function (it) { if (Number(it.id) === Number(item.id)) { it.allow_download = allow; } });
				renderContents();
				renderTree();
				if (state.drawerItem && state.drawerItem.id === item.id) { openDrawer(state.drawerItem); }
			} else {
				toast((response && response.data) || t('error_generic', 'Something went wrong. Please try again.'), 'error');
			}
		});
	}

	function toggleFavorite(item) {
		api('linknacional_toggle_favorite', { item_type: item.type, id: item.id }).then(function (response) {
			if (response && response.success) {
				toast(response.data.message, 'success');
				var on = !!response.data.is_favorite;
				// Reflect the new state on the drawer star without closing the panel.
				$('#lkn-fb-drawer-preview .lkn-fb-fav-btn')
					.toggleClass('is-fav', on)
					.attr('aria-pressed', on ? 'true' : 'false')
					.find('i').toggleClass('fas', on).toggleClass('far', !on);
				if (state.drawerItem && state.drawerItem.id === item.id) { state.drawerItem.is_favorite = on ? 1 : 0; }
				// Update the in-memory row and re-render so the favourite jumps to the top right away.
				var list = item.type === 'folder' ? state.contents.folders : state.contents.files;
				list.forEach(function (it) { if (Number(it.id) === Number(item.id)) { it.is_favorite = on ? 1 : 0; } });
				if (state.collection === 'favorites') { loadCollection('favorites'); } else { renderContents(); }
			} else {
				toast((response && response.data) || t('error_generic', 'Something went wrong. Please try again.'), 'error');
			}
		});
	}

	/* ------------------------------------------------------------------ *
	 *  Migration banner
	 * ------------------------------------------------------------------ */

	function runMigration() {
		var $btn = $('#linknacional-migrate-btn');
		var $status = $('#linknacional-migration-status');
		$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> ' + esc(t('migrating_text', 'Migrating…')));
		api('linknacional_migrate', {}).then(function (response) {
			if (response && response.success) {
				toast(response.data.message, 'success');
				$('#linknacional-migration-banner').slideUp(200, function () { $(this).remove(); });
				loadTree();
				loadContents(state.currentFolderId);
			} else {
				$status.text((response && response.data) || t('migrate_error', 'Migration failed.')).addClass('error');
				$btn.prop('disabled', false).html('<i class="fas fa-rotate"></i> ' + esc(t('migrate_text', 'Migrate Now')));
			}
		});
	}

	/* ------------------------------------------------------------------ *
	 *  Event wiring
	 * ------------------------------------------------------------------ */

	$(function () {

		/* ---- Keep the contextual menu anchored ---- */
		document.addEventListener('scroll', function () {
			if ($('.lkn-fb-menu').length) { closeMenu(); }
		}, true);

		/* ---- Tabs ---- */
		$(document).on('click', '.lkn-fb-tab', function () {
			var tab = $(this).data('tab');
			$('.lkn-fb-tab').removeClass('is-active').attr('aria-selected', 'false');
			$(this).addClass('is-active').attr('aria-selected', 'true');
			$('.lkn-fb-panel').prop('hidden', true);
			$('.lkn-fb-panel[data-panel="' + tab + '"]').prop('hidden', false);
		});

		/* ---- View toggle ---- */
		$(document).on('click', '.lkn-fb-view-btn', function () {
			state.view = $(this).data('view');
			$('.lkn-fb-view-btn').removeClass('is-active');
			$(this).addClass('is-active');
			$('#lkn-fb-contents').toggleClass('is-grid', state.view === 'grid').toggleClass('is-list', state.view === 'list');
		});

		/* ---- Collections ---- */
		$(document).on('click', '.lkn-fb-collection', function () {
			var collection = $(this).data('collection');
			if (collection === state.collection && collection === 'files') { return; }
			loadCollection(collection);
		});

		/* ---- Sort ---- */
		$('#lkn-fb-sort').on('change', function () { state.sort = $(this).val(); renderContents(); renderTree(); });

		/* ---- Filter ---- */
		$('#lkn-fb-search').on('input', function () {
			state.filter = $(this).val();
			$('#lkn-fb-search-clear').prop('hidden', state.filter === '');
			renderContents();
		});
		$('#lkn-fb-search-clear').on('click', function () {
			state.filter = '';
			$('#lkn-fb-search').val('').trigger('focus');
			$(this).prop('hidden', true);
			renderContents();
		});

		/* ---- Sidebar collapse (single centred handle) ---- */
		$('#lkn-fb-sidebar-handle').on('click', function () {
			var collapsed = $('.lkn-fb-body').toggleClass('sidebar-collapsed').hasClass('sidebar-collapsed');
			$(this).find('i').toggleClass('fa-chevron-left', !collapsed).toggleClass('fa-chevron-right', collapsed);
			$(this).attr('title', collapsed ? t('show_folders', 'Show folders') : t('hide_folders', 'Hide folders'));
		});

		/* ---- Create folder ---- */
		$('#lkn-fb-new-folder').on('click', function () {
			$('#parent-folder-id').val(state.currentFolderId);
			$('#lkn-fb-folder-name').val('');
			openModal('#create-folder-modal');
			window.setTimeout(function () { $('#lkn-fb-folder-name').trigger('focus'); }, 60);
		});
		$('#create-folder-form').on('submit', function (e) {
			e.preventDefault();
			var name = $('#lkn-fb-folder-name').val().trim();
			if (!name) { return; }
			api('linknacional_create_folder', { folder_name: name, parent_id: $('#parent-folder-id').val() }).then(function (response) {
				if (response && response.success) {
					closeModal($('#create-folder-modal'));
					toast(response.data.message || t('folder_created', 'Folder created'), 'success');
					loadTree();
					loadContents(state.currentFolderId);
				} else {
					toast((response && response.data) || t('create_folder_error', 'Error creating folder'), 'error');
				}
			});
		});

		/* ---- Upload tile ---- */
		$('#lkn-fb-upload-tile').on('click', function () { $('#lkn-fb-file-input').trigger('click'); });
		$('#lkn-fb-file-input').on('change', function () {
			var files = Array.prototype.slice.call(this.files || []);
			this.value = '';
			if (files.length) { uploadBatch(files); }
		});
		$('#lkn-fb-upload-clear').on('click', function () {
			hideUploadPanel();
		});

		/* ---- Upload tile (the only drop target for new uploads) ---- */
		var dragDepth = 0;
		var $dz = tileEl();
		$dz.on('dragenter', function (e) {
			if (!hasFiles(e)) { return; }
			dragDepth++;
			beginDragHint();
		});
		$dz.on('dragleave', function (e) {
			if (!hasFiles(e)) { return; }
			dragDepth--;
			if (dragDepth <= 0) { dragDepth = 0; endDragHint(); }
		});
		$dz.on('dragover', function (e) {
			if (!hasFiles(e)) { return; }
			e.preventDefault();
			e.originalEvent.dataTransfer.dropEffect = 'copy';
		});
		$dz.on('drop', function (e) {
			if (!hasFiles(e)) { return; }
			e.preventDefault();
			dragDepth = 0;
			endDragHint();
			var files = Array.prototype.slice.call(e.originalEvent.dataTransfer.files || []);
			if (files.length) { uploadBatch(files); }
		});

		/* Keep the browser from opening a file dropped anywhere outside a drop target. */
		$(document).on('dragover drop', function (e) {
			if (hasFiles(e)) { e.preventDefault(); }
		});

		/* ---- Breadcrumb / tree navigation ---- */
		$(document).on('click', '.lkn-fb-tree-item.folder', function (e) {
			if ($(e.target).closest('.lkn-fb-tree-toggle').length) { return; }
			navigateTo(Number($(this).data('folder-id')));
		});
		$(document).on('click', '.lkn-fb-tree-item.file', function (e) {
			e.stopPropagation();
			openDrawer({
				type: 'file',
				id: Number($(this).attr('data-file-id')) || 0,
				name: String($(this).attr('data-file-name')),
				url: String($(this).attr('data-file-url')),
				filetype: String($(this).attr('data-filetype')),
				size: Number($(this).attr('data-size')) || 0,
				allow_download: Number($(this).attr('data-allow-download')) === 0 ? 0 : 1,
				folder_id: Number($(this).attr('data-parent-folder-id')) || 0
			});
		});
		$(document).on('click', '.lkn-fb-tree-toggle', function (e) {
			e.stopPropagation();
			var $item = $(this).closest('.lkn-fb-tree-item');
			var $children = $item.next('.lkn-fb-tree-children');
			var open = $children.toggleClass('is-open').hasClass('is-open');
			$item.toggleClass('is-open', open);
			$(this).find('i').toggleClass('fa-caret-right', !open).toggleClass('fa-caret-down', open);
		});

		/* ---- Content clicks ---- */
		$(document).on('click', '.lkn-fb-item', function (e) {
			if ($(e.target).closest('.lkn-fb-check, .lkn-fb-kebab, .lkn-fb-fav-btn').length) { return; }
			var item = itemFromEl($(this));
			if (item.type === 'folder') { navigateTo(item.id); }
			else { openDrawer(item); }
		});
		$(document).on('click', '.lkn-fb-fav-btn', function (e) {
			e.stopPropagation();
			toggleFavorite({ type: $(this).attr('data-type'), id: Number($(this).attr('data-id')) });
		});
		$(document).on('click', '.lkn-fb-kebab', function (e) {
			e.stopPropagation();
			openMenu($(this), itemFromEl($(this).closest('.lkn-fb-item')));
		});
		$(document).on('change', '.lkn-fb-select', function () {
			$(this).closest('.lkn-fb-item').toggleClass('is-selected', this.checked);
			syncSelection();
		});
		$('#lkn-fb-bulk-delete').on('click', function () {
			var items = getSelectedItems();
			if (state.collection === 'trash') { purgeItems(items); } else { trashItems(items); }
		});

		/* ---- Drag to move (grid items + tree + crumbs) ---- */
		$(document).on('dragstart', '.lkn-fb-item', function (e) {
			var item = itemFromEl($(this));
			state.dragging = { item: item, selected: $(this).find('.lkn-fb-select').is(':checked') };
			$(this).addClass('is-dragging');
			var dt = e.originalEvent.dataTransfer;
			if (dt) {
				dt.effectAllowed = 'move';
				try { dt.setData('text/plain', item.type + ':' + item.id); } catch (err) { /* noop */ }
			}
		});
		$(document).on('dragend', '.lkn-fb-item', function () {
			state.dragging = null;
			$(this).removeClass('is-dragging');
			$('.drop-target').removeClass('drop-target');
		});

		// Folder cards accept both external files (upload) and internal items (move).
		$(document).on('dragenter', '.lkn-fb-item.folder', function (e) {
			// Keep file drags over a folder card from triggering the whole-area upload overlay.
			if (hasFiles(e) || state.dragging) { e.stopPropagation(); }
		});
		$(document).on('dragover', '.lkn-fb-item.folder', function (e) {
			var files = hasFiles(e);
			if (!files && !state.dragging) { return; }
			e.preventDefault();
			e.stopPropagation();
			$(this).addClass('drop-target');
			if (e.originalEvent.dataTransfer) { e.originalEvent.dataTransfer.dropEffect = files ? 'copy' : 'move'; }
		});
		$(document).on('dragleave', '.lkn-fb-item.folder, .lkn-fb-tree-item.folder, .lkn-fb-crumb', function () {
			$(this).removeClass('drop-target');
		});
		$(document).on('drop', '.lkn-fb-item.folder', function (e) {
			var folderId = Number($(this).data('id'));
			$(this).removeClass('drop-target');
			if (hasFiles(e)) {
				e.preventDefault();
				e.stopPropagation();
				var files = Array.prototype.slice.call(e.originalEvent.dataTransfer.files || []);
				if (files.length) { uploadBatch(files, folderId); }
				return;
			}
			if (state.dragging) {
				e.preventDefault();
				e.stopPropagation();
				moveDraggedTo(folderId);
			}
		});

		// Tree nodes & breadcrumbs: internal move only.
		$(document).on('dragover', '.lkn-fb-tree-item.folder, .lkn-fb-crumb', function (e) {
			if (!state.dragging) { return; }
			e.preventDefault();
			e.stopPropagation();
			$(this).addClass('drop-target');
			if (e.originalEvent.dataTransfer) { e.originalEvent.dataTransfer.dropEffect = 'move'; }
		});
		$(document).on('drop', '.lkn-fb-tree-item.folder, .lkn-fb-crumb', function (e) {
			if (!state.dragging) { return; }
			e.preventDefault();
			e.stopPropagation();
			$(this).removeClass('drop-target');
			moveDraggedTo(Number($(this).data('folder-id')));
		});

		/* ---- Modals: close / confirm / escape ---- */
		$(document).on('click', '.lkn-fb-modal [data-close]', function () {
			var $m = $(this).closest('.lkn-fb-modal');
			if ($m.attr('id') === 'lkn-fb-confirm-modal') { resolveConfirm(false); } else { closeModal($m); }
		});
		$(document).on('mousedown', '.lkn-fb-modal', function (e) {
			if (e.target !== this) { return; }
			if (this.id === 'lkn-fb-confirm-modal') { resolveConfirm(false); } else { closeModal($(this)); }
		});
		$('#lkn-fb-confirm-ok').on('click', function () { resolveConfirm(true); });
		$(document).on('keydown', function (e) {
			if (e.key !== 'Escape') { return; }
			closeMenu();
			if ($('#lkn-fb-drawer').hasClass('is-open')) { closeDrawer(); return; }
			var $m = $('.lkn-fb-modal').filter(function () { return !this.hidden; }).last();
			if (!$m.length) { return; }
			if ($m.attr('id') === 'lkn-fb-confirm-modal') { resolveConfirm(false); } else { closeModal($m); }
		});

		/* ---- Detail drawer ---- */
		$(document).on('click', '#lkn-fb-drawer [data-drawer-close]', function () { closeDrawer(); });
		$('#lkn-fb-drawer-copyurl').on('click', function () { if (state.drawerItem) { copyLink(state.drawerItem); } });
		// Clicking empty space in the content area (outside the panel) closes it.
		$(document).on('click', '.lkn-fb-content-area', function (e) {
			if ($(e.target).closest('.lkn-fb-drawer-panel, .lkn-fb-item, .lkn-fb-kebab').length) { return; }
			if ($('#lkn-fb-drawer').hasClass('is-open')) { closeDrawer(); }
		});

		/* ---- Rename / Move forms ---- */
		$('#lkn-fb-rename-form').on('submit', function (e) { e.preventDefault(); submitRename(); });
		$('#lkn-fb-move-confirm').on('click', function () {
			var target = Number($('#lkn-fb-move-select').val());
			var item = state.moveSubject;
			var mode = state.moveMode;
			closeModal($('#lkn-fb-move-modal'));
			if (!item) { return; }
			if (mode === 'copy') { copyItem(item, target); } else { moveItems([item], target); }
		});

		/* ---- Copy shortcode ---- */
		$(document).on('click', '.copy-shortcode', function (e) {
			e.preventDefault();
			var $btn = $(this);
			var code = $btn.data('shortcode');
			fallbackCopy(code, function () {
				var original = $btn.html();
				$btn.html('<i class="fas fa-check"></i> ' + esc(t('copied_text', 'Copied!')));
				window.setTimeout(function () { $btn.html(original); }, 1800);
			});
		});

		/* ---- Copy for AI ---- */
		$('#lkn-fb-copy-ai').on('click', function () { copyForAI(null); });

		/* ---- Migration ---- */
		$('#linknacional-migrate-btn').on('click', runMigration);

		/* ---- Boot ---- */
		fetchAndSetNonce(function () {
			loadTree();
			loadContents(0);
		});
	});

})(jQuery);
