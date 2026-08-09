<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WPD_DB {
	public static function workshops_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpd_workshops';
	}

	public static function passes_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpd_passes';
	}

	public static function attendance_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpd_attendance';
	}

	public static function sessions_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpd_sessions';
	}

	public static function waitlist_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpd_waitlist';
	}

	public static function events_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpd_events';
	}

	public static function activate(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;
		$collate = $wpdb->get_charset_collate();
		$workshops = self::workshops_table();
		$passes = self::passes_table();
		$attendance = self::attendance_table();
		$sessions = self::sessions_table();
		$waitlist = self::waitlist_table();
		$events = self::events_table();

		dbDelta( "CREATE TABLE {$workshops} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			owner_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(190) NOT NULL,
			description TEXT NOT NULL,
			starts_at DATETIME NOT NULL,
			ends_at DATETIME NOT NULL,
			capacity INT UNSIGNED NOT NULL DEFAULT 1,
			status VARCHAR(20) NOT NULL DEFAULT 'draft',
			product_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id), KEY owner_status (owner_id, status), KEY product_id (product_id), KEY starts_at (starts_at)
		) {$collate};" );

		dbDelta( "CREATE TABLE {$passes} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			workshop_id BIGINT UNSIGNED NOT NULL,
			order_id BIGINT UNSIGNED NOT NULL,
			order_item_id BIGINT UNSIGNED NOT NULL,
			customer_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			code_hash CHAR(64) NOT NULL,
			code_ciphertext TEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			valid_from DATETIME NOT NULL,
			valid_until DATETIME NOT NULL,
			checked_in_at DATETIME NULL,
			checked_in_by BIGINT UNSIGNED NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY order_item (order_id, order_item_id), UNIQUE KEY code_hash (code_hash),
			KEY workshop_status (workshop_id, status), KEY customer_id (customer_id), KEY valid_window (valid_from, valid_until)
		) {$collate};" );

		dbDelta( "CREATE TABLE {$attendance} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			pass_id BIGINT UNSIGNED NOT NULL,
			workshop_id BIGINT UNSIGNED NOT NULL,
			actor_id BIGINT UNSIGNED NOT NULL,
			checked_in_at DATETIME NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY pass_id (pass_id), KEY workshop_time (workshop_id, checked_in_at)
		) {$collate};" );

		dbDelta( "CREATE TABLE {$sessions} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			workshop_id BIGINT UNSIGNED NOT NULL,
			title VARCHAR(190) NOT NULL,
			starts_at DATETIME NOT NULL,
			ends_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id), KEY workshop_time (workshop_id, starts_at)
		) {$collate};" );

		dbDelta( "CREATE TABLE {$waitlist} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			workshop_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			email VARCHAR(190) NOT NULL,
			name VARCHAR(190) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY (id), UNIQUE KEY workshop_email (workshop_id, email), KEY workshop_status (workshop_id, status)
		) {$collate};" );

		dbDelta( "CREATE TABLE {$events} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			workshop_id BIGINT UNSIGNED NOT NULL,
			pass_id BIGINT UNSIGNED NULL,
			actor_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			event_type VARCHAR(40) NOT NULL,
			details TEXT NOT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id), KEY workshop_time (workshop_id, created_at), KEY pass_time (pass_id, created_at)
		) {$collate};" );

		update_option( 'wpd_db_version', WPD_DB_VERSION );
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'manage_workshop_passes' );
		}
		flush_rewrite_rules();
	}

	public static function maybe_upgrade(): void {
		if ( WPD_DB_VERSION !== get_option( 'wpd_db_version' ) ) {
			self::activate();
		}
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
