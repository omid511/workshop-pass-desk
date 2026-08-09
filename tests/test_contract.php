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
$admin = file_get_contents( $root . '/includes/class-wpd-admin.php' );
$bootstrap = file_get_contents( $root . '/workshop-pass-desk.php' );
$checks = array(
	'separate tables' => str_contains( $db, 'wpd_workshops' ) && str_contains( $db, 'wpd_passes' ) && str_contains( $db, 'wpd_attendance' ) && str_contains( $db, 'wpd_sessions' ) && str_contains( $db, 'wpd_waitlist' ) && str_contains( $db, 'wpd_events' ),
	'order-item idempotency' => str_contains( $db, 'UNIQUE KEY order_item' ) && str_contains( $service, 'order_item_id' ),
	'hash and random code' => str_contains( $service, 'random_bytes' ) && str_contains( $service, 'hash_hmac' ),
	'atomic check-in' => str_contains( $service, "checked_in_at IS NULL" ) && str_contains( $service, 'attendance_table' ),
	'check-in capability' => str_contains( $service, "manage_workshop_passes" ) && str_contains( $service, "'forbidden'" ),
	'explicit results' => str_contains( $service, 'already_checked_in' ) && str_contains( $service, 'out_of_window' ) && str_contains( $service, 'cancelled' ),
	'frontend ownership gate' => str_contains( $plugin, "manage_workshop_passes" ),
	'datetime normalization' => str_contains( $service, 'normalize_datetime' ) && str_contains( $service, "Y-m-d H:i:s" ),
	'session-aware windows' => str_contains( $service, 'is_in_active_window' ) && str_contains( $service, 'session_count' ),
	'waitlist lifecycle' => str_contains( $service, 'join_waitlist' ) && str_contains( $service, 'promote_waitlist' ) && str_contains( $plugin, 'wpd_waitlist' ),
	'audit trail' => str_contains( $service, 'log_event' ) && str_contains( $db, 'event_type' ),
	'csv reporting' => str_contains( $admin, 'fputcsv' ) && str_contains( $admin, 'wpd_export' ),
	'upgrade hook' => str_contains( $bootstrap, 'maybe_upgrade' ) && str_contains( $db, 'WPD_DB_VERSION' ),
);
foreach ( $checks as $name => $ok ) {
	if ( ! $ok ) {
		fwrite( STDERR, "Failed: {$name}\n" );
		exit( 1 );
	}
}
echo 'Workshop Pass Desk contract checks passed (' . count( $checks ) . ")\n";
