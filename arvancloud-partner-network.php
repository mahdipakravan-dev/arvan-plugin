<?php
/**
 * Plugin Name:       ArvanCloud Partner Network
 * Plugin URI:        https://www.arvancloud.ir/
 * Description:       A foundation for the ArvanCloud reseller and commercial partner network.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            ArvanCloud
 * Text Domain:       arvancloud-partner-network
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 *
 * @package ArvanCloudPartnerNetwork
 */

defined( 'ABSPATH' ) || exit;

define( 'ACPN_VERSION', '1.0.0' );
define( 'ACPN_FILE', __FILE__ );
define( 'ACPN_PATH', plugin_dir_path( __FILE__ ) );
define( 'ACPN_URL', plugin_dir_url( __FILE__ ) );
define( 'ACPN_BASENAME', plugin_basename( __FILE__ ) );

require_once ACPN_PATH . 'includes/class-acpn-activator.php';
require_once ACPN_PATH . 'includes/class-acpn-deactivator.php';
require_once ACPN_PATH . 'includes/icons.php';
require_once ACPN_PATH . 'includes/class-acpn-plugin.php';

register_activation_hook( __FILE__, array( 'ACPN_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'ACPN_Deactivator', 'deactivate' ) );

function acpn_run_plugin(): void {
	ACPN_Plugin::instance()->run();
}

add_action( 'plugins_loaded', 'acpn_run_plugin' );

