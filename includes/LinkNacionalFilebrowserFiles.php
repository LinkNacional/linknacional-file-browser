<?php

namespace LinkNacional\Filebrowser\Includes;

/**
 * Shared helpers for delivering plugin files through a controlled endpoint.
 *
 * Files live outside the webroot and are only reachable via the plugin's proxy
 * endpoint. The endpoint renders in-page previews (PDF natively, Office via a
 * cached LibreOffice→PDF conversion, text as-is) and restricts the explicit
 * download to the per-file `allow_download` flag.
 */
class LinkNacionalFilebrowserFiles {

	/**
	 * Nonce action used by the file-serving endpoint.
	 */
	const NONCE_ACTION = 'linknacional_filebrowser_serve_nonce';

	/**
	 * Option holding the resolved storage directory.
	 */
	const STORAGE_OPTION = 'linknacional_filebrowser_storage_path';

	/**
	 * Lifetime of a share token, in seconds.
	 */
	const TOKEN_TTL = 3600;

	/**
	 * Cron hook that purges expired/revoked share tokens.
	 */
	const CLEANUP_HOOK = 'linknacional_filebrowser_purge_tokens';

	/**
	 * Directory that holds the physical files, kept outside the webroot.
	 *
	 * @return string
	 */
	public static function storage_dir() {
		$stored = \get_option( self::STORAGE_OPTION );
		if ( is_string( $stored ) && '' !== $stored ) {
			return untrailingslashit( $stored );
		}
		return untrailingslashit( self::default_storage_dir() );
	}

	/**
	 * Preferred storage location: a sibling of the WordPress install, so it is
	 * not reachable over HTTP.
	 *
	 * @return string
	 */
	public static function default_storage_dir() {
		$dir = \apply_filters( 'linknacional_filebrowser_storage_dir', '' );
		if ( is_string( $dir ) && '' !== $dir ) {
			return $dir;
		}
		return \dirname( untrailingslashit( ABSPATH ) ) . '/linknacional-filebrowser-files';
	}

	/**
	 * Directory holding the cached preview images.
	 *
	 * @return string
	 */
	public static function cache_dir() {
		return self::storage_dir() . '/previews';
	}

	/**
	 * Recursively create a directory when missing.
	 *
	 * @param string $dir
	 * @return bool
	 */
	public static function ensure_dir( $dir ) {
		if ( \is_dir( $dir ) ) {
			return true;
		}
		return wp_mkdir_p( $dir );
	}

	/**
	 * Whether the given path lives inside the storage directory.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function is_within_storage_dir( $path ) {
		if ( ! $path ) {
			return false;
		}
		$real = realpath( $path );
		$base = realpath( self::storage_dir() );
		if ( false === $real || false === $base ) {
			return false;
		}
		$real = wp_normalize_path( $real );
		$base = wp_normalize_path( $base );
		return $real === $base || 0 === strpos( $real, $base . '/' );
	}

	/**
	 * Random, unguessable stored file name that keeps the original extension.
	 *
	 * @param string $extension Original file extension (with or without dot).
	 * @param string $dir       Optional directory to guarantee uniqueness against.
	 * @return string
	 */
	public static function hashed_filename( $extension, $dir = '' ) {
		$extension = strtolower( ltrim( (string) $extension, '.' ) );
		$name      = wp_generate_password( 24, false, false );
		$name      = $extension ? $name . '.' . $extension : $name;
		if ( $dir ) {
			$name = wp_unique_filename( $dir, $name );
		}
		return $name;
	}

