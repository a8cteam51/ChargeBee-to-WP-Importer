<?php
/**
 * Action: Compile WC Import
 *
 * Compiles the WooCommerce import file.
 *
 * @package     T51_ChargeBee_to_WP_Importer
 * @author      Automattic Special Projects
 * @license     GPL-3.0-or-later
 * @since       0.1.0
 * @version     0.1.0
 */

declare( strict_types=1 );

namespace T51\ChargeBee_To_WP\Importer\Action;

use Exception;
use DateInterval;
use DateTimeImmutable;
use RuntimeException;
use InvalidArgumentException;
use T51\ChargeBee_To_WP\Importer\CSV\Writer;

defined( 'ABSPATH' ) || exit;

/**
 * Compile WC Import Class
 */
class Compile_WC_Import {

	/**
	 * Access the CSV Writer class.
	 *
	 * @var Writer
	 */
	private Writer $csv_writer;

	/**
	 * Create a new instance of the Compile_WC_Import class.
	 *
	 * @param string $file_name The name of the CSV file.
	 */
	public function __construct( string $file_name = 'pop_wc_import.csv' ) {
		$this->csv_writer = new Writer( T51_ChargeBee_to_WP_Importer_get_csv_path( $file_name ) );
	}

	/**
	 * Get the data from CSV Writer.
	 *
	 * @return array
	 *
	 * @throws RuntimeException If no data is found in the CSV file.
	 */
	public function get_csv_rows(): array {
		$data = $this->csv_writer->get_csv_rows();

		if ( empty( $data ) ) {
			throw new RuntimeException( 'No data found in the CSV file.' );
		}

		return $data;
	}

	/**
	 * Returns the WC CSV headers.
	 *
	 * @return array
	 */
	private function wc_csv_headers(): array {
		return array(
			'customer_id',
			'customer_email',
			'customer_username',
			'customer_password',
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
			'billing_email',
			'billing_phone',
			'billing_company',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_state',
			'shipping_postcode',
			'shipping_country',
			'subscription_status',
			'start_date',
			'trial_end_date',
			'next_payment_date',
			'end_date',
			'billing_period',
			'billing_interval',
			'order_items',
			'coupon_items',
			'fee_items',
			'tax_items',
			'cart_discount',
			'cart_discount_tax',
			'order_shipping',
			'order_shipping_tax',
			'order_total',
			'order_tax',
			'order_currency',
			'shipping_method',
			'download_permissions',
			'order_notes',
			'payment_method',
			'payment_method_title',
			'payment_method_post_meta',
			'payment_method_user_meta',
			'customer_note',
			'custom_user_meta',
			'custom_post_meta',
			'custom_user_post_meta',
			'_requires_manual_renewal',
			'wp__stripe_customer_id',
		);
	}

	/**
	 * Insert the data into the CSV file.
	 *
	 * @param array<int, array<string, mixed>> $data The data to be inserted into the CSV file.
	 *
	 * @throws InvalidArgumentException If the data is not an array.
	 * @throws RuntimeException If the CSV file is not writable.
	 * @throws Exception If there is an error writing to the CSV file.
	 *
	 * @return integer The number of rows written to the CSV file.
	 */
	public function insert_data( array $data ): int {
		$data  = array_values( $data );
		$count = 0;
		$rows  = array();
		$log   = array();
		// Iterate over the rows and map to WC Import.
		foreach ( $data as $i => $row ) {
			$row_mapped = $this->map_row( $row );
			if ( null === $row_mapped ) {
				continue;
			}
			$rows[] = $row_mapped;

			$log[] = 'Email : ' . $row['email'] . ' | ' . $row['plan_ref'] . ' | ' . $row['sub_id'];

			++$count;
		}

		$this->csv_writer->set_csv_rows( $rows, $this->wc_csv_headers() );
		$this->csv_writer->write_csv();

		// Write the log toa file.
		$fp = fopen( T51_ChargeBee_to_WP_Importer_metadata( 'log.txt' ), 'w' ); // phpcs:ignore
		// Add each row to the log file.
		foreach ( $log as $line ) {
			fwrite( $fp, $line . "\n" ); // phpcs:ignore
		}
		fclose( $fp ); // phpcs:ignore

		return $count;
	}

