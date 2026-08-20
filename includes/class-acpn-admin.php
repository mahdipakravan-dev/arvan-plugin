<?php
/** @package ArvanCloudPartnerNetwork */
defined( 'ABSPATH' ) || exit;

final class ACPN_Admin {
	private const PAGE_SLUG  = 'acpn-partner-network';
	private const CAPABILITY = 'manage_options';

	public function register_hooks(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_init', array( $this, 'maybe_redirect_after_activation' ) );
		add_action( 'admin_post_acpn_start_setup', array( $this, 'handle_start_setup' ) );
		add_filter( 'plugin_action_links_' . ACPN_BASENAME, array( $this, 'add_plugin_action_links' ) );
	}

	public function register_menu(): void {
		add_menu_page(
			esc_html__( 'ArvanCloud Partner Network', 'arvancloud-partner-network' ),
			esc_html__( 'ArvanCloud Partners', 'arvancloud-partner-network' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			'dashicons-networking',
			58
		);
		add_submenu_page(
			self::PAGE_SLUG,
			esc_html__( 'Welcome', 'arvancloud-partner-network' ),
			esc_html__( 'Welcome', 'arvancloud-partner-network' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}
		wp_enqueue_style( 'acpn-admin', ACPN_URL . 'assets/css/admin.css', array(), ACPN_VERSION );
		wp_enqueue_script( 'acpn-admin', ACPN_URL . 'assets/js/admin.js', array(), ACPN_VERSION, true );
	}

	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'arvancloud-partner-network' ) );
		}
		$settings = get_option( 'acpn_settings', array() );
		$step     = isset( $settings['onboarding_step'] ) ? sanitize_key( $settings['onboarding_step'] ) : 'welcome';
		include ACPN_PATH . 'admin/views/welcome.php';
	}

	public function maybe_redirect_after_activation(): void {
		if ( ! get_option( 'acpn_activation_redirect' ) ) {
			return;
		}
		delete_option( 'acpn_activation_redirect' );
		if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) || ! current_user_can( self::CAPABILITY ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::PAGE_SLUG ) );
		exit;
	}

	public function handle_start_setup(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to perform this action.', 'arvancloud-partner-network' ) );
		}
		check_admin_referer( 'acpn_start_setup' );
		$settings                       = get_option( 'acpn_settings', array() );
		$settings['onboarding_step']    = 'business-profile';
		$settings['onboarding_started'] = time();
		$settings['installed_version']  = ACPN_VERSION;
		update_option( 'acpn_settings', $settings, false );
		do_action( 'acpn_onboarding_started', get_current_user_id() );
		wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG, 'step' => 'business-profile', 'started' => '1' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public function add_plugin_action_links( array $links ): array {
		$url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Welcome', 'arvancloud-partner-network' ) . '</a>' );
		return $links;
	}
}

