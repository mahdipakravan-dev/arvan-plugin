<?php
/**
 * Phone-number OTP authentication backed by WordPress users and auth cookies.
 *
 * @package ArvanCloudReseller
 */

defined( 'ABSPATH' ) || exit;

final class ACR_OTP {
	private const EXPIRES_IN   = 300;
	private const MAX_ATTEMPTS = 5;

	public static function init(): void {
		add_action( 'wp_ajax_nopriv_acr_request_otp', array( __CLASS__, 'request' ) );
		add_action( 'wp_ajax_acr_request_otp', array( __CLASS__, 'request' ) );
		add_action( 'wp_ajax_nopriv_acr_verify_otp', array( __CLASS__, 'verify' ) );
		add_action( 'wp_ajax_acr_verify_otp', array( __CLASS__, 'verify' ) );
	}

	public static function request(): void {
		if ( false === check_ajax_referer( 'acr_phone_login', 'nonce', false ) ) {
			ACR_Audit::log( 'auth', 'otp_request', 'failed', __( 'OTP request nonce validation failed.', 'arvancloud-reseller' ) );
			wp_send_json_error( array( 'message' => __( 'نشست صفحه منقضی شده است؛ صفحه را تازه‌سازی کنید.', 'arvancloud-reseller' ) ), 403 );
		}
		$phone = self::normalize_phone( wp_unslash( $_POST['phone'] ?? '' ) );
		if ( '' === $phone ) {
			ACR_Audit::log( 'auth', 'otp_request', 'failed', __( 'Invalid phone number.', 'arvancloud-reseller' ) );
			wp_send_json_error( array( 'message' => __( 'شماره موبایل معتبر وارد کنید.', 'arvancloud-reseller' ) ), 400 );
		}

		$rate_key = 'acr_otp_rate_' . hash_hmac( 'sha256', $phone . '|' . self::client_ip(), wp_salt( 'nonce' ) );
		if ( get_transient( $rate_key ) ) {
			ACR_Audit::log( 'auth', 'otp_request', 'rate_limited', __( 'OTP resend requested too soon.', 'arvancloud-reseller' ), array( 'phone' => self::mask_phone( $phone ) ) );
			wp_send_json_error( array( 'message' => __( 'لطفاً یک دقیقه تا ارسال مجدد کد صبر کنید.', 'arvancloud-reseller' ) ), 429 );
		}
		$ip_rate_key = 'acr_otp_ip_' . hash_hmac( 'sha256', self::client_ip(), wp_salt( 'nonce' ) );
		$ip_requests = absint( get_transient( $ip_rate_key ) );
		if ( $ip_requests >= 10 ) {
			ACR_Audit::log( 'auth', 'otp_request', 'rate_limited', __( 'Hourly OTP request limit reached.', 'arvancloud-reseller' ), array( 'phone' => self::mask_phone( $phone ) ) );
			wp_send_json_error( array( 'message' => __( 'تعداد درخواست‌های این دستگاه بیش از حد مجاز است؛ بعداً تلاش کنید.', 'arvancloud-reseller' ) ), 429 );
		}
		set_transient( $ip_rate_key, $ip_requests + 1, HOUR_IN_SECONDS );

		$request_id = wp_generate_password( 32, false, false );
		$otp        = '1111';
		set_transient(
			'acr_otp_' . $request_id,
			array(
				'phone'    => $phone,
				'hash'     => wp_hash_password( $otp ),
				'attempts' => 0,
			),
			self::EXPIRES_IN
		);
		set_transient( $rate_key, 1, MINUTE_IN_SECONDS );

		$data = array(
			'message'    => __( 'کد آزمایشی ۱۱۱۱ برای ورود فعال است.', 'arvancloud-reseller' ),
			'requestId'  => $request_id,
			'expiresIn'  => self::EXPIRES_IN,
			'maskedPhone' => self::mask_phone( $phone ),
			'demoOtp'    => $otp,
		);
		ACR_Audit::log( 'auth', 'otp_sent_mock', 'success', __( 'Mock OTP 1111 generated.', 'arvancloud-reseller' ), array( 'phone' => self::mask_phone( $phone ), 'request_id' => $request_id ) );
		wp_send_json_success( $data );
	}

