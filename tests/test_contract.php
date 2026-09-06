<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$files = array(
	'workshop-pass-desk.php',
	'includes/class-wpd-db.php',
	'includes/class-wpd-service.php',
	'includes/class-wpd-product.php',
	'includes/class-wpd-admin.php',
	'includes/class-wpd-plugin.php',
);

foreach ( $files as $file ) {
	if ( ! is_file( $root . '/' . $file ) ) {
		fwrite( STDERR, "Missing {$file}\n" );
		exit( 1 );
	}
}
$service = file_get_contents( $root . '/includes/class-wpd-service.php' );
$db = file_get_contents( $root . '/includes/class-wpd-db.php' );
$plugin = file_get_contents( $root . '/includes/class-wpd-plugin.php' );
$product = file_get_contents( $root . '/includes/class-wpd-product.php' );
$admin = file_get_contents( $root . '/includes/class-wpd-admin.php' );
$bootstrap = file_get_contents( $root . '/workshop-pass-desk.php' );
$blueprint = json_decode( (string) file_get_contents( $root . '/blueprint/blueprint.json' ), true );
$ci = file_get_contents( $root . '/.github/workflows/ci.yml' );
$release = file_get_contents( $root . '/.github/workflows/release.yml' );
$checks = array(
	'separate tables' => str_contains( $db, 'wpd_workshops' ) && str_contains( $db, 'wpd_passes' ) && str_contains( $db, 'wpd_attendance' ) && str_contains( $db, 'wpd_sessions' ) && str_contains( $db, 'wpd_waitlist' ) && str_contains( $db, 'wpd_events' ),
	'order-item idempotency' => str_contains( $db, 'UNIQUE KEY order_item' ) && str_contains( $service, 'order_item_id' ),
	'hash and random code' => str_contains( $service, 'random_bytes' ) && str_contains( $service, 'hash_hmac' ),
	'atomic check-in' => str_contains( $service, "checked_in_at IS NULL" ) && str_contains( $service, 'attendance_table' ),
	'check-in capability' => str_contains( $service, "manage_workshop_passes" ) && str_contains( $service, "'forbidden'" ),
	'explicit results' => str_contains( $service, 'already_checked_in' ) && str_contains( $service, 'out_of_window' ) && str_contains( $service, 'cancelled' ),
	'frontend ownership gate' => str_contains( $plugin, "manage_workshop_passes" ),
	'datetime normalization' => str_contains( $service, 'normalize_datetime' ) && str_contains( $service, "Y-m-d H:i:s" ),
	'upgrade hook' => str_contains( $bootstrap, 'maybe_upgrade' ) && str_contains( $db, 'WPD_DB_VERSION' ),
	'hpos compatibility' => str_contains( $bootstrap, 'before_woocommerce_init' ) && str_contains( $bootstrap, 'custom_order_tables' ),
	'dependency headers' => str_contains( $bootstrap, 'Requires Plugins:' ) && str_contains( $bootstrap, 'WC tested up to:' ) && str_contains( $bootstrap, 'Tested up to:' ),
	'version consistency' => (bool) preg_match( '/^\s*\*\s*Version:\s*([0-9.]+)/m', $bootstrap, $m ) && str_contains( $bootstrap, "WPD_VERSION', '" . $m[1] . "'" ),
	'blueprint order' => is_array( $blueprint ) && isset( $blueprint['steps'] ) && (function () use ( $blueprint ): bool {
		$installs = array();
		$activations = array();
		foreach ( $blueprint['steps'] as $step ) {
			if ( 'installPlugin' === ( $step['step'] ?? '' ) ) {
				$installs[] = (string) ( $step['pluginData']['url'] ?? '' );
			}
			if ( 'activatePlugin' === ( $step['step'] ?? '' ) ) {
				$activations[] = (string) ( $step['pluginPath'] ?? '' );
			}
		}
		$woo_install = null;
		$wpd_install = null;
		foreach ( $installs as $i => $url ) {
			if ( null === $woo_install && str_contains( $url, 'woocommerce' ) ) {
				$woo_install = $i;
			}
			if ( null === $wpd_install && str_contains( $url, 'workshop-pass-desk' ) ) {
				$wpd_install = $i;
			}
		}
		return null !== $woo_install && null !== $wpd_install && $woo_install < $wpd_install
			&& str_contains( $installs[ $wpd_install ], 'releases' )
			&& in_array( 'woocommerce/woocommerce.php', $activations, true )
			&& in_array( 'workshop-pass-desk/workshop-pass-desk.php', $activations, true );
	})(),
	'waitlist lifecycle' => str_contains( $service, 'join_waitlist' ) && str_contains( $service, 'promote_waitlist' ) && str_contains( $plugin, 'wpd_waitlist' ),
	'audit trail' => str_contains( $service, 'log_event' ) && str_contains( $db, 'event_type' ),
	'csv reporting' => str_contains( $admin, 'fputcsv' ) && str_contains( $admin, 'wpd_export' ),
	'session-aware windows' => str_contains( $service, 'is_in_active_window' ) && str_contains( $service, 'session_count' ),
	'per-unit idempotency' => str_contains( $db, 'item_index' ) && str_contains( $service, 'item_index' ) && str_contains( $service, 'get_quantity' ),
	'variation mapping' => str_contains( $service, 'get_variation_id' ) && str_contains( $product, 'woocommerce_save_product_variation' ),
	'guest order passes' => str_contains( $service, 'order_passes' ) && str_contains( $product, 'order_passes' ) && str_contains( $product, 'get_order_key' ),
	'admin assets' => str_contains( $admin, 'admin_enqueue_scripts' ) && str_contains( $admin, 'assets/style.css' ),
	'textdomain' => str_contains( $plugin, 'load_plugin_textdomain' ),
	'uninstall cleanup' => is_file( $root . '/uninstall.php' ) && str_contains( (string) file_get_contents( $root . '/uninstall.php' ), 'WP_UNINSTALL_PLUGIN' ) && str_contains( $ci, 'uninstall.php' ) && str_contains( $release, 'uninstall.php' ),
	'partial refund revocation' => str_contains( $product, 'woocommerce_order_partially_refunded' ) && str_contains( $service, 'revoke_refunded_units' ) && str_contains( $service, '_refunded_item_id' ),
	'renewal guard' => str_contains( $service, '_subscription_renewal' ),
	'crypto fail-closed' => str_contains( $service, 'crypto_available' ) && str_contains( $service, 'crypto_unavailable' ),
	'dedicated pass secret' => str_contains( $db, 'wpd_secret' ) && str_contains( $service, 'rotate_secret' ) && str_contains( $service, 'wpd_secret_previous' ),
	'variation nonce' => str_contains( $product, 'woocommerce_meta_nonce' ) && substr_count( $product, 'wp_verify_nonce' ) >= 2,
	'mail-gated invites' => str_contains( $service, 'mail_failed' ) && str_contains( $service, 'wp_mail' ),
	'waitlist rate limit' => str_contains( $service, 'rate_limited' ) && str_contains( $service, 'HOUR_IN_SECONDS' ),
	'atomic check-in transaction' => str_contains( $service, 'START TRANSACTION' ) && str_contains( $service, "'COMMIT'" ),
	'csv formula guard' => str_contains( $admin, 'csv_cell' ),
	'workshop edit and delete' => str_contains( $admin, 'wpd_delete_workshop' ) && str_contains( $service, 'delete_workshop' ) && str_contains( $service, 'capacity_below_issued' ),
	'locked upgrades' => str_contains( $db, 'wpd_upgrade_lock' ) && str_contains( $bootstrap, 'admin_init' ),
	'erase-on-uninstall setting' => str_contains( (string) file_get_contents( $root . '/uninstall.php' ), 'erase_on_uninstall' ),
	'single-site guard' => str_contains( $plugin, 'is_plugin_active_for_network' ),
	'org readme and translations' => is_file( $root . '/readme.txt' ) && str_contains( (string) file_get_contents( $root . '/readme.txt' ), 'Stable tag:' ) && is_file( $root . '/languages/workshop-pass-desk.pot' ),
	'behavioral suite wired' => str_contains( $ci, 'test_behavioral.php' ) && str_contains( $release, 'test_behavioral.php' ) && is_file( $root . '/tests/test_behavioral.php' ),
);
foreach ( $checks as $name => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "Failed: {$name}\n" );
		exit( 1 );
	}
}
echo 'Workshop Pass Desk contract checks passed (' . count( $checks ) . ")\n";
