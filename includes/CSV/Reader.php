<?php
/**
 * CSV Reader Class
 *
 * This class is responsible for reading data to a CSV file.
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

/**
 * CSV Reader Class
 */
class Reader {

	/**
	 * The rows.
	 *
	 * @var array<string, mixed>
	 */
	private array $rows = array();

	/**
	 * Data headers.
	 *
	 * @var array<string>
	 */
	private array $headers = array();

	/**
	 * Holds the path to the CSV file.
	 *
	 * @var string
	 */
	private string $csv_path;

	/**
	 * The delimiter to use in the CSV file.
	 *
	 * @var string
	 */
	private string $delimiter = ',';

	/**
	 * Creates an instance of the CSV Reader class.
	 *
	 * @param string      $csv_path  The path to the CSV file.
	 * @param string|null $delimiter The delimiter to use in the CSV file.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the file name is invalid.
	 * @throws \InvalidArgumentException If the file is not readable.
	 */
	public function __construct( string $csv_path, ?string $delimiter = ',' ) {
		// Check the file exists.
		if ( ! file_exists( $csv_path ) ) {
			throw new \InvalidArgumentException( 'File does not exist.' );
		}

		// Check the file is readable.
		if ( ! is_readable( $csv_path ) ) {
			throw new \InvalidArgumentException( 'File is not readable.' );
		}
		$this->csv_path = $csv_path;

		// If we have a delimiter, set it.
		if ( ! empty( $delimiter ) && is_string( $delimiter ) ) {
			$this->delimiter = $delimiter;
		}
	}

	/**
	 * Set the headers.
	 *
	 * If set, these will be used for every row based on position
	 *
	 * @param array<string> $headers The headers to set.
	 *
	 * @return void
	 *
	 * @throws \InvalidArgumentException If the headers are not an array.
	 * @throws \InvalidArgumentException If the headers are empty.
	 */
	public function set_headers( array $headers ): void {
		if ( empty( $headers ) || ! is_array( $headers ) ) {
			throw new \InvalidArgumentException( 'Headers must be an array.' );
		}

		$this->headers = $headers;
	}

	/**
	 * Infer the headers from the CSV file.
	 *
	 * This will use the first row of the CSV file as the headers.
	 *
	 * @return void
	 */
	public function infer_headers(): void {
		if ( empty( $this->headers ) ) {
			$this->headers = array_map(
				'trim',
				fgetcsv( fopen( $this->csv_path, 'r' ), 0, $this->delimiter ) //phpcs:ignore
			);
		}
	}

	/**
	 * Get the rows from the CSV file.
	 *
	 * @param boolean $ignore_first_row If true, the first row will be ignored.
	 *
	 * @return array<string, mixed>
	 */
	public function get_rows( bool $ignore_first_row = true ): array {
		// If we dont have any row, return the rows.
		if ( ! empty( $this->rows ) ) {
			return $this->rows;
		}

		$file = fopen( $this->csv_path, 'r' ); //phpcs:ignore

		// If we need to ignore the first row
		if ( $ignore_first_row ) {
			// Skip the first row
			fgetcsv( $file );
		}

		// Loop through the rest of the rows in the CSV file
		while ( ( $row = fgetcsv( $file ) ) !== false ) { //phpcs:ignore
			// Append the mapped row directly to the rows array
			$this->rows[] = $this->map_row( $row );
		}

		// Close the file
		fclose( $file ); //phpcs:ignore

		return $this->rows;
	}

	/**
	 * Map a row using the headers.
	 *
	 * @param array<string, mixed> $row The row to map.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws \InvalidArgumentException If the headers are not set.
	 */
	public function map_row( array $row ): array {
		if ( empty( $this->headers ) ) {
			throw new \InvalidArgumentException( 'Headers must be set before mapping a row.' );
		}

		if ( count( $row ) !== count( $this->headers ) ) {
			// Padd the row with empty values if the row is shorter than the headers.
			$row = array_pad( $row, count( $this->headers ), '' );
		}

		return array_combine( $this->headers, array_map( 'trim', $row ) );
	}

	/**
	 * Get the data.
	 *
	 * @return array<string, mixed>
	 */
	public function get_data(): array {
		$data = array();

		foreach ( $this->get_rows() as $row ) {
			$data[] = array_combine( $this->headers, $row );
		}

		return $data;
	}

	/**
	 * Get row based on index.
	 *
	 * @param integer $index The index of the row to get.
	 *
	 * @return array<string, mixed>
	 *
	 * @throws \OutOfRangeException If the index is out of range.
	 */
	public function get_row( int $index ): array {
		if ( $index < 0 || $index >= count( $this->rows ) ) {
			throw new \OutOfRangeException( 'Index is out of range.' );
		}

		return $this->rows[ $index ];
	}
}
