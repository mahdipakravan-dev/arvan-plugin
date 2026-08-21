<?php
/**
 * Plugin Name:       ArvanCloud Reseller
 * Plugin URI:        https://example.com/arvancloud-reseller
 * Description:       پنل مستقل فروش و مدیریت خدمات آروان‌کلاد با کیف پول پیش‌پرداخت.
 * Version:           1.2.1
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            ArvanCloud Reseller Team
 * Text Domain:       arvancloud-reseller
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'ACR_VERSION', '1.2.1' );
define( 'ACR_FILE', __FILE__ );
define( 'ACR_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACR_URL', plugin_dir_url( __FILE__ ) );

require_once ACR_PATH . 'includes/class-acr-crypto.php';
require_once ACR_PATH . 'includes/class-acr-settings.php';
require_once ACR_PATH . 'includes/class-acr-audit.php';
require_once ACR_PATH . 'includes/class-acr-catalog.php';
require_once ACR_PATH . 'includes/class-acr-installer.php';
require_once ACR_PATH . 'includes/class-acr-wallet.php';
require_once ACR_PATH . 'includes/class-acr-otp.php';
require_once ACR_PATH . 'includes/class-acr-api.php';
require_once ACR_PATH . 'includes/class-acr-cron.php';
require_once ACR_PATH . 'includes/class-acr-admin.php';
require_once ACR_PATH . 'includes/class-acr-frontend.php';
require_once ACR_PATH . 'includes/class-acr-blocks.php';

register_activation_hook( __FILE__, array( 'ACR_Installer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ACR_Installer', 'deactivate' ) );

function acr_boot_plugin(): void {
	load_plugin_textdomain( 'arvancloud-reseller', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	ACR_Installer::maybe_upgrade();
	ACR_Audit::init();
	ACR_Catalog::init();
	ACR_OTP::init();
	ACR_Admin::init();
	ACR_Frontend::init();
	ACR_Cron::init();
	ACR_Blocks::init();
}
add_action( 'plugins_loaded', 'acr_boot_plugin' );
