=== Link Nacional File Browser ===
Contributors: linknacional
Tags: file manager, documents, upload, folders, download
Requires at least: 6.0
Tested up to: 7.1
Stable tag: 1.1.0
Requires PHP: 8.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A complete file browser plugin with hierarchical folder system and search interface for WordPress.

== Description ==

Link Nacional File Browser lets you create and manage a complete hierarchical file system within WordPress. It provides an admin interface to organize files into folders and a shortcode to display the file browser on the frontend.

**Key Features:**

* **Hierarchical Folder System** — Create nested folders organized like a file explorer
* **Multiple File Uploads** — Support for PDF, Word, Excel, PowerPoint, images, and more
* **Search Interface** — Quick file and folder search on the frontend
* **Collections** — Favorites, Recent, and Trash to organize what matters
* **Copy & Move** — Duplicate or relocate files and whole folder trees
* **Bulk Actions** — Select several items to act on them at once
* **Shortcode Scoping** — Start from a folder and hide the ones you don't need
* **Responsive Design** — Grid or list layout, adaptable to mobile devices
* **Breadcrumb Navigation** — Intuitive folder browsing
* **Flexible Shortcode** — Multiple configuration options

**Supported File Types:**
* Documents: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT
* Images: JPG, JPEG, PNG, GIF, WEBP

== Installation ==

1. Upload the plugin to the `/wp-content/plugins/linknacional-file-browser/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. The plugin will automatically create the required database tables
4. Go to 'File Browser' in the admin menu to start organizing your files

== Frequently Asked Questions ==

= How do I use the shortcode? =

The plugin includes an instructions panel in the admin area with practical examples and copy buttons.

Go to 'File Browser' in the admin menu for complete usage instructions.

**Basic usage:**
`[linkn_filebrowser]`

**Specific folder:**
`[linkn_filebrowser folder_id="1"]`

**Different layout:**
`[linkn_filebrowser layout="list"]`

**Available layouts:** grid (default) and list

= Where do I find usage instructions? =

The plugin admin panel includes an instructions block with:
* Shortcode examples
* Copy shortcode buttons
* Usage tips
* Feature guide

= How do I organize files into folders? =

1. Go to 'File Browser' in the admin menu
2. Read the instructions at the top of the page
3. Click 'Create Folder' to create a new folder
4. Navigate to the desired folder and click 'Upload Files'
5. Select multiple files for simultaneous upload

= How can users access the files? =

Users can:
* Navigate through folders by clicking on them
* Use the search bar to find specific files
* Download files by clicking on them
* Switch between grid and list views

= Is the plugin responsive? =

Yes! The plugin is built with responsive design and automatically adapts to tablets and smartphones.

== Screenshots ==

1. Admin interface — Main configuration page with folder tree and file grid
2. Admin interface — Selecting and managing files in a folder
3. Frontend — File browser component displayed via shortcode
4. Frontend — Navigating and previewing files in grid layout
5. Frontend — File browser in list layout mode
6. Frontend — Mobile responsive view
7. Admin interface — How to use the shortcode instructions panel

== Changelog ==

= 1.1.0 - 2026-09-24 =
* Redesigned admin and frontend interfaces: sidebar collections, workspace toolbar, detail drawer, toasts, and a fully refreshed look.
* Favorites: mark files and folders as favorites and browse them in a dedicated collection (admin and frontend).
* Recent: a collection listing the most recently added files (admin and frontend).
* Trash: soft-delete items with restore, permanent delete, and empty trash (admin).
* Copy: duplicate files and whole folder trees.
* Move: move files and folders via drag & drop or the "Move to…" modal, with folder-cycle protection.
* Bulk actions: select multiple items to delete them at once.
* Uploads: drag & drop with a multi-file queue and progress; added WEBP support.
* Shortcode scoping: new `root` and `exclude` attributes to start from a folder and hide folders (case-insensitive).
* Database upgrade runs automatically on update (favorites/trash columns) without reactivating the plugin.
* Fixed the collection breadcrumb — it now shows only the selected collection, with its icon.

= 1.0.2 - 2026-08-03 =
* Bug: LiteSpeed adaptation loading.

= 1.0.1 - 2026-08-03 =
* Bug: Font Awesome script loading.

= 1.0.0 - 2026-07-27 =
* Initial release
* Complete hierarchical folder system
* Multiple file uploads
* Search interface
* Flexible shortcode with multiple options
* Responsive design
* Support for multiple file types

== Technical Details ==

**Database Structure:**
* `{prefix}linknacional_filebrowser_folders` — Stores folder information
* `{prefix}linknacional_filebrowser_files` — Stores file information

**Upload Directory:**
Files are stored in `/wp-content/uploads/linknacional-filebrowser/`

**Available Hooks:**
* `linknacional_filebrowser_before_upload` — Fires before a file upload
* `linknacional_filebrowser_after_upload` — Fires after a file upload
* `linknacional_filebrowser_before_delete` — Fires before a file deletion

== Support ==

For technical support, contact us:
* Email: contato@linknacional.com
* Website: https://www.linknacional.com.br

== License ==

This plugin is licensed under GPL v2 or later.

== Upgrade Notice ==

= 1.0.0 - 2026-07-27 =
* Documentation update and WordPress coding standards compliance. No breaking changes.
