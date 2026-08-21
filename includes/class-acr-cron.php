<?php
/**
 * Hourly consumption billing and daily settlement simulation.
 *
 * @package ArvanCloudReseller
 */

defined( 'ABSPATH' ) || exit;

final class ACR_Cron {
	public static function init(): void {
		add_action( 'acr_hourly_billing', array( __CLASS__, 'bill_resources' ) );
		add_action( 'acr_daily_settlement', array( __CLASS__, 'settle' ) );
	}

	public static function bill_resources(): void {
		global $wpdb;
		$resources = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}acr_resources WHERE status = 'active' LIMIT 500" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$markup    = min( 20, max( 0, (float) ACR_Settings::get( 'markup_percent', 10 ) ) );
		$base      = max( 0, (float) ACR_Settings::get( 'demo_hourly_cost', 1200 ) );
		$now       = current_time( 'mysql', true );

		foreach ( $resources as $resource ) {
			$start = $resource->last_billed_at ?: gmdate( 'Y-m-d H:00:00', strtotime( '-1 hour' ) );
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$wpdb->prefix}acr_usage_logs WHERE resource_id = %d AND period_start = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					(int) $resource->id,
					$start
				)
			);
			if ( $exists ) {
				continue;
			}

			$final = round( $base * ( 1 + ( $markup / 100 ) ), 2 );
			$wallet = ACR_Wallet::get( (int) $resource->user_id );
			if ( (float) $wallet->balance < $final ) {
				ACR_Audit::log( 'billing', 'usage_charge', 'blocked', __( 'Resource billing blocked by insufficient balance.', 'arvancloud-reseller' ), array( 'resource_id' => (int) $resource->id, 'charge' => $final, 'balance' => (float) $wallet->balance ), (int) $resource->user_id );
				self::restrict( $resource, 'suspend' );
				continue;
			}

			$reference = 'usage-' . (int) $resource->id . '-' . strtotime( $start );
			if ( ! ACR_Wallet::debit( (int) $resource->user_id, $final, $reference, __( 'هزینه مصرف ساعتی CDN', 'arvancloud-reseller' ) ) ) {
				ACR_Audit::log( 'billing', 'usage_charge', 'failed', __( 'Wallet debit failed for usage.', 'arvancloud-reseller' ), array( 'resource_id' => (int) $resource->id, 'charge' => $final ), (int) $resource->user_id );
				continue;
			}
			$wpdb->insert(
				$wpdb->prefix . 'acr_usage_logs',
				array(
					'resource_id'    => (int) $resource->id,
					'user_id'        => (int) $resource->user_id,
					'period_start'   => $start,
					'period_end'     => $now,
					'units'          => 1,
					'base_amount'    => $base,
					'markup_percent' => $markup,
					'final_amount'   => $final,
					'source'         => (string) ACR_Settings::get( 'api_mode', 'demo' ),
					'created_at'     => $now,
				),
				array( '%d', '%d', '%s', '%s', '%f', '%f', '%f', '%f', '%s', '%s' )
			);
			$wpdb->update( $wpdb->prefix . 'acr_resources', array( 'last_billed_at' => $now, 'updated_at' => $now ), array( 'id' => (int) $resource->id ), array( '%s', '%s' ), array( '%d' ) );
			ACR_Audit::log( 'billing', 'usage_charge', 'success', __( 'Hourly service usage recorded and charged.', 'arvancloud-reseller' ), array( 'resource_id' => (int) $resource->id, 'period_start' => $start, 'period_end' => $now, 'base_amount' => $base, 'markup_percent' => $markup, 'final_amount' => $final ), (int) $resource->user_id );

			$wallet = ACR_Wallet::get( (int) $resource->user_id );
			if ( (float) $wallet->balance <= (float) $wallet->threshold ) {
				self::notify_low_balance( (int) $resource->user_id, (float) $wallet->balance );
			}
			if ( (float) $wallet->balance <= 0 ) {
				self::restrict( $resource, 'suspend' );
			}
		}
	}

	private static function restrict( object $resource, string $action ): void {
		global $wpdb;
		if ( ACR_API::restrict_resource( $resource, $action ) ) {
			$wpdb->update( $wpdb->prefix . 'acr_resources', array( 'status' => 'suspended', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $resource->id ), array( '%s', '%s' ), array( '%d' ) );
			ACR_Audit::log( 'service', 'resource_restriction', 'success', __( 'Resource suspended.', 'arvancloud-reseller' ), array( 'resource_id' => (int) $resource->id, 'action' => $action ), (int) $resource->user_id );
		}
	}

	private static function notify_low_balance( int $user_id, float $balance ): void {
		$user = get_userdata( $user_id );
		if ( ! $user || ! is_email( $user->user_email ) ) {
			return;
		}
		$lock = 'acr_low_balance_' . $user_id;
		if ( get_transient( $lock ) ) {
			return;
		}
		wp_mail(
			$user->user_email,
			__( 'هشدار موجودی کیف پول', 'arvancloud-reseller' ),
			sprintf( __( 'موجودی کیف پول شما به %s تومان رسیده است.', 'arvancloud-reseller' ), number_format_i18n( $balance ) )
		);
		set_transient( $lock, 1, 12 * HOUR_IN_SECONDS );
	}

	public static function settle(): void {
		global $wpdb;
		$end   = current_time( 'mysql', true );
		$start = gmdate( 'Y-m-d H:i:s', strtotime( '-1 day', strtotime( $end ) ) );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(base_amount),0) base_total, COALESCE(SUM(final_amount-base_amount),0) reseller_total FROM {$wpdb->prefix}acr_usage_logs WHERE period_end > %s AND period_end <= %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$start,
				$end
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'acr_settlements',
			array(
				'period_start'    => $start,
				'period_end'      => $end,
				'base_amount'     => (float) $row->base_total,
				'reseller_amount' => (float) $row->reseller_total,
				'status'          => 'simulated',
				'reference'       => 'settlement-' . gmdate( 'Ymd' ),
				'created_at'      => $end,
			),
			array( '%s', '%s', '%f', '%f', '%s', '%s', '%s' )
		);
	}
}
