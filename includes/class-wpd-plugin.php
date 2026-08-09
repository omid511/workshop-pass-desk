<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPD_Plugin {
	public static function boot(): void {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', static function(): void { if ( current_user_can( 'activate_plugins' ) ) { echo '<div class="notice notice-warning"><p>' . esc_html__( 'Workshop Pass Desk needs WooCommerce active.', 'workshop-pass-desk' ) . '</p></div>'; } } );
			return;
		}
		WPD_Admin::hooks();
		WPD_Product::hooks();
		add_shortcode( 'wpd_my_passes', array( __CLASS__, 'my_passes' ) );
		add_shortcode( 'wpd_check_in', array( __CLASS__, 'check_in_shortcode' ) );
		add_shortcode( 'wpd_waitlist', array( __CLASS__, 'waitlist_shortcode' ) );
	}

	public static function my_passes(): string {
		if ( ! is_user_logged_in() ) {
			return '<p>' . esc_html__( 'Log in to view your workshop passes.', 'workshop-pass-desk' ) . '</p>';
		}
		$rows = WPD_Service::customer_passes( get_current_user_id() );
		if ( ! $rows ) {
			return '<p>' . esc_html__( 'No workshop passes found.', 'workshop-pass-desk' ) . '</p>';
		}
		$out = '<div class="wpd-passes"><h2>' . esc_html__( 'My workshop passes', 'workshop-pass-desk' ) . '</h2><ul>';
		foreach ( $rows as $row ) {
			$out .= '<li><strong>' . esc_html( $row->title ) . '</strong><br><span>' . esc_html( $row->valid_from . ' – ' . $row->valid_until ) . '</span><br><code>' . esc_html( $row->code ) . '</code><br><span>' . esc_html( ucfirst( $row->status ) ) . ( $row->checked_in_at ? esc_html__( ' · Checked in', 'workshop-pass-desk' ) : '' ) . '</span></li>';
		}
		return $out . '</ul></div>';
	}

	public static function check_in_shortcode(): string {
		if ( ! current_user_can( 'manage_workshop_passes' ) ) {
			return '<p>' . esc_html__( 'Organizer access is required.', 'workshop-pass-desk' ) . '</p>';
		}
		if ( isset( $_POST['wpd_code'], $_POST['wpd_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpd_nonce'] ) ), 'wpd_front_check_in' ) ) {
			$result = WPD_Service::check_in( sanitize_text_field( wp_unslash( $_POST['wpd_code'] ) ), get_current_user_id() );
			return '<div class="wpd-result wpd-result-' . esc_attr( $result['status'] ) . '"><strong>' . esc_html( ucfirst( str_replace( '_', ' ', $result['status'] ) ) ) . '</strong><p>' . esc_html( $result['message'] ) . '</p></div>' . self::check_in_form();
		}
		return self::check_in_form();
	}

	public static function waitlist_shortcode( array $atts ): string {
		$atts = shortcode_atts( array( 'workshop_id' => 0 ), $atts, 'wpd_waitlist' );
		$workshop_id = absint( $atts['workshop_id'] );
		if ( ! $workshop_id ) {
			return '<p>' . esc_html__( 'Choose a workshop for this waitlist form.', 'workshop-pass-desk' ) . '</p>';
		}
		$notice = '';
		if ( isset( $_POST['wpd_waitlist_nonce'], $_POST['wpd_waitlist_email'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpd_waitlist_nonce'] ) ), 'wpd_join_waitlist_' . $workshop_id ) ) {
			$result = WPD_Service::join_waitlist( $workshop_id, sanitize_email( wp_unslash( $_POST['wpd_waitlist_email'] ) ), sanitize_text_field( wp_unslash( $_POST['wpd_waitlist_name'] ?? '' ) ) );
			$notice = is_wp_error( $result ) ? '<p class="wpd-error">' . esc_html( $result->get_error_message() ) . '</p>' : '<p class="wpd-success">' . esc_html__( 'You are on the waitlist. We will email you if a place opens.', 'workshop-pass-desk' ) . '</p>';
		}
		$user = wp_get_current_user();
		$email = $user->exists() ? $user->user_email : '';
		$name = $user->exists() ? $user->display_name : '';
		return $notice . '<form class="wpd-waitlist-form" method="post"><label>' . esc_html__( 'Name', 'workshop-pass-desk' ) . '<input name="wpd_waitlist_name" value="' . esc_attr( $name ) . '"></label><label>' . esc_html__( 'Email', 'workshop-pass-desk' ) . '<input required type="email" name="wpd_waitlist_email" value="' . esc_attr( $email ) . '"></label><input type="hidden" name="wpd_waitlist_nonce" value="' . esc_attr( wp_create_nonce( 'wpd_join_waitlist_' . $workshop_id ) ) . '"><button type="submit">' . esc_html__( 'Join waitlist', 'workshop-pass-desk' ) . '</button></form>';
	}

	private static function check_in_form(): string {
		return '<form class="wpd-checkin-form" method="post"><label for="wpd-front-code">' . esc_html__( 'Pass code', 'workshop-pass-desk' ) . '</label><input id="wpd-front-code" name="wpd_code" required autocomplete="off" autocapitalize="characters" inputmode="text"><input type="hidden" name="wpd_nonce" value="' . esc_attr( wp_create_nonce( 'wpd_front_check_in' ) ) . '"><button type="submit">' . esc_html__( 'Check in', 'workshop-pass-desk' ) . '</button></form>';
	}
}
