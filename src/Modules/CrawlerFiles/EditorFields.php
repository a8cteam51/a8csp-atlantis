<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the editor's sections and fields.
 *
 * @since   1.5.0
 * @version 1.5.0
 */
class EditorFields {
	// region FIELDS AND CONSTANTS

	/**
	 * Query argument that loads the llms.txt starter content into the editor.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	public const STARTER_ARG = 'a8csp-llms-starter';

	// endregion

	// region METHODS

	/**
	 * Renders the robots.txt section introduction.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public static function render_robots_section(): void {
		echo '<p>';
		printf(
			/* translators: %s: link to the site's robots.txt */
			esc_html__( 'These rules are added to the end of %s. WordPress, the host and other plugins keep adding their own lines; nothing here replaces them.', 'a8csp-atlantis' ),
			self::file_link( '/robots.txt' ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in file_link().
		);
		echo '</p>';

		if ( CrawlerFiles::physical_file_exists( 'robots.txt' ) ) {
			self::render_inline_notice( __( 'A robots.txt file exists in the site root. The web server sends that file directly, so WordPress never builds robots.txt and these rules are not served. Remove the file to use them.', 'a8csp-atlantis' ), 'warning' );
		}
	}

	/**
	 * Renders the robots.txt rules field.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public static function render_robots_field(): void {
		$rejected = CrawlerFiles::pull_rejected( RobotsTxt::OPTION );
		$rules    = $rejected ?? RobotsTxt::get_rules();

		if ( null !== $rejected ) {
			self::render_inline_notice( __( 'These are the rules you submitted. They have not been saved.', 'a8csp-atlantis' ), 'error' );
		}

		printf(
			'<textarea name="%1$s" id="%1$s" rows="12" class="large-text code" spellcheck="false" placeholder="%2$s">%3$s</textarea>',
			esc_attr( RobotsTxt::OPTION ),
			esc_attr( "User-agent: GPTBot\nDisallow: /" ),
			esc_textarea( $rules )
		);

		echo '<p class="description">' . esc_html__( 'Start with a User-agent line. Leave empty to add nothing.', 'a8csp-atlantis' ) . '</p>';

		if ( '' !== $rules && RobotsTxt::validate( $rules )['blocks_all'] ) {
			printf(
				'<p><label><input type="checkbox" name="%1$s" value="1" /> %2$s</label></p>',
				esc_attr( RobotsTxt::CONFIRM_FIELD ),
				esc_html__( 'These rules stop search engines from crawling the whole site. I understand, save them anyway.', 'a8csp-atlantis' )
			);
		}

		self::render_last_change( RobotsTxt::OPTION );
	}

	/**
	 * Renders the llms.txt section introduction.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public static function render_llms_section(): void {
		echo '<p>';
		printf(
			/* translators: %s: link to the site's llms.txt */
			esc_html__( 'The full content of %s, a Markdown summary of the site for AI tools. Leave empty to serve nothing.', 'a8csp-atlantis' ),
			self::file_link( LlmsTxt::PATH ) // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in file_link().
		);
		echo '</p>';

		if ( ! LlmsTxt::is_home_at_root() ) {
			self::render_inline_notice( __( 'This site\'s address includes a path, so llms.txt cannot be served at the domain root. Nothing will be served.', 'a8csp-atlantis' ), 'warning' );
		}

		if ( CrawlerFiles::physical_file_exists( 'llms.txt' ) ) {
			self::render_inline_notice( __( 'An llms.txt file exists in the site root. The web server sends that file directly, so this content is not served. Remove the file to use it.', 'a8csp-atlantis' ), 'warning' );
		}
	}

	/**
	 * Renders the llms.txt content field.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public static function render_llms_field(): void {
		$rejected = CrawlerFiles::pull_rejected( LlmsTxt::OPTION );
		$content  = $rejected ?? LlmsTxt::get_content();

		if ( null !== $rejected ) {
			self::render_inline_notice( __( 'This is the content you submitted. It has not been saved.', 'a8csp-atlantis' ), 'error' );
		} elseif ( '' === $content && isset( $_GET[ self::STARTER_ARG ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- only pre-fills the form.
			$content = LlmsTxt::get_starter_content();
			self::render_inline_notice( __( 'Starter content loaded from the site name, tagline and top-level pages. Nothing is saved until you click Save Changes.', 'a8csp-atlantis' ), 'info' );
		}

		printf(
			'<textarea name="%1$s" id="%1$s" rows="20" class="large-text code" spellcheck="false" placeholder="%2$s">%3$s</textarea>',
			esc_attr( LlmsTxt::OPTION ),
			esc_attr( "# Site name\n\n> One-line summary of the site." ),
			esc_textarea( $content )
		);

		if ( '' === $content ) {
			printf(
				'<p><a href="%1$s">%2$s</a></p>',
				esc_url( add_query_arg( self::STARTER_ARG, '1', menu_page_url( EditorPage::PAGE_SLUG, false ) ) ),
				esc_html__( 'Start from the site\'s name, tagline and pages', 'a8csp-atlantis' )
			);
		}

		self::render_last_change( LlmsTxt::OPTION );
	}

	/**
	 * Renders an inline notice that stays where it is printed.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $message The unescaped message.
	 * @param   string $type    One of info, warning, error, success.
	 *
	 * @return  void
	 */
	public static function render_inline_notice( string $message, string $type ): void {
		printf(
			'<div class="notice notice-%1$s inline"><p>%2$s</p></div>',
			esc_attr( $type ),
			esc_html( $message )
		);
	}

	/**
	 * Renders who last changed an option.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $option The content option.
	 *
	 * @return  void
	 */
	private static function render_last_change( string $option ): void {
		$description = ChangeLog::describe( $option );
		if ( null === $description ) {
			return;
		}

		echo '<p class="description">' . esc_html( $description ) . '</p>';
	}

	/**
	 * Returns an escaped link to a file at the site root.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $path The path, e.g. /robots.txt.
	 *
	 * @return  string
	 */
	private static function file_link( string $path ): string {
		$url = home_url( $path );

		return sprintf( '<a href="%1$s" target="_blank" rel="noopener noreferrer"><code>%2$s</code></a>', esc_url( $url ), esc_html( $url ) );
	}

	// endregion
}
