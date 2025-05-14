<?php
/**
 * Main plugin class.
 *
 * @since       0.1.0
 * @version     0.1.0
 *
 * @package     T51_ChargeBee_to_WP_Importer
 * @author      Automattic Special Projects
 * @license     GPL-3.0-or-later
 */

declare( strict_types=1 );

namespace T51\ChargeBee_To_WP\Importer;

use ChargeBee\ChargeBee\Environment;
use T51\ChargeBee_To_WP\Importer\Command\Compile_Chargebee_Subscriptions_Command;
use T51\ChargeBee_To_WP\Importer\Command\Compile_WC_Import_From_Chargbee_Command;

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
class Plugin {

	/**
	 * Initializes the plugin.
	 *
	 * @return  void
	 */
	public static function init(): void {

		// Load the autoloader in parent wp-dir.
		$dir = dirname( __DIR__, 3 );
		require_once $dir . '/vendor/autoload.php';

		\ChargeBee\ChargeBee\Environment::configure(
			t51_chargbee_to_wp_importer_get_config( 'membership_id' ),
			t51_chargbee_to_wp_importer_get_config( 'api_key' )
		);
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
				\WP_CLI::add_command( 'cb2wp compile-chargebee', Compile_Chargebee_Subscriptions_Command::class );
				\WP_CLI::add_command( 'cb2wp compile-wc', Compile_WC_Import_From_Chargbee_Command::class );
		}
	}
}
