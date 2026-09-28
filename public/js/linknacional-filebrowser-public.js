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
				{ label: t('open_file', 'Open'), icon: 'fas fa-folder-open', run: function () { navigateTo(item.id); } },
				{ label: t('copy_ai', 'Copy for AI'), icon: 'fas fa-robot', run: function () { copyForAI(item); } }
			];
		}
		var actions = [
			{ label: t('preview', 'Preview'), icon: 'fas fa-eye', run: function () { openDrawer(item); } },
			{ label: t('view', 'View'), icon: 'fas fa-expand', run: function () { openViewer(item); } }
		];
		if (item.allow_download) {
			actions.push({ label: t('download', 'Download'), icon: 'fas fa-download', run: function () { downloadFile(item); } });
			actions.push({ label: t('copy_link', 'Copy link'), icon: 'fas fa-link', run: function () { copyLink(item); } });
		}
		actions.push({ label: t('copy_ai', 'Copy for AI'), icon: 'fas fa-robot', run: function () { copyForAI(item); } });
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

	function nopreviewHtml(ext) {
		return '<div class="lnfb-drawer-nopreview"><i class="' + typeIcon(ext) + '"></i></div>';
	}
	function setupDrawerPreview($p, item, ext) {
		// Only images render inline; every other type (PDF included) shows the
		// type icon and is opened in the fullscreen viewer instead.
		if (!item.url || IMG_EXT.indexOf(ext) === -1) {
			$p.append(nopreviewHtml(ext));
			return;
		}
		// Locked images are drawn to a canvas so there's no saveable <img> in the
		// DOM (best-effort; same rationale as the fullscreen viewer).
		if (Number(item.allow_download) === 0) {
			var $c = $('<canvas class="lnfb-drawer-preview-img" role="img">').attr('aria-label', item.name);
			$p.addClass('is-preview').append($c);
			var probe = new Image();
			probe.onload = function () {
				var cv = $c[0];
				if (!cv || state.drawerItem !== item) { return; }
				cv.width = probe.naturalWidth;
				cv.height = probe.naturalHeight;
				cv.getContext('2d').drawImage(probe, 0, 0);
				sizeDrawerToContent();
			};
			probe.onerror = function () {
				if (state.drawerItem !== item) { return; }
				$p.removeClass('is-preview').empty().append(nopreviewHtml(ext));
			};
			probe.src = withParam(item.url, 'mode=image');
			return;
		}
		$p.addClass('is-preview').append(
			$('<img class="lnfb-drawer-preview-img">')
				.attr('alt', item.name)
				.attr('src', withParam(item.url, 'mode=image'))
				.on('error', function () {
					if (state.drawerItem !== item) { return; }
					$p.removeClass('is-preview').empty().append(nopreviewHtml(ext));
				})
		);
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

		var $p = $('#lnfb-drawer-preview').empty().removeClass('is-preview');
		setupDrawerPreview($p, item, ext);
		// Read-only favourite indicator (public never toggles favourites).
		if (Number(item.is_favorite)) {
			$p.append('<span class="lnfb-fav-badge" title="' + esc(t('col_favorites', 'Favorites')) + '"><i class="fas fa-star"></i></span>');
		}
		if (item.type === 'file' && Number(item.allow_download) === 0) {
			$p.append('<span class="lnfb-drawer-lock" title="' + esc(t('download_locked', 'Download restricted')) + '"><i class="fas fa-lock"></i></span>');
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

		// Read-only quick actions: the fullscreen viewer is always available.
		var quick = [
			{ label: t('view', 'View'), icon: 'fas fa-expand', run: function () { openViewer(item); } },
			{ label: t('copy_ai', 'Copy for AI'), icon: 'fas fa-robot', run: function () { copyForAI(item); } }
		];
		if (item.allow_download) {
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
	 *  Copy for AI (Markdown export for LLMs)
	 * ------------------------------------------------------------------ */

	function copyText(text, done) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(done, function () { fallbackCopy(text, done); });
		} else {
			fallbackCopy(text, done);
		}
	}

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

	function appendFolderMarkdown(out, byParent, byFolder, folderId, level, urlMap) {
		var heading = new Array(Math.min(level, 6) + 1).join('#');
		(byFolder[Number(folderId)] || []).forEach(function (f) {
			out.push('- ' + mdLink(f.original_name, urlMap[Number(f.id)] || f.file_url) + ' (' + formatSize(f.file_size) + ')');
		});
		(byParent[Number(folderId)] || []).forEach(function (sf) {
			out.push('');
			out.push(heading + ' ' + mdEscape(sf.name));
			out.push('');
			appendFolderMarkdown(out, byParent, byFolder, sf.id, level + 1, urlMap);
		});
	}

	function buildFileMarkdown(item, urlMap) {
		var name = item.name || item.original_name || '';
		var ext = (item.filetype || extOf(name) || '').toLowerCase();
		var size = item.size !== undefined ? item.size : item.file_size;
		var url = (urlMap && urlMap[Number(item.id)]) || item.url || item.file_url || '';
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

	function buildFolderMarkdown(folder, urlMap) {
		var byParent = groupBy(state.folders || [], 'parent_id', 'name');
		var byFolder = groupBy(state.files || [], 'folder_id', 'original_name');
		var out = ['# ' + mdEscape(folder ? folder.name : t('ai_title', 'File Browser')), ''];
		appendFolderMarkdown(out, byParent, byFolder, folder ? folder.id : 0, 2, urlMap);
		return out.join('\n').replace(/\n{3,}/g, '\n\n').trim();
	}

	function buildAIMarkdown(item, urlMap) {
		if (item && item.type === 'file') { return buildFileMarkdown(item, urlMap); }
		return buildFolderMarkdown(item && item.type === 'folder' ? item : null, urlMap);
	}

	/** File ids included in a copy: the file itself, or a folder's whole subtree. */
	function collectFileIds(item) {
		if (item && item.type === 'file') { return [Number(item.id)]; }
		var rootId = item && item.type === 'folder' ? Number(item.id) : 0;
		var byParent = groupBy(state.folders || [], 'parent_id', 'name');
		var scope = {};
		(function walk(id) {
			scope[id] = true;
			(byParent[id] || []).forEach(function (sf) { walk(Number(sf.id)); });
		})(rootId);
		return (state.files || [])
			.filter(function (f) { return scope[Number(f.folder_id)]; })
			.map(function (f) { return Number(f.id); });
	}

	function copyForAI(item) {
		var ids = collectFileIds(item).filter(function (n) { return n > 0; });
		if (!ids.length) { toast(t('ai_nothing', 'Nothing to copy'), 'error'); return; }

		var done = function () { toast(t('ai_copied', 'Copied for AI'), 'success'); };
		var copy = function (urlMap) {
			var text = buildAIMarkdown(item, urlMap || {});
			if (!text) { toast(t('ai_nothing', 'Nothing to copy'), 'error'); return; }
			copyText(text, done);
		};

		// Each file gets a temporary share link so the LLM can reach it.
		api('linknacional_frontend_share', { file_ids: ids.join(',') }).then(function (response) {
			copy(response && response.success && response.data ? response.data.links : {});
		}, function () { copy({}); });
	}

	/* ------------------------------------------------------------------ *
	 *  Fullscreen viewer (lightbox)
	 * ------------------------------------------------------------------ */

	var viewer = { item: null, mode: 'none', page: 0, pages: 1, fit: true, scale: 1, tx: 0, ty: 0, dragging: false, sx: 0, sy: 0, pdfDoc: null, renderSeq: 0, renderTask: null, closeTimer: null, locked: false, imgEl: null, standalone: false };

	var OFFICE_EXT = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf'];
	var TEXT_EXT = ['txt', 'csv', 'md', 'markdown', 'json', 'log', 'xml', 'yaml', 'yml', 'ini', 'html', 'css', 'js'];

	function withParam(url, param) { return url + (url.indexOf('?') === -1 ? '?' : '&') + param; }

	function clientKind(ext) {
		if (IMG_EXT.indexOf(ext) !== -1) { return 'image'; }
		if (ext === 'pdf') { return 'pdf'; }
		if (OFFICE_EXT.indexOf(ext) !== -1) { return 'office'; }
		if (TEXT_EXT.indexOf(ext) !== -1) { return 'text'; }
		return 'none';
	}

	function ensureViewer() {
		if ($('#lnfb-viewer-overlay').length) { return; }
		$('body').append(
			'<div class="lnfb-lightbox" id="lnfb-viewer-overlay" hidden>'
			+ '<div class="lnfb-lightbox-bar">'
			+ '<span class="lnfb-lightbox-title" id="lnfb-lightbox-title"></span>'
			+ '<div class="lnfb-lightbox-tools">'
			+ '<span class="lnfb-lb-pagegroup" id="lnfb-lb-pagegroup" hidden>'
			+ '<button type="button" class="lnfb-lb-btn lnfb-lb-prev" aria-label="' + esc(t('prev_page', 'Previous page')) + '"><i class="fas fa-chevron-left"></i></button>'
			+ '<span class="lnfb-lb-count" id="lnfb-lb-count"></span>'
			+ '<button type="button" class="lnfb-lb-btn lnfb-lb-next" aria-label="' + esc(t('next_page', 'Next page')) + '"><i class="fas fa-chevron-right"></i></button>'
			+ '</span>'
			+ '<span class="lnfb-lb-zoomgroup" id="lnfb-lb-zoomgroup" hidden>'
			+ '<button type="button" class="lnfb-lb-btn lnfb-lb-out" aria-label="' + esc(t('zoom_out', 'Zoom out')) + '"><i class="fas fa-magnifying-glass-minus"></i></button>'
			+ '<input type="text" class="lnfb-lb-zoom" id="lnfb-lb-zoom" inputmode="numeric" autocomplete="off" value="100" aria-label="' + esc(t('zoom_in', 'Zoom')) + '" title="' + esc(t('zoom_in', 'Zoom')) + '">'
			+ '<button type="button" class="lnfb-lb-btn lnfb-lb-in" aria-label="' + esc(t('zoom_in', 'Zoom in')) + '"><i class="fas fa-magnifying-glass-plus"></i></button>'
			+ '<button type="button" class="lnfb-lb-btn lnfb-lb-fit" aria-label="' + esc(t('zoom_fit', 'Fit to screen')) + '"><i class="fas fa-expand"></i></button>'
			+ '</span>'
			+ '<a class="lnfb-lb-btn lnfb-lb-dl" hidden><i class="fas fa-download"></i></a>'
			+ '<button type="button" class="lnfb-lb-btn lnfb-lb-close" aria-label="' + esc(t('close', 'Close')) + '"><i class="fas fa-xmark"></i></button>'
			+ '</div>'
			+ '</div>'
			+ '<div class="lnfb-lightbox-stage" id="lnfb-lightbox-stage">'
			+ '<img class="lnfb-lightbox-img" id="lnfb-lightbox-img" alt="" draggable="false" hidden>'
			+ '<canvas class="lnfb-lightbox-canvas" id="lnfb-lightbox-canvas" hidden></canvas>'
			+ '<pre class="lnfb-lightbox-text" id="lnfb-lightbox-text" hidden></pre>'
			+ '<div class="lnfb-lightbox-loading" id="lnfb-lightbox-loading" hidden><i class="fas fa-spinner fa-spin"></i></div>'
			+ '<div class="lnfb-lightbox-nopreview" id="lnfb-lightbox-nopreview" hidden><i class="fas fa-file"></i><p></p></div>'
			+ '</div>'
			+ '</div>'
		);
		bindViewerEvents();
	}

	function bindViewerEvents() {
		var $o = $('#lnfb-viewer-overlay');
		$o.on('click', '.lnfb-lb-close', closeViewer);
		$o.on('click', '.lnfb-lb-prev', function () { if (viewer.page > 0) { viewer.page--; loadViewerPage(); } });
		$o.on('click', '.lnfb-lb-next', function () { if (viewer.page < viewer.pages - 1) { viewer.page++; loadViewerPage(); } });
		$o.on('click', '.lnfb-lb-in', function () { zoomViewer(1.25); });
		$o.on('click', '.lnfb-lb-out', function () { zoomViewer(1 / 1.25); });
		$o.on('click', '.lnfb-lb-fit', function () { viewer.fit = true; viewer.scale = 1; viewer.tx = 0; viewer.ty = 0; refreshViewer(); });
		$o.on('focus', '#lnfb-lb-zoom', function () { $(this).select(); });
		$o.on('keydown', '#lnfb-lb-zoom', function (e) {
			if (e.key === 'Enter') { e.preventDefault(); $(this).blur(); }
			else if (e.key === 'Escape') { e.preventDefault(); updZoomLabel(); $(this).blur(); }
			e.stopPropagation();
		});
		$o.on('blur', '#lnfb-lb-zoom', applyZoomInput);
		$o.on('mousedown', '.lnfb-lightbox-img, .lnfb-lightbox-canvas', function (e) {
			if (viewer.fit) { return; }
			viewer.dragging = true;
			viewer.sx = e.clientX - viewer.tx;
			viewer.sy = e.clientY - viewer.ty;
			$('body').addClass('lnfb-lb-dragging');
			e.preventDefault();
		});
		$(document).on('mousemove', function (e) {
			if (!viewer.dragging) { return; }
			viewer.tx = e.clientX - viewer.sx;
			viewer.ty = e.clientY - viewer.sy;
			applyViewerTransform();
		});
		$(document).on('mouseup', function () {
			if (viewer.dragging) { viewer.dragging = false; $('body').removeClass('lnfb-lb-dragging'); }
		});
		$(document).on('keydown', function (e) {
			if (!$o.hasClass('is-open')) { return; }
			if ($(e.target).is('input, textarea')) { return; }
			var active = !$('#lnfb-lightbox-img').prop('hidden') || !$('#lnfb-lightbox-canvas').prop('hidden');
			if (e.key === 'Escape') { closeViewer(); }
			else if (active && e.key === 'ArrowLeft' && viewer.page > 0) { viewer.page--; loadViewerPage(); }
			else if (active && e.key === 'ArrowRight' && viewer.page < viewer.pages - 1) { viewer.page++; loadViewerPage(); }
			else if (active && (e.key === '+' || e.key === '=')) { zoomViewer(1.25); }
			else if (active && e.key === '-') { zoomViewer(1 / 1.25); }
			else if (active && e.key === '0') { viewer.fit = true; viewer.scale = 1; viewer.tx = 0; viewer.ty = 0; refreshViewer(); }
		});
		// Clicking the backdrop no longer closes the viewer — only the close
		// button (or Esc) does, so a stray click outside the image is harmless.
		// Deter saving locked content: block the context menu and drag-out on
		// the rendered image/canvas (best-effort — not a real barrier).
		$o.on('contextmenu', '.lnfb-lightbox-img, .lnfb-lightbox-canvas', function (e) {
			if (viewer.locked) { e.preventDefault(); }
		});
		$o.on('dragstart', '.lnfb-lightbox-img, .lnfb-lightbox-canvas', function (e) {
			if (viewer.locked) { e.preventDefault(); }
		});
	}

	function openViewer(item) {
		ensureViewer();
		var ext = String(item.filetype || extOf(item.name) || '').toLowerCase();
		if (viewer.closeTimer) { window.clearTimeout(viewer.closeTimer); viewer.closeTimer = null; }
		if (viewer.pdfDoc) { try { viewer.pdfDoc.destroy(); } catch (err) {} viewer.pdfDoc = null; }
		viewer.item = item;
		viewer.mode = 'none';
		viewer.page = 0;
		viewer.pages = 1;
		viewer.fit = true; viewer.scale = 1; viewer.tx = 0; viewer.ty = 0;

		$('#lnfb-lightbox-title').text(item.name);
		var $dl = $('#lnfb-viewer-overlay .lnfb-lb-dl');
		if (item.allow_download && item.url) {
			$dl.attr('href', withParam(item.url, 'dl=1')).attr('download', item.name).prop('hidden', false);
		} else {
			$dl.prop('hidden', true);
		}

		// When the download is restricted, deter "Save image as" / drag-out.
		viewer.locked = item.type === 'file' && Number(item.allow_download) === 0;
		$('#lnfb-viewer-overlay').toggleClass('is-locked', viewer.locked);

		resetViewerStage();
		applyViewerTransform();
		updViewerNav();

		var $o = $('#lnfb-viewer-overlay');
		$o.prop('hidden', false);
		$('html, body').addClass('lnfb-viewer-open');
		window.requestAnimationFrame(function () { $o.addClass('is-open'); });

		renderViewerPreview(item, ext);
	}

	function resetViewerStage() {
		// Detach handlers first: clearing the src fires a spurious `error` that
		// would otherwise trip the previous file's error handler.
		$('#lnfb-lightbox-img').off('load error').attr('src', '').prop('hidden', true);
		$('#lnfb-lightbox-canvas').prop('hidden', true);
		$('#lnfb-lightbox-text').prop('hidden', true).text('');
		$('#lnfb-lightbox-nopreview').prop('hidden', true);
		$('#lnfb-lb-zoomgroup').prop('hidden', true);
		$('#lnfb-lb-pagegroup').prop('hidden', true);
		$('#lnfb-lb-zoom').val(100);
		viewer.imgEl = null;
		showViewerLoading();
	}

	function showViewerLoading() { $('#lnfb-lightbox-loading').prop('hidden', false); }
	function hideViewerLoading() { $('#lnfb-lightbox-loading').prop('hidden', true); }
	function bindViewerImg($img) {
		$img.off('load error')
			.on('load', function () {
				hideViewerLoading();
				$('#lnfb-lightbox-nopreview').prop('hidden', true);
			})
			.on('error', function () { showViewerNopreview(''); });
	}

	function renderViewerPreview(item, ext) {
		if (!item.url) { showViewerNopreview(ext); return; }
		var kind = clientKind(ext);

		if (kind === 'image') {
			showViewerImage(withParam(item.url, 'mode=image'));
			return;
		}
		if (kind === 'none') { showViewerNopreview(ext); return; }

		// pdf / office / text — ask the server what it can render.
		$.ajax({ url: withParam(item.url, 'mode=info'), dataType: 'json' }).then(function (res) {
			if (viewer.item !== item) { return; }
			var data = res && res.success ? res.data : {};
			var serverKind = data.kind || kind;
			if (!data.viewable) { showViewerNopreview(ext); return; }
			if (serverKind === 'pdf' || serverKind === 'office') {
				loadPdfViewer(item, ext);
			} else if (serverKind === 'text') {
				showViewerText(item, ext);
			} else if (serverKind === 'image') {
				showViewerImage(withParam(item.url, 'mode=image'));
			} else {
				showViewerNopreview(ext);
			}
		}, function () { if (viewer.item === item) { showViewerNopreview(ext); } });
	}

	/* PDF rendering via PDF.js: rasterised on a canvas at the exact zoom scale,
	   so text stays crisp at any magnification (no fixed-DPI bitmaps). */
	function loadPdfViewer(item, ext) {
		var lib = window.pdfjsLib;
		if (!lib || !lib.getDocument) { showViewerNopreview(ext); return; }
		if (L.pdfjs_worker) { lib.GlobalWorkerOptions.workerSrc = L.pdfjs_worker; }
		viewer.mode = 'pdf';
		lib.getDocument({ url: withParam(item.url, 'mode=pdf') }).promise.then(function (doc) {
			viewer.pdfDoc = doc;
			viewer.pages = Math.max(1, doc.numPages);
			viewer.page = 0;
			viewer.fit = true; viewer.scale = 1; viewer.tx = 0; viewer.ty = 0;
			$('#lnfb-lb-zoomgroup').prop('hidden', false);
			updViewerNav();
			renderPdfPage();
		}, function () { showViewerNopreview(ext); });
	}

	function pdfPageElement() { return document.getElementById('lnfb-lightbox-canvas'); }

	function renderPdfPage() {
		if (!viewer.pdfDoc) { return; }
		var seq = ++viewer.renderSeq;
		if (viewer.renderTask) { try { viewer.renderTask.cancel(); } catch (err) {} viewer.renderTask = null; }
		showViewerLoading();
		viewer.pdfDoc.getPage(viewer.page + 1).then(function (page) {
			if (seq !== viewer.renderSeq) { return; }
			var base = page.getViewport({ scale: 1 });
			var stage = document.getElementById('lnfb-lightbox-stage');
			var availW = Math.max(160, stage.clientWidth - 40);
			var availH = Math.max(160, stage.clientHeight - 40);
			var fitScale = Math.min(availW / base.width, availH / base.height);
			var scale = fitScale * (viewer.fit ? 1 : viewer.scale);
			var dpr = window.devicePixelRatio || 1;
			var vp = page.getViewport({ scale: scale * dpr });
			var canvas = pdfPageElement();
			canvas.width = Math.floor(vp.width);
			canvas.height = Math.floor(vp.height);
			canvas.style.width = Math.floor(vp.width / dpr) + 'px';
			canvas.style.height = Math.floor(vp.height / dpr) + 'px';
			canvas.hidden = false;
			$('#lnfb-lightbox-img').prop('hidden', true);
			applyViewerTransform();
			var ctx = canvas.getContext('2d');
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			var task = page.render({ canvasContext: ctx, viewport: vp });
			viewer.renderTask = task;
			task.promise.then(function () {
				if (seq !== viewer.renderSeq) { return; }
				hideViewerLoading();
				$('#lnfb-lightbox-nopreview').prop('hidden', true);
			}, function (err) {
				if (err && err.name === 'RenderingCancelledException') { return; }
				if (seq === viewer.renderSeq) { showViewerNopreview(''); }
			});
		}, function () { if (seq === viewer.renderSeq) { showViewerNopreview(''); } });
	}

	function loadViewerPage() {
		if (viewer.mode === 'pdf') { renderPdfPage(); updViewerNav(); return; }
		if (viewer.mode === 'imagecanvas') { renderImageCanvas(); updViewerNav(); return; }
		var $img = $('#lnfb-lightbox-img');
		bindViewerImg($img);
		showViewerLoading();
		$img.prop('hidden', false).attr('src', withParam(viewer.item.url, 'mode=image'));
		updViewerNav();
	}

	function updViewerNav() {
		var $o = $('#lnfb-viewer-overlay');
		$o.find('#lnfb-lb-count').text((viewer.page + 1) + ' / ' + viewer.pages);
		$o.find('.lnfb-lb-prev').prop('disabled', viewer.page <= 0);
		$o.find('.lnfb-lb-next').prop('disabled', viewer.page >= viewer.pages - 1);
		$('#lnfb-lb-pagegroup').prop('hidden', viewer.pages <= 1);
	}

	function showViewerImage(src) {
		viewer.mode = 'image';
		$('#lnfb-lb-zoomgroup').prop('hidden', false);
		// Locked images are drawn onto a canvas (like PDFs): no image element in
		// the DOM, so no "Save image as" and no draggable source. Quality is kept
		// (full resolution scaled to the zoom). Best-effort deterrence only.
		if (viewer.locked) {
			viewer.mode = 'imagecanvas';
			showViewerLoading();
			var seq = ++viewer.renderSeq;
			var probe = new Image();
			probe.onload = function () {
				if (seq !== viewer.renderSeq || viewer.mode !== 'imagecanvas') { return; }
				viewer.imgEl = probe;
				renderImageCanvas();
			};
			probe.onerror = function () {
				if (seq !== viewer.renderSeq) { return; }
				showViewerNopreview('');
			};
			probe.src = src;
			return;
		}
		var $img = $('#lnfb-lightbox-img');
		bindViewerImg($img);
		showViewerLoading();
		$img.prop('hidden', false).attr('src', src);
	}

	function renderImageCanvas() {
		var img = viewer.imgEl;
		if (!img || !img.naturalWidth) { return; }
		var stage = document.getElementById('lnfb-lightbox-stage');
		var availW = Math.max(160, stage.clientWidth - 40);
		var availH = Math.max(160, stage.clientHeight - 40);
		var fitScale = Math.min(availW / img.naturalWidth, availH / img.naturalHeight);
		var scale = fitScale * (viewer.fit ? 1 : viewer.scale);
		var dpr = window.devicePixelRatio || 1;
		var canvas = pdfPageElement();
		canvas.width = Math.max(1, Math.floor(img.naturalWidth * scale * dpr));
		canvas.height = Math.max(1, Math.floor(img.naturalHeight * scale * dpr));
		canvas.style.width = Math.floor(img.naturalWidth * scale) + 'px';
		canvas.style.height = Math.floor(img.naturalHeight * scale) + 'px';
		canvas.hidden = false;
		$('#lnfb-lightbox-img').prop('hidden', true);
		applyViewerTransform();
		var ctx = canvas.getContext('2d');
		ctx.clearRect(0, 0, canvas.width, canvas.height);
		ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
		hideViewerLoading();
		$('#lnfb-lightbox-nopreview').prop('hidden', true);
	}

	function showViewerText(item, ext) {
		viewer.mode = 'text';
		$.ajax({ url: withParam(item.url, 'mode=text'), dataType: 'text' }).then(function (text) {
			hideViewerLoading();
			$('#lnfb-lightbox-text').text(String(text || '')).prop('hidden', false);
		}, function () { showViewerNopreview(ext); });
	}

	function showViewerNopreview(ext) {
		hideViewerLoading();
		$('#lnfb-lightbox-img').prop('hidden', true);
		$('#lnfb-lightbox-canvas').prop('hidden', true);
		$('#lnfb-lightbox-text').prop('hidden', true);
		var $np = $('#lnfb-lightbox-nopreview');
		$np.find('i').attr('class', ext ? typeIcon(ext) : 'fas fa-file');
		$np.find('p').text(t('preview_unavailable', 'Preview unavailable'));
		$np.prop('hidden', false);
		$('#lnfb-lb-zoomgroup').prop('hidden', true);
		$('#lnfb-lb-pagegroup').prop('hidden', true);
	}

	function refreshViewer() {
		if (viewer.mode === 'pdf') { renderPdfPage(); }
		else if (viewer.mode === 'imagecanvas') { renderImageCanvas(); }
		else { applyViewerTransform(); }
		$('#lnfb-lb-zoom').val(viewer.fit ? 100 : Math.round(viewer.scale * 100));
	}

	/* Editable zoom percentage: type a number, press Enter/blur to apply. */
	function applyZoomInput() {
		var $i = $('#lnfb-lb-zoom');
		var pct = parseInt(String($i.val()).replace(/[^\d]/g, ''), 10);
		if (isNaN(pct) || pct <= 0) { updZoomLabel(); return; }
		pct = Math.max(10, Math.min(800, pct));
		viewer.fit = false;
		viewer.scale = pct / 100;
		viewer.tx = 0; viewer.ty = 0;
		refreshViewer();
	}

	function updZoomLabel() {
		if (viewer.mode === 'pdf' || viewer.mode === 'image' || viewer.mode === 'imagecanvas') {
			$('#lnfb-lb-zoom').val(viewer.fit ? 100 : Math.round(viewer.scale * 100));
		}
	}

	function zoomViewer(factor) {
		viewer.fit = false;
		viewer.scale = Math.min(8, Math.max(0.2, viewer.scale * factor));
		refreshViewer();
	}

	function applyViewerTransform() {
		// Canvas content (PDF pages / locked images) is re-rendered at scale
		// (crisp); only pan via translate.
		if (viewer.mode === 'pdf' || viewer.mode === 'imagecanvas') {
			var canvas = pdfPageElement();
			if (canvas) {
				canvas.style.transform = (viewer.tx || viewer.ty)
					? 'translate(' + viewer.tx + 'px,' + viewer.ty + 'px)'
					: 'none';
			}
			return;
		}
		var $img = $('#lnfb-lightbox-img');
		if (viewer.fit) {
			$img.css('transform', 'none');
			return;
		}
		$img.css('transform', 'translate(' + viewer.tx + 'px,' + viewer.ty + 'px) scale(' + viewer.scale + ')');
	}

	function closeViewer() {
		if (viewer.standalone) { return; }
		var $o = $('#lnfb-viewer-overlay');
		$o.removeClass('is-open');
		$('html, body').removeClass('lnfb-viewer-open lnfb-lb-dragging');
		if (viewer.renderTask) { try { viewer.renderTask.cancel(); } catch (err) {} viewer.renderTask = null; }
		viewer.renderSeq++;
		if (viewer.closeTimer) { window.clearTimeout(viewer.closeTimer); }
		viewer.closeTimer = window.setTimeout(function () {
			viewer.closeTimer = null;
			$o.prop('hidden', true);
			$('#lnfb-lightbox-img').attr('src', '');
			$('#lnfb-lightbox-canvas').prop('hidden', true);
		}, 200);
		if (viewer.pdfDoc) { try { viewer.pdfDoc.destroy(); } catch (err) {} viewer.pdfDoc = null; }
		viewer.imgEl = null;
		viewer.mode = 'none';
		viewer.item = null;
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

		/* Standalone viewer page: open the lightbox immediately and stop. There is
		   no page behind it, so the close button/Esc are disabled. */
		var $viewerPage = $('#lnfb-viewer');
		if ($viewerPage.length && !$('.linknacional-filebrowser-public').length) {
			viewer.standalone = true;
			$('body').addClass('lnfb-viewer-standalone');
			openViewer({
				type: 'file',
				id: 0,
				name: String($viewerPage.attr('data-name') || ''),
				url: String($viewerPage.attr('data-url') || ''),
				filetype: String($viewerPage.attr('data-filetype') || ''),
				size: Number($viewerPage.attr('data-size')) || 0,
				allow_download: Number($viewerPage.attr('data-allow-download')) === 0 ? 0 : 1
			});
			return;
		}

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
