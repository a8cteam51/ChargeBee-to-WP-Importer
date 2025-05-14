<?php
/**
 * Action: Compile ChargeBee Data
 *
 * Compiles the data frm ChargeBee SDK to a unified csv file.
 *
 * @package     T51_ChargeBee_to_WP_Importer
 * @author      Automattic Special Projects
 * @license     GPL-3.0-or-later
 * @since       0.1.0
 * @version     0.1.0
 */

declare( strict_types=1 );

namespace T51\ChargeBee_To_WP\Importer\Action;

use WP_CLI;
use RuntimeException;
use ChargeBee\ChargeBee;
use InvalidArgumentException;
use ChargeBee\ChargeBee\Result;
use ChargeBee\ChargeBee\Models\Coupon;
use ChargeBee\ChargeBee\Models\Subscription;
use T51\ChargeBee_To_WP\Importer\CSV\Writer;

/**
 * Compile ChargeBee Data Class
 */
class Compile_ChargeBee_Data {

	/**
	 * CSV Writer instance.
	 *
	 * @var Writer
	 */
	private Writer $csv_writer;

	/**
	 * Holds the next offset.
	 *
	 * @var string
	 */
	private string $next_offset = 'start';

	/**
	 * The results.
	 *
	 * @var array<int ,ChargeBee\ChargeBee\Result>
	 */
	private array $results = array();

	/**
	 * Create a new instance of the Subscription_Compiler class.
	 *
	 * @param string $file_name The name of the CSV file.
	 *
	 * @throws InvalidArgumentException If the file name is invalid.
	 */
	public function __construct( string $file_name = 'pop_wc_import.csv' ) {
		if ( empty( $file_name ) || ! is_string( $file_name ) ) {
			throw new InvalidArgumentException( 'Invalid file name provided.' );
		}

		$this->csv_writer = new Writer( t51_chargbee_to_wp_importer_get_csv_path( $file_name ) );
	}

	/**
	 * Set the next offset.
	 *
	 * @param string $next_offset The next offset to set.
	 *
	 * @return void
	 */
	public function set_next_offset( string $next_offset ): void {
		$this->next_offset = $next_offset;
	}

	/**
	 * Checks if we have a next offset.
	 *
	 * @return boolean True if we have a next offset, false otherwise.
	 */
	public function has_next_offset(): bool {
		return ! empty( $this->next_offset );
	}

	/**
	 * Count the number of results.
	 *
	 * @return integer The number of results.
	 */
	public function count_results(): int {
		return count( $this->results );
	}

	/**
	 * Fetch the next set of results from Chargebee.
	 *
	 * @param integer $limit The number of results to fetch.
	 *
	 * @return integer The number of results fetched.
	 */
	public function fetch_next( int $limit = 100 ): int {
		$args = array(
			'limit' => $limit,
		);
		if ( 'start' !== $this->next_offset && ! empty( $this->next_offset ) ) {
			$args['offset'] = $this->next_offset;
		}

		$results = $this->get_results( $args );
		$count   = count( $results );
		foreach ( $results as $result ) {
			$this->results[] = $this->map_result( $result );
		}

		return $count;
	}

