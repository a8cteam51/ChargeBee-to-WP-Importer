<?php
/**
 * Compile the WC Import sheet from the Chargebee subscriptions.
 *
 * Runs part of WP CLI CLI to compile the WooCommerce import sheet from the Chargebee subscriptions.
 *
 * @package     T51_ChargeBee_to_WP_Importer
 * @author      Automattic Special Projects
 * @license     GPL-3.0-or-later
 *
 * @since       0.1.0
 * @version     0.1.0
 */

declare( strict_types=1 );

namespace T51\ChargeBee_To_WP\Importer\Command;

use WP_CLI;
use T51\ChargeBee_To_WP\Importer\Action\Compile_WC_Import;
use T51\ChargeBee_To_WP\Importer\CSV\Reader;

/**
 * Compile WC Import From Chargebee Command
 */
class Compile_WC_Import_From_Chargbee_Command {

	/**
	 * Invokes the command.
	 *
	 * @param array $args       The command arguments.
	 * @param array $assoc_args The command associative arguments.
	 *
	 * @return void
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		// Default args.
		$defualt = array(
			'limit'       => 100000,
			'offset'      => 0,
			'obfusticate' => t51_chargbee_to_wp_importer_get_config( 'obfusticate' ),
			'csv_file'    => T51_ChargeBee_to_WP_Importer_metadata( 'pop_chargebee_subscriptions.csv' ),
		);


		// Replace the default args with the provided ones.
		$args = array(
			'limit'       => $assoc_args['limit'] ?? $defualt['limit'],
			'offset'      => $assoc_args['offset'] ?? $defualt['offset'],
			'obfusticate' => $assoc_args['obfusticate'] ?? $defualt['obfusticate'],
			'csv_file'    => $assoc_args['csv_file'] ?? $defualt['csv_file'],
		);

		$args['obfusticate'] = false;


		// Get the data.
		$reader = new Reader( $args['csv_file'] );
		$reader->infer_headers();
		$data = $reader->get_data();

		// If we have no data, exit with error.
		if ( empty( $data ) ) {
			WP_CLI::error( 'No data found in the CSV of ChargeBee data, did you run the intiial command?' );
			return;
		}

		// Remove any no active subscriptions.
		$data = $this->remove_no_active_subscriptions( $data );

		// If we obfusticate, obfusticate the data.
		if ( (bool) $args['obfusticate'] ) {
			$this->obfusticate_message();
			$data = array_map( array( $this, 'obfusticate_row' ), $data );
		}

		WP_CLI::log( 'Found ' . count( $data ) . ' active subscriptions.' );

		// Setup the compiler and import
		$compiler = new Compile_WC_Import();
		$count    = $compiler->insert_data( $data );

		WP_CLI::success( 'The CSV file has been created! - ' . $compiler->get_csv_path() );
		WP_CLI::success( 'The number of subscriptions fetched: ' . $count );
	}

	/**
	 * Obfusticate a rows data.
	 *
	 * @param array $row The row to obfusticate.
	 *
	 * @return array The obfusticated row.
	 */
	private function obfusticate_row( array $row ): array {
		// Obfusticate the row.
		$row['email'] = str_replace( '@', 'obfusticated@', $row['email'] );

		// If the payment gateway is stripe.
		if ( 'stripe' === $row['gateway'] || 'gocardless' === $row['gateway'] ) {
			$row['stripe_payment']  = str_replace( 'pm_', 'obfusticated_', $row['stripe_payment'] );
			$row['stripe_customer'] = str_replace( 'cus_', 'obfusticated_', $row['stripe_customer'] );
		} elseif ( 'gocardless' === $row['gateway'] ) {
			$row['stripe_payment'] = 'obfusticated_' . $row['stripe_payment'];
		} else {
			dd( 'Cant obfusticate this customer as unkown payment gateway.', $row );
		}

		return $row;
	}

	/**
	 * Show that we are in obfusticate mode.
	 *
	 * @return void
	 */
	public function obfusticate_message(): void {
		WP_CLI::log( '' );
		WP_CLI::log( '' );
		WP_CLI::log( '          ###############################################################################' );
		WP_CLI::log( '          ###############################################################################' );
		WP_CLI::log(
			'           _  _  _    ________ _   ______ _             _  _  _    _         _    _ _
          / \|_)|_| |(_  |  | /  /\ |  | / \|\ |   |\/|/ \| \|_   |_|\ | /\ |_)| |_| \
          \_/|_)| |_|__) | _|_\_/--\| _|_\_/| \|   |  |\_/|_/|_   |_| \|/--\|_)|_|_|_/ '
		);
		WP_CLI::log( '          ###############################################################################' );
		WP_CLI::log( '          ###############################################################################' );
		WP_CLI::log( '' );
		WP_CLI::log( '' );
	}

	/**
	 * Remove any no active subscriptions.
	 *
	 * @param array $data The data to filter.
	 *
	 * @return array The filtered data.
	 */
	public function remove_no_active_subscriptions( array $data ): array {
		// Remove all that are not active.
		$data = array_filter(
			$data,
			function ( $row ) {
				return 'active' === $row['status'];
			}
		);

		return $data;
	}
}
