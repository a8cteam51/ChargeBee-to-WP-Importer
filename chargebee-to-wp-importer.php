<?php
/**
 * The chargebee-to-wp-importer plugin file.
 *
 * @since       0.1.0
 * @version     0.1.0
 * @author      Automattic Special Projects
 * @package     T51_ChargBee_to_WP_Importer
 * @license     GPL-3.0-or-later
 *
 * @noinspection    ALL
 *
 * @wordpress-plugin
 * Plugin Name:             Team51 Chargebee to WP Importer
 * Description:             Imports Stripe Subscribers from Chargebee to WordPress (WooCommerce subscriptions with WC Payments)
 * Version:                 0.1.0
 * Requires at least:       6.6
 * Requires PHP:            8.3
 * Author:                  Automattic Special Projects
 * Author URI:              https://wpspecialprojects.com
 * License:                 GPL-3.0-or-later
 * License URI:             https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:             t51-chargbee-to-wp-importer
 * Domain Path:             /languages
 */

use T51\ChargeBee_To_WP\Importer\Plugin;


// Define plugin constants.
define( 'T51_CHARGEBEE_TO_WPPATH', plugin_dir_path( __FILE__ ) );
define( 'T51_CHARGEBEE_TO_WPURL', plugin_dir_url( __FILE__ ) );

add_action(
	'plugins_loaded',
	function () {
		// Load all functions.
		require_once T51_CHARGEBEE_TO_WPPATH . '/functions.php';
		if ( is_php_version_compatible( t51_chargbee_to_wp_importer_metadata( 'RequiresPHP' ) ) && is_wp_version_compatible( t51_chargbee_to_wp_importer_metadata( 'RequiresWP' ) ) ) {

			// Load the chargebee sdk
			require_once T51_CHARGEBEE_TO_WPPATH . '/chargebee/init.php';

			$directory = new RecursiveDirectoryIterator( T51_CHARGEBEE_TO_WPPATH . 'includes/' );
			$iterator  = new RecursiveIteratorIterator( $directory );
			$php_files = array();

			foreach ( $iterator as $file ) {
				if ( $file->isFile() && preg_match( '/\.php$/', $file->getFilename() ) ) {
					$php_files[] = $file->getPathname();
				}
			}

			if ( ! empty( $php_files ) ) {
				foreach ( $php_files as $file ) {
					if ( preg_match( '#/includes/_#i', $file ) ) {
						continue; // Ignore files prefixed with an underscore.
					}

					require_once $file;
				}
			}
		}
		Plugin::init();

		add_action(
			'init',
			function () {
				// If you want to inspect a specific subscription, uncomment the following lines
				// $sub = \ChargeBee\ChargeBee\Models\Subscription::retrieve('16BlLjUJH3Y1554Df');
				// dd($sub); // Please ensure you have the PinkCrab Debugging plugin installed and activated to use dd()
				// var_dump( $sub ); die(); // If you dont have the plugin, use this instead
			}
		);
	}
);