	/**
	 * Rewrite each file row's URL to the proxy URL, expose the download flag and
	 * drop the absolute server path from the payload.
	 *
	 * @param array $files File rows.
	 * @return array
	 */
	public static function prepare_rows( $files ) {
		if ( ! is_array( $files ) ) {
			return $files;
		}
		$nonce = wp_create_nonce( self::NONCE_ACTION );
		$base  = admin_url( 'admin-ajax.php' );
		foreach ( $files as $file ) {
			if ( ! isset( $file->id ) ) {
				continue;
			}
			$file->file_url       = add_query_arg(
				array(
					'action'  => 'linknacional_serve_file',
					'file_id' => (int) $file->id,
					'nonce'   => $nonce,
				),
				$base
			);
			$file->allow_download = isset( $file->allow_download ) ? (int) $file->allow_download : 1;
			unset( $file->file_path );
		}
		return $files;
	}

	/* ------------------------------------------------------------------ *
	 *  Temporary share tokens (presigned-style links)
	 * ------------------------------------------------------------------ */

	/**
	 * Name of the table holding issued share tokens.
	 *
	 * @return string
	 */
	private static function tokens_table() {
		global $wpdb;
		return $wpdb->prefix . 'linknacional_filebrowser_tokens';
	}

	/**
	 * Issue a short-lived token for a file. Only the hash is stored; the raw
	 * token is returned once and lives solely in the generated link.
	 *
	 * @param int $file_id
	 * @param int $ttl     Lifetime in seconds.
	 * @return string Raw token, or '' on failure.
	 */
	public static function create_share_token( $file_id, $ttl = self::TOKEN_TTL ) {
		$file_id = (int) $file_id;
		if ( $file_id <= 0 ) {
			return '';
		}
		global $wpdb;
		// Purge at most once per hour on the hot path; a daily cron also runs it.
		if ( false === get_transient( 'linknacional_filebrowser_tokens_purged' ) ) {
			self::purge_expired_tokens();
			set_transient( 'linknacional_filebrowser_tokens_purged', 1, HOUR_IN_SECONDS );
		}

		$raw     = bin2hex( random_bytes( 32 ) );
		$hash    = hash( 'sha256', $raw );
		$expires = gmdate( 'Y-m-d H:i:s', time() + max( 60, (int) $ttl ) );

		$ok = $wpdb->insert(
			self::tokens_table(),
			array(
				'file_id'    => $file_id,
				'token_hash' => $hash,
				'expires_at' => $expires,
				'created_by' => get_current_user_id(),
				'revoked'    => 0,
			),
			array( '%d', '%s', '%s', '%d', '%d' )
		);

		return $ok ? $raw : '';
	}

