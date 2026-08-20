<?php
/** @package ArvanCloudPartnerNetwork */
defined( 'ABSPATH' ) || exit;

require_once ACPN_PATH . 'includes/class-acpn-admin.php';

final class ACPN_Plugin {
	private static ?self $instance = null;
	private ACPN_Admin $admin;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->admin = new ACPN_Admin();
	}

	public function run(): void {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		$this->admin->register_hooks();
		do_action( 'acpn_loaded', $this );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain( 'arvancloud-partner-network', false, dirname( ACPN_BASENAME ) . '/languages' );
	}

	private function __clone() {}
}

