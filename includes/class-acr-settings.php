<?php
/**
 * Custom-table settings repository.
 *
 * @package ArvanCloudReseller
 */

defined( 'ABSPATH' ) || exit;

final class ACR_Settings {
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'acr_settings';
	}

	public static function get( string $key, mixed $default = '' ): mixed {
		global $wpdb;
		$value = wp_cache_get( $key, 'acr_settings' );
		if ( false !== $value ) {
			return $value;
		}

		$table = self::table();
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT setting_value FROM {$table} WHERE setting_key = %s", $key ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( null === $value ) {
			return $default;
		}

		if ( str_starts_with( $key, 'secret_' ) ) {
			$value = ACR_Crypto::decrypt( (string) $value );
		}

		wp_cache_set( $key, $value, 'acr_settings' );
		return $value;
	}

	public static function set( string $key, mixed $value ): bool {
		global $wpdb;
		if ( str_starts_with( $key, 'secret_' ) ) {
			$value = ACR_Crypto::encrypt( (string) $value );
		}

		$result = $wpdb->replace(
			self::table(),
			array(
				'setting_key'   => $key,
				'setting_value' => (string) $value,
				'updated_at'    => current_time( 'mysql', true ),
			),
			array( '%s', '%s', '%s' )
		);
		wp_cache_delete( $key, 'acr_settings' );
		return false !== $result;
	}

	public static function is_onboarded(): bool {
		return 'yes' === self::get( 'onboarding_completed', 'no' );
	}
}