	/**
	 * Get all subscriptions.
	 *
	 * @param integer $limit The number of results to fetch.
	 * @param integer $delay The delay between requests in seconds.
	 *
	 * @return integer The number of results fetched.
	 */
	public function get_all_subscriptions( int $limit = 100, int $delay = 1 ): int {

		// Compile the args,
		$args = array(
			'limit' => $limit,
		);
		if ( 'start' !== $this->next_offset && ! empty( $this->next_offset ) ) {
			$args['offset'] = $this->next_offset;
		}

		$count = 0;

		while ( ( $results = $this->get_results( $args ) ) && count( $results ) > 0 ) { //phpcs:ignore
			// Add the count of results to $count
			$count += count( $results );

			foreach ( $results as $result ) {
				$mapped = $this->map_result( $result );

				// Manually ignore these.
				$ignored = array(
					// '2smoc9AEQtbsWXOK8b', // Add whatever subscriptions you want to ignore here.
					
				);

				if ( in_array( $mapped['sub_id'], $ignored, true ) ) {
					WP_CLI::log( 'Skipping subscription (old Intro): ' . $mapped['sub_id'] );
					continue;
				}

				// If the subscription is not active, skip it.
				if ( 'active' !== $mapped['status'] ) {
					WP_CLI::log( 'Skipping non-active subscription: ' . $mapped['sub_id'] . ' | ' . $mapped['status'] );
					continue;
				}
				$this->results[] = $mapped;
			}

			// Update the next offset.
			$next_offset = $results->nextOffset();

			// If this is null, end.
			if ( empty( $next_offset ) ) {
				WP_CLI::log( 'No more results to fetch.' );
				$this->write_csv();

				WP_CLI::success( 'The CSV file has been created! - ' . $this->csv_writer->get_csv_path() );
				WP_CLI::success( 'The number of subscriptions fetched: ' . $count );

				break;
			}

			$this->next_offset = $next_offset;
			$args['offset']    = $this->next_offset;

			// wait for the delay.
			if ( $delay > 0 ) {
				sleep( $delay );
			}

			\WP_CLI::log( "Current Count: $count" );
			$this->write_csv();
		}

		return $count;
	}

	/**
	 * Write the CSV file.
	 *
	 * @return string The path to the CSV file.
	 *
	 * @throws RuntimeException If there are no results to write to CSV.
	 */
	public function write_csv(): string {
		if ( empty( $this->results ) ) {
			throw new RuntimeException( 'No results to write to CSV.' );
		}

		// Set the CSV rows and header.
		$this->csv_writer->set_csv_rows( $this->results );
		$this->csv_writer->write_csv();
		return $this->csv_writer->get_csv_path();
	}

	/**
	 * Get the results.
	 *
	 * @param array<string,mixed> $args The Chargebee arguments.
	 *
	 * @return ChargeBee\ChargeBee\ListResult
	 */
	private function get_results( array $args = array() ): \ChargeBee\ChargeBee\ListResult {
		return \ChargeBee\ChargeBee\Models\Subscription::all( $args );
	}

	/**
	 * Map a row, gets all the needed data.
	 *
	 * @param Result $result TThe API List result.
	 *
	 * @return array The mapped row.
	 */
	private function map_result( Result $result ): array {
		$mapped = array();

		// Get the payment details
		$subscription = $result->subscription();
		$customer     = $result->customer();
		$card         = $result->card();

		$mapped = array_merge(
			$mapped,
			$this->compile_payment_method( $customer, $card, $subscription ),
			$this->compile_billing_details( $customer, $card, $subscription ),
			$this->compile_subscription_details( $customer, $card, $subscription )
		);

		return $mapped;
	}

	/**
	 * Compiles the subscription details.
	 *
	 * @param Customer     $customer     The customer object.
	 * @param Card         $card         The card object.
	 * @param Subscription $subscription The subscription object.
	 *
	 * @return array The subscription details.
	 */
	private function compile_subscription_details( $customer, $card, $subscription ): array {
		$status        = $subscription->status;
		$created_at    = $subscription->createdAt;
		$stated_at     = $subscription->startedAt;
		$next_payment  = $subscription->nextBillingAt;
		$last_update   = $subscription->updatedAt;
		$price         = $subscription->planAmount;
		$interval_unit = $subscription->billingPeriodUnit;
		$interval      = $subscription->billingPeriod;
		$plan_ref      = $subscription->planId;
		$currency      = $subscription->currencyCode;

		$discount_data = isset( $subscription->coupon ) && '' !== $subscription->coupon
			? $this->get_coupon_data( $subscription->coupon )
			: null;

		$subscription_details = array(
			'sub_id'          => $subscription->id,
			'status'          => $status,
			'created_at'      => $created_at,
			'started_at'      => $stated_at,
			'next_payment'    => $next_payment,
			'last_update'     => $last_update,
			'price'           => $price,
			'interval_unit'   => $interval_unit,
			'interval'        => $interval,
			'plan_ref'        => $plan_ref,
			'currency_code'   => $currency,
			'discount_name'   => $discount_data ? $discount_data['name'] : '',
			'discount_amount' => $discount_data ? $discount_data['amount'] : 0,
			'discount_type'   => $discount_data ? $discount_data['type'] : '',
		);

		return $subscription_details;
	}

