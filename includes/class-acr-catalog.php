<?php
/**
 * Official-source catalog synchronization and price snapshots.
 *
 * @package ArvanCloudReseller
 */

defined( 'ABSPATH' ) || exit;

final class ACR_Catalog {
	public const API_URL = 'https://www.arvancloud.ir/fa/dev/api';
	public const PRICING_URL = 'https://www.arvancloud.ir/fa/pricing';
	public const TERMINATION_URL = 'https://www.arvancloud.ir/fa/legal/service-termination';
	public const PLAN_API_URL = 'https://napi.arvancloud.ir/cdn/4.0/plans';
	private const CRON_HOOK = 'acr_catalog_sync';
	private const CRON_SCHEDULE = 'acr_catalog_interval';

	public static function init(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'sync' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_route' ) );
		self::ensure_scheduled();
	}

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'acr_products';
	}

	public static function cron_schedule( array $schedules ): array {
		$minutes = self::interval_minutes();
		$schedules[ self::CRON_SCHEDULE ] = array(
			'interval' => $minutes * MINUTE_IN_SECONDS,
			'display'  => sprintf( __( 'هر %d دقیقه — کاتالوگ آروان', 'arvancloud-reseller' ), $minutes ),
		);
		return $schedules;
	}

	public static function interval_minutes(): int {
		return min( 10080, max( 15, absint( ACR_Settings::get( 'catalog_sync_minutes', 360 ) ) ) );
	}

	public static function ensure_scheduled(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'cron_schedule' ) );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + 120, self::CRON_SCHEDULE, self::CRON_HOOK );
		}
	}

	public static function reschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		self::ensure_scheduled();
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	public static function seed_defaults(): void {
		if ( self::get_products() ) {
			return;
		}
		foreach ( self::definitions() as $slug => $product ) {
			self::upsert(
				$slug,
				$product,
				$product['fallback_price'],
				array(),
				'fallback',
				''
			);
		}
	}

	public static function sync(): array {
		$plan_result = ACR_API::list_cdn_plans();
		$sources     = array(
			'plans' => self::plan_source_summary( $plan_result ),
		);
		$updated = 0;
		$error   = '';

		foreach ( self::definitions() as $slug => $product ) {
			if ( 'cdn' === $slug ) {
				$plans = self::extract_plan_catalog( $plan_result['data'] ?? array() );
				if ( $plan_result['success'] && $plans ) {
					$label = self::headline_plan_price( $plans, $product['fallback_price'] );
					self::upsert(
						$slug,
						$product,
						$label,
						array(
							'currency' => self::plan_currency( $plan_result['data'] ?? array() ),
							'plans'    => $plans,
						),
						'official',
						hash( 'sha256', wp_json_encode( $plan_result['data'] ) )
					);
					++$updated;
					continue;
				}

				if ( empty( $plan_result['success'] ) ) {
					continue;
				}
			}

			self::upsert(
				$slug,
				$product,
				$product['fallback_price'],
				array(),
				'fallback',
				''
			);
			++$updated;
		}

		if ( empty( $plan_result['success'] ) ) {
			$error = $sources['plans']['message'] ?: __( 'دریافت پلن‌های CDN از API ناموفق بود؛ آخرین کاتالوگ معتبر حفظ شد.', 'arvancloud-reseller' );
			self::seed_defaults();
		}

		$status      = array();
		$diagnostics = array();
		foreach ( $sources as $key => $source ) {
			$status[ $key ] = array(
				'success' => (bool) $source['success'],
				'status'  => absint( $source['status'] ?? 0 ),
				'url'     => $source['url'],
				'hash'    => sanitize_text_field( (string) ( $source['hash'] ?? '' ) ),
				'message' => sanitize_text_field( (string) ( $source['message'] ?? '' ) ),
			);
			$diagnostics[] = array(
				'label'    => strtoupper( $key ),
				'request'  => array(
					'method'   => 'GET',
					'endpoint' => $source['url'],
					'headers'  => array( 'Accept' => 'application/json' ),
				),
				'response' => array(
					'success'      => (bool) $source['success'],
					'status'       => absint( $source['status'] ?? 0 ),
					'message'      => sanitize_text_field( (string) ( $source['message'] ?? '' ) ),
					'body_excerpt' => function_exists( 'mb_substr' ) ? mb_substr( (string) ( $source['body'] ?? '' ), 0, 4000 ) : substr( (string) ( $source['body'] ?? '' ), 0, 4000 ),
				),
			);
		}

		ACR_Settings::set( 'catalog_source_status', wp_json_encode( $status ) );
		ACR_Settings::set( 'catalog_last_sync', current_time( 'mysql', true ) );
		ACR_Settings::set( 'termination_policy_date', '' );
		ACR_Settings::set( 'catalog_last_error', $error );

		return array(
			'success'     => ! empty( $plan_result['success'] ),
			'updated'     => $updated,
			'sources'     => $status,
			'message'     => $error,
			'diagnostics' => $diagnostics,
		);
	}

	private static function plan_source_summary( array $result ): array {
		$body = wp_json_encode( $result['data'] ?? array(), JSON_UNESCAPED_UNICODE );
		return array(
			'success' => (bool) ( $result['success'] ?? false ),
			'status'  => absint( $result['status'] ?? 0 ),
			'body'    => $body ?: '',
			'message' => sanitize_text_field( (string) ( $result['message'] ?? '' ) ),
			'url'     => self::PLAN_API_URL,
			'hash'    => $body ? hash( 'sha256', $body ) : '',
		);
	}

	private static function definitions(): array {
		return array(
			'cdn' => array(
				'name'           => __( 'شبکه توزیع محتوا (CDN)', 'arvancloud-reseller' ),
				'description'    => __( 'افزایش سرعت، امنیت و پایداری وب‌سایت با پرداخت مبتنی بر مصرف.', 'arvancloud-reseller' ),
				'icon'           => 'dashicons-admin-site-alt3',
				'fallback_price' => __( 'شروع از رایگان', 'arvancloud-reseller' ),
				'purchasable'    => true,
				'source_url'     => self::API_URL,
			),
			'cloud-server' => array(
				'name'           => __( 'سرور ابری', 'arvancloud-reseller' ),
				'description'    => __( 'منابع پردازشی منعطف با محاسبه هزینه ساعتی و ماهانه.', 'arvancloud-reseller' ),
				'icon'           => 'dashicons-cloud',
				'fallback_price' => __( 'قیمت‌گذاری براساس منابع', 'arvancloud-reseller' ),
				'purchasable'    => true,
				'source_url'     => self::PRICING_URL . '/cloud-server',
			),
			'object-storage' => array(
				'name'           => __( 'فضای ابری', 'arvancloud-reseller' ),
				'description'    => __( 'ذخیره‌سازی سازگار با S3 برای فایل‌ها و داده‌های حجیم.', 'arvancloud-reseller' ),
				'icon'           => 'dashicons-database',
				'fallback_price' => __( 'شروع از رایگان', 'arvancloud-reseller' ),
				'purchasable'    => false,
				'source_url'     => self::PRICING_URL . '/cloud-storage',
			),
		);
	}

	private static function extract_plan_catalog( array $payload ): array {
		$plans = $payload['data']['plans'] ?? $payload['plans'] ?? array();
		if ( ! is_array( $plans ) ) {
			return array();
		}

		$out = array();
		foreach ( $plans as $plan ) {
			if ( ! is_array( $plan ) ) {
				continue;
			}

			$out[] = array(
				'key'            => sanitize_key( (string) ( $plan['key'] ?? '' ) ),
				'name'           => sanitize_text_field( (string) ( $plan['name'] ?? '' ) ),
				'monthly_cost'   => (float) ( $plan['monthly_cost'] ?? 0 ),
				'discount'       => (float) ( $plan['discount'] ?? 0 ),
				'needed_balance' => (float) ( $plan['needed_balance'] ?? 0 ),
			);
		}

		return array_values(
			array_filter(
				$out,
				static fn( array $plan ): bool => '' !== $plan['name'] || '' !== $plan['key']
			)
		);
	}

	private static function plan_currency( array $payload ): array {
		$currency = $payload['data']['currency'] ?? $payload['currency'] ?? array();
		return array(
			'key'   => sanitize_key( (string) ( $currency['key'] ?? '' ) ),
			'label' => sanitize_text_field( (string) ( $currency['label'] ?? '' ) ),
		);
	}

	private static function headline_plan_price( array $plans, string $fallback ): string {
		if ( ! $plans ) {
			return $fallback;
		}

		$monthly_costs = array_map(
			static fn( array $plan ): float => (float) $plan['monthly_cost'],
			$plans
		);
		$positive_costs = array_values(
			array_filter(
				$monthly_costs,
				static fn( float $cost ): bool => $cost > 0
			)
		);

		if ( ! $positive_costs && min( $monthly_costs ) <= 0 ) {
			return __( 'شروع از رایگان', 'arvancloud-reseller' );
		}

		if ( ! $positive_costs ) {
			return $fallback;
		}

		return sprintf(
			__( 'از %s در ماه', 'arvancloud-reseller' ),
			number_format_i18n( min( $positive_costs ) )
		);
	}

	private static function upsert( string $slug, array $product, string $price_label, array $prices, string $source_state, string $source_hash ): void {
		global $wpdb;
		$wpdb->replace(
			self::table(),
			array(
				'slug'             => $slug,
				'name'             => $product['name'],
				'description'      => $product['description'],
				'icon'             => $product['icon'],
				'price_label'      => $price_label,
				'price_details'    => wp_json_encode( $prices, JSON_UNESCAPED_UNICODE ),
				'purchasable'      => $product['purchasable'] ? 1 : 0,
				'status'           => 'active',
				'source_state'     => $source_state,
				'source_url'       => $product['source_url'],
				'source_hash'      => $source_hash,
				'source_synced_at' => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public static function get_products(): array {
		global $wpdb;
		$table = self::table();
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		if ( $table !== $exists ) {
			return array();
		}
		return $wpdb->get_results( "SELECT * FROM {$table} WHERE status = 'active' ORDER BY id ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public static function register_rest_route(): void {
		register_rest_route(
			'acr/v1',
			'/catalog',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => static function (): WP_REST_Response {
					$products = self::get_products();
					foreach ( $products as &$product ) {
						unset( $product['source_hash'], $product['price_details'] );
					}
					return new WP_REST_Response( array( 'products' => $products, 'last_sync' => ACR_Settings::get( 'catalog_last_sync', '' ) ) );
				},
			)
		);
	}
}
