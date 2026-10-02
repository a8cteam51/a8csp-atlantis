<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles;

defined( 'ABSPATH' ) || exit;

/**
 * Appends saved rules to the end of the site's robots.txt.
 *
 * Append only: WordPress, the host and other plugins keep producing their own
 * lines, and these rules follow them. The filter runs at `PHP_INT_MAX` so the
 * rules land after everything else, including Yoast's 99999 filter, which
 * rewrites the output it is given.
 *
 * @since   1.5.0
 * @version 1.5.0
 */
class RobotsTxt {
	// region FIELDS AND CONSTANTS

	/**
	 * Option holding the rules to append.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	public const OPTION = 'a8csp_atlantis_robots_txt_rules';

	/**
	 * Form field confirming rules that stop search engines crawling the whole site.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	public const CONFIRM_FIELD = 'a8csp_atlantis_robots_txt_confirm_block_all';

	// endregion

	// region METHODS

	/**
	 * Registers the robots.txt filter.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public function initialize(): void {
		add_filter( 'robots_txt', array( self::class, 'append_rules' ), PHP_INT_MAX );
	}

	/**
	 * Returns the saved rules.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  string
	 */
	public static function get_rules(): string {
		$rules = get_option( self::OPTION, '' );

		return is_string( $rules ) ? $rules : '';
	}

	/**
	 * Checks rules before they are saved. See RobotsTxtValidator.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $rules Normalized rules.
	 *
	 * @return  array{errors: string[], warnings: string[], blocks_all: bool}
	 */
	public static function validate( string $rules ): array {
		return ( new RobotsTxtValidator() )->validate( $rules );
	}

	// endregion

	// region HOOKS

	/**
	 * Appends the saved rules to robots.txt.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   mixed $output The robots.txt body built so far.
	 *
	 * @return  mixed
	 */
	public static function append_rules( mixed $output ): mixed {
		if ( ! CrawlerFiles::is_serving() ) {
			return $output;
		}

		$rules = self::get_rules();
		if ( '' === $rules ) {
			return $output;
		}

		$existing = is_string( $output ) ? rtrim( $output ) : '';

		return ( '' === $existing ? '' : $existing . "\n\n" ) . $rules . "\n";
	}

	/**
	 * Sanitizes and validates rules on save.
	 *
	 * On an error the previous rules are kept and the rejected text is held
	 * briefly so the editor can show it again.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   mixed $value The submitted value.
	 *
	 * @return  string
	 */
	public static function sanitize( mixed $value ): string {
		$rules = CrawlerFiles::normalize_text( $value );
		if ( '' === $rules ) {
			return '';
		}

		$report = self::validate( $rules );
		$refuse = array() !== $report['errors'] || ( $report['blocks_all'] && ! self::is_block_all_confirmed() );

		// Shown whether or not the rules save, so everything can be fixed in one pass. Core
		// drops its "Settings saved." notice when any other notice exists, so say whether it saved.
		if ( array() !== $report['warnings'] ) {
			CrawlerFiles::add_notice(
				self::OPTION,
				'a8csp-robots-txt-warnings',
				CrawlerFiles::format_problems(
					$refuse ? __( 'Also check these robots.txt lines:', 'a8csp-atlantis' ) : __( 'The robots.txt rules were saved, but check these lines:', 'a8csp-atlantis' ),
					$report['warnings']
				),
				'warning'
			);
		}

		if ( array() !== $report['errors'] ) {
			CrawlerFiles::add_notice(
				self::OPTION,
				'a8csp-robots-txt-invalid',
				CrawlerFiles::format_problems( __( 'The robots.txt rules were not saved:', 'a8csp-atlantis' ), $report['errors'] ),
				'error'
			);
			CrawlerFiles::remember_rejected( self::OPTION, $rules );

			return self::get_rules();
		}

		if ( $report['blocks_all'] && ! self::is_block_all_confirmed() ) {
			CrawlerFiles::add_notice(
				self::OPTION,
				'a8csp-robots-txt-blocks-all',
				esc_html__( 'The robots.txt rules were not saved: they stop search engines from crawling the whole site. Tick the confirmation box under the rules if that is what you want.', 'a8csp-atlantis' ),
				'error'
			);
			CrawlerFiles::remember_rejected( self::OPTION, $rules );

			return self::get_rules();
		}

		return $rules;
	}

	// endregion

	// region HELPERS

	/**
	 * Whether the user ticked the box confirming rules that block the whole site.
	 *
	 * Read during the options.php save, which has already checked the nonce.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  bool
	 */
	private static function is_block_all_confirmed(): bool {
		return isset( $_POST[ self::CONFIRM_FIELD ] ) && '1' === $_POST[ self::CONFIRM_FIELD ]; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	}

	// endregion
}
