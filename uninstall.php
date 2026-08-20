<?php
/** @package ArvanCloudPartnerNetwork */
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'acpn_settings' );
delete_option( 'acpn_activation_redirect' );

if ( is_multisite() ) {
	delete_site_option( 'acpn_settings' );
	delete_site_option( 'acpn_activation_redirect' );
}

