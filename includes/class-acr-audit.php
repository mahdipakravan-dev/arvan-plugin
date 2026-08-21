<?php
/**
 * Persistent authentication and service activity audit log.
 *
 * @package ArvanCloudReseller
 */

defined( 'ABSPATH' ) || exit;

final class ACR_Audit {
	public static function init(): void {
		add_action( 'wp_login', array( __CLASS__, 'wordpress_login' ), 10, 2 );
		add_action( 'wp_login_failed', array( __CLASS__, 'wordpress_login_failed' ), 10, 2 );
		add_action( 'wp_logout', array( __CLASS__, 'wordpress_logout' ) );
	}

	public static function log( string $category, string $event, string $status, string $message = '', array $context = array(), int $user_id = 0 ): void {
		global $wpdb;
		$json = wp_json_encode( $context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		$wpdb->insert(
			$wpdb->prefix . 'acr_audit_logs',
			array(
				'user_id'   => $user_id ?: get_current_user_id(),
				'category'  => sanitize_key( $category ),
				'event'     => sanitize_key( $event ),
				'status'    => sanitize_key( $status ),
				'message'   => self::excerpt( sanitize_text_field( $message ), 500 ),
				'context'   => is_string( $json ) ? self::excerpt( $json, 8000 ) : '{}',
				'ip_address'=> self::client_ip(),
				'created_at'=> current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public static function wordpress_login( string $user_login, WP_User $user ): void {
		self::log( 'auth', 'wordpress_login', 'success', __( 'WordPress auth cookie established.', 'arvancloud-reseller' ), array( 'login' => $user_login ), $user->ID );
	}

	public static function wordpress_login_failed( string $username, WP_Error $error ): void {
		self::log( 'auth', 'wordpress_login_failed', 'failed', $error->get_error_message(), array( 'login' => sanitize_text_field( $username ) ) );
	}

	public static function wordpress_logout( int $user_id ): void {
		self::log( 'auth', 'wordpress_logout', 'success', __( 'WordPress session ended.', 'arvancloud-reseller' ), array(), $user_id );
	}

	private static function client_ip(): string {
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'system' ) );
	}

	private static function excerpt( string $value, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, $length ) : substr( $value, 0, $length );
	}
}
