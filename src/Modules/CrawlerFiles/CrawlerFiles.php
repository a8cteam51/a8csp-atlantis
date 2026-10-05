<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles;

use A8C\SpecialProjects\Atlantis\Modules\AbstractModule;

defined( 'ABSPATH' ) || exit;

/**
 * Crawler Files module.
 *
 * Appends rules to the end of the site's robots.txt and serves a site-root
 * llms.txt, both edited under Settings → Robots & llms.txt.
 *
 * Who may edit: Automatticians always. Other administrators only when an
 * Automattician ticks "Editor access" on the Atlantis Modules screen, which
 * only Automatticians can save (see `Settings::MANAGE_MODULES_CAP`).
 *
 * What is served: nothing outside production, and nothing while Yoast SEO is
 * active — Yoast has its own robots.txt and llms.txt tools, so this module
 * steps aside rather than competing with them. Empty content serves nothing,
 * so enabling the module on a site changes no behavior until someone saves.
 *
 * @since   1.5.0
 * @version 1.5.0
 */
class CrawlerFiles extends AbstractModule {
	// region FIELDS AND CONSTANTS

	/**
	 * The module's human-readable name. Drives the settings option key.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	private const NAME = 'Crawler Files';

	/**
	 * Capability required to open the editor and save its content.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	public const CAPABILITY = 'a8csp_atlantis_edit_crawler_files';

	/**
	 * Filter that decides whether this environment serves the files. Defaults to production only.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	public const SERVE_FILTER = 'a8csp_atlantis_crawler_files_serve';

	/**
	 * Filter that decides whether to step aside for Yoast SEO. Defaults to whether Yoast is loaded.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	public const YOAST_FILTER = 'a8csp_atlantis_crawler_files_defer_to_yoast';

	/**
	 * Largest content accepted for either file, in bytes.
	 *
	 * Google reads the first 500 KiB of robots.txt and ignores the rest.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var int
	 */
	public const MAX_BYTES = 512000;

	/**
	 * How long rejected content is kept so the editor can show it again, in seconds.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var int
	 */
	private const REJECTED_TTL = 300;

	// endregion