	/**
	 * Get the interval type
	 *
	 * @param array $row The row to get the interval type from.
	 *
	 * @return string The interval type.
	 */
	private function get_interval_type( array $row ): string {
		if ( $this->is_obscure( $row['plan_ref'] ) ) {
			if ( 'print-technique-special' === $row['plan_ref']
			|| 'membership-(intro)-world-zone-1+2-individual-' === $row['plan_ref']
			|| 'membership-(intro)-uk-company-' === $row['plan_ref']
			|| 'membership-(intro)-world-zone-1+2-company' === $row['plan_ref']
			) {
				return 'year';
			}
			dd( array( 'UNHANDLED OBSCURE PLAN', $row, 'get_interval_type()' ) );
		}

		// Get the plan type.
		$plan_type = $row['plan_ref'];

		$plans = t51_chargbee_to_wp_importer_get_config( 'product_map' );
		$plan  = $plans['UK'][ $plan_type ];
		return 'Annual' === $plan['frequency'] ? 'year' : 'month';
	}

	/**
	 * Get the interval.
	 *
	 * @param array $row The row to get the frequency from.
	 *
	 * @return integer The interval.
	 */
	private function get_frequency( array $row ): int {
		if ( $this->is_obscure( $row['plan_ref'] ) ) {
			if ( 'print-technique-special' === $row['plan_ref']
			|| 'membership-(intro)-world-zone-1+2-individual-' === $row['plan_ref']
			|| 'membership-(intro)-uk-company-' === $row['plan_ref']
			|| 'membership-(intro)-world-zone-1+2-company' === $row['plan_ref']
			) {
				return 1;
			}
			dd( array( 'UNHANDLED OBSCURE PLAN', $row, 'get_frequency()' ) );
		}

		// Get the plan type.
		$plan_type = $row['plan_ref'];

		$plans = t51_chargbee_to_wp_importer_get_config( 'product_map' );

		// If frequency is quarterly, set to 3.
		if ( 'Quarterly' === $plans['UK'][ $plan_type ]['frequency'] ) {
			return 3;
		} else {
			return 1;
		}
	}

