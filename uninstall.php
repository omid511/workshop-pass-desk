<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once dirname( __FILE__ ) . '/includes/class-wpd-db.php';

$settings = get_option( 'wpd_settings', array() );
$erase = is_array( $settings ) && ! empty( $settings['erase_on_uninstall'] );
WPD_DB::uninstall( $erase );
