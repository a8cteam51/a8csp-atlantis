<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Autoupdates;

defined( 'ABSPATH' ) || exit;

/**
 * Shared rules and storage for per-plugin autoupdate filter controls.
 *
 * @since   1.0.3
 * @version 1.0.3
 */
class PluginFilterRules {

	/**
	 * Option key that stores plugins for which this module's filters are disabled.
	 *
	 * @var string
	 */
	public const DISABLED_PLUGIN_FILTERS_OPTION = 'plugin_autoupdate_filter_disabled_plugins';

	/**
	 * Get a normalized list of plugin files for which filter logic is disabled.
	 *
	 * @return array<int, string>
	 */
	public static function get_filter_disabled_plugins(): array {
		$disabled_plugins = get_site_option( self::DISABLED_PLUGIN_FILTERS_OPTION, array() );

		if ( ! is_array( $disabled_plugins ) ) {
			return array();
		}

		return array_values(
			array_unique(
				array_map(
					'plugin_basename',
					array_filter( $disabled_plugins, 'is_string' )
				)
			)
		);
	}

	/**
	 * Determine whether filter logic is disabled for a plugin file.
	 *
	 * @param string $plugin_file Plugin basename or file path.
	 *
	 * @return bool
	 */
	public static function is_filter_disabled_for_plugin_file( string $plugin_file ): bool {
		return in_array( plugin_basename( $plugin_file ), self::get_filter_disabled_plugins(), true );
	}

	/**
	 * Evaluate external plugin-level autoupdate rules.
	 *
	 * @param \stdClass $plugin_obj Plugin-like object containing a slug property.
	 *
	 * @return bool
	 */
	public static function is_plugin_allowed_to_autoupdate( \stdClass $plugin_obj ): bool {
		if ( ! function_exists( 'disable_autoupdate_specific_plugins' ) ) {
			return true;
		}

		// @phpstan-ignore-next-line Optional external callback may be unavailable in some installs.
		return (bool) call_user_func( '\disable_autoupdate_specific_plugins', true, $plugin_obj );
	}

	/**
	 * Determine whether plugin autoupdates are blocked by centralized settings.
	 *
	 * @param \stdClass $plugin_obj Plugin-like object containing plugin and/or slug.
	 * @param \stdClass $settings   Centralized settings.
	 *
	 * @return bool
	 */
	public static function is_plugin_disabled_by_centralized_settings( \stdClass $plugin_obj, \stdClass $settings ): bool {
		$disabled_plugins = self::get_centrally_disabled_plugins( $settings );
		if ( empty( $disabled_plugins ) ) {
			return false;
		}

		$plugin_file = '';
		if ( isset( $plugin_obj->plugin ) && is_string( $plugin_obj->plugin ) ) {
			$plugin_file = plugin_basename( $plugin_obj->plugin );
		}

		$plugin_slug = '';
		if ( isset( $plugin_obj->slug ) && is_string( $plugin_obj->slug ) ) {
			$plugin_slug = sanitize_key( $plugin_obj->slug );
		}

		if ( '' === $plugin_slug && '' !== $plugin_file ) {
			$plugin_slug = sanitize_key( dirname( $plugin_file ) );
		}

		foreach ( $disabled_plugins as $disabled_plugin_file ) {
			$disabled_plugin_slug = sanitize_key( dirname( $disabled_plugin_file ) );
			if ( false === strpos( $disabled_plugin_file, '/' ) ) {
				$disabled_plugin_slug = sanitize_key( $disabled_plugin_file );
			}

			if ( '' !== $plugin_file && $plugin_file === $disabled_plugin_file ) {
				return true;
			}

			if ( '' !== $plugin_slug && $plugin_slug === $disabled_plugin_slug ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Determine whether plugin autoupdates are blocked by either external callback or centralized settings.
	 *
	 * @param \stdClass $plugin_obj Plugin-like object containing plugin and/or slug.
	 * @param \stdClass $settings   Centralized settings.
	 *
	 * @return bool
	 */
	public static function is_plugin_blocked_from_autoupdates( \stdClass $plugin_obj, \stdClass $settings ): bool {
		if ( ! self::is_plugin_allowed_to_autoupdate( $plugin_obj ) ) {
			return true;
		}

		return self::is_plugin_disabled_by_centralized_settings( $plugin_obj, $settings );
	}

	/**
	 * Get normalized centralized plugin files disabled from autoupdates.
	 *
	 * @param \stdClass $settings Centralized settings.
	 *
	 * @return array<int, string>
	 */
	private static function get_centrally_disabled_plugins( \stdClass $settings ): array {
		if ( ! isset( $settings->disabled_plugins ) || ! is_array( $settings->disabled_plugins ) ) {
			return array();
		}

		$normalized_plugins = array();

		foreach ( $settings->disabled_plugins as $disabled_plugin ) {
			if ( ! is_string( $disabled_plugin ) ) {
				continue;
			}

			$disabled_plugin = plugin_basename( sanitize_text_field( $disabled_plugin ) );
			if ( '' === $disabled_plugin || '.' === $disabled_plugin ) {
				continue;
			}

			$normalized_plugins[] = $disabled_plugin;
		}

		return array_values( array_unique( $normalized_plugins ) );
	}
}
