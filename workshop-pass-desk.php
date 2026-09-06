<?php
/**
 * Plugin Name: Workshop Pass Desk
 * Description: Sell one-time workshop passes with WooCommerce and check attendees in from a mobile-friendly desk.
 * Version: 0.2.0
 * Requires at least: 6.6
 * Tested up to: 6.8
 * Requires PHP: 8.3
 * Requires Plugins: woocommerce
 * WC requires at least: 9.0
 * WC tested up to: 9.6
 * Text Domain: workshop-pass-desk
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPD_VERSION', '0.2.0' );
define( 'WPD_DB_VERSION', '1.2.0' );
define( 'WPD_FILE', __FILE__ );
define( 'WPD_DIR', plugin_dir_path( __FILE__ ) );

require_once WPD_DIR . 'includes/class-wpd-db.php';
require_once WPD_DIR . 'includes/class-wpd-service.php';
require_once WPD_DIR . 'includes/class-wpd-product.php';
require_once WPD_DIR . 'includes/class-wpd-admin.php';
require_once WPD_DIR . 'includes/class-wpd-plugin.php';

register_activation_hook( __FILE__, array( 'WPD_DB', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WPD_DB', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WPD_DB', 'maybe_upgrade' ), 5 );
add_action( 'plugins_loaded', array( 'WPD_Plugin', 'boot' ), 10 );
add_action( 'before_woocommerce_init', static function(): void {
	if ( class_exists( 'Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );
