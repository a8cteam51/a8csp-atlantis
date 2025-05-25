<?php declare( strict_types=1 );

defined( 'ABSPATH' ) || exit;

/**
 * Returns the option key for the module settings.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string $module_name The name of the module.
 *
 * @return  string
 */
function a8csp_atlantis_generate_module_settings_key( string $module_name ): string {
	$settings_key = sanitize_key( sanitize_title( $module_name ) );
	return "a8csp_module_$settings_key";
}

/**
 * Retrieves the settings for a specific module.
 *
 * @since   1.0.0
 * @version 1.0.0
 *
 * @param   string $module_name The name of the module.
 *
 * @return  array
 */
function a8csp_atlantis_get_module_settings( string $module_name ): array {
	$settings_key = a8csp_atlantis_generate_module_settings_key( $module_name );

	$settings = get_option( $settings_key, array() );
	return ( '' === $settings ) ? array() : $settings;
}
