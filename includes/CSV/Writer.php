<?php
/**
 * CSV Writer Class
 *
 * This class is responsible for writing data to a CSV file.
 *
 * @package     T51_ChargeBee_to_WP_Importer
 * @author      Automattic Special Projects
 * @license     GPL-3.0-or-later
 *
 * @since       0.1.0
 * @version     0.1.0
 */

declare( strict_types=1 );

namespace T51\ChargeBee_To_WP\Importer\CSV;

use InvalidArgumentException;
use RuntimeException;
use Exception;

defined( 'ABSPATH' ) || exit;

/**
 * CSV Writer Class
 */
class Writer {

	/**
	 * The path to the CSV file.
	 *
	 * @var string
	 */
	private string $csv_path;

	/**
	 * The data to be written to the CSV file.
	 *
	 * @var array
	 */
	private array $csv_rows = array();

	/**
	 * The row header for the CSV file.
	 *
	 * @var array
	 */
	private array $csv_header = array();

	/**
	 * The delimiter to use in the CSV file.
	 *
	 * @var string
	 */
	private string $delimiter;

	/**
	 * Messages to be displayed.
	 *
	 * @var array<int string>
	 */
	private array $messages = array();


	/**
	 * Create a new CSV Writer instance.
	 *
	 * @param string $csv_path  The path to the CSV file.
	 * @param string $delimiter The delimiter to use in the CSV file.
	 */
	public function __construct( string $csv_path, string $delimiter = ',' ) {
		$this->csv_path  = $csv_path;
		$this->delimiter = $delimiter;
	}

	/**
	 * Get the CSV path.
	 *
	 * @return string
	 */
	public function get_csv_path(): string {
		return $this->csv_path;
	}

	/**
	 * Set the data to be written to the CSV file.
	 *
	 * @param array      $csv_rows   The data to be written to the CSV file.
	 * @param array|null $csv_header The header for the CSV file.
	 *
	 * @return void
	 *
	 * @throws InvalidArgumentException If the CSV rows are not an array of arrays.
	 */
	public function set_csv_rows( array $csv_rows, ?array $csv_header = null ): void {
		if ( empty( $csv_rows ) ) {
			return;
		}

		// Clear any indexes from the array.
		$csv_rows = \array_values( $csv_rows );

		// If we dont have any array of arrays, throw an exception.
		if ( ! \is_array( $csv_rows ) || ! \is_array( $csv_rows[0] ) ) {
			throw new InvalidArgumentException( 'CSV rows must be an array of arrays.' );
		}

		// If we dont have a header, get it from the first row.
		if ( empty( $csv_header ) ) {
			$csv_header = \array_keys( $csv_rows[0] );
		}

		$this->csv_rows   = $csv_rows;
		$this->csv_header = $csv_header;
	}

	/**
	 * Write the CSV file.
	 *
	 * @return integer
	 * @throws Exception If the CSV path is not writable or if there are no CSV rows to write.
	 * @throws RuntimeException If the CSV file cannot be opened or if there is an error writing to the file.
	 */
	public function write_csv(): int {
		// Check we have CSV rows.
		if ( empty( $this->csv_rows ) ) {
			throw new Exception( 'No CSV rows to write.' );
		}

		// If file doesnt exist, create.
		if ( ! \file_exists( $this->csv_path ) ) {
			\touch( $this->csv_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_touch
		}

		// Check the CSV path is writable.
		if ( ! \is_writable( $this->csv_path ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
			throw new Exception( 'CSV path is not writable.' );
		}

		$csv = \fopen( $this->csv_path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $csv ) {
			throw new RuntimeException( 'Failed to open CSV file for writing.' );
		}

		if ( ! \fputcsv( $csv, $this->csv_header, $this->delimiter ) ) {
			throw new RuntimeException( 'Failed to write header to CSV file.' );
		}

		foreach ( $this->csv_rows as $row ) {
			if ( ! \fputcsv( $csv, $row, $this->delimiter ) ) {
				throw new RuntimeException( 'Failed to write row to CSV file.' );
			}
		}

		fclose( $csv ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		// Add a message to the messages array.
		$this->messages[] = sprintf(
			'Wrote %d rows to %s',
			\count( $this->csv_rows ),
			$this->csv_path
		);
		$this->messages[] = sprintf(
			'CSV file created at %s',
			$this->csv_path
		);

		return 0;
	}


	/**
	 * Gets the CSV header from the subscriptions.
	 *
	 * @return array
	 */
	private function get_csv_header(): array {
		return \array_keys( $this->subscriptions[0] );
	}

	/**
	 * Get the messages.
	 *
	 * @return array<int, string>
	 */
	public function get_messages(): array {
		return $this->messages;
	}

	/**
	 * Get the rows
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_csv_rows(): array {
		return $this->csv_rows;
	}
}
