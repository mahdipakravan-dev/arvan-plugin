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
		$sources = array(
			'api'         => self::fetch( self::API_URL ),
			'termination' => self::fetch( self::TERMINATION_URL ),
		);
		$product_sources = array();
		foreach ( self::definitions() as $slug => $product ) {
			$product_sources[ $slug ] = self::fetch( $product['source_url'] );
		}
		$sources['pricing'] = self::pricing_source_summary( $product_sources );
		$updated = 0;
		$error   = '';

		foreach ( self::definitions() as $slug => $product ) {
			$pricing = $product_sources[ $slug ];
			if ( ! $pricing['success'] ) {
				continue;
			}

			$section = self::html_text( $pricing['body'] );
			$prices  = self::extract_prices( $section );
			if ( ! $prices ) {
				continue;
			}

			$label = self::headline_price( $slug, $prices, $product['fallback_price'] );
			self::upsert( $slug, $product, $label, $prices, 'official', hash( 'sha256', $section ) );
			++$updated;
		}

		if ( 0 === $updated ) {
			$error = $sources['pricing']['message'] ?: __( 'ساختار صفحه قیمت تغییر کرده است؛ آخرین کاتالوگ معتبر حفظ شد.', 'arvancloud-reseller' );
			self::seed_defaults();
		}

		$status      = array();
		$diagnostics = array();
		foreach ( $sources as $key => $source ) {
			$status[ $key ] = array(
				'success' => (bool) $source['success'],
				'status'  => absint( $source['status'] ?? 0 ),
				'url'     => $source['url'],
				'hash'    => $source['success'] ? hash( 'sha256', $source['body'] ) : '',
				'message' => sanitize_text_field( (string) ( $source['message'] ?? '' ) ),
			);
			$diagnostics[] = array(
				'label'    => strtoupper( $key ),
				'request'  => array(
					'method'   => 'GET',
					'endpoint' => $source['url'],
					'headers'  => array( 'Accept' => 'text/html,application/xhtml+xml' ),
				),
				'response' => array(
					'success'      => (bool) $source['success'],
					'status'       => absint( $source['status'] ?? 0 ),
					'message'      => sanitize_text_field( (string) ( $source['message'] ?? '' ) ),
					'body_excerpt' => function_exists( 'mb_substr' ) ? mb_substr( (string) $source['body'], 0, 4000 ) : substr( (string) $source['body'], 0, 4000 ),
				),
			);
		}

		$termination_text = $sources['termination']['success'] ? self::html_text( $sources['termination']['body'] ) : '';
		$policy_date = '';
		if ( preg_match( '/آخرین تاریخ به.?روزرسانی\s*[:：]?\s*([^\n]{3,40})/u', $termination_text, $match ) ) {
			$policy_date = sanitize_text_field( trim( $match[1] ) );
		}

		ACR_Settings::set( 'catalog_source_status', wp_json_encode( $status ) );
		ACR_Settings::set( 'catalog_last_sync', current_time( 'mysql', true ) );
		ACR_Settings::set( 'termination_policy_date', $policy_date );
		ACR_Settings::set( 'catalog_last_error', $error );

		return array( 'success' => $updated > 0, 'updated' => $updated, 'sources' => $status, 'message' => $error, 'diagnostics' => $diagnostics );
	}

	private static function fetch( string $url ): array {
		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 25,
				'redirection'         => 3,
				'limit_response_size' => 4 * MB_IN_BYTES,
				'user-agent'          => 'Mozilla/5.0 (compatible; ArvanCloud-Reseller/' . ACR_VERSION . '; +' . home_url( '/' ) . ')',
				'headers'             => array(
					'Accept'          => 'text/html,application/xhtml+xml',
					'Accept-Language' => 'fa-IR,fa;q=0.9,en;q=0.7',
				),
			)
		);
		if ( is_wp_error( $response ) ) {
			return array( 'success' => false, 'status' => 0, 'body' => '', 'message' => $response->get_error_message(), 'url' => $url );
		}
		$status       = wp_remote_retrieve_response_code( $response );
		$body         = wp_remote_retrieve_body( $response );
		$is_challenge = preg_match( '/در\s*حال\s*انتقال|Transferring to the website/iu', self::html_text( $body ) );
		$success      = $status >= 200 && $status < 400 && '' !== trim( $body ) && ! $is_challenge;
		return array(
			'success' => $success,
			'status'  => $status,
			'body'    => $body,
			'message' => $is_challenge ? __( 'صفحه قیمت به صفحه انتقال موقت هدایت شد؛ بعداً دوباره تلاش کنید.', 'arvancloud-reseller' ) : ( $status >= 200 && $status < 400 ? '' : sprintf( __( 'پاسخ HTTP %d', 'arvancloud-reseller' ), $status ) ),
			'url'     => $url,
		);
	}

	private static function pricing_source_summary( array $product_sources ): array {
		$successful = array_filter(
			$product_sources,
			static fn( array $source ): bool => (bool) $source['success']
		);
		$failed = array_diff_key( $product_sources, $successful );
		$bodies = array_column( $successful, 'body' );
		return array(
			'success' => count( $successful ) === count( $product_sources ),
			'status'  => $failed ? absint( reset( $failed )['status'] ?? 0 ) : 200,
			'body'    => implode( "\n", $bodies ),
			'message' => $failed ? __( 'دریافت یک یا چند صفحه قیمت محصول ناموفق بود.', 'arvancloud-reseller' ) : '',
			'url'     => self::PRICING_URL,
		);
	}

	private static function definitions(): array {
		return array(
			'cdn' => array(
				'name'           => __( 'شبکه توزیع محتوا (CDN)', 'arvancloud-reseller' ),
				'description'    => __( 'افزایش سرعت، امنیت و پایداری وب‌سایت با پرداخت مبتنی بر مصرف.', 'arvancloud-reseller' ),
				'icon'           => 'dashicons-admin-site-alt3',
				'start'          => 'شبکه توزیع محتوا (CDN)',
				'end'            => 'سرور ابری',
				'fallback_price' => __( 'شروع از رایگان', 'arvancloud-reseller' ),
				'purchasable'    => true,
				'source_url'     => self::PRICING_URL . '/cdn',
			),
			'cloud-server' => array(
				'name'           => __( 'سرور ابری', 'arvancloud-reseller' ),
				'description'    => __( 'منابع پردازشی منعطف با محاسبه هزینه ساعتی و ماهانه.', 'arvancloud-reseller' ),
				'icon'           => 'dashicons-cloud',
				'start'          => 'سرور ابری',
				'end'            => 'فضای ابری',
				'fallback_price' => __( 'قیمت‌گذاری براساس منابع', 'arvancloud-reseller' ),
				'purchasable'    => true,
				'source_url'     => self::PRICING_URL . '/cloud-server',
			),
			'object-storage' => array(
				'name'           => __( 'فضای ابری', 'arvancloud-reseller' ),
				'description'    => __( 'ذخیره‌سازی سازگار با S3 برای فایل‌ها و داده‌های حجیم.', 'arvancloud-reseller' ),
				'icon'           => 'dashicons-database',
				'start'          => 'فضای ابری',
				'end'            => 'پلتفرم ویدیو',
				'fallback_price' => __( 'شروع از رایگان', 'arvancloud-reseller' ),
				'purchasable'    => false,
				'source_url'     => self::PRICING_URL . '/cloud-storage',
			),
		);
	}

	private static function html_text( string $html ): string {
		$html = preg_replace( '#<(script|style|svg|noscript)[^>]*>.*?</\1>#isu', ' ', $html );
		$text = html_entity_decode( wp_strip_all_tags( (string) $html, true ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = preg_replace( '/[\x{200c}\x{200f}\x{202a}-\x{202e}]/u', '', $text );
		return trim( (string) preg_replace( '/[\t ]+/u', ' ', preg_replace( '/\R+/u', "\n", (string) $text ) ) );
	}

	private static function find_priced_section( string $text, string $start, string $end ): string {
		$offset = 0;
		while ( false !== ( $position = strpos( $text, $start, $offset ) ) ) {
			$after = $position + strlen( $start );
			$finish = strpos( $text, $end, $after );
			if ( false === $finish ) {
				break;
			}
			$section = substr( $text, $after, $finish - $after );
			if ( strlen( $section ) > 150 && preg_match( '/تومان|رایگان/u', $section ) ) {
				return $section;
			}
			$offset = $after;
		}
		return '';
	}

	private static function extract_prices( string $section ): array {
		if ( '' === $section ) {
			return array();
		}
		preg_match_all( '/(?:رایگان|[۰-۹0-9][۰-۹0-9٬,٫\.]*\s*تومان)/u', $section, $matches );
		$prices = array_values( array_unique( array_map( 'sanitize_text_field', $matches[0] ?? array() ) ) );
		return array_slice( $prices, 0, 8 );
	}

	private static function headline_price( string $slug, array $prices, string $fallback ): string {
		if ( ! $prices ) {
			return $fallback;
		}
		if ( in_array( $slug, array( 'cdn', 'object-storage' ), true ) && in_array( 'رایگان', $prices, true ) ) {
			return __( 'شروع از رایگان', 'arvancloud-reseller' );
		}
		foreach ( $prices as $price ) {
			if ( 'رایگان' !== $price ) {
				return sprintf( __( 'از %s', 'arvancloud-reseller' ), $price );
			}
		}
		return $fallback;
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
