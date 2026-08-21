<?php
/**
 * Uninstall behavior keeps financial records by default.
 * Define ACR_REMOVE_DATA_ON_UNINSTALL as true to remove plugin data explicitly.
 *
 * @package ArvanCloudReseller
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

if ( ! defined( 'ACR_REMOVE_DATA_ON_UNINSTALL' ) || true !== ACR_REMOVE_DATA_ON_UNINSTALL ) {
	return;
}

global $wpdb;
$tables = array( 'settings', 'wallets', 'transactions', 'payments', 'orders', 'resources', 'usage_logs', 'settlements', 'products' );
foreach ( $tables as $table ) {
	$name = $wpdb->prefix . 'acr_' . $table;
	$wpdb->query( "DROP TABLE IF EXISTS {$name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}
delete_option( 'acr_db_version' );
