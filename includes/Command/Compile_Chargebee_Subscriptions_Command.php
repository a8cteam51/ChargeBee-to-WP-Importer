<?php
/**
 * Compile Chargebee Subscriptions Command
 *
 * Runs part of WP CLI CLI to compile the chargebee subscriptions
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
use T51\ChargeBee_To_WP\Importer\Action\Compile_ChargeBee_Data;

/**
 * Compile Chargebee Subscriptions Command
 */
class Compile_Chargebee_Subscriptions_Command {

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
			'limit'     => 100,
			'offset'    => 'start',
			'file_name' => 'pop_chargebee_subscriptions',
			'delay'     => 30,
		);

		// Replace the default args with the provided ones.
		$args = array(
			'limit'     => $assoc_args['limit'] ?? $defualt['limit'],
			'offset'    => $assoc_args['offset'] ?? $defualt['offset'],
			'file_name' => $assoc_args['file'] ?? $defualt['file_name'],
			'delay'     => $assoc_args['delay'] ?? $defualt['delay'],
		);


		// Create an instance of the Subscription_Compiler class.
		$compiler = new Compile_ChargeBee_Data( esc_attr( $args['file_name'] ) . '.csv' );

		// If the offert is not 'start', set the next offset.
		if ( 'start' !== $args['offset'] ) {
			$compiler->set_next_offset( esc_attr( $args['offset'] ) );
		}

		$count = $compiler->get_all_subscriptions(
			absint( $args['limit'] ),
			absint( $args['delay'] )
		);

		// Create the CSV file.
		$path = $compiler->write_csv();

		WP_CLI::success( 'The CSV file has been created! - ' . $path );
		WP_CLI::success( 'The number of subscriptions fetched: ' . $count );
	}
}