	/**
	 * Map the row before being added to the CSV.
	 *
	 * @param array $row The row to map.
	 *
	 * @return ?array
	 */
	private function map_row( array $row ): ?array {

		// If we have no email,
		if ( empty( $row['email'] ) ) {
			return array();
		}

		// Ignore old and cancelled subscriptions.
		if ( 'active' !== $row['status'] ) {
			return null;
		}

		// If payment type is gocardless, ignore.
		if ( 'gocardless' === $row['gateway'] ) {
			dump( 'Skipping gocardless subscription: ' . $row['email'] . ' | ' . $row['sub_id'] );
			return null;
		}

		// Handle uk customs who have the international plan. When UK users have the int plan, var is added, so we need to give a custom price to give the same result.
		if ( 'annual-membership-individual-international' === $row['plan_ref'] && 'GB' === $row['country'] ) {
			$row['price'] = 8500;
		}

		if ( 'discounted-individual-membership' === $row['plan_ref'] ) {
			dump( 'Discounts Inv Plan: ' . $row['email'] . ' | ' . $row['sub_id'] );
		}

		try {
				$compiled = array(
					null, // customer id - leave blank
					$row['email'],
					$row['email'], // Use the customer email as username.
					null, // password - leave blank
					$row['first_name'],
					$row['last_name'],
					$row['address_1'],
					$row['address_2'],
					$row['city'],
					$row['state'],
					$row['postcode'],
					$row['country'],
					$row['email'],
					$row['phone'],
					null, // billing company - leave blank
					$row['first_name'],
					$row['last_name'],
					$row['address_1'],
					$row['address_2'],
					$row['city'],
					$row['state'],
					$row['postcode'],
					$row['country'],
					$row['status'], // subscription status
					\DateTimeImmutable::createFromFormat( 'U', $row['started_at'] )->format( 'Y-m-d H:i:s' ), // start date
					null, // trial end date - leave blank
					\DateTimeImmutable::createFromFormat( 'U', $row['next_payment'] )->format( 'Y-m-d H:i:s' ), // next date
					null, // end date - leave blank
					$this->get_interval_type( $row ), // billing period
					$this->get_frequency( $row ), // billing interval
					$this->compile_order_items( $row ), // order items
					$this->compile_coupons( $row ), // coupon items - leave blank
					null, // fee items - leave blank
					$this->compile_tax_items( $row ), // tax items - leave blank
					$this->get_coupon_value( $row ), // cart discount - leave blank
					null, // cart discount tax - leave blank
					null, // order shipping
					null, // order shipping tax - leave blank
					'GB' === $row['country'] ? ( $this->get_price_nett( $row ) * 1.2 ) : $this->get_price_nett( $row ),  // order total
					'GB' === $row['country'] ? ( $this->get_price_nett( $row ) * 0.2 ) : 0, // order tax - leave blank
					$row['currency_code'], // order currency
					null, // shipping method
					null, // download permissions - leave blank
					null, // order notes - leave blank
					'stripe', // payment method
					'Credit Card (Stripe)', // payment method title
					$this->compile_payment_meta( $row ), // payment method post meta
					null, // payment method user meta - leave blank
					null, // customer note - leave blank
					null, // custom user meta - leave blank
					null, // custom post meta - leave blank
					null, // custom user post meta - leave blank,
					'false', // _requires_manual_renewal
					$row['stripe_customer'], // wp__stripe_customer_id
				);
		} catch ( \Throwable $th ) {
			dump( array( 'Error compiling row: ' . $row['email'] => $th->getMessage() ) );
			return null;
		}

		return $compiled;
	}

	/**
	 * Checks if a package is obscure.
	 *
	 * @param string $package The package to check.
	 *
	 * @return boolean
	 */
	private function is_obscure( string $package ): bool {
		$obscure = array(
			'print-technique-special',
			'membership-(intro)-world-zone-1+2-individual-',
			'membership-(intro)-uk-company-',
			'membership-(intro)-uk-individual',
			'membership-(intro)-world-zone-1+2-company',
		);

		return in_array( $package, $obscure, true );
	}

	/**
	 * Get the net row price
	 *
	 * This is cast to a float (2 decimal places)
	 *
	 * @param array $row The row to get the price from.
	 *
	 * @return float The price.
	 */
	private function get_price_nett( array $row ): float {
		// Get any coupons.
		$discount = $this->get_coupon_value( $row );
		// Get the price.
		$price = $row['price'] / 100;

		return round( floatval( $price - $discount ), 2 );
	}

	/**
	 * Compiles a rows taxes
	 *
	 * @param array $row The row to compile.
	 *
	 * @return string
	 */
	private function compile_tax_items( array $row ): string {
		// if the country is GB, we need to add 20% tax.
		if ( 'GB' !== $row['country'] ) {
			return '';
		}

		// Get the tax amount.
		$tax = $this->get_price_nett( $row ) * 0.2;
		$tax = round( $tax, 2 );

		return sprintf(
			'id:1|code:GB|total:%s',
			number_format( $tax, 2, '.', '' )
		);
	}

