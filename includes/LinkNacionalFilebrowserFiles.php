<?php

namespace LinkNacional\Filebrowser\Includes;

/**
 * Shared helpers for delivering plugin files through a controlled endpoint.
 *
 * Files never point to a public URL: rows are rewritten to a proxy URL and the
 * bytes are streamed by `serve_file_ajax()`, which enforces the per-file
 * `allow_download` flag.
 */
class LinkNacionalFilebrowserFiles {

	/**
	 * Nonce action used by the file-serving endpoint.
	 */
	const NONCE_ACTION = 'linknacional_filebrowser_serve_nonce';

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

	/**
	 * Absolute path of the plugin's upload directory.
	 *
	 * @return string
	 */
	public static function upload_dir() {
		$uploads = wp_upload_dir();
		return untrailingslashit( $uploads['basedir'] . '/linknacional-filebrowser' );
	}

	/**
	 * Whether the given path lives inside the plugin's upload directory.
	 *
	 * @param string $path Absolute path.
	 * @return bool
	 */
	public static function is_within_upload_dir( $path ) {
		if ( ! $path ) {
			return false;
		}
		$real = realpath( $path );
		$base = realpath( self::upload_dir() );
		if ( false === $real || false === $base ) {
			return false;
		}
		$real = wp_normalize_path( $real );
		$base = wp_normalize_path( $base );
		return 0 === strpos( $real, $base . '/' );
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
}
