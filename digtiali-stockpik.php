<?php
/**
 * Plugin Name: Digtiali Stockpik
 * Description: Integrates WooCommerce orders with the Stockpik API and surfaces account activation details to customers.
 * Version: 1.0.5
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: Digtiali
 * Author URI: https://digtiali.com
 * Text Domain: digtiali-stockpik
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('DIGTIALI_STOCKPIK_PATH', plugin_dir_path(__FILE__));
define('DIGTIALI_STOCKPIK_URL', plugin_dir_url(__FILE__));

/**
 * Installed version — read from version.json when present.
 */
function digtiali_stockpik_read_installed_version(): string {
	$fallback = '1.0.5'; // digtiali-stockpik version fallback
	$path     = __DIR__ . '/version.json';
	if ( ! is_readable( $path ) ) {
		return $fallback;
	}
	$data = json_decode( (string) file_get_contents( $path ), true );
	if ( ! is_array( $data ) || empty( $data['version'] ) ) {
		return $fallback;
	}
	return (string) $data['version'];
}

define('DIGTIALI_STOCKPIK_VERSION', digtiali_stockpik_read_installed_version());
define('DIGTIALI_STOCKPIK_OPTION', 'digtiali_stockpik_settings');
define('DIGTIALI_STOCKPIK_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once DIGTIALI_STOCKPIK_PATH . 'includes/plugin-updater.php';
require_once DIGTIALI_STOCKPIK_PATH . 'includes/helpers.php';
require_once DIGTIALI_STOCKPIK_PATH . 'includes/api.php';
require_once DIGTIALI_STOCKPIK_PATH . 'includes/settings.php';
require_once DIGTIALI_STOCKPIK_PATH . 'includes/orders.php';
require_once DIGTIALI_STOCKPIK_PATH . 'includes/emails.php';
require_once DIGTIALI_STOCKPIK_PATH . 'includes/frontend.php';
