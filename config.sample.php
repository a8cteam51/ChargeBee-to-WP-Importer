<?php
/**
 * The t51-chargebee-to-wp importer plugin configuration file.
 *
 * @since       0.1.0
 * @version     0.1.0
 * @author      Automattic Special Projects
 * @package     T51_ChargBee_to_WP_Importer
 * @license     GPL-3.0-or-later
 */

defined( 'ABSPATH' ) || exit;

/**
 * Returns the plugin configuration.
 *
 * Example of product_map:
 * 'pop-membership-company-annual' => array(    // Plan id from Chargebee.
 *   'prod'      => 70716,   // Product ID.
 *   'var'       => 70719,   // Variation ID (leave blank if simple product).
 *   'type'      => 'SME',   // Type of membership (Attribute for variation, leave blank if simple product).
 *   'frequency' => 'Annual' // Frequency of membership (Attribute for variation, leave blank if simple product).
 *  ),
 *
 * @return  array{
 *  'api_key': string,
 *  'membership_id': string,
 *  'obfusticate': bool,
 *  'product_map': array<string, array{string, int[]}>,
 * }
 */
return array(
	'api_key'       => 'APIKEY',  // Your ChargeBee API key.
	'membership_id' => 'ACCID',   // Your ChargeBee account ID.
	'obfusticate'   => true,      // If all stripe data should be obfusticated.
	'product_map'   => array(
		'UK'      => array(
			// Variation
			'pop-membership-individual-monthly' => array(
				'prod'      => 70927,
				'var'       => 70939,
				'type'      => 'Individual', // Update these to your attributes.
				'frequency' => 'Annually',
			),
		),
		'INT'     => array(
			'pop-membership-individual-yearly' => array(
				'prod'      => 70927,
				'var'       => 70940,
				'type'      => 'Individual',
				'frequency' => 'Monthly',
			),
		),
		'Obscure' => array(
			// Simple product
			'pop-membership-individual-monthly' => array( // <-- "pop-membership-individual-monthly" name of plan on Chargebee.
				'prod'      => 70936,
				'var'       => null,
				'type'      => null, // Leave blank if simple product.
				'frequency' => null,
			),
		),
	),
);
