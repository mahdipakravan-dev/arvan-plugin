<?php
/**
 * Wallet ledger. Balance changes and ledger rows are committed together.
 *
 * @package ArvanCloudReseller
 */

defined( 'ABSPATH' ) || exit;

final class ACR_Wallet {
	public static function get( int $user_id ): object {
		global $wpdb;
		$table  = $wpdb->prefix . 'acr_wallets';
		$wallet = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE user_id = %d", $user_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $wallet ) {
			return $wallet;
		}

		$now = current_time( 'mysql', true );
		$wpdb->insert(
			$table,
			array(
				'user_id'    => $user_id,
				'balance'    => 0,
				'threshold'  => (float) ACR_Settings::get( 'default_threshold', 50000 ),
				'created_at' => $now,
				'updated_at' => $now,
			),
			array( '%d', '%f', '%f', '%s', '%s' )
		);

		return (object) array(
			'id'        => (int) $wpdb->insert_id,
			'user_id'   => $user_id,
			'balance'   => '0.00',
			'threshold' => (string) ACR_Settings::get( 'default_threshold', 50000 ),
		);
	}

	public static function credit( int $user_id, float $amount, string $reference, string $description = '' ): bool {
		return self::change( $user_id, abs( $amount ), 'credit', $reference, $description );
	}

	public static function debit( int $user_id, float $amount, string $reference, string $description = '' ): bool {
		return self::change( $user_id, -abs( $amount ), 'debit', $reference, $description );
	}

	private static function change( int $user_id, float $delta, string $type, string $reference, string $description ): bool {
		global $wpdb;
		$wallet = self::get( $user_id );
		$new     = round( (float) $wallet->balance + $delta, 2 );
		if ( $new < 0 ) {
			ACR_Audit::log( 'wallet', 'balance_change', 'failed', __( 'Insufficient wallet balance.', 'arvancloud-reseller' ), array( 'delta' => $delta, 'reference' => $reference ), $user_id );
			return false;
		}

		$wallets = $wpdb->prefix . 'acr_wallets';
		$ledger  = $wpdb->prefix . 'acr_transactions';
		$wpdb->query( 'START TRANSACTION' );
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wallets} SET balance = %f, updated_at = %s WHERE id = %d AND balance = %f", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$new,
				current_time( 'mysql', true ),
				(int) $wallet->id,
				(float) $wallet->balance
			)
		);
		if ( 1 !== $updated ) {
			$wpdb->query( 'ROLLBACK' );
			ACR_Audit::log( 'wallet', 'balance_change', 'failed', __( 'Concurrent wallet update rejected.', 'arvancloud-reseller' ), array( 'delta' => $delta, 'reference' => $reference ), $user_id );
			return false;
		}

		$inserted = $wpdb->insert(
			$ledger,
			array(
				'wallet_id'     => (int) $wallet->id,
				'user_id'       => $user_id,
				'type'          => $type,
				'amount'        => abs( $delta ),
				'balance_after' => $new,
				'status'        => 'completed',
				'reference'     => $reference,
				'description'   => $description,
				'metadata'      => '{}',
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%f', '%f', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$wpdb->query( 'ROLLBACK' );
			ACR_Audit::log( 'wallet', 'balance_change', 'failed', __( 'Wallet ledger insert failed.', 'arvancloud-reseller' ), array( 'delta' => $delta, 'reference' => $reference ), $user_id );
			return false;
		}
		$wpdb->query( 'COMMIT' );
		ACR_Audit::log( 'wallet', 'balance_change', 'success', $description, array( 'delta' => $delta, 'balance_after' => $new, 'reference' => $reference ), $user_id );
		return true;
	}
}