	public static function verify(): void {
		if ( false === check_ajax_referer( 'acr_phone_login', 'nonce', false ) ) {
			ACR_Audit::log( 'auth', 'otp_verify', 'failed', __( 'OTP verification nonce validation failed.', 'arvancloud-reseller' ) );
			wp_send_json_error( array( 'message' => __( 'نشست صفحه منقضی شده است؛ صفحه را تازه‌سازی کنید.', 'arvancloud-reseller' ) ), 403 );
		}
		$phone = self::normalize_phone( wp_unslash( $_POST['phone'] ?? '' ) );
		$otp   = preg_replace( '/\D+/', '', self::latin_digits( wp_unslash( $_POST['otp'] ?? '' ) ) );
		if ( '' === $phone ) {
			ACR_Audit::log( 'auth', 'otp_verify', 'failed', __( 'Invalid phone number.', 'arvancloud-reseller' ) );
			wp_send_json_error( array( 'message' => __( 'شماره موبایل معتبر نیست؛ شماره را اصلاح کنید.', 'arvancloud-reseller' ) ), 400 );
		}

		$attempt_key = 'acr_otp_attempts_' . hash_hmac( 'sha256', $phone . '|' . self::client_ip(), wp_salt( 'nonce' ) );
		$attempts    = absint( get_transient( $attempt_key ) );
		if ( $attempts >= self::MAX_ATTEMPTS ) {
			ACR_Audit::log( 'auth', 'otp_verify', 'blocked', __( 'Maximum OTP attempts reached.', 'arvancloud-reseller' ), array( 'phone' => self::mask_phone( $phone ) ) );
			wp_send_json_error( array( 'message' => __( 'تعداد تلاش بیش از حد مجاز بود؛ کد جدید دریافت کنید.', 'arvancloud-reseller' ) ), 429 );
		}
		if ( '1111' !== $otp ) {
			set_transient( $attempt_key, $attempts + 1, 15 * MINUTE_IN_SECONDS );
			ACR_Audit::log( 'auth', 'otp_verify', 'failed', __( 'Incorrect mock OTP.', 'arvancloud-reseller' ), array( 'phone' => self::mask_phone( $phone ), 'attempt' => $attempts + 1 ) );
			wp_send_json_error( array( 'message' => __( 'کد واردشده صحیح نیست.', 'arvancloud-reseller' ) ), 401 );
		}

		delete_transient( $attempt_key );
		ACR_Audit::log( 'auth', 'otp_sent_mock', 'success', __( 'Mock OTP 1111 accepted for verification.', 'arvancloud-reseller' ), array( 'phone' => self::mask_phone( $phone ) ) );
		$user = self::user_for_phone( $phone );
		if ( is_wp_error( $user ) ) {
			ACR_Audit::log( 'auth', 'user_registration', 'failed', $user->get_error_message(), array( 'phone' => self::mask_phone( $phone ) ) );
			wp_send_json_error( array( 'message' => $user->get_error_message() ), 500 );
		}

		wp_clear_auth_cookie();
		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true, is_ssl() );
		do_action( 'wp_login', $user->user_login, $user );
		ACR_Audit::log( 'auth', 'otp_verify', 'success', __( 'OTP verified and system login completed.', 'arvancloud-reseller' ), array( 'phone' => self::mask_phone( $phone ) ), $user->ID );
		wp_send_json_success(
			array(
				'message'  => __( 'ورود با موفقیت انجام شد.', 'arvancloud-reseller' ),
				'redirect' => self::safe_redirect_url( wp_unslash( $_POST['redirect'] ?? '' ) ),
			)
		);
	}

	private static function user_for_phone( string $phone ): WP_User|WP_Error {
		$local_phone = '0' . substr( $phone, 3 );
		$users = get_users(
			array(
				'number'     => 2,
				'count_total'=> false,
				'meta_query' => array(
					'relation' => 'OR',
					array( 'key' => '_acr_phone', 'value' => array( $phone, $local_phone ), 'compare' => 'IN' ),
					array( 'key' => 'billing_phone', 'value' => array( $phone, $local_phone ), 'compare' => 'IN' ),
				),
			)
		);
		if ( count( $users ) > 1 ) {
			return new WP_Error( 'acr_duplicate_phone', __( 'این شماره به بیش از یک حساب متصل است؛ با مدیر سایت تماس بگیرید.', 'arvancloud-reseller' ) );
		}
		if ( $users ) {
			$user = $users[0];
			update_user_meta( $user->ID, '_acr_phone', $phone );
			ACR_Audit::log( 'auth', 'user_resolved', 'existing', __( 'Existing WordPress user matched by phone.', 'arvancloud-reseller' ), array( 'phone' => self::mask_phone( $phone ) ), $user->ID );
			return $user;
		}
		if ( ! get_option( 'users_can_register' ) ) {
			return new WP_Error( 'acr_registration_disabled', __( 'ثبت‌نام کاربران در تنظیمات وردپرس غیرفعال است.', 'arvancloud-reseller' ) );
		}

		$user_id = wp_insert_user(
			array(
				'user_login'   => 'acr_' . substr( hash_hmac( 'sha256', $phone, wp_salt( 'auth' ) ), 0, 20 ),
				'user_pass'    => wp_generate_password( 32, true, true ),
				'display_name' => sprintf( __( 'کاربر %s', 'arvancloud-reseller' ), self::mask_phone( $phone ) ),
				'role'         => 'subscriber',
			)
		);
		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}
		update_user_meta( $user_id, '_acr_phone', $phone );
		ACR_Audit::log( 'auth', 'user_registered', 'success', __( 'New WordPress subscriber created from phone login.', 'arvancloud-reseller' ), array( 'phone' => self::mask_phone( $phone ) ), $user_id );
		$user = get_user_by( 'id', $user_id );
		return $user instanceof WP_User ? $user : new WP_Error( 'acr_user_missing', __( 'ساخت حساب وردپرس کامل نشد.', 'arvancloud-reseller' ) );
	}

	private static function normalize_phone( mixed $raw ): string {
		$phone = preg_replace( '/[^0-9+]/', '', self::latin_digits( (string) $raw ) );
		if ( preg_match( '/^09\d{9}$/', $phone ) ) {
			return '+98' . substr( $phone, 1 );
		}
		if ( preg_match( '/^(?:\+98|0098|98)9\d{9}$/', $phone ) ) {
			return '+98' . substr( preg_replace( '/^(?:\+98|0098|98)/', '', $phone ), 0 );
		}
		return '';
	}

	private static function latin_digits( string $value ): string {
		return strtr( $value, array( '۰'=>'0', '۱'=>'1', '۲'=>'2', '۳'=>'3', '۴'=>'4', '۵'=>'5', '۶'=>'6', '۷'=>'7', '۸'=>'8', '۹'=>'9', '٠'=>'0', '١'=>'1', '٢'=>'2', '٣'=>'3', '٤'=>'4', '٥'=>'5', '٦'=>'6', '٧'=>'7', '٨'=>'8', '٩'=>'9' ) );
	}

	private static function mask_phone( string $phone ): string {
		return substr( $phone, 0, 5 ) . '***' . substr( $phone, -3 );
	}

	private static function client_ip(): string {
		return sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? 'unknown' ) );
	}

	private static function safe_redirect_url( mixed $url ): string {
		$fallback = home_url( '/' );
		return wp_validate_redirect( esc_url_raw( (string) $url ), $fallback );
	}
}
