<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles;

defined( 'ABSPATH' ) || exit;

/**
 * The Settings → Robots & llms.txt editor.
 *
 * Lives under Settings rather than the Atlantis menu because the Atlantis
 * menu is hidden from everyone who is not an Automattician, and this page
 * must also be reachable by administrators an Automattician has let in.
 *
 * The content saves through its own settings group. Core's options.php
 * requires `manage_options` unless the group's capability is filtered, so
 * the group is pinned to `CrawlerFiles::CAPABILITY`; without that, any
 * administrator could POST content even with the page hidden from them.
 *
 * @since   1.5.0
 * @version 1.5.0
 */
class EditorPage {
	// region FIELDS AND CONSTANTS

	/**
	 * The admin page slug.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'a8csp-atlantis-crawler-files';

	/**
	 * The settings group the content saves through.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	public const OPTION_GROUP = 'a8csp_crawler_files_group';

	// endregion

	// region METHODS

	/**
	 * Registers the page, settings and change tracking.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public function initialize(): void {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'option_page_capability_' . self::OPTION_GROUP, array( self::class, 'filter_option_page_capability' ) );

		( new ChangeLog() )->initialize();
	}

	/**
	 * Returns the options holding file content.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  string[]
	 */
	public static function get_content_options(): array {
		return array( RobotsTxt::OPTION, LlmsTxt::OPTION );
	}

	// endregion

	// region HOOKS

	/**
	 * Adds the page under Settings for users with the editor capability.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public function register_page(): void {
		$hook = add_options_page(
			__( 'Robots & llms.txt', 'a8csp-atlantis' ),
			__( 'Robots & llms.txt', 'a8csp-atlantis' ),
			CrawlerFiles::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);

		if ( false !== $hook ) {
			add_action( "load-{$hook}", array( self::class, 'prepare_options' ) );
		}
	}

	/**
	 * Creates the content options, not autoloaded, before the form is shown.
	 *
	 * They are read only when robots.txt or llms.txt is requested, so there
	 * is no reason to load them on every page. Creating them here also means
	 * the save that follows is an update, which runs the sanitize callback
	 * once rather than twice (core sanitizes again when update_option() falls
	 * through to add_option()).
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public static function prepare_options(): void {
		foreach ( self::get_content_options() as $option ) {
			add_option( $option, '', '', false );
		}
	}

	/**
	 * Registers the content settings and the page's sections.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public function register_settings(): void {
		$sanitizers = array(
			RobotsTxt::OPTION => array( RobotsTxt::class, 'sanitize' ),
			LlmsTxt::OPTION   => array( LlmsTxt::class, 'sanitize' ),
		);

		foreach ( $sanitizers as $option => $sanitizer ) {
			register_setting(
				self::OPTION_GROUP,
				$option,
				array(
					'type'              => 'string',
					'sanitize_callback' => $sanitizer,
					'default'           => '',
					'show_in_rest'      => false,
					'capability'        => CrawlerFiles::CAPABILITY,
				)
			);
		}

		add_settings_section( 'a8csp-crawler-files-robots', __( 'robots.txt', 'a8csp-atlantis' ), array( EditorFields::class, 'render_robots_section' ), self::PAGE_SLUG );
		add_settings_field( RobotsTxt::OPTION, __( 'Rules to append', 'a8csp-atlantis' ), array( EditorFields::class, 'render_robots_field' ), self::PAGE_SLUG, 'a8csp-crawler-files-robots', array( 'label_for' => RobotsTxt::OPTION ) );

		add_settings_section( 'a8csp-crawler-files-llms', __( 'llms.txt', 'a8csp-atlantis' ), array( EditorFields::class, 'render_llms_section' ), self::PAGE_SLUG );
		add_settings_field( LlmsTxt::OPTION, __( 'File content', 'a8csp-atlantis' ), array( EditorFields::class, 'render_llms_field' ), self::PAGE_SLUG, 'a8csp-crawler-files-llms', array( 'label_for' => LlmsTxt::OPTION ) );
	}

	/**
	 * Requires the editor capability, not `manage_options`, to save the content.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $capability The capability core would otherwise require.
	 *
	 * @return  string
	 */
	public static function filter_option_page_capability( string $capability ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return CrawlerFiles::CAPABILITY;
	}

	/**
	 * Renders the page.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public function render_page(): void {
		echo '<div class="wrap">';
		echo '<h1>' . esc_html( get_admin_page_title() ) . '</h1>';

		if ( CrawlerFiles::is_yoast_active() ) {
			YoastHandoff::render();
			echo '</div>';
			return;
		}

		if ( ! CrawlerFiles::is_serving_environment() ) {
			EditorFields::render_inline_notice(
				sprintf(
					/* translators: %s: environment type, e.g. staging */
					__( 'This is a %s site. Atlantis only serves these files in production, so nothing below is served here. You can still edit and save.', 'a8csp-atlantis' ),
					wp_get_environment_type()
				),
				'info'
			);
		}

		echo '<form action="options.php" method="post">';
		settings_fields( self::OPTION_GROUP );
		do_settings_sections( self::PAGE_SLUG );
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	// endregion
}
