<?php
/**
 * Plugin Name: Digtiali Stockpik
 * Description: Integrates WooCommerce orders with the Stockpik API and surfaces account activation details to customers.
 * Version: 1.0.2
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: Digtiali
 * Text Domain: digtiali-stockpik
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('DIGTIALI_STOCKPIK_VERSION', '1.0.4');
define('DIGTIALI_STOCKPIK_PATH', plugin_dir_path(__FILE__));
define('DIGTIALI_STOCKPIK_URL', plugin_dir_url(__FILE__));
define('DIGTIALI_STOCKPIK_OPTION', 'digtiali_stockpik_settings');
define('DIGTIALI_STOCKPIK_PLUGIN_BASENAME', plugin_basename(__FILE__));

require_once DIGTIALI_STOCKPIK_PATH . 'includes/helpers.php';
require_once DIGTIALI_STOCKPIK_PATH . 'includes/api.php';
require_once DIGTIALI_STOCKPIK_PATH . 'includes/settings.php';
require_once DIGTIALI_STOCKPIK_PATH . 'includes/orders.php';
require_once DIGTIALI_STOCKPIK_PATH . 'includes/emails.php';
require_once DIGTIALI_STOCKPIK_PATH . 'includes/frontend.php';
