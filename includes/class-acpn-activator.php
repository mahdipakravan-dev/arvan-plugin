<?php
/** @package ArvanCloudPartnerNetwork */
defined( 'ABSPATH' ) || exit;

final class ACPN_Activator {
	public static function activate(): void {
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( ACPN_BASENAME );
			wp_die(
				esc_html__( 'ArvanCloud Partner Network requires PHP 8.1 or newer.', 'arvancloud-partner-network' ),
				esc_html__( 'Plugin activation failed', 'arvancloud-partner-network' ),
				array( 'back_link' => true )
			);
		}

		$defaults = array(
			'installed_version' => ACPN_VERSION,
			'onboarding_step'   => 'welcome',
			'onboarding_done'   => false,
			'installed_at'      => time(),
		);

		add_option( 'acpn_settings', $defaults, '', false );
		update_option( 'acpn_activation_redirect', 1, false );
		do_action( 'acpn_activated', $defaults );
	}
}

