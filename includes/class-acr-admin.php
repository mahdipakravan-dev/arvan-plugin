<?php
/**
 * Reseller admin application shell and onboarding.
 *
 * @package ArvanCloudReseller
 */

defined('ABSPATH') || exit;

final class ACR_Admin
{
	public static function init(): void
	{
		add_action('admin_menu', array(__CLASS__, 'menu'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'assets'));
		add_action('admin_init', array(__CLASS__, 'activation_redirect'));
		add_action('admin_post_acr_save_onboarding', array(__CLASS__, 'save_onboarding'));
		add_action('admin_post_acr_skip_onboarding', array(__CLASS__, 'skip_onboarding'));
		add_action('admin_post_acr_save_settings', array(__CLASS__, 'save_settings'));
		add_action('admin_post_acr_test_connection', array(__CLASS__, 'test_connection'));
		add_action('admin_post_acr_run_billing', array(__CLASS__, 'run_billing'));
		add_action('admin_post_acr_refresh_api', array(__CLASS__, 'refresh_api'));
		add_action('admin_post_acr_sync_catalog', array(__CLASS__, 'sync_catalog'));
	}

	public static function menu(): void
	{
		add_menu_page(__('ریسلر آروان', 'arvancloud-reseller'), __('ریسلر آروان', 'arvancloud-reseller'), 'manage_options', 'acr-dashboard', array(__CLASS__, 'render'), 'dashicons-cloud', 3);
		add_submenu_page('acr-dashboard', __('داشبورد', 'arvancloud-reseller'), __('داشبورد', 'arvancloud-reseller'), 'manage_options', 'acr-dashboard', array(__CLASS__, 'render'));
		add_submenu_page('acr-dashboard', __('سرویس‌ها', 'arvancloud-reseller'), __('سرویس‌ها', 'arvancloud-reseller'), 'manage_options', 'acr-services', array(__CLASS__, 'render'));
		add_submenu_page('acr-dashboard', __('محصولات ابری', 'arvancloud-reseller'), __('محصولات ابری', 'arvancloud-reseller'), 'manage_options', 'acr-cloud-products', array(__CLASS__, 'render'));
		add_submenu_page('acr-dashboard', __('کاتالوگ فرانت', 'arvancloud-reseller'), __('کاتالوگ فرانت', 'arvancloud-reseller'), 'manage_options', 'acr-catalog', array(__CLASS__, 'render'));
		add_submenu_page('acr-dashboard', __('پروفایل مشتریان', 'arvancloud-reseller'), __('پروفایل مشتریان', 'arvancloud-reseller'), 'manage_options', 'acr-customers', array(__CLASS__, 'render'));
		add_submenu_page('acr-dashboard', __('داده‌های API', 'arvancloud-reseller'), __('داده‌های API', 'arvancloud-reseller'), 'manage_options', 'acr-api-inventory', array(__CLASS__, 'render'));
		add_submenu_page('acr-dashboard', __('مدیران', 'arvancloud-reseller'), __('مدیران', 'arvancloud-reseller'), 'manage_options', 'acr-admins', array(__CLASS__, 'render'));
		add_submenu_page('acr-dashboard', __('کیف پول و تراکنش‌ها', 'arvancloud-reseller'), __('تراکنش‌ها', 'arvancloud-reseller'), 'manage_options', 'acr-transactions', array(__CLASS__, 'render'));
		add_submenu_page('acr-dashboard', __('لاگ ورود و مصرف', 'arvancloud-reseller'), __('لاگ‌ها', 'arvancloud-reseller'), 'manage_options', 'acr-logs', array(__CLASS__, 'render'));
		add_submenu_page('acr-dashboard', __('تنظیمات', 'arvancloud-reseller'), __('تنظیمات', 'arvancloud-reseller'), 'manage_options', 'acr-settings', array(__CLASS__, 'render'));
		add_submenu_page(null, __('خوش‌آمدگویی', 'arvancloud-reseller'), __('خوش‌آمدگویی', 'arvancloud-reseller'), 'manage_options', 'acr-welcome', array(__CLASS__, 'render'));
	}

