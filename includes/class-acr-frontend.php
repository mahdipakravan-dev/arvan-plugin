<?php
/**
 * Customer portal shortcode and self-service actions.
 *
 * @package ArvanCloudReseller
 */

defined( 'ABSPATH' ) || exit;

final class ACR_Frontend {
	public static function init(): void {
		add_shortcode( 'arvan_reseller_portal', array( __CLASS__, 'portal' ) );
		add_shortcode( 'arvan_reseller_products', array( __CLASS__, 'products' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ) );
		add_action( 'admin_post_acr_mock_topup', array( __CLASS__, 'topup' ) );
		add_action( 'admin_post_acr_order_cdn', array( __CLASS__, 'order' ) );
		add_action( 'admin_post_acr_order_server', array( __CLASS__, 'order_server' ) );
		add_action( 'admin_post_acr_manage_server', array( __CLASS__, 'manage_server' ) );
	}

	public static function products( array $attributes = array() ): string {
		return ACR_Blocks::render_catalog( $attributes );
	}

	public static function register_assets(): void {
		$css_version = (string) filemtime( ACR_PATH . 'assets/css/frontend.css' );
		$js_version  = (string) filemtime( ACR_PATH . 'assets/js/frontend.js' );
		wp_register_style( 'acr-frontend', ACR_URL . 'assets/css/frontend.css', array(), $css_version );
		wp_register_script( 'acr-frontend', ACR_URL . 'assets/js/frontend.js', array(), $js_version, true );
		wp_enqueue_style( 'acr-frontend' );
		wp_enqueue_script( 'acr-frontend' );
		wp_localize_script(
			'acr-frontend',
			'acrPhoneAuth',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'acr_phone_login' ),
				'redirect' => ( is_singular() ? get_permalink() : home_url( '/' ) ) . '#acr-customer-profile',
			)
		);
	}

	public static function portal(): string {
		wp_enqueue_style( 'acr-frontend' );
		wp_enqueue_script( 'acr-frontend' );
		wp_enqueue_style( 'dashicons' );
		if ( ! is_user_logged_in() ) {
			ob_start();
			?>
			<div class="acr-portal acr-login" id="acr-customer-profile" dir="rtl">
				<div class="acr-login__icon"><span class="dashicons dashicons-smartphone"></span></div>
				<h2><?php esc_html_e( 'ورود به پنل مشتری با شماره موبایل', 'arvancloud-reseller' ); ?></h2>
				<p><?php esc_html_e( 'شماره موبایل خود را وارد کنید. پس از تأیید کد، حساب وردپرس شما ساخته یا بازیابی می‌شود.', 'arvancloud-reseller' ); ?></p>
				<div class="acr-auth-message" role="status" aria-live="polite"></div>
				<form class="acr-phone-auth" data-step="phone">
					<div class="acr-phone-auth__phone"><label for="acr-phone"><?php esc_html_e( 'شماره موبایل', 'arvancloud-reseller' ); ?></label><input id="acr-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" placeholder="09121234567" required><button class="acr-p-btn" type="submit"><span class="dashicons dashicons-arrow-left-alt"></span><?php esc_html_e( 'دریافت کد ورود', 'arvancloud-reseller' ); ?></button></div>
					<div class="acr-phone-auth__otp" hidden><label for="acr-otp"><?php esc_html_e( 'کد یک‌بارمصرف (فعلاً ۱۱۱۱)', 'arvancloud-reseller' ); ?></label><input id="acr-otp" name="otp" type="text" inputmode="numeric" autocomplete="one-time-code" dir="ltr" maxlength="4" pattern="[0-9۰-۹]{4}" required disabled><button class="acr-p-btn" type="submit"><span class="dashicons dashicons-yes-alt"></span><?php esc_html_e( 'تأیید و ورود', 'arvancloud-reseller' ); ?></button><button class="acr-auth-back" type="button"><?php esc_html_e( 'تغییر شماره', 'arvancloud-reseller' ); ?></button></div>
				</form>
			</div>
			<?php
			return (string) ob_get_clean();
		}

		global $wpdb;
		$user_id      = get_current_user_id();
		$user         = wp_get_current_user();
		$wallet       = ACR_Wallet::get( $user_id );
		$resources    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}acr_resources WHERE user_id=%d ORDER BY id DESC LIMIT 20", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$transactions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}acr_transactions WHERE user_id=%d ORDER BY id DESC LIMIT 10", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$notice       = sanitize_key( wp_unslash( $_GET['acr_portal_notice'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$required     = (float) ACR_Settings::get( 'demo_hourly_cost', 1200 ) * ( 1 + min( 20, max( 0, (float) ACR_Settings::get( 'markup_percent', 10 ) ) ) / 100 );
		$can_order    = (float) $wallet->balance >= $required;
		$messages     = array(
			'top_up_ok'   => 'کیف پول با موفقیت شارژ شد.',
			'order_ok'    => 'سرویس CDN ساخته شد و مصرف آن از کیف پول محاسبه می‌شود.',
			'order_error' => 'ساخت سرویس ناموفق بود؛ اطلاعات دامنه یا اتصال API را بررسی کنید.',
			'low_balance' => 'برای سفارش سرویس، ابتدا کیف پول را شارژ کنید.',
			'server_ok'   => 'درخواست ساخت سرور ثبت شد و سرور به پنل شما اضافه شد.',
			'server_error' => 'عملیات سرور ناموفق بود؛ ورودی‌ها و اتصال API را بررسی کنید.',
			'server_action_ok' => 'عملیات سرور با موفقیت ثبت شد.',
		);

		ob_start();
		?>
		<div class="acr-portal" id="acr-customer-profile" dir="rtl">
			<aside class="acr-dashboard-sidebar">
				<div class="acr-sidebar-brand"><span class="acr-p-logo"><span class="dashicons dashicons-cloud"></span></span><span><strong><?php echo esc_html( (string) ACR_Settings::get( 'organization_name', get_bloginfo( 'name' ) ) ); ?></strong><small>پنل خدمات ابری</small></span></div>
				<nav class="acr-sidebar-nav" aria-label="منوی حساب کاربری">
					<button type="button" class="is-active" data-acr-tab="profile" aria-controls="acr-tab-profile"><span class="dashicons dashicons-admin-users"></span><span>پروفایل</span></button>
					<button type="button" data-acr-tab="services" aria-controls="acr-tab-services"><span class="dashicons dashicons-cloud"></span><span>سرویس‌ها</span><b><?php echo esc_html( count( $resources ) ); ?></b></button>
					<button type="button" data-acr-tab="wallet" aria-controls="acr-tab-wallet"><span class="dashicons dashicons-money-alt"></span><span>کیف پول</span></button>
					<button type="button" data-acr-tab="settings" aria-controls="acr-tab-settings"><span class="dashicons dashicons-admin-generic"></span><span>تنظیمات</span></button>
				</nav>
				<div class="acr-sidebar-user"><span><span class="dashicons dashicons-admin-users"></span></span><div><strong><?php echo esc_html( $user->display_name ); ?></strong><small><?php echo esc_html( $user->user_email ); ?></small></div><a href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>" aria-label="خروج"><span class="dashicons dashicons-exit"></span></a></div>
			</aside>

			<div class="acr-dashboard-main" role="main">
				<?php if ( isset( $messages[ $notice ] ) ) : ?><div class="acr-p-notice <?php echo in_array( $notice, array( 'order_error', 'server_error', 'low_balance' ), true ) ? 'is-error' : ''; ?>"><?php echo esc_html( $messages[ $notice ] ); ?></div><?php endif; ?>
				<section class="acr-tab-panel is-active" id="acr-tab-profile" data-acr-panel="profile">
					<header class="acr-content-head"><div><span>داشبورد حساب</span><h1>سلام <?php echo esc_html( $user->display_name ); ?> 👋</h1><p>از اینجا وضعیت حساب و سرویس‌های ابری خود را مدیریت کنید.</p></div><button class="acr-p-btn" type="button" data-acr-go="services"><span class="dashicons dashicons-plus-alt2"></span>سرویس جدید</button></header>
					<div class="acr-overview-grid"><article><span class="dashicons dashicons-cloud"></span><div><small>کل سرویس‌ها</small><strong><?php echo esc_html( count( $resources ) ); ?></strong></div></article><article><span class="dashicons dashicons-money-alt"></span><div><small>موجودی کیف پول</small><strong><?php echo esc_html( number_format_i18n( (float) $wallet->balance ) ); ?> <i>تومان</i></strong></div></article><article><span class="dashicons dashicons-yes-alt"></span><div><small>وضعیت حساب</small><strong><?php echo $can_order ? 'آماده سفارش' : 'نیازمند شارژ'; ?></strong></div></article></div>
					<section class="acr-section-card"><div class="acr-section-title"><div><h2>سرویس‌های اخیر</h2><p>آخرین منابع ایجادشده در حساب شما</p></div><button type="button" data-acr-go="services">مشاهده همه</button></div><?php self::render_resources( array_slice( $resources, 0, 3 ) ); ?></section>
				</section>

				<section class="acr-tab-panel" id="acr-tab-services" data-acr-panel="services" hidden>
					<header class="acr-content-head"><div><span>زیرساخت ابری</span><h1>سرویس‌های من</h1><p>سرویس‌های فعال را مدیریت کنید یا یک سرویس تازه بسازید.</p></div><button class="acr-p-btn" type="button" data-acr-toggle-create aria-expanded="false"><span class="dashicons dashicons-plus-alt2"></span>ساخت سرویس جدید</button></header>
					<div class="acr-create-service" hidden>
						<div class="acr-create-tabs"><button type="button" class="is-active" data-acr-create="server"><span class="dashicons dashicons-cloud"></span>سرور ابری</button><button type="button" data-acr-create="cdn"><span class="dashicons dashicons-admin-site-alt3"></span>شبکه توزیع محتوا</button></div>
						<section class="acr-create-panel is-active" data-acr-create-panel="server"><div class="acr-form-intro"><span class="dashicons dashicons-cloud"></span><div><h2>ساخت سرور ابری</h2><p>مشخصات زیر را وارد کنید؛ بعد از ثبت، سرور به فهرست سرویس‌ها اضافه می‌شود.</p></div></div><?php if ( $can_order ) : ?><form class="acr-server-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="acr_order_server"><?php wp_nonce_field( 'acr_order_server' ); ?><label><span>نام سرور</span><small>یک نام انگلیسی برای شناسایی سرور</small><input type="text" name="server_name" dir="ltr" placeholder="my-cloud-server" minlength="3" maxlength="63" pattern="[A-Za-z0-9][A-Za-z0-9.-]{1,61}[A-Za-z0-9]" required></label><label><span>منطقه</span><small>نزدیک‌ترین موقعیت به کاربران</small><select name="region" dir="ltr" required><?php foreach ( ACR_API::regions() as $region ) : ?><option value="<?php echo esc_attr( $region ); ?>"><?php echo esc_html( $region ); ?></option><?php endforeach; ?></select></label><label><span>ناحیه دسترس‌پذیری</span><small>Availability Zone</small><input type="text" name="availability_zone" dir="ltr" value="ir-thr-c2-a" placeholder="ir-thr-c2-a" required></label><label><span>پلن سخت‌افزاری</span><small>Flavor ID</small><input type="text" name="flavor_id" dir="ltr" value="g2-2-2-0" placeholder="g2-2-2-0" required></label><label class="is-wide"><span>تصویر سیستم‌عامل</span><small>شناسه Image مورد نظر را وارد کنید</small><input type="text" name="image_id" dir="ltr" placeholder="Image UUID" required></label><label><span>دیسک روت</span><small>بین ۱۰ تا ۲۰۴۸ گیگابایت</small><div class="acr-input-suffix"><input type="number" name="root_disk" min="10" max="2048" value="25" required><b>GB</b></div></label><div class="acr-form-submit"><p><span class="dashicons dashicons-lock"></span>درخواست شما از طریق اتصال امن ثبت می‌شود.</p><button class="acr-p-btn" type="submit">ساخت سرور<span class="dashicons dashicons-arrow-left-alt"></span></button></div></form><?php else : ?><?php self::render_order_lock( $required ); ?><?php endif; ?></section>
						<section class="acr-create-panel" data-acr-create-panel="cdn" hidden><div class="acr-form-intro"><span class="dashicons dashicons-admin-site-alt3"></span><div><h2>ساخت سرویس CDN</h2><p>دامنه خود را برای تحویل سریع‌تر و امن‌تر محتوا متصل کنید.</p></div></div><?php if ( $can_order ) : ?><form class="acr-cdn-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="acr_order_cdn"><?php wp_nonce_field( 'acr_order_cdn' ); ?><label><span>نام دامنه</span><small>دامنه را بدون http یا https وارد کنید.</small><input type="text" name="domain" dir="ltr" placeholder="example.com" pattern="[A-Za-z0-9.-]+\.[A-Za-z]{2,}" required></label><button class="acr-p-btn" type="submit">ساخت سرویس CDN</button></form><?php else : ?><?php self::render_order_lock( $required ); ?><?php endif; ?></section>
					</div>
					<section class="acr-section-card"><div class="acr-section-title"><div><h2>همه سرویس‌ها</h2><p><?php echo esc_html( count( $resources ) ); ?> سرویس در حساب شما ثبت شده است.</p></div></div><?php self::render_resources( $resources ); ?></section>
				</section>

				<section class="acr-tab-panel" id="acr-tab-wallet" data-acr-panel="wallet" hidden><header class="acr-content-head"><div><span>امور مالی</span><h1>کیف پول</h1><p>موجودی و تراکنش‌های حساب خود را مشاهده کنید.</p></div></header><section class="acr-p-hero"><div><span>موجودی قابل استفاده</span><strong><?php echo esc_html( number_format_i18n( (float) $wallet->balance ) ); ?> <small>تومان</small></strong><em>آستانه هشدار: <?php echo esc_html( number_format_i18n( (float) $wallet->threshold ) ); ?> تومان</em></div><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="acr_mock_topup"><?php wp_nonce_field( 'acr_mock_topup' ); ?><label>مبلغ شارژ<input type="number" name="amount" min="10000" step="10000" value="100000" required></label><button class="acr-p-btn" type="submit">شارژ آزمایشی کیف پول</button></form></section><section class="acr-section-card acr-p-history"><div class="acr-section-title"><div><h2>آخرین تراکنش‌ها</h2><p>اطلاعات این بخش فقط متعلق به حساب شماست.</p></div></div><div class="acr-p-table"><table><thead><tr><th>شرح</th><th>نوع</th><th>مبلغ</th><th>موجودی</th><th>تاریخ</th></tr></thead><tbody><?php if ( ! $transactions ) : ?><tr><td colspan="5">تراکنشی ثبت نشده است.</td></tr><?php endif; ?><?php foreach ( $transactions as $row ) : ?><tr><td><?php echo esc_html( $row->description ); ?></td><td><?php echo esc_html( $row->type ); ?></td><td><?php echo esc_html( number_format_i18n( $row->amount ) ); ?></td><td><?php echo esc_html( number_format_i18n( $row->balance_after ) ); ?></td><td><?php echo esc_html( $row->created_at ); ?></td></tr><?php endforeach; ?></tbody></table></div></section></section>

				<section class="acr-tab-panel" id="acr-tab-settings" data-acr-panel="settings" hidden><header class="acr-content-head"><div><span>حساب کاربری</span><h1>تنظیمات</h1><p>اطلاعات حساب متصل به پنل خدمات ابری.</p></div></header><section class="acr-section-card acr-account-card"><div class="acr-account-avatar"><span class="dashicons dashicons-admin-users"></span></div><div><small>نام نمایشی</small><strong><?php echo esc_html( $user->display_name ); ?></strong></div><div><small>ایمیل</small><strong dir="ltr"><?php echo esc_html( $user->user_email ); ?></strong></div><a class="acr-logout-btn" href="<?php echo esc_url( wp_logout_url( get_permalink() ) ); ?>"><span class="dashicons dashicons-exit"></span>خروج از حساب</a></section></section>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	public static function topup(): void {
		self::customer_guard( 'acr_mock_topup' );
		global $wpdb;
		$user_id   = get_current_user_id();
		$amount    = max( 10000, min( 100000000, (float) wp_unslash( $_POST['amount'] ?? '0' ) ) );
		$reference = 'mock-pay-' . wp_generate_uuid4();
		$now       = current_time( 'mysql', true );
		$wpdb->insert(
			$wpdb->prefix . 'acr_payments',
			array( 'user_id' => $user_id, 'amount' => $amount, 'status' => 'success', 'reference' => $reference, 'metadata' => '{"gateway":"mock"}', 'created_at' => $now, 'updated_at' => $now ),
			array( '%d', '%f', '%s', '%s', '%s', '%s', '%s' )
		);
		ACR_Wallet::credit( $user_id, $amount, $reference, __( 'شارژ آزمایشی کیف پول', 'arvancloud-reseller' ) );
		ACR_Audit::log( 'service', 'mock_topup', 'success', __( 'Mock wallet top-up completed.', 'arvancloud-reseller' ), array( 'amount' => $amount, 'reference' => $reference ), $user_id );
		self::portal_redirect( 'top_up_ok' );
	}

	public static function order(): void {
		self::customer_guard( 'acr_order_cdn' );
		global $wpdb;
		$user_id = get_current_user_id();
		$domain  = strtolower( sanitize_text_field( wp_unslash( $_POST['domain'] ?? '' ) ) );
		$domain  = preg_replace( '#^https?://#', '', $domain );
		$domain  = trim( (string) $domain, "/. \t\n\r\0\x0B" );
		if ( ! preg_match( '/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain ) ) {
			ACR_Audit::log( 'service', 'cdn_order', 'failed', __( 'Invalid domain submitted.', 'arvancloud-reseller' ), array( 'domain' => $domain ), $user_id );
			self::portal_redirect( 'order_error' );
		}

		$markup = min( 20, max( 0, (float) ACR_Settings::get( 'markup_percent', 10 ) ) );
		$amount = (float) ACR_Settings::get( 'demo_hourly_cost', 1200 ) * ( 1 + $markup / 100 );
		$wallet = ACR_Wallet::get( $user_id );
		if ( (float) $wallet->balance < $amount ) {
			ACR_Audit::log( 'service', 'cdn_order', 'failed', __( 'Order blocked by insufficient balance.', 'arvancloud-reseller' ), array( 'domain' => $domain, 'required' => $amount, 'balance' => (float) $wallet->balance ), $user_id );
			self::portal_redirect( 'low_balance' );
		}

		$now = current_time( 'mysql', true );
		$wpdb->insert(
			$wpdb->prefix . 'acr_orders',
			array( 'user_id' => $user_id, 'product_type' => 'cdn', 'configuration' => wp_json_encode( array( 'domain' => $domain ) ), 'status' => 'provisioning', 'amount' => 0, 'created_at' => $now, 'updated_at' => $now ),
			array( '%d', '%s', '%s', '%s', '%f', '%s', '%s' )
		);
		$order_id = (int) $wpdb->insert_id;
		ACR_Audit::log( 'service', 'cdn_order', 'started', __( 'CDN provisioning request started.', 'arvancloud-reseller' ), array( 'order_id' => $order_id, 'domain' => $domain ), $user_id );
		$result   = ACR_API::provision_cdn( $domain );
		if ( ! $result['success'] ) {
			$wpdb->update( $wpdb->prefix . 'acr_orders', array( 'status' => 'failed', 'error_message' => sanitize_text_field( (string) ( $result['message'] ?? '' ) ), 'updated_at' => $now ), array( 'id' => $order_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
			ACR_Audit::log( 'service', 'cdn_provision', 'failed', (string) ( $result['message'] ?? __( 'API provisioning failed.', 'arvancloud-reseller' ) ), array( 'order_id' => $order_id, 'domain' => $domain ), $user_id );
			self::portal_redirect( 'order_error' );
		}

		$external = sanitize_text_field( (string) $result['resource_id'] );
		$wpdb->update( $wpdb->prefix . 'acr_orders', array( 'status' => 'completed', 'resource_id' => $external, 'updated_at' => $now ), array( 'id' => $order_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
		$wpdb->insert(
			$wpdb->prefix . 'acr_resources',
			array( 'user_id' => $user_id, 'order_id' => $order_id, 'external_id' => $external, 'product_type' => 'cdn', 'status' => 'active', 'region' => 'global', 'configuration' => wp_json_encode( array( 'domain' => $domain ) ), 'last_billed_at' => null, 'created_at' => $now, 'updated_at' => $now ),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		ACR_API::clear_snapshot();
		ACR_Audit::log( 'service', 'cdn_provision', 'success', __( 'CDN resource created.', 'arvancloud-reseller' ), array( 'order_id' => $order_id, 'resource_id' => $external, 'domain' => $domain ), $user_id );
		self::portal_redirect( 'order_ok' );
	}

	public static function order_server(): void {
		self::customer_guard( 'acr_order_server' );
		global $wpdb;
		$user_id = get_current_user_id();
		$config = array(
			'name'              => sanitize_text_field( wp_unslash( $_POST['server_name'] ?? '' ) ),
			'region'            => sanitize_key( wp_unslash( $_POST['region'] ?? '' ) ),
			'availability_zone' => sanitize_text_field( wp_unslash( $_POST['availability_zone'] ?? '' ) ),
			'flavor_id'         => sanitize_text_field( wp_unslash( $_POST['flavor_id'] ?? '' ) ),
			'image_id'          => sanitize_text_field( wp_unslash( $_POST['image_id'] ?? '' ) ),
			'root_disk'         => min( 2048, max( 10, absint( wp_unslash( $_POST['root_disk'] ?? 0 ) ) ) ),
		);
		if ( ! preg_match( '/^[A-Za-z0-9][A-Za-z0-9.-]{1,61}[A-Za-z0-9]$/', $config['name'] ) || ! in_array( $config['region'], ACR_API::regions(), true ) || '' === $config['availability_zone'] || '' === $config['flavor_id'] || '' === $config['image_id'] ) {
			self::portal_redirect( 'server_error' );
		}
		$required = (float) ACR_Settings::get( 'demo_hourly_cost', 1200 ) * ( 1 + min( 20, max( 0, (float) ACR_Settings::get( 'markup_percent', 10 ) ) ) / 100 );
		if ( (float) ACR_Wallet::get( $user_id )->balance < $required ) {
			self::portal_redirect( 'low_balance' );
		}
		$now = current_time( 'mysql', true );
		$wpdb->insert( $wpdb->prefix . 'acr_orders', array( 'user_id' => $user_id, 'product_type' => 'cloud_server', 'configuration' => wp_json_encode( $config ), 'status' => 'provisioning', 'amount' => 0, 'created_at' => $now, 'updated_at' => $now ), array( '%d', '%s', '%s', '%s', '%f', '%s', '%s' ) );
		$order_id = (int) $wpdb->insert_id;
		$result = ACR_API::provision_server( $config );
		if ( ! $result['success'] || empty( $result['resource_id'] ) ) {
			$wpdb->update( $wpdb->prefix . 'acr_orders', array( 'status' => 'failed', 'error_message' => sanitize_text_field( (string) ( $result['message'] ?? '' ) ), 'updated_at' => $now ), array( 'id' => $order_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
			ACR_Audit::log( 'service', 'server_provision', 'failed', (string) ( $result['message'] ?? 'API error' ), array( 'order_id' => $order_id ), $user_id );
			self::portal_redirect( 'server_error' );
		}
		$external = sanitize_text_field( (string) $result['resource_id'] );
		$wpdb->update( $wpdb->prefix . 'acr_orders', array( 'status' => 'completed', 'resource_id' => $external, 'updated_at' => $now ), array( 'id' => $order_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
		$wpdb->insert( $wpdb->prefix . 'acr_resources', array( 'user_id' => $user_id, 'order_id' => $order_id, 'external_id' => $external, 'product_type' => 'cloud_server', 'status' => 'active', 'region' => $config['region'], 'configuration' => wp_json_encode( $config ), 'last_billed_at' => null, 'created_at' => $now, 'updated_at' => $now ), array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
		ACR_API::clear_snapshot();
		ACR_Audit::log( 'service', 'server_provision', 'success', __( 'Cloud server created.', 'arvancloud-reseller' ), array( 'order_id' => $order_id, 'resource_id' => $external ), $user_id );
		self::portal_redirect( 'server_ok' );
	}

	public static function manage_server(): void {
		self::customer_guard( 'acr_manage_server' );
		global $wpdb;
		$user_id     = get_current_user_id();
		$resource_id = absint( wp_unslash( $_POST['resource_id'] ?? 0 ) );
		$action      = sanitize_key( wp_unslash( $_POST['server_action'] ?? '' ) );
		$resource    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}acr_resources WHERE id=%d AND user_id=%d AND product_type='cloud_server'", $resource_id, $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( ! $resource ) {
			self::portal_redirect( 'server_error' );
		}
		$config = json_decode( (string) $resource->configuration, true ) ?: array();
		$values = array(
			'availability_zone' => $config['availability_zone'] ?? '',
			'name'              => sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) ),
			'flavor_id'         => sanitize_text_field( wp_unslash( $_POST['value'] ?? '' ) ),
			'root_disk'         => absint( wp_unslash( $_POST['value'] ?? 0 ) ),
		);
		$result = ACR_API::manage_server( (string) $resource->region, (string) $resource->external_id, $action, $values );
		if ( ! $result['success'] ) {
			ACR_Audit::log( 'service', 'server_' . $action, 'failed', (string) ( $result['message'] ?? 'API error' ), array( 'resource_id' => $resource_id ), $user_id );
			self::portal_redirect( 'server_error' );
		}
		$status = 'terminate' === $action ? 'terminated' : ( 'power_off' === $action ? 'stopped' : ( 'power_on' === $action ? 'active' : (string) $resource->status ) );
		if ( 'rename' === $action && '' !== $values['name'] ) {
			$config['name'] = $values['name'];
		}
		if ( 'resize' === $action && '' !== $values['flavor_id'] ) {
			$config['flavor_id'] = $values['flavor_id'];
		}
		if ( 'resize_disk' === $action && $values['root_disk'] > 0 ) {
			$config['root_disk'] = $values['root_disk'];
		}
		$wpdb->update( $wpdb->prefix . 'acr_resources', array( 'status' => $status, 'configuration' => wp_json_encode( $config ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $resource_id, 'user_id' => $user_id ), array( '%s', '%s', '%s' ), array( '%d', '%d' ) );
		ACR_Audit::log( 'service', 'server_' . $action, 'success', __( 'Cloud server action completed.', 'arvancloud-reseller' ), array( 'resource_id' => $resource_id ), $user_id );
		self::portal_redirect( 'server_action_ok' );
	}

	private static function render_resources( array $resources ): void {
		if ( ! $resources ) {
			echo '<div class="acr-p-empty">' . esc_html__( 'هنوز سرویسی نساخته‌اید.', 'arvancloud-reseller' ) . '</div>';
			return;
		}
		echo '<div class="acr-p-list">';
		foreach ( $resources as $resource ) {
			$config = json_decode( (string) $resource->configuration, true ) ?: array();
			$name = 'cloud_server' === $resource->product_type ? ( $config['name'] ?? $resource->external_id ) : ( $config['domain'] ?? $resource->external_id );
			?><article class="acr-service-item"><span class="acr-p-status is-<?php echo esc_attr( $resource->status ); ?>"></span><div><strong dir="ltr"><?php echo esc_html( $name ); ?></strong><small dir="ltr"><?php echo esc_html( $resource->external_id ); ?></small></div><em><?php echo esc_html( $resource->status ); ?></em><?php if ( 'cloud_server' === $resource->product_type && 'terminated' !== $resource->status ) : ?><details class="acr-server-manage"><summary><?php esc_html_e( 'مدیریت سرور', 'arvancloud-reseller' ); ?></summary><div class="acr-server-actions"><?php foreach ( array( 'power_on' => 'روشن', 'power_off' => 'خاموش', 'reboot' => 'راه‌اندازی مجدد', 'reset_password' => 'بازنشانی رمز', 'rescue' => 'Rescue', 'unrescue' => 'خروج از Rescue' ) as $action => $label ) : ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="acr_manage_server"><input type="hidden" name="resource_id" value="<?php echo esc_attr( $resource->id ); ?>"><input type="hidden" name="server_action" value="<?php echo esc_attr( $action ); ?>"><?php wp_nonce_field( 'acr_manage_server' ); ?><button type="submit"><?php echo esc_html( $label ); ?></button></form><?php endforeach; ?><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="acr_manage_server"><input type="hidden" name="resource_id" value="<?php echo esc_attr( $resource->id ); ?>"><input type="hidden" name="server_action" value="rename"><?php wp_nonce_field( 'acr_manage_server' ); ?><input name="value" maxlength="63" placeholder="نام جدید" required><button>تغییر نام</button></form><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="acr_manage_server"><input type="hidden" name="resource_id" value="<?php echo esc_attr( $resource->id ); ?>"><input type="hidden" name="server_action" value="resize"><?php wp_nonce_field( 'acr_manage_server' ); ?><input name="value" placeholder="Flavor ID" required><button>تغییر Flavor</button></form><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"><input type="hidden" name="action" value="acr_manage_server"><input type="hidden" name="resource_id" value="<?php echo esc_attr( $resource->id ); ?>"><input type="hidden" name="server_action" value="resize_disk"><?php wp_nonce_field( 'acr_manage_server' ); ?><input type="number" name="value" min="10" max="2048" placeholder="GB" required><button>افزایش دیسک</button></form><form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('سرور برای همیشه حذف شود؟');"><input type="hidden" name="action" value="acr_manage_server"><input type="hidden" name="resource_id" value="<?php echo esc_attr( $resource->id ); ?>"><input type="hidden" name="server_action" value="terminate"><?php wp_nonce_field( 'acr_manage_server' ); ?><button class="is-danger">حذف سرور</button></form></div></details><?php endif; ?></article><?php
		}
		echo '</div>';
	}

	private static function render_order_lock( float $required ): void {
		?>
		<div class="acr-order-locked"><span class="dashicons dashicons-lock"></span><div><strong><?php esc_html_e( 'برای ساخت سرویس، کیف پول را شارژ کنید', 'arvancloud-reseller' ); ?></strong><small><?php echo esc_html( sprintf( 'حداقل موجودی لازم: %s تومان', number_format_i18n( $required ) ) ); ?></small></div><button type="button" data-acr-go="wallet"><?php esc_html_e( 'رفتن به کیف پول', 'arvancloud-reseller' ); ?></button></div>
		<?php
	}

	private static function customer_guard( string $action ): void {
		if ( ! is_user_logged_in() ) {
			auth_redirect();
		}
		check_admin_referer( $action );
	}

	private static function portal_redirect( string $notice ): never {
		$url = wp_get_referer() ?: home_url( '/' );
		wp_safe_redirect( add_query_arg( 'acr_portal_notice', $notice, $url ) );
		exit;
	}
}
