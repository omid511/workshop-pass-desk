<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;
foreach ( array( 'wpd_events', 'wpd_attendance', 'wpd_passes', 'wpd_sessions', 'wpd_waitlist', 'wpd_workshops' ) as $table ) {
	$wpdb->query( 'DROP TABLE IF EXISTS ' . $wpdb->prefix . $table );
}
delete_option( 'wpd_db_version' );
$admin = get_role( 'administrator' );
if ( $admin && $admin->has_cap( 'manage_workshop_passes' ) ) {
	$admin->remove_cap( 'manage_workshop_passes' );
}
