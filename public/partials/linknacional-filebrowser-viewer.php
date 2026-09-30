<?php
/**
 * Standalone viewer page for a token-protected shared file.
 *
 * Rendered by LinkNacionalFilebrowserPublic::render_viewer_page(). The page is
 * intentionally theme-free: it only prints the plugin's own assets and hands
 * the file data to the frontend lightbox.
 *
 * @var array $data View data: name, ext, allow_download, serve_url, size.
 * @package LinkNacional_Filebrowser
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php echo esc_html( $data['name'] ); ?></title>
	<?php wp_print_styles( array( 'linknacional-file-browser' ) ); ?>
</head>
<body class="lnfb-viewer-page">
	<div id="lnfb-viewer"
		data-name="<?php echo esc_attr( $data['name'] ); ?>"
		data-filetype="<?php echo esc_attr( $data['ext'] ); ?>"
		data-size="<?php echo esc_attr( $data['size'] ); ?>"
		data-allow-download="<?php echo esc_attr( $data['allow_download'] ? '1' : '0' ); ?>"
		data-url="<?php echo esc_attr( $data['serve_url'] ); ?>"></div>
	<?php wp_print_scripts( array( 'linknacional-filebrowser-fontawesome', 'linknacional-filebrowser-pdfjs', 'linknacional-file-browser' ) ); ?>
</body>
</html>