	/**
	 * Validate a raw token. Returns the file id it grants access to, or 0.
	 *
	 * @param string $raw
	 * @return int
	 */
	public static function validate_share_token( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return 0;
		}
		global $wpdb;
		$hash = hash( 'sha256', $raw );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT file_id, expires_at FROM ' . self::tokens_table() . ' WHERE token_hash = %s AND revoked = 0 LIMIT 1',
			$hash
		) );
		if ( ! $row ) {
			return 0;
		}
		if ( strtotime( $row->expires_at . ' UTC' ) < time() ) {
			return 0;
		}
		return (int) $row->file_id;
	}

	/**
	 * Drop expired and revoked tokens so the table only holds usable rows.
	 *
	 * @return void
	 */
	public static function purge_expired_tokens() {
		global $wpdb;
		$now = gmdate( 'Y-m-d H:i:s' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::tokens_table() . ' WHERE expires_at < %s OR revoked = 1', $now ) );
	}

	/**
	 * Schedule the daily token-cleanup cron (idempotent).
	 *
	 * @return void
	 */
	public static function schedule_cleanup() {
		if ( ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Unschedule the token-cleanup cron.
	 *
	 * @return void
	 */
	public static function clear_cleanup() {
		wp_clear_scheduled_hook( self::CLEANUP_HOOK );
	}

	/**
	 * Proxy URL for a file using a raw share token (outer visitors / AI).
	 *
	 * @param int    $file_id
	 * @param string $token
	 * @return string
	 */
	public static function token_serve_url( $file_id, $token ) {
		return add_query_arg(
			array(
				'action'  => 'linknacional_serve_file',
				'file_id' => (int) $file_id,
				'token'   => $token,
			),
			admin_url( 'admin-ajax.php' )
		);
	}

	/**
	 * Public viewer page URL that renders a file (or its preview) via a token.
	 *
	 * @param int    $file_id
	 * @param string $token
	 * @return string
	 */
	public static function viewer_url( $file_id, $token ) {
		return add_query_arg(
			array(
				'linknacional_viewer' => (int) $file_id,
				'token'               => $token,
			),
			home_url( '/' )
		);
	}

	/**
	 * Classify a file into a preview kind: image | pdf | office | text | none.
	 *
	 * @param string $ext Extension (with or without dot).
	 * @return string
	 */
	public static function preview_kind( $ext ) {
		$ext = strtolower( ltrim( (string) $ext, '.' ) );
		if ( in_array( $ext, array( 'jpg', 'jpeg', 'jpe', 'png', 'gif', 'webp' ), true ) ) {
			return 'image';
		}
		if ( 'pdf' === $ext ) {
			return 'pdf';
		}
		if ( self::is_office( $ext ) ) {
			return 'office';
		}
		if ( self::is_text( $ext ) ) {
			return 'text';
		}
		return 'none';
	}

	/**
	 * Whether the extension is an Office document we can convert to PDF.
	 *
	 * @param string $ext
	 * @return bool
	 */
	public static function is_office( $ext ) {
		$ext = strtolower( ltrim( (string) $ext, '.' ) );
		return in_array( $ext, array( 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf' ), true );
	}

	/**
	 * Whether the extension is a plain-text file we can show as-is.
	 *
	 * @param string $ext
	 * @return bool
	 */
	public static function is_text( $ext ) {
		$ext = strtolower( ltrim( (string) $ext, '.' ) );
		return in_array( $ext, array( 'txt', 'csv', 'md', 'markdown', 'json', 'log', 'xml', 'yaml', 'yml', 'ini', 'html', 'css', 'js' ), true );
	}

	/**
	 * Path to the LibreOffice binary, or '' when none is available.
	 *
	 * @return string
	 */
	public static function soffice_binary() {
		static $found = null;
		if ( null !== $found ) {
			return $found;
		}
		$found = '';
		$candidates = (array) apply_filters(
			'linknacional_filebrowser_soffice_bins',
			array( 'soffice', 'libreoffice', '/usr/bin/soffice', '/usr/bin/libreoffice', '/usr/local/bin/soffice', '/opt/libreoffice/program/soffice' )
		);
		foreach ( $candidates as $candidate ) {
			$resolved = self::resolve_binary( $candidate );
			if ( '' !== $resolved ) {
				$found = $resolved;
				break;
			}
		}
		return $found;
	}


	/**
	 * Shell environment prefix for external binaries.
	 *
	 * A normal PATH (php-fpm pools often expose only a minimal one) and an empty
	 * LD_LIBRARY_PATH — PHP hosts like Local set it to bundled libs whose
	 * libstdc++ is older than Ghostscript/LibreOffice need.
	 *
	 * @return string
	 */
	private static function shell_env() {
		$sys = (string) apply_filters( 'linknacional_filebrowser_exec_path', '/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin' );
		return 'PATH=' . escapeshellarg( $sys . PATH_SEPARATOR . (string) getenv( 'PATH' ) )
			. ' LD_LIBRARY_PATH=' . escapeshellarg( '' );
	}

	/**
	 * A scratch directory guaranteed to have no spaces in its path.
	 *
	 * LibreOffice hangs when its profile/output paths contain spaces (common in
	 * dev hosts like "Local Sites"); the system temp dir avoids that.
	 *
	 * @return string
	 */
	private static function work_dir() {
		$candidates = array();
		if ( \function_exists( 'get_temp_dir' ) ) {
			$candidates[] = \get_temp_dir();
		}
		$candidates[] = \sys_get_temp_dir();
		$candidates[] = '/tmp';
		foreach ( $candidates as $candidate ) {
			$candidate = untrailingslashit( (string) $candidate );
			if ( '' !== $candidate && false === strpos( $candidate, ' ' ) && ( \is_dir( $candidate ) || wp_mkdir_p( $candidate ) ) && is_writable( $candidate ) ) {
				return $candidate;
			}
		}
		return '';
	}

	/**
	 * Convert an Office file to PDF (cached) using LibreOffice headless.
	 *
	 * @param string $path Absolute source path.
	 * @param string $ext  Source extension.
	 * @return string|null Cached PDF path, or null when unavailable.
	 */
	public static function converted_pdf( $path, $ext ) {
		if ( ! self::is_office( $ext ) || ! $path || ! \file_exists( $path ) || ! \function_exists( 'exec' ) ) {
			return null;
		}
		$cache = self::cache_dir();
		if ( ! self::ensure_dir( $cache ) ) {
			return null;
		}
		$key = self::preview_key( $path );
		$out = $cache . '/' . $key . '.pdf';
		if ( \file_exists( $out ) ) {
			return $out;
		}
		$bin = self::soffice_binary();
		if ( '' === $bin ) {
			return null;
		}

		// Serialise conversions of the same file: concurrent viewers wait for the
		// first run instead of each spawning their own headless LibreOffice.
		$lock = @fopen( $cache . '/' . $key . '.lock', 'c' );
		if ( ! $lock ) {
			return null;
		}
		@flock( $lock, LOCK_EX );

		$result = null;
		if ( \file_exists( $out ) ) {
			$result = $out; // Another request finished while we waited.
		} else {
			self::sweep_conversion_dirs( $cache );
			$work = self::work_dir();
			$tmp  = '' !== $work ? $work . '/lnfb-' . wp_generate_password( 12, false, false ) : '';
			if ( '' !== $tmp && wp_mkdir_p( $tmp ) ) {
				@chmod( $tmp, 0700 );
				$profile = $tmp . '/profile';
				$home    = $tmp . '/home';
				$xdg     = $tmp . '/cache';
				wp_mkdir_p( $home );
				wp_mkdir_p( $xdg );
				$timeout = (int) apply_filters( 'linknacional_filebrowser_soffice_timeout', 60 );

				// LibreOffice needs a writable HOME / cache dir (fontconfig fails
				// otherwise) and a normal PATH — php-fpm pools often expose only a
				// minimal PATH (e.g. Local sets it to the Ghostscript bin alone),
				// which breaks the `/usr/bin/soffice` shell wrapper. Use inline
				// VAR=value assignments (no `env`, which may itself be off-PATH).
				$env = self::shell_env()
					. ' HOME=' . escapeshellarg( $home )
					. ' XDG_CACHE_HOME=' . escapeshellarg( $xdg );

				$soffice_args = '--headless --norestore --invisible --nolockcheck --nodefault --nofirststartwizard'
					. ' -env:UserInstallation=' . escapeshellarg( 'file://' . $profile )
					. ' --convert-to pdf --outdir ' . escapeshellarg( $tmp )
					. ' ' . escapeshellarg( $path );

				$timeout_bin = self::resolve_binary( 'timeout' );
				if ( '' === $timeout_bin && is_executable( '/usr/bin/timeout' ) ) {
					$timeout_bin = '/usr/bin/timeout';
				}
				// VAR=value assignments must precede the command (and timeout, which
				// does not itself understand inline assignments).
				$runner = ( '' !== $timeout_bin )
					? escapeshellarg( $timeout_bin ) . ' ' . $timeout . ' ' . escapeshellarg( $bin )
					: escapeshellarg( $bin );
				$cmd = $env . ' ' . $runner . ' ' . $soffice_args;

				$output = array();
				$code   = 1;
				@exec( $cmd . ' 2>&1', $output, $code );

				$produced = $tmp . '/' . pathinfo( $path, PATHINFO_FILENAME ) . '.pdf';
				if ( \file_exists( $produced ) && @rename( $produced, $out ) ) {
					$result = $out;
				}
				self::rrmdir( $tmp );
			}
		}

		@flock( $lock, LOCK_UN );
		@fclose( $lock );
		return $result;
	}

	/**
	 * Cache key for a source file's converted preview.
	 *
	 * The key folds in mtime and size so an edited file maps to a fresh entry
	 * instead of serving a stale conversion.
	 *
	 * @param string $path Absolute source path.
	 * @return string
	 */
	public static function preview_key( $path ) {
		$mtime = @filemtime( $path );
		$size  = @filesize( $path );
		return md5( wp_normalize_path( $path ) . '|' . $mtime . '|' . $size );
	}

	/**
	 * Drop the cached preview (and its lock) for a source file.
	 *
	 * Must be called while the source still exists — the key depends on its
	 * mtime/size — i.e. before the physical file is removed.
	 *
	 * @param string $path Absolute source path.
	 * @return void
	 */
	public static function delete_preview( $path ) {
		if ( ! $path ) {
			return;
		}
		$cache = self::cache_dir();
		$key   = self::preview_key( $path );
		wp_delete_file_from_directory( $cache . '/' . $key . '.pdf', $cache );
		wp_delete_file_from_directory( $cache . '/' . $key . '.lock', $cache );
	}

	/**
	 * Delete cached previews whose source file no longer exists (or changed).
	 *
	 * The cache key cannot be reversed, so instead keep only the entries that
	 * still match a live row in the files table and discard the rest.
	 *
	 * @return void
	 */
	public static function sweep_orphan_previews() {
		global $wpdb;
		$cache = self::cache_dir();
		if ( ! \is_dir( $cache ) ) {
			return;
		}
		$live  = array();
		$table = $wpdb->prefix . 'linknacional_filebrowser_files';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$paths = $wpdb->get_col( "SELECT file_path FROM {$table}" );
		foreach ( (array) $paths as $path ) {
			if ( $path && \file_exists( $path ) ) {
				$live[ self::preview_key( $path ) ] = true;
			}
		}
		$items = @glob( $cache . '/*' );
		if ( ! is_array( $items ) ) {
			return;
		}
		foreach ( $items as $item ) {
			if ( \is_dir( $item ) ) {
				continue;
			}
			$name = basename( $item );
			if ( ! preg_match( '/\.(pdf|lock)$/', $name ) ) {
				continue;
			}
			$key = preg_replace( '/\.(pdf|lock)$/', '', $name );
			if ( ! isset( $live[ $key ] ) ) {
				wp_delete_file_from_directory( $item, $cache );
			}
		}
	}

	/**
	 * Remove conversion scratch directories older than a day.
	 *
	 * @param string $cache Cache directory.
	 * @return void
	 */
	private static function sweep_conversion_dirs( $cache ) {
		$items = @glob( self::work_dir() . '/lnfb-*' );
		if ( ! is_array( $items ) ) {
			return;
		}
		$cutoff = time() - DAY_IN_SECONDS;
		foreach ( $items as $item ) {
			if ( \is_dir( $item ) && @filemtime( $item ) < $cutoff ) {
				self::rrmdir( $item );
			}
		}
	}

	/**
	 * Recursively remove a directory (best effort, for temp conversion dirs).
	 *
	 * @param string $dir
	 * @return void
	 */
	private static function rrmdir( $dir ) {
		if ( ! \is_dir( $dir ) ) {
			return;
		}
		$items = @scandir( $dir );
		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				if ( '.' === $item || '..' === $item ) {
					continue;
				}
				$full = $dir . '/' . $item;
				if ( \is_dir( $full ) ) {
					self::rrmdir( $full );
				} else {
					@unlink( $full );
				}
			}
		}
		@rmdir( $dir );
	}
}
