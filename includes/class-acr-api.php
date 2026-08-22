<?php
/**
 * ArvanCloud CDN adapter and demo provisioner.
 *
 * @package ArvanCloudReseller
 */

defined( 'ABSPATH' ) || exit;

final class ACR_API {
	private const CDN_BASE = 'https://napi.arvancloud.ir/cdn/4.0';
	private const ECC_BASE = 'https://napi.arvancloud.ir/ecc/v1';
	private const ECC_V3_HOST = 'ecc.%s.arvanapis.ir';
	private const SNAPSHOT_TRANSIENT = 'acr_api_snapshot_v2';
	private static array $diagnostics = array();

	public static function reset_diagnostics(): void {
		self::$diagnostics = array();
	}

	public static function diagnostics(): array {
		return self::$diagnostics;
	}

	public static function test_connection(): array {
		return self::request( 'GET', '/domains' );
	}

	public static function clear_snapshot(): void {
		delete_transient( self::SNAPSHOT_TRANSIENT );
	}

	public static function dashboard_snapshot( bool $force = false ): array {
		if ( ! $force ) {
			$cached = get_transient( self::SNAPSHOT_TRANSIENT );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$snapshot = 'demo' === ACR_Settings::get( 'api_mode', 'demo' )
			? self::demo_snapshot()
			: self::live_snapshot();

		$snapshot['refreshed_at'] = current_time( 'mysql' );
		set_transient( self::SNAPSHOT_TRANSIENT, $snapshot, 3 * MINUTE_IN_SECONDS );
		return $snapshot;
	}

	private static function demo_snapshot(): array {
		global $wpdb;
		$domains = array();
		$rows    = $wpdb->get_results( "SELECT external_id, status, configuration, created_at FROM {$wpdb->prefix}acr_resources WHERE product_type = 'cdn' ORDER BY id DESC LIMIT 20", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		foreach ( $rows as $row ) {
			$config    = json_decode( $row['configuration'], true );
			$domains[] = array(
				'id'         => $row['external_id'],
				'name'       => $config['domain'] ?? $row['external_id'],
				'status'     => $row['status'],
				'plan'       => 'pay-as-you-go',
				'created_at' => $row['created_at'],
			);
		}

		$snapshot = array(
			'mode'          => 'demo',
			'cdn'           => array( 'success' => true, 'items' => $domains, 'count' => count( $domains ), 'endpoint' => '/cdn/4.0/domains' ),
			'cloud_servers' => array( 'success' => true, 'items' => array(), 'count' => 0, 'regions' => self::regions(), 'endpoint' => '/ecc/v1/regions/{region}/servers' ),
			'object_storage' => array( 'success' => true, 'items' => array(), 'count' => 0, 'endpoint' => 'S3 ListBuckets', 'configured' => false ),
		);
		self::$diagnostics[] = array(
			'label'    => __( 'داده‌های آزمایشی محلی', 'arvancloud-reseller' ),
			'request'  => array( 'method' => 'LOCAL', 'endpoint' => __( 'پایگاه داده وردپرس (حالت دمو)', 'arvancloud-reseller' ) ),
			'response' => $snapshot,
		);
		return $snapshot;
	}

	private static function live_snapshot(): array {
		$cdn = self::request( 'GET', '/domains' );
		$cdn_items = $cdn['success'] ? self::normalize_domains( self::payload_list( $cdn['data'] ) ) : array();

		$servers = array();
		$server_errors = array();
		foreach ( self::regions() as $region ) {
			$result = self::request_url( 'GET', self::ECC_BASE . '/regions/' . rawurlencode( $region ) . '/servers' );
			if ( ! $result['success'] ) {
				$server_errors[] = $region . ': ' . ( $result['message'] ?? 'API error' );
				continue;
			}
			$servers = array_merge( $servers, self::normalize_servers( self::payload_list( $result['data'] ), $region ) );
		}

		$storage = self::list_buckets();
		return array(
			'mode'          => 'live',
			'cdn'           => array(
				'success'  => (bool) $cdn['success'],
				'items'    => $cdn_items,
				'count'    => count( $cdn_items ),
				'endpoint' => '/cdn/4.0/domains',
				'message'  => $cdn['message'] ?? '',
			),
			'cloud_servers' => array(
				'success'  => empty( $server_errors ),
				'items'    => $servers,
				'count'    => count( $servers ),
				'regions'  => self::regions(),
				'endpoint' => '/ecc/v1/regions/{region}/servers',
				'message'  => implode( ' | ', $server_errors ),
			),
			'object_storage' => $storage,
		);
	}

	public static function regions(): array {
		$raw = (string) ACR_Settings::get( 'cloud_regions', 'ir-thr-c2' );
		$regions = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', $raw ) ) ) );
		return $regions ?: array( 'ir-thr-c2' );
	}

	public static function provision_server( array $configuration ): array {
		$region = sanitize_key( (string) ( $configuration['region'] ?? '' ) );
		if ( ! in_array( $region, self::regions(), true ) ) {
			return array( 'success' => false, 'message' => __( 'منطقه انتخاب‌شده مجاز نیست.', 'arvancloud-reseller' ) );
		}

		if ( 'demo' === ACR_Settings::get( 'api_mode', 'demo' ) ) {
			return array(
				'success'     => true,
				'resource_id' => 'demo-server-' . wp_rand( 1000, 9999 ),
				'data'        => array( 'data' => array( 'state' => 'ACTIVE', 'name' => $configuration['name'] ) ),
			);
		}

		$body = array(
			'availabilityZone'        => sanitize_text_field( (string) $configuration['availability_zone'] ),
			'enableIpv4'              => true,
			'enableIpv6'              => false,
			'flavorId'                => sanitize_text_field( (string) $configuration['flavor_id'] ),
			'imageId'                 => sanitize_text_field( (string) $configuration['image_id'] ),
			'name'                    => sanitize_text_field( (string) $configuration['name'] ),
			'rootVolumeSizeGigaBytes' => absint( $configuration['root_disk'] ),
		);
		$result = self::request_url( 'POST', self::server_url( $region, '/servers' ), $body );
		if ( ! $result['success'] ) {
			return $result;
		}
		$data = $result['data']['data'] ?? $result['data'];
		return array(
			'success'     => true,
			'resource_id' => sanitize_text_field( (string) ( $data['id'] ?? '' ) ),
			'data'        => $result['data'],
		);
	}

	public static function manage_server( string $region, string $server_id, string $action, array $values = array() ): array {
		$region = sanitize_key( $region );
		if ( ! in_array( $region, self::regions(), true ) ) {
			return array( 'success' => false, 'message' => __( 'منطقه سرور مجاز نیست.', 'arvancloud-reseller' ) );
		}
		$actions = array(
			'power_on'      => array( '/power-on', array() ),
			'power_off'     => array( '/power-off', array() ),
			'reboot'        => array( '/reboot', array() ),
			'reset_password' => array( '/reset-root-password', array() ),
			'rescue'        => array( '/rescue', array() ),
			'unrescue'      => array( '/unrescue', array() ),
			'terminate'     => array( '/terminate', array() ),
			'rename'        => array( '/rename', array( 'name' => sanitize_text_field( (string) ( $values['name'] ?? '' ) ) ) ),
			'resize'        => array( '/resize', array( 'flavorId' => sanitize_text_field( (string) ( $values['flavor_id'] ?? '' ) ) ) ),
			'resize_disk'   => array( '/resize-root-disk', array( 'newSizeGigaBytes' => absint( $values['root_disk'] ?? 0 ) ) ),
		);
		if ( ! isset( $actions[ $action ] ) ) {
			return array( 'success' => false, 'message' => __( 'عملیات سرور معتبر نیست.', 'arvancloud-reseller' ) );
		}
		if ( ( 'rename' === $action && '' === $actions[ $action ][1]['name'] ) || ( 'resize' === $action && '' === $actions[ $action ][1]['flavorId'] ) || ( 'resize_disk' === $action && $actions[ $action ][1]['newSizeGigaBytes'] < 1 ) ) {
			return array( 'success' => false, 'message' => __( 'مقدار عملیات سرور معتبر نیست.', 'arvancloud-reseller' ) );
		}
		if ( 'demo' === ACR_Settings::get( 'api_mode', 'demo' ) ) {
			return array( 'success' => true, 'data' => array( 'action' => $action ) );
		}
		$body = array_merge( array( 'availabilityZone' => sanitize_text_field( (string) ( $values['availability_zone'] ?? '' ) ) ), $actions[ $action ][1] );
		return self::request_url( 'POST', self::server_url( $region, '/servers/' . rawurlencode( $server_id ) . $actions[ $action ][0] ), $body );
	}

	private static function server_url( string $region, string $path ): string {
		return 'https://' . sprintf( self::ECC_V3_HOST, $region ) . '/v3' . $path;
	}

	private static function payload_list( array $data ): array {
		$candidates = array( $data['data']['data'] ?? null, $data['data'] ?? null, $data['items'] ?? null, $data );
		foreach ( $candidates as $candidate ) {
			if ( is_array( $candidate ) && array_is_list( $candidate ) ) {
				return $candidate;
			}
		}
		return array();
	}

	private static function normalize_domains( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$out[] = array(
				'id'         => sanitize_text_field( (string) ( $item['id'] ?? $item['domain'] ?? '' ) ),
				'name'       => sanitize_text_field( (string) ( $item['domain'] ?? $item['name'] ?? '—' ) ),
				'status'     => sanitize_key( (string) ( $item['status'] ?? $item['dns_status'] ?? 'unknown' ) ),
				'plan'       => sanitize_text_field( (string) ( $item['plan']['name'] ?? $item['plan'] ?? '—' ) ),
				'created_at' => sanitize_text_field( (string) ( $item['created_at'] ?? '—' ) ),
			);
		}
		return $out;
	}

	private static function normalize_servers( array $items, string $region ): array {
		$out = array();
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$addresses = $item['addresses'] ?? array();
			$ip = '';
			if ( is_array( $addresses ) ) {
				foreach ( $addresses as $network ) {
					if ( is_array( $network ) && isset( $network[0]['addr'] ) ) {
						$ip = (string) $network[0]['addr'];
						break;
					}
				}
			}
			$out[] = array(
				'id'     => sanitize_text_field( (string) ( $item['id'] ?? '' ) ),
				'name'   => sanitize_text_field( (string) ( $item['name'] ?? $item['hostname'] ?? '—' ) ),
				'status' => sanitize_key( (string) ( $item['status'] ?? 'unknown' ) ),
				'region' => sanitize_key( $region ),
				'ip'     => sanitize_text_field( $ip ),
				'flavor' => sanitize_text_field( (string) ( $item['flavor']['name'] ?? $item['flavor_name'] ?? '—' ) ),
			);
		}
		return $out;
	}

