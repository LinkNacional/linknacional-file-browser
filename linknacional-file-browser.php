<?php

/**
 * The plugin bootstrap file
 *
 * @link              https://www.linknacional.com.br
 * @since             1.0.0
 * @package           LinkNacional_Filebrowser
 *
 * @wordpress-plugin
 * Plugin Name:       Link Nacional File Browser
 * Plugin URI:        https://www.linknacional.com.br
 * Description:       Create your folder structure and display it on the frontend.
 * Version:           1.1.0
 * Requires at least: 6.0
 * Requires PHP:      8.2
 * Author:            Link Nacional
 * Author URI:        https://www.linknacional.com.br/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       linknacional-file-browser
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 */
define( 'LINKNACIONAL_FILEBROWSER_VERSION', '1.1.0' );
define( 'LINKNACIONAL_FILEBROWSER_PLUGIN_NAME', 'linknacional-file-browser' );

/**
 * Plugin constants
 */
define( 'LINKNACIONAL_FILEBROWSER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'LINKNACIONAL_FILEBROWSER_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

/**
 * Autoloader using Composer
 */
if ( file_exists( plugin_dir_path( __FILE__ ) . 'vendor/autoload.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'vendor/autoload.php';
} else {
	// Manual autoloader fallback for PSR-4
	spl_autoload_register( function ( $class ) {
		$namespaces = [
			'LinkNacional\\Filebrowser\\Admin\\'    => plugin_dir_path( __FILE__ ) . 'admin/',
			'LinkNacional\\Filebrowser\\Public\\'   => plugin_dir_path( __FILE__ ) . 'public/',
			'LinkNacional\\Filebrowser\\Includes\\' => plugin_dir_path( __FILE__ ) . 'includes/',
		];

		foreach ( $namespaces as $prefix => $base_dir ) {
			$len = strlen( $prefix );
			if ( strncmp( $prefix, $class, $len ) !== 0 ) {
				continue;
			}

			$relative_class = substr( $class, $len );
			$file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( file_exists( $file ) ) {
				require $file;
				return;
			}
		}
	});
}

use LinkNacional\Filebrowser\Includes\LinkNacionalFilebrowser;
use LinkNacional\Filebrowser\Includes\LinkNacionalFilebrowserActivator;
use LinkNacional\Filebrowser\Includes\LinkNacionalFilebrowserDeactivator;

// Include backward compatibility aliases
require_once plugin_dir_path( __FILE__ ) . 'includes/Aliases.php';

/**
 * The code that runs during plugin activation.
 */
function linknacional_filebrowser_activate() {
	LinkNacionalFilebrowserActivator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function linknacional_filebrowser_deactivate() {
	LinkNacionalFilebrowserDeactivator::deactivate();
}

register_activation_hook( __FILE__, 'linknacional_filebrowser_activate' );
register_deactivation_hook( __FILE__, 'linknacional_filebrowser_deactivate' );

/**
 * Begins execution of the plugin.
 */
function linknacional_filebrowser_run() {
	$plugin = new LinkNacionalFilebrowser();
	$plugin->run();
}
linknacional_filebrowser_run();