	public static function assets(string $hook): void
	{
		if (!str_contains($hook, 'acr-') && !isset($_GET['page'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$page = sanitize_key(wp_unslash($_GET['page'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (!str_starts_with($page, 'acr-')) {
			return;
		}
		wp_enqueue_style('dashicons');
		wp_enqueue_style('acr-admin', ACR_URL . 'assets/css/admin.css', array(), ACR_VERSION);
		wp_enqueue_script('acr-admin', ACR_URL . 'assets/js/admin.js', array(), ACR_VERSION, true);
	}

	public static function activation_redirect(): void
	{
		if (!current_user_can('manage_options') || !get_transient('acr_activation_redirect')) {
			return;
		}
		delete_transient('acr_activation_redirect');
		if (wp_doing_ajax() || isset($_GET['activate-multi'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		wp_safe_redirect(admin_url('admin.php?page=acr-welcome'));
		exit;
	}

	private static function guard(string $action): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('شما اجازه انجام این کار را ندارید.', 'arvancloud-reseller'));
		}
		check_admin_referer($action);
	}

	public static function save_onboarding(): void
	{
		self::guard('acr_save_onboarding');
		$machine_id = sanitize_text_field(wp_unslash($_POST['machine_user_id'] ?? ''));
		$token = sanitize_text_field(wp_unslash($_POST['machine_token'] ?? ''));
		$org = sanitize_text_field(wp_unslash($_POST['organization_name'] ?? ''));
		if ('' === $machine_id || '' === $token) {
			self::redirect('acr-welcome', 'missing_credentials');
		}
		ACR_Settings::set('machine_user_id', $machine_id);
		ACR_Settings::set('secret_machine_token', $token);
		ACR_Settings::set('organization_name', $org ?: get_bloginfo('name'));
		ACR_Settings::set('onboarding_completed', 'yes');
		ACR_API::clear_snapshot();
		self::redirect('acr-dashboard', 'connected');
	}

	public static function skip_onboarding(): void
	{
		self::guard('acr_skip_onboarding');
		ACR_Settings::set('onboarding_completed', 'yes');
		ACR_Settings::set('onboarding_skipped', 'yes');
		self::redirect('acr-dashboard', 'skipped');
	}

	public static function save_settings(): void
	{
		self::guard('acr_save_settings');
		$old_interval = ACR_Catalog::interval_minutes();
		$api_mode = sanitize_key(wp_unslash($_POST['api_mode'] ?? 'demo'));
		$sms_provider = sanitize_key(wp_unslash($_POST['sms_provider'] ?? 'mock'));
		$fields = array(
			'organization_name' => sanitize_text_field(wp_unslash($_POST['organization_name'] ?? '')),
			'machine_user_id' => sanitize_text_field(wp_unslash($_POST['machine_user_id'] ?? '')),
			'api_mode' => in_array($api_mode, array('demo', 'live'), true) ? $api_mode : 'demo',
			'sms_provider' => in_array($sms_provider, array('mock'), true) ? $sms_provider : 'mock',
			'markup_percent' => (string) min(20, max(0, (float) wp_unslash($_POST['markup_percent'] ?? '10'))),
			'default_threshold' => (string) max(0, (float) wp_unslash($_POST['default_threshold'] ?? '50000')),
			'demo_hourly_cost' => (string) max(0, (float) wp_unslash($_POST['demo_hourly_cost'] ?? '1200')),
			'cloud_regions' => self::sanitize_regions(wp_unslash($_POST['cloud_regions'] ?? 'ir-thr-c2')),
			's3_endpoint' => self::sanitize_s3_endpoint(wp_unslash($_POST['s3_endpoint'] ?? '')),
			'catalog_sync_minutes' => (string) min(10080, max(15, absint(wp_unslash($_POST['catalog_sync_minutes'] ?? '360')))),
			'portal_page_id' => (string) absint(wp_unslash($_POST['portal_page_id'] ?? '0')),
		);
		foreach ($fields as $key => $value) {
			ACR_Settings::set($key, $value);
		}
		$token = sanitize_text_field(wp_unslash($_POST['machine_token'] ?? ''));
		if ('' !== $token) {
			ACR_Settings::set('secret_machine_token', $token);
		}
		$s3_access = sanitize_text_field(wp_unslash($_POST['s3_access_key'] ?? ''));
		$s3_secret = sanitize_text_field(wp_unslash($_POST['s3_secret_key'] ?? ''));
		if ('' !== $s3_access) {
			ACR_Settings::set('secret_s3_access_key', $s3_access);
		}
		if ('' !== $s3_secret) {
			ACR_Settings::set('secret_s3_secret_key', $s3_secret);
		}
		ACR_API::clear_snapshot();
		if ($old_interval !== ACR_Catalog::interval_minutes()) {
			ACR_Catalog::reschedule();
		}
		self::redirect('acr-settings', 'saved');
	}

	private static function sanitize_regions(string $raw): string
	{
		$regions = array_filter(array_map('sanitize_key', array_map('trim', explode(',', $raw))));
		return implode(',', array_slice(array_unique($regions), 0, 10));
	}

	private static function sanitize_s3_endpoint(string $raw): string
	{
		$url = esc_url_raw(trim($raw), array('https'));
		$host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
		if ('' === $url || ('arvanstorage.ir' !== $host && !str_ends_with($host, '.arvanstorage.ir'))) {
			return '';
		}
		return untrailingslashit($url);
	}

	public static function test_connection(): void
	{
		self::guard('acr_test_connection');
		ACR_API::reset_diagnostics();
		$result = ACR_API::test_connection();
		self::store_diagnostics(__('آزمایش ارتباط API', 'arvancloud-reseller'), ACR_API::diagnostics());
		self::redirect('acr-settings', $result['success'] ? 'connection_ok' : 'connection_failed');
	}

	public static function run_billing(): void
	{
		self::guard('acr_run_billing');
		ACR_Cron::bill_resources();
		self::redirect('acr-dashboard', 'billing_ran');
	}

	public static function refresh_api(): void
	{
		self::guard('acr_refresh_api');
		ACR_API::clear_snapshot();
		ACR_API::reset_diagnostics();
		ACR_API::dashboard_snapshot(true);
		self::store_diagnostics(__('بروزرسانی داده‌های API', 'arvancloud-reseller'), ACR_API::diagnostics());
		$page = sanitize_key(wp_unslash($_POST['return_page'] ?? 'acr-dashboard'));
		if (!in_array($page, array('acr-dashboard', 'acr-api-inventory', 'acr-cloud-products'), true)) {
			$page = 'acr-dashboard';
		}
		self::redirect($page, 'api_refreshed');
	}

	public static function sync_catalog(): void
	{
		self::guard('acr_sync_catalog');
		$result = ACR_Catalog::sync();
		self::store_diagnostics(__('همگام‌سازی کاتالوگ', 'arvancloud-reseller'), $result['diagnostics'] ?? array());
		self::redirect('acr-catalog', $result['success'] ? 'catalog_synced' : 'catalog_failed');
	}

	private static function store_diagnostics(string $title, array $entries): void
	{
		set_transient(
			'acr_sync_diagnostics_' . get_current_user_id(),
			array('title' => $title, 'entries' => $entries, 'created_at' => current_time('mysql')),
			10 * MINUTE_IN_SECONDS
		);
	}

	private static function redirect(string $page, string $notice): never
	{
		wp_safe_redirect(add_query_arg(array('page' => $page, 'acr_notice' => $notice), admin_url('admin.php')));
		exit;
	}

	public static function render(): void
	{
		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('دسترسی غیرمجاز است.', 'arvancloud-reseller'));
		}
		$page = sanitize_key(wp_unslash($_GET['page'] ?? 'acr-dashboard')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ('acr-welcome' === $page) {
			self::welcome();
			return;
		}
		self::shell_start($page);
		switch ($page) {
			case 'acr-services':
				self::services();
				break;
			case 'acr-cloud-products':
				self::cloud_products();
				break;
			case 'acr-catalog':
				self::catalog();
				break;
			case 'acr-customers':
				self::customers();
				break;
			case 'acr-api-inventory':
				self::api_inventory();
				break;
			case 'acr-admins':
				self::admins();
				break;
			case 'acr-transactions':
				self::transactions();
				break;
			case 'acr-logs':
				self::logs();
				break;
			case 'acr-settings':
				self::settings();
				break;
			default:
				self::dashboard();
		}
		self::shell_end();
	}

	private static function welcome(): void
	{
		$guide = 'https://docs.arvancloud.ir/fa/developer-tools/api/api-key';
		?>
		<div class="wrap acr-wrap acr-welcome" dir="rtl">
			<div class="acr-welcome__brand"><span class="dashicons dashicons-cloud"></span><strong>ابرینو</strong><small>پنل
					ریسلری آروان‌کلاد</small></div>
			<div class="acr-welcome__grid">
				<section class="acr-welcome__content">
					<span class="acr-eyebrow">راه‌اندازی سریع · کمتر از ۲ دقیقه</span>
					<h1>به پنل خوش آمدید</h1>
					<p>جهت شروع، ابتدا Machine User خود را تعریف کنید. این کلید ارتباط امن فروشگاه شما با سرویس‌های آروان‌کلاد
						را برقرار می‌کند.</p>
					<div class="acr-steps">
						<div class="is-active"><b>۱</b><span><strong>اتصال حساب آروان</strong><small>Machine User و Access
									Token</small></span></div>
						<div><b>۲</b><span><strong>تنظیم فروشگاه</strong><small>نام مجموعه و سهم ریسلر</small></span></div>
						<div><b>۳</b><span><strong>شروع فروش</strong><small>ارائه CDN به مشتریان</small></span></div>
					</div>
					<a class="acr-guide" href="<?php echo esc_url($guide); ?>" target="_blank" rel="noopener noreferrer"><span
							class="dashicons dashicons-book-alt"></span><span><strong>راهنمای تعریف Machine
								User</strong><small>آموزش گام‌به‌گام ساخت کلید در پنل آروان</small></span><span
							class="dashicons dashicons-arrow-left-alt2"></span></a>
				</section>
				<section class="acr-setup-card">
					<div class="acr-card-icon"><span class="dashicons dashicons-admin-network"></span></div>
					<h2>اتصال حساب مادر</h2>
					<p>اطلاعات زیر رمزگذاری شده و فقط برای ارتباط با API استفاده می‌شود.</p>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<input type="hidden" name="action" value="acr_save_onboarding">
						<?php wp_nonce_field('acr_save_onboarding'); ?>
						<label>نام مجموعه<input name="organization_name" type="text"
								value="<?php echo esc_attr(get_bloginfo('name')); ?>" autocomplete="organization"></label>
						<label>شناسه Machine User<input name="machine_user_id" type="text" placeholder="مثلاً reseller-main"
								dir="ltr" required></label>
						<label>Access Token<span class="acr-secret"><input name="machine_token" type="password"
									placeholder="xxxxxxxx-xxxx-xxxx-xxxx" dir="ltr" autocomplete="off" required><button
									type="button" class="acr-toggle-secret" aria-label="نمایش توکن"><span
										class="dashicons dashicons-visibility"></span></button></span></label>
						<button class="acr-btn acr-btn--primary acr-btn--wide" type="submit">ذخیره و ورود به پنل <span
								class="dashicons dashicons-arrow-left-alt"></span></button>
					</form>
					<form class="acr-skip" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<input type="hidden" name="action" value="acr_skip_onboarding">
						<?php wp_nonce_field('acr_skip_onboarding'); ?>
						<button type="submit">فعلاً رد می‌کنم؛ بعداً از تنظیمات وارد می‌کنم</button>
					</form>
					<div class="acr-safe"><span class="dashicons dashicons-shield"></span>توکن به‌صورت AES-256-GCM رمزگذاری
						می‌شود.</div>
				</section>
			</div>
		</div>
		<?php
	}

	private static function shell_start(string $page): void
	{
		$org = (string) ACR_Settings::get('organization_name', get_bloginfo('name'));
		$token = '' !== ACR_Settings::get('secret_machine_token', '');
		$initial = function_exists('mb_substr') ? mb_substr($org, 0, 1) : substr($org, 0, 1);
		?>
		<div class="wrap acr-wrap acr-app" dir="rtl">
			<header class="acr-topbar">
				<div class="acr-topbar__brand"><span class="dashicons dashicons-cloud"></span><strong>ابرینو</strong></div>
				<div class="acr-topbar__tools"><span
						class="acr-mode"><?php echo 'demo' === ACR_Settings::get('api_mode', 'demo') ? 'حالت دمو' : 'اتصال زنده'; ?></span><button
						aria-label="اعلان‌ها"><span class="dashicons dashicons-bell"></span></button><span
						class="acr-avatar"><?php echo esc_html($initial); ?></span><span><?php echo esc_html($org); ?></span>
				</div>
			</header>
			<div class="acr-product-rail" aria-label="محصولات"><a class="is-active"
					href="<?php echo esc_url(admin_url('admin.php?page=acr-dashboard')); ?>" title="CDN"><span
						class="dashicons dashicons-admin-site-alt3"></span></a><span title="سرور ابری"><span
						class="dashicons dashicons-cloud"></span></span><span title="Object Storage"><span
						class="dashicons dashicons-database"></span></span><i></i><a
					href="<?php echo esc_url(admin_url('admin.php?page=acr-settings')); ?>"><span
						class="dashicons dashicons-admin-generic"></span></a></div>
			<aside class="acr-sidebar" id="acr-sidebar">
				<div class="acr-sidebar__title"><span class="dashicons dashicons-admin-site-alt3"></span><span><strong>مدیریت
							CDN</strong><small>Reseller Console</small></span></div>
				<nav>
					<a class="<?php echo 'acr-dashboard' === $page ? 'is-active' : ''; ?>"
						href="<?php echo esc_url(admin_url('admin.php?page=acr-dashboard')); ?>"><span
							class="dashicons dashicons-dashboard"></span>داشبورد</a>
					<a class="<?php echo 'acr-services' === $page ? 'is-active' : ''; ?>"
						href="<?php echo esc_url(admin_url('admin.php?page=acr-services')); ?>"><span
							class="dashicons dashicons-admin-site"></span>سرویس‌های مشتریان</a>
					<a class="<?php echo 'acr-cloud-products' === $page ? 'is-active' : ''; ?>"
						href="<?php echo esc_url(admin_url('admin.php?page=acr-cloud-products')); ?>"><span
							class="dashicons dashicons-screenoptions"></span>محصولات ابری</a>
					<a class="<?php echo 'acr-catalog' === $page ? 'is-active' : ''; ?>"
						href="<?php echo esc_url(admin_url('admin.php?page=acr-catalog')); ?>"><span
							class="dashicons dashicons-store"></span>کاتالوگ فرانت</a>
					<a class="<?php echo 'acr-customers' === $page ? 'is-active' : ''; ?>"
						href="<?php echo esc_url(admin_url('admin.php?page=acr-customers')); ?>"><span
							class="dashicons dashicons-id"></span>پروفایل مشتریان</a>
					<a class="<?php echo 'acr-api-inventory' === $page ? 'is-active' : ''; ?>"
						href="<?php echo esc_url(admin_url('admin.php?page=acr-api-inventory')); ?>"><span
							class="dashicons dashicons-rest-api"></span>داده‌های API</a>
					<a class="<?php echo 'acr-admins' === $page ? 'is-active' : ''; ?>"
						href="<?php echo esc_url(admin_url('admin.php?page=acr-admins')); ?>"><span
							class="dashicons dashicons-admin-users"></span>مدیران</a>
					<a class="<?php echo 'acr-transactions' === $page ? 'is-active' : ''; ?>"
						href="<?php echo esc_url(admin_url('admin.php?page=acr-transactions')); ?>"><span
							class="dashicons dashicons-money-alt"></span>تراکنش‌ها</a>
					<a class="<?php echo 'acr-logs' === $page ? 'is-active' : ''; ?>"
						href="<?php echo esc_url(admin_url('admin.php?page=acr-logs')); ?>"><span
							class="dashicons dashicons-list-view"></span>لاگ ورود و مصرف</a>
					<a class="<?php echo 'acr-settings' === $page ? 'is-active' : ''; ?>"
						href="<?php echo esc_url(admin_url('admin.php?page=acr-settings')); ?>"><span
							class="dashicons dashicons-admin-settings"></span>تنظیمات</a>
				</nav>
				<div class="acr-sidebar__status"><span
						class="acr-dot <?php echo $token ? 'is-ok' : ''; ?>"></span><span><strong><?php echo $token ? 'حساب متصل است' : 'حساب متصل نیست'; ?></strong><small><?php echo $token ? 'Machine User فعال' : 'از تنظیمات تکمیل کنید'; ?></small></span>
				</div>
			</aside>
			<main class="acr-main"><button class="acr-mobile-menu" type="button" aria-controls="acr-sidebar"
					aria-expanded="false"><span class="dashicons dashicons-menu"></span></button>
				<?php self::notice(); ?>
				<?php self::diagnostics_notice(); ?>
				<?php
	}

	private static function shell_end(): void
	{
		echo '</main></div>';
	}

	private static function notice(): void
	{
		$key = sanitize_key(wp_unslash($_GET['acr_notice'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$messages = array(
			'connected' => 'حساب آروان با موفقیت ذخیره شد.',
			'skipped' => 'راه‌اندازی رد شد؛ هر زمان خواستید از تنظیمات تکمیل کنید.',
			'saved' => 'تنظیمات ذخیره شد.',
			'connection_ok' => 'ارتباط با API آروان با موفقیت برقرار شد.',
			'connection_failed' => 'ارتباط برقرار نشد؛ توکن و دسترسی‌ها را بررسی کنید.',
			'billing_ran' => 'محاسبه مصرف اجرا شد.',
			'api_refreshed' => 'اطلاعات سرویس‌ها از API به‌روزرسانی شد.',
			'catalog_synced' => 'کاتالوگ و قیمت‌ها از منابع رسمی آروان بروزرسانی شد.',
			'catalog_failed' => 'همگام‌سازی کامل نشد؛ آخرین کاتالوگ معتبر حفظ شده است.',
			'missing_credentials' => 'شناسه و توکن Machine User الزامی است.',
		);
		if (isset($messages[$key])) {
			printf('<div class="acr-notice %1$s">%2$s</div>', in_array($key, array('connection_failed', 'missing_credentials', 'catalog_failed'), true) ? 'is-error' : '', esc_html($messages[$key]));
		}
	}

	private static function diagnostics_notice(): void
	{
		$key = 'acr_sync_diagnostics_' . get_current_user_id();
		$report = get_transient($key);
		if (!is_array($report) || empty($report['entries'])) {
			return;
		}
		delete_transient($key);
		?>
				<section class="acr-panel acr-sync-report">
					<div class="acr-panel-head">
						<div>
							<h2><?php echo esc_html((string) $report['title']); ?></h2>
							<p><?php echo esc_html(sprintf(__('جزئیات درخواست و پاسخ در %s — اطلاعات احراز هویت مخفی شده‌اند.', 'arvancloud-reseller'), (string) $report['created_at'])); ?>
							</p>
						</div>
					</div>
					<?php foreach ($report['entries'] as $index => $entry): ?>
						<details class="acr-sync-report__entry" <?php echo 0 === $index ? ' open' : ''; ?>>
							<summary>
								<?php echo esc_html((string) ($entry['label'] ?? sprintf(__('درخواست %d', 'arvancloud-reseller'), $index + 1))); ?>
							</summary>
							<div class="acr-sync-report__grid">
								<div><strong><?php esc_html_e('درخواست ارسال‌شده', 'arvancloud-reseller'); ?></strong>
									<pre
										dir="ltr"><?php echo esc_html(self::diagnostic_json($entry['request'] ?? array())); ?></pre>
								</div>
								<div><strong><?php esc_html_e('پاسخ دریافت‌شده', 'arvancloud-reseller'); ?></strong>
									<pre
										dir="ltr"><?php echo esc_html(self::diagnostic_json($entry['response'] ?? array())); ?></pre>
								</div>
							</div>
						</details>
					<?php endforeach; ?>
				</section>
				<?php
	}

	private static function diagnostic_json(mixed $value): string
	{
		$json = wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if (!is_string($json)) {
			return __('امکان نمایش داده وجود ندارد.', 'arvancloud-reseller');
		}
		return function_exists('mb_substr') ? mb_substr($json, 0, 12000) : substr($json, 0, 12000);
	}

	private static function dashboard(): void
	{
		global $wpdb;
		$snapshot = ACR_API::dashboard_snapshot();
		$admins = get_users(array('role__in' => array('administrator'), 'number' => 4, 'orderby' => 'registered', 'order' => 'DESC'));
		$orders = $wpdb->get_results("SELECT o.*, u.display_name FROM {$wpdb->prefix}acr_orders o LEFT JOIN {$wpdb->users} u ON u.ID=o.user_id ORDER BY o.id DESC LIMIT 5"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$activity = $wpdb->get_results("SELECT t.*, u.display_name FROM {$wpdb->prefix}acr_transactions t LEFT JOIN {$wpdb->users} u ON u.ID=t.user_id ORDER BY t.id DESC LIMIT 5"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$required = (float) ACR_Settings::get('demo_hourly_cost', 1200) * (1 + min(20, max(0, (float) ACR_Settings::get('markup_percent', 10))) / 100);
		$customer_stats = $wpdb->get_row($wpdb->prepare("SELECT COUNT(*) total, SUM(CASE WHEN balance >= %f THEN 1 ELSE 0 END) ready, SUM(CASE WHEN balance < %f THEN 1 ELSE 0 END) needs_topup FROM {$wpdb->prefix}acr_wallets", $required, $required)); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$catalog_products = ACR_Catalog::get_products();
		$catalog_last_sync = (string) ACR_Settings::get('catalog_last_sync', '');
		$catalog_next_sync = wp_next_scheduled('acr_catalog_sync');
		$portal_page_id = absint(ACR_Settings::get('portal_page_id', 0));
		$portal_url = $portal_page_id ? get_permalink($portal_page_id) : '';
		$stats = array(
			'users' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}acr_wallets"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'services' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}acr_resources WHERE status = 'active'"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'wallets' => (float) $wpdb->get_var("SELECT COALESCE(SUM(balance),0) FROM {$wpdb->prefix}acr_wallets"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			'usage' => (float) $wpdb->get_var("SELECT COALESCE(SUM(final_amount),0) FROM {$wpdb->prefix}acr_usage_logs"), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		?>
				<div class="acr-page-head">
					<div><span class="acr-eyebrow">نمای کلی کسب‌وکار</span>
						<h1>داشبورد ریسلری</h1>
						<p>فروش، موجودی، مدیران و داده‌های سرویس‌های ابری را یکجا مدیریت کنید.</p>
					</div>
					<div class="acr-head-actions"><?php self::refresh_form('acr-dashboard'); ?>
						<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input
								type="hidden" name="action"
								value="acr_run_billing"><?php wp_nonce_field('acr_run_billing'); ?><button
								class="acr-btn acr-btn--primary" type="submit"><span
									class="dashicons dashicons-update"></span>اجرای محاسبه مصرف</button></form>
					</div>
				</div>
				<div class="acr-metrics">
					<article><span class="dashicons dashicons-groups"></span><small>مشتریان کیف
							پول</small><strong><?php echo esc_html(number_format_i18n($stats['users'])); ?></strong><em>حساب
							فعال</em></article>
					<article><span class="dashicons dashicons-admin-site"></span><small>سرویس فعال
							CDN</small><strong><?php echo esc_html(number_format_i18n($stats['services'])); ?></strong><em>دامنه
							متصل</em></article>
					<article><span class="dashicons dashicons-money-alt"></span><small>مجموع
							موجودی</small><strong><?php echo esc_html(number_format_i18n($stats['wallets'])); ?>
							<i>تومان</i></strong><em>کیف پول مشتریان</em></article>
					<article><span class="dashicons dashicons-chart-area"></span><small>مصرف
							ثبت‌شده</small><strong><?php echo esc_html(number_format_i18n($stats['usage'])); ?>
							<i>تومان</i></strong><em>با سهم ریسلر</em></article>
				</div>

				<section class="acr-section-head">
					<div>
						<h2>سرویس‌های ابری آروان</h2>
						<p>خلاصه موجودی دریافت‌شده از API و اتصال‌های محصول</p>
					</div><a href="<?php echo esc_url(admin_url('admin.php?page=acr-api-inventory')); ?>">مشاهده همه داده‌ها
						<span class="dashicons dashicons-arrow-left-alt2"></span></a>
				</section>
				<div class="acr-products-grid">
					<?php self::product_card('cdn', 'شبکه توزیع محتوا', 'دامنه‌های متصل و DNS ابری', 'dashicons-admin-site-alt3', $snapshot['cdn']); ?>
					<?php self::product_card('cloud', 'سرور ابری', 'ابرک‌ها در دیتاسنترهای انتخابی', 'dashicons-cloud', $snapshot['cloud_servers']); ?>
					<?php self::product_card('storage', 'Object Storage', 'Bucketهای ذخیره‌سازی سازگار با S3', 'dashicons-database', $snapshot['object_storage']); ?>
				</div>

				<div class="acr-dashboard-grid">
					<section class="acr-panel acr-api-health">
						<div class="acr-panel-head">
							<div>
								<h2>وضعیت اتصال API</h2>
								<p>Machine User و endpointهای فعال</p>
							</div>
							<?php self::api_badge('live' === $snapshot['mode'], 'live' === $snapshot['mode'] ? 'اتصال زنده' : 'حالت دمو'); ?>
						</div>
						<div class="acr-connection-row"><span class="dashicons dashicons-admin-network"></span>
							<div><strong>Machine User</strong><small
									dir="ltr"><?php echo esc_html((string) ACR_Settings::get('machine_user_id', 'تعریف نشده')); ?></small>
							</div>
							<em><?php echo '' !== ACR_Settings::get('secret_machine_token', '') ? 'توکن ذخیره شده' : 'توکن وارد نشده'; ?></em>
						</div>
						<div class="acr-api-checks">
							<div><span>CDN
									API</span><?php self::api_badge((bool) $snapshot['cdn']['success'], $snapshot['cdn']['success'] ? 'دردسترس' : 'خطا'); ?>
							</div>
							<div><span>Cloud Server
									API</span><?php self::api_badge((bool) $snapshot['cloud_servers']['success'], $snapshot['cloud_servers']['success'] ? 'دردسترس' : 'خطا'); ?>
							</div>
							<div><span>Object Storage
									API</span><?php self::api_badge((bool) $snapshot['object_storage']['success'], !empty($snapshot['object_storage']['configured']) ? ($snapshot['object_storage']['success'] ? 'دردسترس' : 'خطا') : 'نیازمند کلید S3'); ?>
							</div>
						</div>
						<footer>آخرین بروزرسانی: <?php echo esc_html($snapshot['refreshed_at']); ?></footer>
					</section>

					<section class="acr-panel acr-admin-list">
						<div class="acr-panel-head">
							<div>
								<h2>مدیران پنل</h2>
								<p>کاربران دارای دسترسی مدیریت وردپرس</p>
							</div><a href="<?php echo esc_url(admin_url('admin.php?page=acr-admins')); ?>">همه مدیران</a>
						</div>
						<?php if (!$admins): ?>
							<div class="acr-mini-empty">مدیری ثبت نشده است.</div><?php endif; ?>
						<?php foreach ($admins as $admin): ?>
							<div class="acr-person"><span
									class="acr-person__avatar"><?php echo esc_html(self::initial($admin->display_name)); ?></span>
								<div><strong><?php echo esc_html($admin->display_name); ?></strong><small
										dir="ltr"><?php echo esc_html($admin->user_email); ?></small></div><span
									class="acr-status is-active">مدیر</span>
							</div>
						<?php endforeach; ?>
					</section>
				</div>

				<div class="acr-dashboard-grid">
					<section class="acr-panel acr-catalog-health">
						<div class="acr-panel-head">
							<div>
								<h2>کاتالوگ فرانت‌آفیس</h2>
								<p>محصولات و قیمت‌های همگام‌شده از منابع رسمی</p>
							</div>
							<?php self::api_badge('' !== $catalog_last_sync, '' !== $catalog_last_sync ? 'بروزشده' : 'در انتظار همگام‌سازی'); ?>
						</div>
						<div class="acr-catalog-summary">
							<strong><?php echo esc_html(number_format_i18n(count($catalog_products))); ?></strong><span>محصول
								قابل نمایش</span></div>
						<div class="acr-api-checks">
							<div><span>آخرین
									همگام‌سازی</span><em><?php echo esc_html($catalog_last_sync ?: 'هنوز اجرا نشده'); ?></em>
							</div>
							<div><span>همگام‌سازی
									بعدی</span><em><?php echo $catalog_next_sync ? esc_html(wp_date('Y/m/d H:i', $catalog_next_sync)) : '—'; ?></em>
							</div>
							<div><span>بازه بروزرسانی</span><em>هر
									<?php echo esc_html(number_format_i18n(ACR_Catalog::interval_minutes())); ?> دقیقه</em>
							</div>
						</div><a class="acr-btn acr-btn--wide"
							href="<?php echo esc_url(admin_url('admin.php?page=acr-catalog')); ?>">مدیریت کاتالوگ و
							بلاک‌ها</a>
					</section>
					<section class="acr-panel acr-customer-readiness">
						<div class="acr-panel-head">
							<div>
								<h2>آمادگی پروفایل مشتریان</h2>
								<p>ورود، شارژ کیف پول و امکان ثبت سفارش</p>
							</div><a href="<?php echo esc_url(admin_url('admin.php?page=acr-customers')); ?>">همه
								پروفایل‌ها</a>
						</div>
						<div class="acr-readiness-ring">
							<strong><?php echo esc_html(number_format_i18n(absint($customer_stats->ready ?? 0))); ?></strong><span>آماده
								سفارش</span></div>
						<div class="acr-readiness-list">
							<div><span>پروفایل
									ساخته‌شده</span><b><?php echo esc_html(number_format_i18n(absint($customer_stats->total ?? 0))); ?></b>
							</div>
							<div><span>نیازمند شارژ کیف پول</span><b
									class="is-warning"><?php echo esc_html(number_format_i18n(absint($customer_stats->needs_topup ?? 0))); ?></b>
							</div>
							<div><span>حداقل موجودی سفارش</span><b><?php echo esc_html(number_format_i18n($required)); ?>
									تومان</b></div>
						</div>
					</section>
				</div>

				<div class="acr-dashboard-grid acr-dashboard-grid--wide">
					<section class="acr-panel acr-compact-table">
						<div class="acr-panel-head">
							<div>
								<h2>آخرین سفارش‌ها</h2>
								<p>وضعیت Provisioning سرویس مشتریان</p>
							</div><a href="<?php echo esc_url(admin_url('admin.php?page=acr-services')); ?>">مشاهده
								سرویس‌ها</a>
						</div>
						<div class="acr-table-scroll">
							<table>
								<thead>
									<tr>
										<th>مشتری</th>
										<th>محصول</th>
										<th>وضعیت</th>
										<th>منبع</th>
									</tr>
								</thead>
								<tbody><?php if (!$orders): ?>
										<tr>
											<td colspan="4" class="acr-table-empty">سفارشی ثبت نشده است.</td>
										</tr><?php endif; ?><?php foreach ($orders as $order): ?>
										<tr>
											<td><?php echo esc_html($order->display_name ?: '—'); ?></td>
											<td><?php echo esc_html(strtoupper($order->product_type)); ?></td>
											<td><span
													class="acr-status is-<?php echo esc_attr(sanitize_html_class($order->status)); ?>"><?php echo esc_html($order->status); ?></span>
											</td>
											<td dir="ltr"><?php echo esc_html($order->resource_id ?: '—'); ?></td>
										</tr><?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</section>
					<section class="acr-panel acr-activity">
						<div class="acr-panel-head">
							<div>
								<h2>فعالیت مالی اخیر</h2>
								<p>آخرین تغییرات کیف پول مشتریان</p>
							</div><a href="<?php echo esc_url(admin_url('admin.php?page=acr-transactions')); ?>">همه
								تراکنش‌ها</a>
						</div><?php if (!$activity): ?>
							<div class="acr-mini-empty">تراکنشی ثبت نشده است.</div>
						<?php endif; ?>		<?php foreach ($activity as $item): ?>
							<div class="acr-activity-row"><span
									class="dashicons <?php echo 'credit' === $item->type ? 'dashicons-arrow-down-alt' : 'dashicons-arrow-up-alt'; ?>"></span>
								<div>
									<strong><?php echo esc_html($item->description ?: $item->type); ?></strong><small><?php echo esc_html($item->display_name ?: '—'); ?></small>
								</div><em
									class="is-<?php echo esc_attr($item->type); ?>"><?php echo 'credit' === $item->type ? '+' : '-'; ?><?php echo esc_html(number_format_i18n($item->amount)); ?>
									تومان</em>
							</div><?php endforeach; ?>
					</section>
				</div>

				<section class="acr-panel acr-quick-start">
					<div class="acr-cloud-visual"><span class="dashicons dashicons-cloud"></span></div>
					<div>
						<h2>صفحه یکپارچه فرانت‌آفیس آماده است</h2>
						<p>کاتالوگ محصولات، ورود وردپرس، کیف پول و سفارش در یک برگه قرار گرفته‌اند.</p><code
							dir="ltr">acr/product-catalog + acr/customer-profile</code>
					</div>
					<div class="acr-quick-actions"><?php if ($portal_url): ?><a class="acr-btn acr-btn--primary"
								href="<?php echo esc_url($portal_url); ?>" target="_blank" rel="noopener noreferrer">مشاهده
								پروفایل مشتری</a><a class="acr-btn"
								href="<?php echo esc_url(get_edit_post_link($portal_page_id)); ?>">ویرایش
								برگه</a><?php else: ?><a class="acr-btn acr-btn--primary"
								href="<?php echo esc_url(admin_url('post-new.php?post_type=page')); ?>">ساخت برگه
								مشتری</a><?php endif; ?><a class="acr-btn"
							href="<?php echo esc_url(admin_url('admin.php?page=acr-settings')); ?>">تنظیمات فرانت</a></div>
				</section>
				<?php
	}

	private static function product_card(string $type, string $title, string $description, string $icon, array $data): void
	{
		$success = !empty($data['success']);
		$count = absint($data['count'] ?? 0);
		?>
				<article class="acr-product-card is-<?php echo esc_attr(sanitize_html_class($type)); ?>">
					<div class="acr-product-card__top"><span
							class="dashicons <?php echo esc_attr($icon); ?>"></span><?php self::api_badge($success, $success ? 'متصل' : 'نیازمند تنظیم'); ?>
					</div>
					<h3><?php echo esc_html($title); ?></h3>
					<p><?php echo esc_html($description); ?></p>
					<div class="acr-product-card__count">
						<strong><?php echo esc_html(number_format_i18n($count)); ?></strong><span>منبع شناسایی‌شده</span>
					</div>
					<footer><code dir="ltr"><?php echo esc_html((string) ($data['endpoint'] ?? '—')); ?></code><a
							href="<?php echo esc_url(admin_url('admin.php?page=acr-api-inventory#acr-' . $type)); ?>"><span
								class="dashicons dashicons-arrow-left-alt2"></span></a></footer>
				</article>
				<?php
	}

	private static function api_badge(bool $success, string $label): void
	{
		printf('<span class="acr-api-badge %1$s"><i></i>%2$s</span>', $success ? 'is-ok' : 'is-muted', esc_html($label));
	}

	private static function refresh_form(string $return_page): void
	{
		?>
				<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden"
						name="action" value="acr_refresh_api"><input type="hidden" name="return_page"
						value="<?php echo esc_attr($return_page); ?>"><?php wp_nonce_field('acr_refresh_api'); ?><button
						class="acr-btn" type="submit"><span class="dashicons dashicons-image-rotate"></span>بروزرسانی
						API</button></form>
				<?php
	}

	private static function initial(string $name): string
	{
		return function_exists('mb_substr') ? mb_substr($name, 0, 1) : substr($name, 0, 1);
	}

	private static function cloud_products(): void
	{
		$snapshot = ACR_API::dashboard_snapshot();
		?>
				<div class="acr-page-head">
					<div><span class="acr-eyebrow">کاتالوگ محصول</span>
						<h1>محصولات ابری</h1>
						<p>سرویس‌های اصلی آروان و وضعیت اتصال هرکدام به افزونه</p>
					</div><?php self::refresh_form('acr-cloud-products'); ?>
				</div>
				<div class="acr-products-grid acr-products-grid--catalog">
					<?php self::product_card('cdn', 'شبکه توزیع محتوا (CDN)', 'Provisioning کامل در افزونه و دریافت وضعیت دامنه‌های متصل از API.', 'dashicons-admin-site-alt3', $snapshot['cdn']); ?>
					<?php self::product_card('cloud', 'سرور ابری (ابرک)', 'دریافت ماشین‌ها از منطقه‌های تنظیم‌شده، وضعیت، IP، Flavor و شناسه منبع.', 'dashicons-cloud', $snapshot['cloud_servers']); ?>
					<?php self::product_card('storage', 'فضای ابری Object Storage', 'دریافت Bucketها از API سازگار با S3 با Access Key و Secret Key مستقل.', 'dashicons-database', $snapshot['object_storage']); ?>
				</div>
				<section class="acr-panel acr-capability-matrix">
					<div class="acr-panel-head">
						<div>
							<h2>ماتریس قابلیت‌ها</h2>
							<p>مواردی که اکنون در این نسخه خوانده یا مدیریت می‌شوند</p>
						</div>
					</div>
					<div class="acr-table-scroll">
						<table>
							<thead>
								<tr>
									<th>محصول</th>
									<th>فهرست منابع</th>
									<th>ساخت سرویس</th>
									<th>داده‌های قابل نمایش</th>
									<th>نوع احراز هویت</th>
								</tr>
							</thead>
							<tbody>
								<tr>
									<td><strong>CDN</strong></td>
									<td><span class="acr-check">فعال</span></td>
									<td><span class="acr-check">فعال</span></td>
									<td>دامنه، وضعیت، پلن، تاریخ ساخت</td>
									<td>Machine User</td>
								</tr>
								<tr>
									<td><strong>Cloud Server</strong></td>
									<td><span class="acr-check">فعال</span></td>
									<td><span class="acr-pending">خواندنی</span></td>
									<td>نام، وضعیت، منطقه، IP، Flavor</td>
									<td>Machine User + IAM</td>
								</tr>
								<tr>
									<td><strong>Object Storage</strong></td>
									<td><span class="acr-check">فعال</span></td>
									<td><span class="acr-pending">خواندنی</span></td>
									<td>Bucket، وضعیت، تاریخ ساخت</td>
									<td>S3 Access/Secret</td>
								</tr>
							</tbody>
						</table>
					</div>
				</section>
				<?php
	}

	private static function catalog(): void
	{
		$products = ACR_Catalog::get_products();
		$status = json_decode((string) ACR_Settings::get('catalog_source_status', '{}'), true);
		$next = wp_next_scheduled('acr_catalog_sync');
		?>
				<div class="acr-page-head">
					<div><span class="acr-eyebrow">Front-office Catalog</span>
						<h1>کاتالوگ و قیمت‌های فرانت</h1>
						<p>داده‌های نمایش‌داده‌شده در بلاک محصولات از منابع رسمی بروزرسانی می‌شوند.</p>
					</div>
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden"
							name="action" value="acr_sync_catalog"><?php wp_nonce_field('acr_sync_catalog'); ?><button
							class="acr-btn acr-btn--primary" type="submit"><span
								class="dashicons dashicons-update"></span>همگام‌سازی همین حالا</button></form>
				</div>
				<div class="acr-source-grid">
					<?php self::source_card('مستندات API', ACR_Catalog::API_URL, $status['api'] ?? array()); ?>
					<?php self::source_card('قیمت‌گذاری محصولات', ACR_Catalog::PRICING_URL, $status['pricing'] ?? array()); ?>
					<?php self::source_card('شرایط قطع سرویس', ACR_Catalog::TERMINATION_URL, $status['termination'] ?? array()); ?>
				</div>
				<section class="acr-panel acr-catalog-table">
					<div class="acr-panel-head">
						<div>
							<h2>محصولات قابل نمایش</h2>
							<p>آخرین همگام‌سازی:
								<?php echo esc_html((string) ACR_Settings::get('catalog_last_sync', 'هنوز اجرا نشده')); ?> ·
								اجرای بعدی: <?php echo $next ? esc_html(wp_date('Y/m/d H:i', $next)) : '—'; ?></p>
						</div><span class="acr-api-badge is-ok"><i></i>هر
							<?php echo esc_html(number_format_i18n(ACR_Catalog::interval_minutes())); ?> دقیقه</span>
					</div>
					<div class="acr-table-scroll">
						<table>
							<thead>
								<tr>
									<th>محصول</th>
									<th>قیمت نمایشی</th>
									<th>وضعیت منبع</th>
									<th>قابل سفارش</th>
									<th>آخرین دریافت</th>
								</tr>
							</thead>
							<tbody><?php foreach ($products as $product): ?>
									<tr>
										<td><strong><?php echo esc_html($product['name']); ?></strong><small
												dir="ltr"><?php echo esc_html($product['slug']); ?></small></td>
										<td><?php echo esc_html($product['price_label']); ?></td>
										<td><span
												class="acr-status <?php echo 'official' === $product['source_state'] ? 'is-active' : ''; ?>"><?php echo esc_html($product['source_state']); ?></span>
										</td>
										<td><?php echo (int) $product['purchasable'] ? '<span class="acr-check">بله</span>' : '<span class="acr-pending">فقط نمایش</span>'; ?>
										</td>
										<td><?php echo esc_html($product['source_synced_at'] ?: '—'); ?></td>
									</tr><?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>
				<div class="acr-dashboard-grid">
					<section class="acr-panel acr-block-guide">
						<div class="acr-panel-head">
							<div>
								<h2>بلاک کاتالوگ محصولات</h2>
								<p>داخل ویرایشگر برگه، «کاتالوگ محصولات آروان» را اضافه کنید.</p>
							</div><span class="dashicons dashicons-screenoptions"></span>
						</div><code dir="ltr">&lt;!-- wp:acr/product-catalog /--&gt;</code><small>جایگزین شورت‌کد: <b
								dir="ltr">[arvan_reseller_products]</b></small>
					</section>
					<section class="acr-panel acr-block-guide">
						<div class="acr-panel-head">
							<div>
								<h2>بلاک پروفایل مشتری</h2>
								<p>ورود وردپرس، کیف پول و سفارش CDN را در یک صفحه نشان می‌دهد.</p>
							</div><span class="dashicons dashicons-admin-users"></span>
						</div><code dir="ltr">&lt;!-- wp:acr/customer-profile /--&gt;</code><small>جایگزین شورت‌کد: <b
								dir="ltr">[arvan_reseller_portal]</b></small>
					</section>
				</div>
				<?php
	}

	private static function source_card(string $title, string $url, array $status): void
	{
		$success = !empty($status['success']);
		?>
				<article class="acr-source-card">
					<div><span
							class="dashicons dashicons-admin-links"></span><?php self::api_badge($success, $success ? 'دردسترس' : 'در انتظار دریافت'); ?>
					</div>
					<h3><?php echo esc_html($title); ?></h3><a href="<?php echo esc_url($url); ?>" target="_blank"
						rel="noopener noreferrer"
						dir="ltr"><?php echo esc_html($url); ?></a><?php if (!empty($status['message'])): ?><small><?php echo esc_html($status['message']); ?></small><?php endif; ?>
				</article>
				<?php
	}

	private static function customers(): void
	{
		global $wpdb;
		$required = (float) ACR_Settings::get('demo_hourly_cost', 1200) * (1 + min(20, max(0, (float) ACR_Settings::get('markup_percent', 10))) / 100);
		$rows = $wpdb->get_results("SELECT w.*, u.display_name, u.user_email, (SELECT COUNT(*) FROM {$wpdb->prefix}acr_orders o WHERE o.user_id=w.user_id) orders_count, (SELECT COUNT(*) FROM {$wpdb->prefix}acr_resources r WHERE r.user_id=w.user_id) resources_count FROM {$wpdb->prefix}acr_wallets w INNER JOIN {$wpdb->users} u ON u.ID=w.user_id ORDER BY w.updated_at DESC LIMIT 200"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::page_title('پروفایل مشتریان', 'وضعیت ورود به پنل، موجودی کیف پول و آمادگی ثبت سفارش');
		?>
				<section class="acr-panel acr-customer-table">
					<div class="acr-panel-head">
						<div>
							<h2>مشتریان پنل فرانت</h2>
							<p>حداقل موجودی لازم برای سفارش فعلی: <?php echo esc_html(number_format_i18n($required)); ?>
								تومان</p>
						</div><a class="acr-btn" href="<?php echo esc_url(admin_url('users.php')); ?>">کاربران وردپرس</a>
					</div>
					<div class="acr-table-scroll">
						<table>
							<thead>
								<tr>
									<th>مشتری</th>
									<th>کیف پول</th>
									<th>سفارش‌ها</th>
									<th>سرویس‌ها</th>
									<th>مرحله فعلی</th>
									<th>آخرین بروزرسانی</th>
								</tr>
							</thead>
							<tbody><?php if (!$rows): ?>
									<tr>
										<td class="acr-table-empty" colspan="6">هنوز کاربری وارد پروفایل مشتری نشده است.</td>
									</tr>
								<?php endif; ?>		<?php foreach ($rows as $row):
											  $ready = (float) $row->balance >= $required; ?>
									<tr>
										<td><strong><?php echo esc_html($row->display_name); ?></strong><small
												dir="ltr"><?php echo esc_html($row->user_email); ?></small></td>
										<td><strong><?php echo esc_html(number_format_i18n($row->balance)); ?></strong>
											تومان<small>آستانه:
												<?php echo esc_html(number_format_i18n($row->threshold)); ?></small></td>
										<td><?php echo esc_html(number_format_i18n($row->orders_count)); ?></td>
										<td><?php echo esc_html(number_format_i18n($row->resources_count)); ?></td>
										<td><?php if ($ready): ?><span class="acr-check">آماده سفارش</span><?php else: ?><span
													class="acr-pending">نیازمند شارژ</span><?php endif; ?></td>
										<td><?php echo esc_html($row->updated_at); ?></td>
									</tr><?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>
				<?php
	}

	private static function api_inventory(): void
	{
		$snapshot = ACR_API::dashboard_snapshot();
		?>
				<div class="acr-page-head">
					<div><span class="acr-eyebrow">ArvanCloud API Inventory</span>
						<h1>داده‌های API آروان</h1>
						<p>نمای نرمال‌شده منابع؛ بدون نمایش توکن یا Secret Key</p>
					</div><?php self::refresh_form('acr-api-inventory'); ?>
				</div>
				<?php self::inventory_section('cdn', 'دامنه‌های CDN', $snapshot['cdn'], array('نام دامنه', 'وضعیت', 'پلن', 'تاریخ ساخت')); ?>
				<?php self::inventory_section('cloud', 'سرورهای ابری', $snapshot['cloud_servers'], array('نام سرور', 'وضعیت', 'منطقه', 'IP / Flavor')); ?>
				<?php self::inventory_section('storage', 'Bucketهای Object Storage', $snapshot['object_storage'], array('نام Bucket', 'وضعیت', 'تاریخ ساخت', 'Endpoint')); ?>
				<?php
	}

	private static function inventory_section(string $type, string $title, array $data, array $headers): void
	{
		$items = is_array($data['items'] ?? null) ? $data['items'] : array();
		?>
				<section class="acr-panel acr-inventory" id="acr-<?php echo esc_attr(sanitize_html_class($type)); ?>">
					<div class="acr-panel-head">
						<div>
							<h2><?php echo esc_html($title); ?></h2>
							<p><code dir="ltr"><?php echo esc_html((string) ($data['endpoint'] ?? '—')); ?></code></p>
						</div>
						<?php self::api_badge(!empty($data['success']), !empty($data['success']) ? number_format_i18n(count($items)) . ' مورد' : 'عدم دسترسی'); ?>
					</div>
					<?php if (empty($data['success']) && !empty($data['message'])): ?>
						<div class="acr-inline-warning"><span
								class="dashicons dashicons-info-outline"></span><?php echo esc_html($data['message']); ?><a
								href="<?php echo esc_url(admin_url('admin.php?page=acr-settings')); ?>">تنظیم اتصال</a></div>
					<?php endif; ?>
					<div class="acr-table-scroll">
						<table>
							<thead>
								<tr><?php foreach ($headers as $header): ?>
										<th><?php echo esc_html($header); ?></th><?php endforeach; ?>
								</tr>
							</thead>
							<tbody>
								<?php if (!$items): ?>
									<tr>
										<td class="acr-table-empty" colspan="4">موردی از این سرویس دریافت نشد.</td>
									</tr><?php endif; ?>
								<?php foreach ($items as $item): ?>
									<?php if ('cdn' === $type): ?>
										<tr>
											<td dir="ltr">
												<strong><?php echo esc_html($item['name'] ?? '—'); ?></strong><small><?php echo esc_html($item['id'] ?? ''); ?></small>
											</td>
											<td><span
													class="acr-status is-<?php echo esc_attr(sanitize_html_class($item['status'] ?? 'unknown')); ?>"><?php echo esc_html($item['status'] ?? 'unknown'); ?></span>
											</td>
											<td><?php echo esc_html($item['plan'] ?? '—'); ?></td>
											<td><?php echo esc_html($item['created_at'] ?? '—'); ?></td>
										</tr><?php endif; ?>
									<?php if ('cloud' === $type): ?>
										<tr>
											<td><strong><?php echo esc_html($item['name'] ?? '—'); ?></strong><small
													dir="ltr"><?php echo esc_html($item['id'] ?? ''); ?></small></td>
											<td><span
													class="acr-status is-<?php echo esc_attr(sanitize_html_class($item['status'] ?? 'unknown')); ?>"><?php echo esc_html($item['status'] ?? 'unknown'); ?></span>
											</td>
											<td dir="ltr"><?php echo esc_html($item['region'] ?? '—'); ?></td>
											<td><span
													dir="ltr"><?php echo esc_html($item['ip'] ?? '—'); ?></span><small><?php echo esc_html($item['flavor'] ?? '—'); ?></small>
											</td>
										</tr><?php endif; ?>
									<?php if ('storage' === $type): ?>
										<tr>
											<td dir="ltr"><strong><?php echo esc_html($item['name'] ?? '—'); ?></strong></td>
											<td><span
													class="acr-status is-active"><?php echo esc_html($item['status'] ?? 'active'); ?></span>
											</td>
											<td><?php echo esc_html($item['created_at'] ?? '—'); ?></td>
											<td dir="ltr"><?php echo esc_html((string) ACR_Settings::get('s3_endpoint', '—')); ?>
											</td>
										</tr><?php endif; ?>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>
				<?php
	}

	private static function admins(): void
	{
		$admins = get_users(array('role__in' => array('administrator'), 'orderby' => 'registered', 'order' => 'DESC'));
		self::page_title('مدیران پنل', 'کاربران وردپرس که به تنظیمات و داده‌های مالی افزونه دسترسی دارند');
		?>
				<section class="acr-panel acr-admin-directory">
					<div class="acr-admin-directory__grid"><?php foreach ($admins as $admin): ?>
							<article><span
									class="acr-person__avatar"><?php echo esc_html(self::initial($admin->display_name)); ?></span>
								<div><strong><?php echo esc_html($admin->display_name); ?></strong><small
										dir="ltr"><?php echo esc_html($admin->user_email); ?></small><em>عضویت:
										<?php echo esc_html(mysql2date(get_option('date_format'), $admin->user_registered)); ?></em>
								</div><a href="<?php echo esc_url(get_edit_user_link($admin->ID)); ?>">ویرایش</a>
							</article><?php endforeach; ?>
					</div>
				</section>
				<?php
	}

	private static function services(): void
	{
		global $wpdb;
		$rows = $wpdb->get_results("SELECT r.*, u.display_name FROM {$wpdb->prefix}acr_resources r LEFT JOIN {$wpdb->users} u ON u.ID=r.user_id ORDER BY r.id DESC LIMIT 100"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::page_title('سرویس‌های CDN', 'منابع Provision شده و وضعیت اتصال هر مشتری');
		self::table_start(array('منبع', 'مشتری', 'وضعیت', 'آخرین محاسبه', 'تاریخ ایجاد'));
		if (!$rows) {
			self::empty_row(5, 'هنوز سرویسی ایجاد نشده است.');
		}
		foreach ($rows as $row) {
			printf('<tr><td><strong dir="ltr">%1$s</strong><small>%2$s</small></td><td>%3$s</td><td><span class="acr-status is-%4$s">%4$s</span></td><td>%5$s</td><td>%6$s</td></tr>', esc_html($row->external_id), esc_html($row->product_type), esc_html($row->display_name ?: '—'), esc_attr($row->status), esc_html($row->last_billed_at ?: '—'), esc_html($row->created_at));
		}
		self::table_end();
	}

	private static function transactions(): void
	{
		global $wpdb;
		$rows = $wpdb->get_results("SELECT t.*, u.display_name FROM {$wpdb->prefix}acr_transactions t LEFT JOIN {$wpdb->users} u ON u.ID=t.user_id ORDER BY t.id DESC LIMIT 100"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		self::page_title('تراکنش‌ها', 'دفتر کل تغییرات موجودی به تفکیک مشتری');
		self::table_start(array('مرجع', 'مشتری', 'نوع', 'مبلغ', 'موجودی پس از تراکنش', 'تاریخ'));
		if (!$rows) {
			self::empty_row(6, 'هنوز تراکنشی ثبت نشده است.');
		}
		foreach ($rows as $row) {
			printf('<tr><td dir="ltr">%1$s</td><td>%2$s</td><td><span class="acr-status is-%3$s">%3$s</span></td><td>%4$s تومان</td><td>%5$s تومان</td><td>%6$s</td></tr>', esc_html($row->reference), esc_html($row->display_name ?: '—'), esc_attr($row->type), esc_html(number_format_i18n($row->amount)), esc_html(number_format_i18n($row->balance_after)), esc_html($row->created_at));
		}
		self::table_end();
	}

	private static function logs(): void
	{
		global $wpdb;
		$audit_rows = $wpdb->get_results("SELECT l.*, u.display_name FROM {$wpdb->prefix}acr_audit_logs l LEFT JOIN {$wpdb->users} u ON u.ID=l.user_id ORDER BY l.id DESC LIMIT 300"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$usage_rows = $wpdb->get_results("SELECT g.*, u.display_name, r.external_id FROM {$wpdb->prefix}acr_usage_logs g LEFT JOIN {$wpdb->users} u ON u.ID=g.user_id LEFT JOIN {$wpdb->prefix}acr_resources r ON r.id=g.resource_id ORDER BY g.id DESC LIMIT 200"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		?>
				<div class="acr-page-head">
					<div><span class="acr-eyebrow">Debug &amp; Audit Trail</span>
						<h1>لاگ ورود و مصرف سرویس</h1>
						<p>ردیابی ارسال و تأیید OTP، ورود وردپرس، سفارش، کیف پول، Provision و صورتحساب مصرف.</p>
					</div><a class="acr-btn" href="<?php echo esc_url(admin_url('admin.php?page=acr-logs')); ?>"><span
							class="dashicons dashicons-update"></span>تازه‌سازی</a>
				</div>
				<div class="acr-log-summary">
					<article><strong><?php echo esc_html(number_format_i18n(count($audit_rows))); ?></strong><span>رویداد
							اخیر</span></article>
					<article><strong>1111</strong><span>OTP آزمایشی فعال</span></article>
					<article>
						<strong><?php echo get_option('users_can_register') ? 'فعال' : 'غیرفعال'; ?></strong><span>ثبت‌نام
							وردپرس</span></article>
				</div>
				<section class="acr-panel acr-table-panel acr-log-table">
					<div class="acr-panel-head">
						<div>
							<h2>رویدادهای ورود و سرویس</h2>
							<p>آخرین ۳۰۰ رویداد — هیچ توکن یا رمز عبوری ذخیره نمی‌شود.</p>
						</div>
					</div>
					<div class="acr-table-scroll">
						<table>
							<thead>
								<tr>
									<th>زمان</th>
									<th>دسته</th>
									<th>رویداد</th>
									<th>وضعیت</th>
									<th>کاربر</th>
									<th>پیام</th>
									<th>جزئیات</th>
								</tr>
							</thead>
							<tbody><?php if (!$audit_rows): ?>
									<tr>
										<td class="acr-table-empty" colspan="7">هنوز رویدادی ثبت نشده است.</td>
									</tr><?php endif; ?><?php foreach ($audit_rows as $row): ?>
									<tr>
										<td dir="ltr"><?php echo esc_html($row->created_at); ?></td>
										<td><code><?php echo esc_html($row->category); ?></code></td>
										<td><code><?php echo esc_html($row->event); ?></code></td>
										<td><span
												class="acr-status is-<?php echo esc_attr(sanitize_html_class($row->status)); ?>"><?php echo esc_html($row->status); ?></span>
										</td>
										<td><?php echo esc_html($row->display_name ?: ($row->user_id ? '#' . $row->user_id : 'Guest')); ?><small
												dir="ltr"><?php echo esc_html($row->ip_address); ?></small></td>
										<td><?php echo esc_html($row->message ?: '—'); ?></td>
										<td>
											<details>
												<summary>JSON</summary>
												<pre dir="ltr"><?php echo esc_html(self::pretty_json($row->context)); ?></pre>
											</details>
										</td>
									</tr><?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>
				<section class="acr-panel acr-table-panel acr-log-table">
					<div class="acr-panel-head">
						<div>
							<h2>لاگ مصرف و صورتحساب</h2>
							<p>رکوردهای قطعی ثبت‌شده در جدول مصرف افزونه.</p>
						</div>
					</div>
					<div class="acr-table-scroll">
						<table>
							<thead>
								<tr>
									<th>دوره</th>
									<th>کاربر</th>
									<th>سرویس</th>
									<th>واحد</th>
									<th>مبلغ پایه</th>
									<th>سهم</th>
									<th>مبلغ نهایی</th>
									<th>منبع</th>
								</tr>
							</thead>
							<tbody><?php if (!$usage_rows): ?>
									<tr>
										<td class="acr-table-empty" colspan="8">هنوز مصرفی ثبت نشده است.</td>
									</tr><?php endif; ?><?php foreach ($usage_rows as $row): ?>
									<tr>
										<td><span dir="ltr"><?php echo esc_html($row->period_start); ?></span><small
												dir="ltr"><?php echo esc_html($row->period_end); ?></small></td>
										<td><?php echo esc_html($row->display_name ?: '#' . $row->user_id); ?></td>
										<td dir="ltr"><?php echo esc_html($row->external_id ?: '#' . $row->resource_id); ?></td>
										<td><?php echo esc_html(number_format_i18n($row->units, 2)); ?></td>
										<td><?php echo esc_html(number_format_i18n($row->base_amount)); ?></td>
										<td><?php echo esc_html(number_format_i18n($row->markup_percent, 1)); ?>٪</td>
										<td><strong><?php echo esc_html(number_format_i18n($row->final_amount)); ?></strong>
										</td>
										<td><code><?php echo esc_html($row->source); ?></code></td>
									</tr><?php endforeach; ?>
							</tbody>
						</table>
					</div>
				</section>
				<?php
	}

	private static function pretty_json(string $json): string
	{
		$value = json_decode($json, true);
		$pretty = is_array($value) ? wp_json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : $json;
		return is_string($pretty) ? $pretty : '{}';
	}

	private static function settings(): void
	{
		self::page_title('تنظیمات افزونه', 'اتصال حساب مادر، قیمت‌گذاری و آستانه‌های مالی');
		?>
				<section class="acr-panel acr-settings-panel">
					<form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>"><input type="hidden"
							name="action" value="acr_save_settings"><?php wp_nonce_field('acr_save_settings'); ?>
						<div class="acr-form-section">
							<div>
								<h2>اطلاعات مجموعه</h2>
								<p>این نام در پنل مدیریتی نمایش داده می‌شود.</p>
							</div>
							<div class="acr-fields"><label>نام مجموعه<input type="text" name="organization_name"
										value="<?php echo esc_attr((string) ACR_Settings::get('organization_name', get_bloginfo('name'))); ?>"></label>
							</div>
						</div>
						<div class="acr-form-section">
							<div>
								<h2>فرانت‌آفیس و بروزرسانی</h2>
								<p>برگه پروفایل مشتری و بازه همگام‌سازی قیمت‌ها را مشخص کنید.</p>
							</div>
							<div class="acr-fields"><label>برگه پروفایل
									مشتری<?php wp_dropdown_pages(array('name' => 'portal_page_id', 'selected' => absint(ACR_Settings::get('portal_page_id', 0)), 'show_option_none' => 'انتخاب خودکار / همان برگه بلاک', 'option_none_value' => 0)); ?><small>در
										این برگه بلاک «پروفایل مشتری آروان» را قرار دهید.</small></label><label>بازه بروزرسانی
									کاتالوگ<select name="catalog_sync_minutes">
										<option value="15" <?php selected(ACR_Catalog::interval_minutes(), 15); ?>>هر ۱۵ دقیقه
										</option>
										<option value="60" <?php selected(ACR_Catalog::interval_minutes(), 60); ?>>هر ۱ ساعت
										</option>
										<option value="360" <?php selected(ACR_Catalog::interval_minutes(), 360); ?>>هر ۶ ساعت
										</option>
										<option value="720" <?php selected(ACR_Catalog::interval_minutes(), 720); ?>>هر ۱۲
											ساعت</option>
										<option value="1440" <?php selected(ACR_Catalog::interval_minutes(), 1440); ?>>روزانه
										</option>
										<option value="10080" <?php selected(ACR_Catalog::interval_minutes(), 10080); ?>>هفتگی
										</option>
									</select><small>بروزرسانی با WP-Cron انجام می‌شود و از صفحه کاتالوگ قابل اجرای دستی
										است.</small></label></div>
						</div>
						<div class="acr-form-section">
							<div>
								<h2>اتصال Machine User</h2>
								<p>برای CDN و Cloud Server استفاده می‌شود. توکن فعلی هرگز در فرم نمایش داده نمی‌شود.</p>
							</div>
							<div class="acr-fields"><label>شناسه Machine User<input type="text" name="machine_user_id" dir="ltr"
										value="<?php echo esc_attr((string) ACR_Settings::get('machine_user_id', '')); ?>"></label><label>توکن
									جدید<input type="password" name="machine_token" dir="ltr"
										placeholder="برای حفظ توکن فعلی خالی بگذارید" autocomplete="off"></label><label>حالت
									API<select name="api_mode">
										<option value="demo" <?php selected(ACR_Settings::get('api_mode', 'demo'), 'demo'); ?>>دمو (پیشنهادی برای ارائه)</option>
										<option value="live" <?php selected(ACR_Settings::get('api_mode', 'demo'), 'live'); ?>>زنده</option>
									</select></label><label>Regionهای سرور ابری<input type="text" name="cloud_regions" dir="ltr"
										value="<?php echo esc_attr((string) ACR_Settings::get('cloud_regions', 'ir-thr-c2')); ?>"
										placeholder="ir-thr-c2,ir-tbz-sh1"><small>چند Region را با کاما جدا
										کنید.</small></label></div>
						</div>
						<div class="acr-form-section">
							<div>
								<h2>SMS Provider</h2>
								<p>ارسال و اعتبارسنجی OTP از این بخش کنترل می‌شود و به حالت دمو وابسته نیست.</p>
							</div>
							<div class="acr-fields"><label>ارائه‌دهنده پیامک<select name="sms_provider">
										<option value="mock" <?php selected(ACR_Settings::get('sms_provider', 'mock'), 'mock'); ?>>Mock OTP (1111)</option>
									</select><small>OTP آزمایشی همیشه با کد ۱۱۱۱ فعال می‌ماند تا زمانی که ارائه‌دهنده واقعی اضافه شود.</small></label></div>
						</div>
						<div class="acr-form-section">
							<div>
								<h2>اتصال Object Storage</h2>
								<p>Object Storage از Access Key و Secret Key سازگار با S3 استفاده می‌کند و مستقل از Machine User
									است.</p>
							</div>
							<div class="acr-fields"><label>S3 Endpoint<input type="url" name="s3_endpoint" dir="ltr"
										value="<?php echo esc_attr((string) ACR_Settings::get('s3_endpoint', '')); ?>"
										placeholder="https://s3.ir-thr-at1.arvanstorage.ir"></label><label>S3 Access Key<input
										type="password" name="s3_access_key" dir="ltr"
										placeholder="برای حفظ مقدار فعلی خالی بگذارید" autocomplete="off"></label><label>S3
									Secret Key<input type="password" name="s3_secret_key" dir="ltr"
										placeholder="برای حفظ مقدار فعلی خالی بگذارید" autocomplete="off"></label></div>
						</div>
						<div class="acr-form-section">
							<div>
								<h2>قیمت‌گذاری و محدودیت</h2>
								<p>سهم ریسلر طبق بریف حداکثر ۲۰٪ است.</p>
							</div>
							<div class="acr-fields acr-fields--grid"><label>سهم ریسلر (%)<input type="number"
										name="markup_percent" min="0" max="20" step="0.1"
										value="<?php echo esc_attr((string) ACR_Settings::get('markup_percent', 10)); ?>"></label><label>آستانه
									پیش‌فرض (تومان)<input type="number" name="default_threshold" min="0" step="1000"
										value="<?php echo esc_attr((string) ACR_Settings::get('default_threshold', 50000)); ?>"></label><label>مصرف
									ساعتی دمو (تومان)<input type="number" name="demo_hourly_cost" min="0" step="100"
										value="<?php echo esc_attr((string) ACR_Settings::get('demo_hourly_cost', 1200)); ?>"></label>
							</div>
						</div>
						<div class="acr-form-actions"><button class="acr-btn acr-btn--primary" type="submit">ذخیره
								تنظیمات</button></div>
					</form>
					<form class="acr-test-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
						<input type="hidden" name="action"
							value="acr_test_connection"><?php wp_nonce_field('acr_test_connection'); ?><button class="acr-btn"
							type="submit">آزمایش ارتباط API</button></form>
				</section>
				<?php
	}

	private static function page_title(string $title, string $desc): void
	{
		printf('<div class="acr-page-head"><div><span class="acr-eyebrow">پنل مدیریت</span><h1>%1$s</h1><p>%2$s</p></div></div>', esc_html($title), esc_html($desc));
	}

	private static function table_start(array $headers): void
	{
		echo '<section class="acr-panel acr-table-panel"><div class="acr-table-scroll"><table><thead><tr>';
		foreach ($headers as $header) {
			echo '<th>' . esc_html($header) . '</th>';
		}
		echo '</tr></thead><tbody>';
	}

	private static function empty_row(int $count, string $message): void
	{
		printf('<tr><td class="acr-table-empty" colspan="%1$d">%2$s</td></tr>', absint($count), esc_html($message));
	}

	private static function table_end(): void
	{
		echo '</tbody></table></div></section>';
	}
}