	/**
	 * Gets a coupon data.
	 *
	 * @param string $coupon The coupon code.
	 *
	 * @return array{type: string, amount: float}
	 */
	private function get_coupon_data( string $coupon ): array {
		static $cache = array();

		// if not in cache.
		if ( ! isset( $cache[ $coupon ] ) ) {
			// Get the coupon data.
			$coupon_data      = Coupon::retrieve( $coupon );
			$cache[ $coupon ] = $coupon_data;
		}
		$cached = $cache[ $coupon ];

		return array(
			'type'   => $cached->coupon()->discountType,
			'amount' => 'percentage' === $cached->coupon()->discountType
				? $cached->coupon()->discountPercentage
				: $cached->coupon()->discountAmount,
			'name'   => $cached->coupon()->name,
			'id'     => $coupon,
		);
	}

	/**
	 * Compile the payment method data.
	 *
	 * @param Customer     $customer     The customer object.
	 * @param Card         $card         The card object.
	 * @param Subscription $subscription The subscription object.
	 *
	 * @return array The payment method data.
	 */
	private function compile_payment_method( $customer, $card, $subscription ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed

		$customer_payment_data = $customer->paymentMethod;
		$gateway               = $customer_payment_data->gateway;

		$details = array(
			'gateway' => $gateway,
		);

		if ( 'stripe' === $gateway ) {
			// Extract the customer ref.
			$reference_id = $customer_payment_data->referenceId;
			// Split on /
			$parts = explode( '/', $reference_id );

			// Get the first part
			$customer_id = $parts[0];
			$payment_id  = $parts[1];

			// This doesnt start wtih cus_, show error
			if ( 'cus_' !== substr( $customer_id, 0, 4 ) ) {
				WP_CLI::error( 'Invalid customer id: ' . $customer_id );
			}

			// This doesnt start wtih pm_ OR card_, show error
			if ( 'pm_' !== substr( $payment_id, 0, 3 ) && 'card_' !== substr( $payment_id, 0, 5 ) ) {
				WP_CLI::error( 'Invalid payment id: ' . $payment_id );
			}

			$details['stripe_payment']  = $payment_id;
			$details['stripe_customer'] = $customer_id;
		} elseif ( 'gocardless' === $gateway ) {
			$details['reference_id']    = $customer_payment_data->referenceId;
			$details['gocardless_type'] = $customer_payment_data->type;
		} else {
			$customer_id               = $customer_payment_data->referenceId;
			$details['payment_method'] = $customer_id;
		}

		return $details;
	}

	/**
	 * Compile the customer billing details
	 *
	 * @param Customer     $customer     The customer object.
	 * @param Card         $card         The card object.
	 * @param Subscription $subscription The subscription object.
	 *
	 * @return array The billing details.
	 */
	private function compile_billing_details( $customer, $card, $subscription ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$billing_address = $customer->billingAddress;

		// Address line should have address line, concatinated with a , if set.
		$line_2 = array();
		if ( '' !== $billing_address->line2 ) {
			$line_2[] = $billing_address->line2;
		}
		if ( '' !== $billing_address->line3 ) {
			$line_2[] = $billing_address->line3;
		}
		$line_2 = array_filter( $line_2 );
		$line2  = implode( '، ', $line_2 );
		// Strip any trailing ،.
		if ( '،' === substr( $line2, -1 ) ) {
			$line2 = substr( $line2, 0, -1 );
		}
		// Strip any leading ،.
		if ( '،' === substr( $line2, 0, 1 ) ) {
			$line2 = substr( $line2, 1 );
		}

		$billing_details = array(
			'first_name' => $billing_address->firstName,
			'last_name'  => $billing_address->lastName,
			'email'      => $customer->email,
			'address_1'  => $billing_address->line1,
			'address_2'  => $line2,
			'city'       => $billing_address->city,
			'postcode'   => $billing_address->zip,
			'country'    => $billing_address->country,
			'state'      => $billing_address->state,
			'phone'      => $customer->phone,
		);

		return $billing_details;
	}
}