	private static function list_buckets(): array {
		$access_key = (string) ACR_Settings::get( 'secret_s3_access_key', '' );
		$secret_key = (string) ACR_Settings::get( 'secret_s3_secret_key', '' );
		$endpoint   = untrailingslashit( (string) ACR_Settings::get( 's3_endpoint', '' ) );
		if ( '' === $access_key || '' === $secret_key || '' === $endpoint ) {
			$result = array( 'success' => false, 'configured' => false, 'items' => array(), 'count' => 0, 'endpoint' => 'S3 ListBuckets', 'message' => __( 'کلیدهای S3 تنظیم نشده‌اند.', 'arvancloud-reseller' ) );
			self::$diagnostics[] = array( 'label' => 'S3 ListBuckets', 'request' => array( 'method' => 'GET', 'endpoint' => $endpoint ?: '—', 'authorization' => '[REDACTED]' ), 'response' => $result );
			return $result;
		}

		$date      = gmdate( 'D, d M Y H:i:s \G\M\T' );
		$string    = "GET\n\n\n{$date}\n/";
		$signature = base64_encode( hash_hmac( 'sha1', $string, $secret_key, true ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		$response  = wp_remote_get(
			$endpoint . '/',
			array(
				'timeout' => 20,
				'headers' => array( 'Date' => $date, 'Authorization' => 'AWS ' . $access_key . ':' . $signature ),
			)
		);
		if ( is_wp_error( $response ) ) {
			$result = array( 'success' => false, 'configured' => true, 'items' => array(), 'count' => 0, 'endpoint' => 'S3 ListBuckets', 'message' => $response->get_error_message() );
			self::$diagnostics[] = array( 'label' => 'S3 ListBuckets', 'request' => array( 'method' => 'GET', 'endpoint' => $endpoint . '/', 'authorization' => '[REDACTED]' ), 'response' => $result );
			return $result;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$items  = array();
		$xml    = function_exists( 'simplexml_load_string' ) ? @simplexml_load_string( wp_remote_retrieve_body( $response ), 'SimpleXMLElement', LIBXML_NONET ) : false;
		if ( $xml && isset( $xml->Buckets->Bucket ) ) {
			foreach ( $xml->Buckets->Bucket as $bucket ) {
				$items[] = array( 'name' => sanitize_text_field( (string) $bucket->Name ), 'created_at' => sanitize_text_field( (string) $bucket->CreationDate ), 'status' => 'active' );
			}
		}
		$result = array(
			'success'    => $status >= 200 && $status < 300,
			'status'     => $status,
			'configured' => true,
			'items'      => $items,
			'count'      => count( $items ),
			'endpoint'   => 'S3 ListBuckets',
			'message'    => $status >= 200 && $status < 300 ? '' : __( 'دریافت Bucketها ناموفق بود.', 'arvancloud-reseller' ),
		);
		$diagnostic_response          = $result;
		$diagnostic_response['items'] = array_slice( $items, 0, 50 );
		self::$diagnostics[] = array( 'label' => 'S3 ListBuckets', 'request' => array( 'method' => 'GET', 'endpoint' => $endpoint . '/', 'authorization' => '[REDACTED]' ), 'response' => $diagnostic_response );
		return $result;
	}

	public static function provision_cdn( string $domain ): array {
		if ( 'demo' === ACR_Settings::get( 'api_mode', 'demo' ) ) {
			return array(
				'success'     => true,
				'resource_id' => 'demo-' . sanitize_title( $domain ) . '-' . wp_rand( 1000, 9999 ),
				'data'        => array( 'domain' => $domain, 'mode' => 'demo' ),
			);
		}

		$response = self::request(
			'POST',
			'/domains/dns-service',
			array(
				'domain'      => $domain,
				'domain_type' => 'full',
			)
		);
		if ( ! $response['success'] ) {
			return $response;
		}

		$data = $response['data'];
		$id   = $data['data']['id'] ?? $data['id'] ?? $domain;
		return array(
			'success'     => true,
			'resource_id' => sanitize_text_field( (string) $id ),
			'data'        => $data,
		);
	}

	public static function list_cdn_plans( array $query = array() ): array {
		$url = self::CDN_BASE . '/plans';
		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}
		return self::request_url( 'GET', $url );
	}

	public static function restrict_resource( object $resource, string $action ): bool {
		if ( 'demo' === ACR_Settings::get( 'api_mode', 'demo' ) ) {
			return true;
		}

		/**
		 * Live restriction is deliberately filterable because suspend policy and API
		 * permissions differ by product/account. Returning true marks the action handled.
		 */
		return (bool) apply_filters( 'acr_restrict_live_resource', false, $resource, $action );
	}

	private static function request( string $method, string $path, array $body = array() ): array {
		return self::request_url( $method, self::CDN_BASE . $path, $body );
	}

	private static function request_url( string $method, string $url, array $body = array() ): array {
		$token = (string) ACR_Settings::get( 'secret_machine_token', '' );
		if ( '' === $token ) {
			$result = array( 'success' => false, 'status' => 0, 'message' => __( 'توکن Machine User ثبت نشده است.', 'arvancloud-reseller' ) );
			self::record_diagnostic( $method, $url, $body, $result );
			return $result;
		}

		$args = array(
			'method'      => $method,
			'timeout'     => 20,
			'redirection' => 2,
			'headers'     => array(
				'Accept'        => 'application/json',
				'Authorization' => self::authorization_header( $token ),
				'Content-Type'  => 'application/json',
			),
		);
		if ( $body ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );
		if ( is_wp_error( $response ) ) {
			$result = array( 'success' => false, 'status' => 0, 'message' => $response->get_error_message() );
			self::record_diagnostic( $method, $url, $body, $result );
			return $result;
		}
		$status       = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );
		$data         = json_decode( $response_body, true );
		$result = array(
			'success' => $status >= 200 && $status < 300,
			'status'  => $status,
			'data'    => is_array( $data ) ? $data : array(),
			'message' => sanitize_text_field( (string) ( $data['message'] ?? __( 'پاسخ نامعتبر از API دریافت شد.', 'arvancloud-reseller' ) ) ),
		);
		self::record_diagnostic(
			$method,
			$url,
			$body,
			array(
				'success'      => $result['success'],
				'status'       => $status,
				'message'      => $result['message'],
				'body_excerpt' => function_exists( 'mb_substr' ) ? mb_substr( $response_body, 0, 12000 ) : substr( $response_body, 0, 12000 ),
			)
		);
		return $result;
	}

	private static function record_diagnostic( string $method, string $url, array $body, array $response ): void {
		self::$diagnostics[] = array(
			'label'    => $method . ' ' . (string) wp_parse_url( $url, PHP_URL_PATH ),
			'request'  => array(
				'method'        => $method,
				'endpoint'      => $url,
				'authorization' => '[REDACTED]',
				'body'          => $body ?: null,
			),
			'response' => $response,
		);
	}

	private static function authorization_header( string $token ): string {
		$token = trim( $token );
		if ( preg_match( '/^(?:Bearer|apikey)\s+/i', $token ) ) {
			return $token;
		}
		return 'apikey ' . $token;
	}
}