	/**
	 * Compiles the orders coupons
	 *
	 * @param array $row The row.
	 *
	 * @return string
	 */
	private function compile_coupons( array $row ): string {
		if ( '' === $row['discount_name'] || empty( $row['discount_name'] ) ) {
			return '';
		}

		return sprintf(
			'code:%s|amount:%s',
			$row['discount_name'],
			number_format( $this->get_coupon_value( $row ), 2, '.', '' )
		);
	}

	/**
	 * Gets the value of any cupons on order.
	 *
	 * @param array $row The row to get the coupons from.
	 *
	 * @return float The value of the coupons.
	 */
	private function get_coupon_value( array $row ): float {
		if ( empty( $row['discount_name'] ) ) {
			return 0.00;
		}

		// Get the discount amount and cast to decimal (50 = 0.50)
		$discount = $row['discount_amount'] / 100;

		// Get the discounted amount.
		return ( $row['price'] * $discount ) / 100;
	}

	/**
	 * Compile order items
	 *
	 * @param array $row The row to compile.
	 *
	 * @return string
	 */
	private function compile_order_items( $row ): string {

		$packages = t51_chargbee_to_wp_importer_get_config( 'product_map' );

		// Get the packages.
		if ( $this->is_obscure( $row['plan_ref'] ) ) {
			// These are any obscure packages that are custom.
			$handled = array( 'print-technique-special', 'membership-(intro)-world-zone-1+2-individual-', 'membership-(intro)-uk-company-', 'membership-(intro)-world-zone-1+2-company' );
			if ( ! in_array( $row['plan_ref'], $handled, true ) ) {
				dd(
					array(
						'Found an obscure package' => $row['plan_ref'],
						'Obscure'                  => $package,
						$row,
					)
				);
			}
			$package = $packages['Obscure'][ $row['plan_ref'] ];

			$product = wc_get_product( $package['var'] ?? $package['prod'] );
		} elseif ( 'GB' === $row['country'] ) {
			$package = $packages['UK'][ $row['plan_ref'] ];
			$product = wc_get_product( $package['var'] ?? $package['prod'] );
		} else {
			$package = $packages['INT'][ $row['plan_ref'] ];

			// Use UK products for legacy users on cheaper plan.
			// Some non uk customers have uk products, so no VAT
			$plans = array( 'pop-membership-individual-annual', 'annual-membership-individual' );
			if ( '8500' === $row['price'] && in_array( $row['plan_ref'], $plans, true ) ) {
				$package = $packages['UK'][ $row['plan_ref'] ];
			}

			$product = wc_get_product( $package['var'] ?? $package['prod'] );
		}

		// If we have a UK customerf with the annual-membership-individual-international plan,
		if ( 'annual-membership-individual-international' === $row['plan_ref'] && 'GB' === $row['country'] ) {
			$package = $packages['UK']['annual-membership-individual'];
			$product = wc_get_product( $package['var'] ?? $package['prod'] );
		}

		if ( ! $product ) {
			dd(
				array(
					'Product not found while attempting to compile for package: ' => $package,
					$row,
				)
			);
			return '';
		}

		return sprintf(
			'product_id:%d|name:%s|quantity:1|total:%s|meta:company-type=%s+frequency=%s|tax:%s',
			$package['var'] ?? $package['prod'],
			$product->get_name(),
			$row['price'] / 100,
			$package['type'],
			$package['frequency'],
			'GB' === $row['country'] ? ( ( $row['price'] / 100 ) * 0.2 ) : 0.00
		);
	}

	/**
	 * Compile the stripe payment meta
	 *
	 * @param array $payment_details The payment details.
	 *
	 * @return string
	 */
	private function compile_payment_meta( array $payment_details ): string {
		return sprintf(
			'_stripe_customer_id:%s|_stripe_source_id:%s',
			$payment_details['stripe_customer'],
			$payment_details['stripe_payment'],
		);
	}

	/**
	 * Get the path to the CSV file.
	 *
	 * @return string
	 */
	public function get_csv_path(): string {
		return $this->csv_writer->get_csv_path();
	}
}
