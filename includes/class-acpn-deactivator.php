<?php
/** @package ArvanCloudPartnerNetwork */
defined( 'ABSPATH' ) || exit;

final class ACPN_Deactivator {
	public static function deactivate(): void {
		delete_option( 'acpn_activation_redirect' );
		do_action( 'acpn_deactivated' );
	}
}

