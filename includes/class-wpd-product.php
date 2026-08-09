<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPD_Product {
	public static function hooks(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'field' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save' ) );
		add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'issue' ) );
		add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'issue' ) );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'issue' ) );
		add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'cancel' ) );
		add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'cancel' ) );
		add_action( 'woocommerce_thankyou', array( __CLASS__, 'thankyou' ) );
	}

	public static function field(): void {
		if ( ! current_user_can( 'edit_products' ) ) {
			return;
		}
		woocommerce_wp_text_input( array( 'id' => '_wpd_workshop_id', 'label' => __( 'Workshop ID', 'workshop-pass-desk' ), 'description' => __( 'Map this product to a Workshop Pass Desk workshop.', 'workshop-pass-desk' ), 'desc_tip' => true, 'type' => 'number' ) );
	}

	public static function save( int $product_id ): void {
		$nonce = isset( $_POST['woocommerce_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ) : '';
		if ( ! current_user_can( 'edit_product', $product_id ) || ! isset( $_POST['_wpd_workshop_id'] ) || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}
		$workshop_id = absint( wp_unslash( $_POST['_wpd_workshop_id'] ) );
		update_post_meta( $product_id, '_wpd_workshop_id', $workshop_id );
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . WPD_DB::workshops_table() . ' SET product_id = %d, updated_at = %s WHERE id = %d AND owner_id = %d', $product_id, current_time( 'mysql' ), $workshop_id, get_current_user_id() ) );
	}

	public static function issue( int $order_id ): void {
		WPD_Service::issue_for_order( $order_id );
	}

	public static function cancel( int $order_id ): void {
		if ( wc_get_order( $order_id ) ) {
			WPD_Service::cancel_order( $order_id );
		}
	}

	public static function thankyou( int $order_id ): void {
		$passes = WPD_Service::customer_passes( get_current_user_id() );
		$passes = array_filter( $passes, static fn( object $pass ): bool => (int) $pass->order_id === $order_id );
		if ( ! $passes ) {
			return;
		}
		echo '<section class="wpd-thankyou"><h2>' . esc_html__( 'Your workshop passes', 'workshop-pass-desk' ) . '</h2><ul>';
		foreach ( $passes as $pass ) {
			echo '<li><strong>' . esc_html( $pass->title ) . '</strong>: <code>' . esc_html( $pass->code ) . '</code></li>';
		}
		echo '</ul><p>' . esc_html__( 'Save these codes. They are used at check-in.', 'workshop-pass-desk' ) . '</p></section>';
	}
}