	// region INHERITED METHODS

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 */
	public function get_name(): string {
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 */
	public function get_description(): string {
		return __( 'Appends rules to robots.txt and serves llms.txt, edited under Settings → Robots & llms.txt. Only Automatticians can edit them unless "Editor access" below is ticked. Nothing is served outside production, or while Yoast SEO is active.', 'a8csp-atlantis' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 */
	protected function initialize(): void {
		add_filter( 'user_has_cap', array( self::class, 'filter_grant_capability' ), 10, 4 );

		( new RobotsTxt() )->initialize();
		( new LlmsTxt() )->initialize();
		( new EditorPage() )->initialize();
	}

	/**
	 * {@inheritDoc}
	 *
	 * Adds the "Editor access" checkbox beneath the base Enabled checkbox, and a
	 * sanitize filter that keeps both flags to '0' or '1'.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 */
	public function register_settings(): void {
		parent::register_settings();

		$option_name = a8csp_atlantis_generate_module_settings_key( $this->get_name() );
		add_filter( "sanitize_option_{$option_name}", array( self::class, 'sanitize_settings' ) );

		add_settings_field(
			"{$option_name}_allow_admins",
			__( 'Editor access', 'a8csp-atlantis' ),
			function ( array $args ): void {
				printf(
					'<label><input type="checkbox" id="%1$s" name="%2$s[allow_admins]" value="1" %3$s /> %4$s</label>',
					esc_attr( $args['label_for'] ),
					esc_attr( $args['option_name'] ),
					checked( self::allows_all_administrators(), true, false ),
					esc_html__( 'Let every administrator edit robots.txt and llms.txt', 'a8csp-atlantis' )
				);
				echo '<p class="description">' . esc_html__( 'When unticked, only Automatticians can open the editor.', 'a8csp-atlantis' ) . '</p>';
			},
			'a8csp-atlantis-modules',
			"{$option_name}_section",
			array(
				'option_name' => $option_name,
				'label_for'   => "{$option_name}_allow_admins",
			)
		);
	}

	// endregion

	// region METHODS

	/**
	 * Whether an Automattician has let every administrator use the editor.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  bool
	 */
	public static function allows_all_administrators(): bool {
		$settings = a8csp_atlantis_get_module_settings( self::NAME );

		return isset( $settings['allow_admins'] ) && '1' === $settings['allow_admins'];
	}

	/**
	 * Whether this environment serves the files. Production only, unless filtered.
	 *
	 * Uses the same production test as the Tracking module and Safety Net, so a
	 * staging copy that Safety Net is protecting never serves these rules.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  bool
	 */
	public static function is_serving_environment(): bool {
		return (bool) apply_filters( 'a8csp_atlantis_crawler_files_serve', 'production' === wp_get_environment_type() );
	}

	/**
	 * Whether Yoast SEO is active, in which case this module serves nothing and points to Yoast.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  bool
	 */
	public static function is_yoast_active(): bool {
		return (bool) apply_filters( 'a8csp_atlantis_crawler_files_defer_to_yoast', defined( 'WPSEO_VERSION' ) );
	}

	/**
	 * Whether the saved content should be served right now.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  bool
	 */
	public static function is_serving(): bool {
		return self::is_serving_environment() && ! self::is_yoast_active();
	}

	/**
	 * Returns the module's state for the REST status endpoint.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  array{allow_admins: bool, serving: bool, deferred_to_yoast: bool, environment: string, robots_txt_rules: bool, llms_txt: bool}
	 */
	public static function get_status(): array {
		return array(
			'allow_admins'      => self::allows_all_administrators(),
			'serving'           => self::is_serving(),
			'deferred_to_yoast' => self::is_yoast_active(),
			'environment'       => wp_get_environment_type(),
			'robots_txt_rules'  => '' !== RobotsTxt::get_rules(),
			'llms_txt'          => '' !== LlmsTxt::get_content(),
		);
	}

	/**
	 * Normalizes submitted text for storage.
	 *
	 * Both files are served as text/plain, so this does not strip or escape
	 * HTML. It converts line endings to LF, drops invalid UTF-8, a leading byte
	 * order mark, control characters other than tab and newline, and trailing
	 * whitespace on each line, then trims the whole value.
	 *
	 * Deliberately not sanitize_textarea_field(): that strips `%xx` sequences,
	 * which are common in robots.txt paths and llms.txt URLs.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   mixed $value The submitted value.
	 *
	 * @return  string
	 */
	public static function normalize_text( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = wp_check_invalid_utf8( $value, true );
		$value = str_replace( array( "\r\n", "\r" ), "\n", $value );
		$value = (string) preg_replace( '/^\xEF\xBB\xBF/', '', $value );
		$value = (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value );
		$value = (string) preg_replace( '/[ \t]+$/m', '', $value );

		return trim( $value );
	}

	/**
	 * Adds a settings notice unless one with the same code is already queued.
	 *
	 * Core prints settings messages without escaping, so every message passed
	 * here must already be escaped.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $setting The option the notice belongs to.
	 * @param   string $code    A code unique to this notice.
	 * @param   string $message The escaped message.
	 * @param   string $type    One of error, warning, success, info.
	 *
	 * @return  void
	 */
	public static function add_notice( string $setting, string $code, string $message, string $type ): void {
		foreach ( get_settings_errors( $setting ) as $existing ) {
			if ( $code === $existing['code'] ) {
				return;
			}
		}

		add_settings_error( $setting, $code, $message, $type );
	}

	/**
	 * Builds one escaped notice from a heading and a list of problems.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string   $heading  The unescaped heading.
	 * @param   string[] $problems The unescaped problems.
	 *
	 * @return  string
	 */
	public static function format_problems( string $heading, array $problems ): string {
		$limit = 10;
		$shown = array_map( 'esc_html', array_slice( $problems, 0, $limit ) );
		$extra = count( $problems ) - $limit;

		if ( 0 < $extra ) {
			/* translators: %d: number of problems not listed */
			$shown[] = esc_html( sprintf( _n( '…and %d more.', '…and %d more.', $extra, 'a8csp-atlantis' ), $extra ) );
		}

		return esc_html( $heading ) . '<br />' . implode( '<br />', $shown );
	}

	/**
	 * Keeps content that failed validation so the editor can show it again
	 * instead of silently dropping what the user typed.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $option  The option the content was meant for.
	 * @param   string $content The rejected content.
	 *
	 * @return  void
	 */
	public static function remember_rejected( string $option, string $content ): void {
		if ( self::MAX_BYTES < strlen( $content ) ) {
			return; // Too large to hold on to.
		}

		set_transient( self::rejected_key( $option ), $content, self::REJECTED_TTL );
	}

	/**
	 * Returns and forgets content that failed validation, if any.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $option The option the content was meant for.
	 *
	 * @return  string|null
	 */
	public static function pull_rejected( string $option ): ?string {
		$key     = self::rejected_key( $option );
		$content = get_transient( $key );
		if ( false === $content ) {
			return null;
		}

		delete_transient( $key );

		return is_string( $content ) ? $content : null;
	}

	/**
	 * Whether a physical file with this name sits in the site root, where the
	 * web server would send it without ever asking WordPress.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $filename The file name, e.g. robots.txt.
	 *
	 * @return  bool
	 */
	public static function physical_file_exists( string $filename ): bool {
		$roots = array( ABSPATH );

		if ( function_exists( 'get_home_path' ) ) {
			$roots[] = get_home_path();
		}

		foreach ( array_unique( array_map( 'trailingslashit', $roots ) ) as $root ) {
			if ( file_exists( $root . $filename ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns the transient key holding a user's rejected content for an option.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $option The option name.
	 *
	 * @return  string
	 */
	private static function rejected_key( string $option ): string {
		return 'a8csp_rejected_' . md5( $option . '|' . get_current_user_id() );
	}

	// endregion

	// region HOOKS

	/**
	 * Grants the editor capability to administrators who are Automatticians,
	 * or to every administrator once an Automattician has allowed it.
	 *
	 * Reads `manage_options` out of the capability map rather than calling
	 * `current_user_can()`, which would re-enter this filter.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   array<string, bool> $allcaps The user's capabilities.
	 * @param   string[]            $caps    The capabilities being checked.
	 * @param   array<mixed>        $args    Context for the check.
	 * @param   \WP_User            $user    The user being checked.
	 *
	 * @return  array<string, bool>
	 */
	public static function filter_grant_capability( array $allcaps, array $caps, array $args, \WP_User $user ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundBeforeLastUsed
		if ( ! in_array( self::CAPABILITY, $caps, true ) ) {
			return $allcaps;
		}

		if ( true !== ( $allcaps['manage_options'] ?? false ) ) {
			return $allcaps;
		}

		if ( a8csp_atlantis_user_has_automattic_email( $user ) || self::allows_all_administrators() ) {
			$allcaps[ self::CAPABILITY ] = true;
		}

		return $allcaps;
	}

	/**
	 * Sanitizes the module settings: both flags become '0' or '1'.
	 *
	 * An unticked checkbox is not submitted at all, so a missing key means '0'.
	 * Programmatic writes (`AbstractModule::set_enabled()`) pass the full stored
	 * array, so both flags survive them.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   mixed $input The submitted value.
	 *
	 * @return  array{enabled: string, allow_admins: string}
	 */
	public static function sanitize_settings( mixed $input ): array {
		$input = is_array( $input ) ? $input : array();

		return array(
			'enabled'      => self::is_ticked( $input['enabled'] ?? null ) ? '1' : '0',
			'allow_admins' => self::is_ticked( $input['allow_admins'] ?? null ) ? '1' : '0',
		);
	}

	// endregion

	// region HELPERS

	/**
	 * Whether a submitted checkbox value means "ticked".
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   mixed $value The submitted value.
	 *
	 * @return  bool
	 */
	private static function is_ticked( mixed $value ): bool {
		return in_array( $value, array( '1', 1, true ), true );
	}

	// endregion
}
