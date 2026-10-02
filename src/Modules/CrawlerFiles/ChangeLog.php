<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles;

defined( 'ABSPATH' ) || exit;

/**
 * Records who last changed robots.txt rules or llms.txt content, and when.
 *
 * A robots.txt mistake can quietly drop a site from search results, so the
 * editor shows the last change next to each file.
 *
 * @since   1.5.0
 * @version 1.5.0
 */
class ChangeLog {
	// region FIELDS AND CONSTANTS

	/**
	 * Option holding the last change per content option. Not autoloaded.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	public const OPTION = 'a8csp_atlantis_crawler_files_changes';

	// endregion

	// region METHODS

	/**
	 * Hooks into changes to the content options.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public function initialize(): void {
		foreach ( EditorPage::get_content_options() as $option ) {
			add_action( "add_option_{$option}", array( self::class, 'record_added' ), 10, 2 );
			add_action( "update_option_{$option}", array( self::class, 'record_updated' ), 10, 3 );
		}
	}

	/**
	 * Returns who last changed an option and when, if recorded.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $option The content option.
	 *
	 * @return  array{user_id: int, time: int}|null
	 */
	public static function get_last_change( string $option ): ?array {
		$changes = get_option( self::OPTION, array() );
		$change  = is_array( $changes ) ? ( $changes[ $option ] ?? null ) : null;

		if ( ! is_array( $change ) || ! isset( $change['user_id'], $change['time'] ) ) {
			return null;
		}

		return array(
			'user_id' => (int) $change['user_id'],
			'time'    => (int) $change['time'],
		);
	}

	/**
	 * Describes the last change to an option, e.g. "Last changed by Ana on 2 October 2026 3:04 pm."
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $option The content option.
	 *
	 * @return  string|null Unescaped text, or null if no change is recorded.
	 */
	public static function describe( string $option ): ?string {
		$change = self::get_last_change( $option );
		if ( null === $change ) {
			return null;
		}

		if ( 0 === $change['user_id'] ) {
			$name = __( 'code or WP-CLI', 'a8csp-atlantis' );
		} else {
			$user = get_userdata( $change['user_id'] );
			$name = false !== $user ? $user->display_name : __( 'a user who no longer exists', 'a8csp-atlantis' );
		}

		$when = wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $change['time'] );

		return sprintf(
			/* translators: 1: user's display name, 2: date and time */
			__( 'Last changed by %1$s on %2$s.', 'a8csp-atlantis' ),
			$name,
			false === $when ? '' : $when
		);
	}

	/**
	 * Records a change to an option.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $option The content option.
	 *
	 * @return  void
	 */
	private static function record( string $option ): void {
		$changes = get_option( self::OPTION, array() );
		$changes = is_array( $changes ) ? $changes : array();

		$changes[ $option ] = array(
			'user_id' => get_current_user_id(),
			'time'    => time(),
		);

		update_option( self::OPTION, $changes, false );
	}

	// endregion

	// region HOOKS

	/**
	 * Records a change when a content option is first stored with content.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $option The option name.
	 * @param   mixed  $value  The stored value.
	 *
	 * @return  void
	 */
	public static function record_added( string $option, mixed $value ): void {
		if ( '' === $value ) {
			return; // EditorPage::prepare_options() creating the empty option is not an edit.
		}

		self::record( $option );
	}

	/**
	 * Records a change when a content option's value changes.
	 *
	 * Core only fires this hook when the value actually changed.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   mixed  $old_value The previous value.
	 * @param   mixed  $value     The new value.
	 * @param   string $option    The option name.
	 *
	 * @return  void
	 */
	public static function record_updated( mixed $old_value, mixed $value, string $option ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		self::record( $option );
	}

	// endregion
}
