<?php
/**
 * The utility functions for the t51-chargebee-to-wp importer plugin.
 *
 * @since       0.1.0
 * @version     0.1.0
 *
 * @package     T51_ChargBee_to_WP_Importer
 * @author      Automattic Special Projects
 * @license     GPL-3.0-or-later
 */

declare( strict_types=1 );

/**
 * Get a configuration value.
 *
 * @param   string $key The configuration key.
 * @return  mixed The configuration value.
 *
 * @throws  \InvalidArgumentException If the key is not found.
 * @throws  \RuntimeException If the configuration file is not found.
 */
function t51_chargbee_to_wp_importer_get_config( string $key ) {
	if ( ! file_exists( __DIR__ . '/config.php' ) ) {
		throw new \RuntimeException( 'Configuration file not found.' );
	}
	if ( ! is_readable( __DIR__ . '/config.php' ) ) {
		throw new \RuntimeException( 'Configuration file is not readable.' );
	}

	static $config = null;
	if ( null === $config ) {
		$config = require __DIR__ . '/config.php';
	}
	if ( ! is_array( $config ) ) {
		throw new \RuntimeException( 'Configuration file does not return an array.' );
	}

	// Check if the key exists in the configuration array.
	if ( ! array_key_exists( $key, $config ) ) {
		throw new \InvalidArgumentException( sprintf( 'Configuration key "%s" not found.', esc_attr( $key ) ) );
	}

	return $config[ esc_attr( $key ) ];
}

/**
 * Returns the mu-plugin's metadata.
 *
 * @template PluginMetaKey of key-of<PluginMetaData>
 *
 * @param   PluginMetaKey|null $property Optional. The property to return. Default all.
 *
 * @return  ($property is null ? PluginMetaData : ($property is PluginMetaKey ? PluginMetaData[PluginMetaKey] : null))
 */
function t51_chargbee_to_wp_importer_metadata( ?string $property = null ) {
	static $plugin_data = null;

	if ( null === $plugin_data ) {
		if ( ! function_exists( 'get_plugin_data' ) ) {
			/* @phpstan-ignore requireOnce.fileNotFound */
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$plugin_data = get_plugin_data( __DIR__ . '/chargebee-to-wp-importer.php' );
	}

	$metadata = $plugin_data;
	if ( null === $property ) {
		return $metadata;
	}

	return $metadata[ $property ] ?? null;
}

/**
 * Returns the plugin's slug.
 *
 * @return  string
 */
function t51_chargbee_to_wp_importer_slug(): string {
	return 'chargebee-to-wp-importer';
}

/**
 * Returns an array with meta information for a given asset path. First, it checks for an .asset.php file in the same directory
 * as the given asset file whose contents are returns if it exists. If not, it returns an array with the file's last modified
 * time as the version and the main stylesheet + any extra dependencies passed in as the dependencies.
 *
 * @param   string        $asset_path         The path to the asset file.
 * @param   string[]|null $extra_dependencies Any extra dependencies to include in the returned meta.
 *
 * @return  array{ version: string, dependencies: array<string> }|null
 */
function t51_chargbee_to_wp_importer_asset_meta( string $asset_path, ?array $extra_dependencies = null ): ?array {
	$asset_path = str_starts_with( $asset_path, constant( 'T51_CHARGEBEE_TO_WPPATH' ) ) ? $asset_path : constant( 'T51_CHARGEBEE_TO_WPPATH' ) . $asset_path;
	if ( ! file_exists( $asset_path ) ) {
		return null;
	}

	$asset_meta = array(
		'dependencies' => array(),
		'version'      => (string) filemtime( $asset_path ),
	);
	if ( '' === $asset_meta['version'] ) {
		$asset_meta['version'] = t51_chargbee_to_wp_importer_metadata( 'Version' );
	}

	$asset_pathinfo              = pathinfo( $asset_path );
	$asset_pathinfo['dirname'] ??= '';

	$asset_meta_file = "{$asset_pathinfo['dirname']}/{$asset_pathinfo['filename']}.asset.php";
	if ( file_exists( $asset_meta_file ) ) {
		$asset_meta_generated = require $asset_meta_file;

		if ( isset( $asset_meta_generated['version'] ) ) {
			$asset_meta['version'] = $asset_meta_generated['version'];
		}
		if ( isset( $asset_meta_generated['dependencies'] ) ) {
			$asset_meta['dependencies'] = $asset_meta_generated['dependencies'];
		}
	}

	if ( is_array( $extra_dependencies ) ) {
		$asset_meta['dependencies'] = array_merge( $asset_meta['dependencies'], $extra_dependencies );
		$asset_meta['dependencies'] = array_unique( $asset_meta['dependencies'] );
	}

	return $asset_meta;
}

/**
 * Gets the full path of a plugin csv file.
 *
 * @param   string $filename The filename of the CSV file.
 *
 * @return  string The full path of the CSV file.
 */
function t51_chargbee_to_wp_importer_get_csv_path( string $filename ): string {
	// get the base uploads path
	$uploads   = wp_upload_dir();
	$base_path = $uploads['basedir'] . '/chargebee-imports/';
	// check if the directory exists, if not create it
	if ( ! file_exists( $base_path ) ) {
		mkdir( $base_path, 0755, true ); // phpcs:ignore
	}
	// return the full path
	return $base_path . $filename;
}

/**
 * dd() pollyfill.
 */
if ( ! function_exists( 'dd' ) ) {
	/**
	 * Dump the given variables and die.
	 *
	 * @param  mixed ...$args The variables to dump.
	 *
	 * @return void
	 */
	function dd( ...$args ) {
		foreach ( $args as $arg ) {
			var_dump( $arg );
		}
		die();
	}
}