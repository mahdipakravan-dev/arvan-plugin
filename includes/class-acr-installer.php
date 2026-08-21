<?php
/**
 * Plugin activation and database schema.
 *
 * @package ArvanCloudReseller
 */

defined( 'ABSPATH' ) || exit;

final class ACR_Installer {
	public static function activate(): void {
		self::create_tables();
		update_option( 'acr_db_version', ACR_VERSION, false );
		set_transient( 'acr_activation_redirect', 1, 60 );
		if ( class_exists( 'ACR_Catalog' ) ) {
			ACR_Catalog::seed_defaults();
			ACR_Catalog::ensure_scheduled();
		}
		self::ensure_portal_page();

		if ( ! wp_next_scheduled( 'acr_hourly_billing' ) ) {
			wp_schedule_event( time() + 300, 'hourly', 'acr_hourly_billing' );
		}
		if ( ! wp_next_scheduled( 'acr_daily_settlement' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'acr_daily_settlement' );
		}
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'acr_hourly_billing' );
		wp_clear_scheduled_hook( 'acr_daily_settlement' );
		if ( class_exists( 'ACR_Catalog' ) ) {
			ACR_Catalog::deactivate();
		}
	}

	public static function maybe_upgrade(): void {
		if ( ACR_VERSION === get_option( 'acr_db_version' ) ) {
			return;
		}
		self::create_tables();
		update_option( 'acr_db_version', ACR_VERSION, false );
		if ( class_exists( 'ACR_Catalog' ) ) {
			ACR_Catalog::seed_defaults();
			ACR_Catalog::reschedule();
		}
		self::ensure_portal_page();
	}

	private static function ensure_portal_page(): void {
		global $wpdb;
		$current = absint( ACR_Settings::get( 'portal_page_id', 0 ) );
		if ( $current && 'page' === get_post_type( $current ) ) {
			return;
		}

		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status NOT IN ('trash','auto-draft') AND (post_content LIKE %s OR post_content LIKE %s) ORDER BY ID ASC LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'%' . $wpdb->esc_like( 'acr/customer-profile' ) . '%',
				'%' . $wpdb->esc_like( '[arvan_reseller_portal]' ) . '%'
			)
		);
		if ( $existing ) {
			ACR_Settings::set( 'portal_page_id', (string) absint( $existing ) );
			return;
		}

		$page_id = wp_insert_post(
			array(
				'post_title'   => __( 'پنل خدمات ابری', 'arvancloud-reseller' ),
				'post_name'    => 'cloud-customer-profile',
				'post_status'  => 'publish',
				'post_type'    => 'page',
				'post_content' => "<!-- wp:acr/product-catalog /-->\n\n<!-- wp:acr/customer-profile /-->",
			),
			true
		);
		if ( ! is_wp_error( $page_id ) ) {
			update_post_meta( $page_id, '_acr_portal_page', '1' );
			ACR_Settings::set( 'portal_page_id', (string) absint( $page_id ) );
		}
	}

	private static function create_tables(): void {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$p       = $wpdb->prefix . 'acr_';

		$sql = array();
		$sql[] = "CREATE TABLE {$p}settings (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			setting_key varchar(191) NOT NULL,
			setting_value longtext NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY setting_key (setting_key)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}wallets (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			balance decimal(20,2) NOT NULL DEFAULT 0,
			threshold decimal(20,2) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY user_id (user_id)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}transactions (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			wallet_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			type varchar(30) NOT NULL,
			amount decimal(20,2) NOT NULL,
			balance_after decimal(20,2) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'completed',
			reference varchar(100) NULL,
			description varchar(255) NULL,
			metadata longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_created (user_id, created_at),
			KEY reference (reference)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}payments (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			amount decimal(20,2) NOT NULL,
			status varchar(20) NOT NULL,
			reference varchar(100) NOT NULL,
			metadata longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY reference (reference),
			KEY user_status (user_id, status)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}orders (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			product_type varchar(40) NOT NULL,
			configuration longtext NOT NULL,
			status varchar(30) NOT NULL,
			amount decimal(20,2) NOT NULL DEFAULT 0,
			resource_id varchar(191) NULL,
			error_message text NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_status (user_id, status)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}resources (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			order_id bigint(20) unsigned NOT NULL,
			external_id varchar(191) NOT NULL,
			product_type varchar(40) NOT NULL,
			status varchar(30) NOT NULL,
			region varchar(80) NULL,
			configuration longtext NOT NULL,
			last_billed_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY external_id (external_id),
			KEY user_status (user_id, status)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}usage_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			resource_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			period_start datetime NOT NULL,
			period_end datetime NOT NULL,
			units decimal(20,6) NOT NULL DEFAULT 0,
			base_amount decimal(20,2) NOT NULL DEFAULT 0,
			markup_percent decimal(5,2) NOT NULL DEFAULT 0,
			final_amount decimal(20,2) NOT NULL DEFAULT 0,
			source varchar(30) NOT NULL DEFAULT 'mock',
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY resource_period (resource_id, period_start),
			KEY user_period (user_id, period_start)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}audit_logs (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			category varchar(30) NOT NULL,
			event varchar(80) NOT NULL,
			status varchar(20) NOT NULL,
			message varchar(500) NULL,
			context longtext NULL,
			ip_address varchar(100) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY category_created (category, created_at),
			KEY user_created (user_id, created_at)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}settlements (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			period_start datetime NOT NULL,
			period_end datetime NOT NULL,
			base_amount decimal(20,2) NOT NULL,
			reseller_amount decimal(20,2) NOT NULL,
			status varchar(20) NOT NULL,
			reference varchar(100) NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY period_status (period_end, status)
		) {$charset};";
		$sql[] = "CREATE TABLE {$p}products (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			slug varchar(100) NOT NULL,
			name varchar(191) NOT NULL,
			description text NULL,
			icon varchar(100) NULL,
			price_label varchar(191) NULL,
			price_details longtext NULL,
			purchasable tinyint(1) NOT NULL DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'active',
			source_state varchar(20) NOT NULL DEFAULT 'fallback',
			source_url varchar(500) NULL,
			source_hash varchar(64) NULL,
			source_synced_at datetime NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY status (status)
		) {$charset};";

		foreach ( $sql as $statement ) {
			dbDelta( $statement );
		}
	}
}
