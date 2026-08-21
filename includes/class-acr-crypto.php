<?php
/**
 * Encryption helpers for secrets stored in the WordPress database.
 *
 * @package ArvanCloudReseller
 */

defined( 'ABSPATH' ) || exit;

final class ACR_Crypto {
	private const PREFIX = 'acr:v1:';

	public static function encrypt( string $plain_text ): string {
		if ( '' === $plain_text ) {
			return '';
		}

		$key = hash( 'sha256', wp_salt( 'auth' ), true );
		$iv  = random_bytes( 12 );
		$tag = '';
		$out = openssl_encrypt( $plain_text, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

		if ( false === $out ) {
			return '';
		}

		return self::PREFIX . base64_encode( $iv . $tag . $out ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	public static function decrypt( string $cipher_text ): string {
		if ( '' === $cipher_text || ! str_starts_with( $cipher_text, self::PREFIX ) ) {
			return '';
		}

		$decoded = base64_decode( substr( $cipher_text, strlen( self::PREFIX ) ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $decoded || strlen( $decoded ) < 29 ) {
			return '';
		}

		$iv     = substr( $decoded, 0, 12 );
		$tag    = substr( $decoded, 12, 16 );
		$cipher = substr( $decoded, 28 );
		$key    = hash( 'sha256', wp_salt( 'auth' ), true );
		$out    = openssl_decrypt( $cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag );

		return false === $out ? '' : $out;
	}
}

