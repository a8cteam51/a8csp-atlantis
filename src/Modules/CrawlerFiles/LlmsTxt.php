<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles;

defined( 'ABSPATH' ) || exit;

/**
 * Serves a site-root /llms.txt from saved content.
 *
 * Based on an existing site-specific llms.txt editor: the file is answered early
 * on `init` from an option rather than living on disk, so no rewrite rules
 * need flushing. Unlike that editor, empty content serves nothing — the
 * request falls through to WordPress as before — so enabling the module
 * changes no behavior until someone saves.
 *
 * WP.com and Pressable both pass a missing /llms.txt through to WordPress.
 * A physical llms.txt in the web root is sent by the web server instead.
 *
 * @since   1.5.0
 * @version 1.5.0
 */
class LlmsTxt {
	// region FIELDS AND CONSTANTS

	/**
	 * Option holding the llms.txt content.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	public const OPTION = 'a8csp_atlantis_llms_txt';

	/**
	 * Filter for the content about to be served.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	public const CONTENT_FILTER = 'a8csp_atlantis_llms_txt';

	/**
	 * Filter for the Cache-Control max-age, in seconds.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	public const CACHE_FILTER = 'a8csp_atlantis_llms_txt_cache_max_age';

	/**
	 * The path the file is served at. llms.txt is defined at the domain root.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string
	 */
	public const PATH = '/llms.txt';

	/**
	 * How many top-level pages the starter content lists.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var int
	 */
	private const STARTER_PAGE_LIMIT = 10;

	// endregion

	// region METHODS

	/**
	 * Registers the request handler.
	 *
	 * Priority 0 on `init`, before the main query is parsed.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public function initialize(): void {
		add_action( 'init', array( $this, 'maybe_serve' ), 0 );
	}

	/**
	 * Returns the saved content.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  string
	 */
	public static function get_content(): string {
		$content = get_option( self::OPTION, '' );

		return is_string( $content ) ? $content : '';
	}

	/**
	 * Returns the body to serve for a request, or null to let WordPress carry on.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $request_uri The request URI.
	 * @param   string $method      The HTTP method.
	 *
	 * @return  string|null
	 */
	public static function get_response_body( string $request_uri, string $method ): ?string {
		if ( ! in_array( strtoupper( $method ), array( 'GET', 'HEAD' ), true ) ) {
			return null;
		}

		if ( ! self::is_llms_txt_path( $request_uri ) || ! CrawlerFiles::is_serving() ) {
			return null;
		}

		$content = apply_filters( 'a8csp_atlantis_llms_txt', self::get_content() );
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return null;
		}

		return rtrim( $content, "\n" ) . "\n";
	}

	/**
	 * Whether the request is for /llms.txt at the domain root.
	 *
	 * Sites whose address includes a path (example.com/blog) cannot serve the
	 * domain root, so they never match — the same rule core applies to robots.txt.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $request_uri The request URI.
	 *
	 * @return  bool
	 */
	public static function is_llms_txt_path( string $request_uri ): bool {
		$path = wp_parse_url( $request_uri, PHP_URL_PATH );

		return self::PATH === $path && self::is_home_at_root();
	}

	/**
	 * Whether the site's home URL is the domain root.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  bool
	 */
	public static function is_home_at_root(): bool {
		$home_path = wp_parse_url( home_url(), PHP_URL_PATH );

		return ! is_string( $home_path ) || '' === $home_path || '/' === $home_path;
	}

	/**
	 * Returns the headers sent with the file.
	 *
	 * The noindex header keeps the file out of search results. The short public cache
	 * lets the CDN absorb crawler traffic; edits show within that window.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  array<string, string>
	 */
	public static function get_response_headers(): array {
		$max_age = apply_filters( 'a8csp_atlantis_llms_txt_cache_max_age', 300 );
		$max_age = is_numeric( $max_age ) ? max( 0, (int) $max_age ) : 300;

		return array(
			'Content-Type'           => 'text/plain; charset=utf-8',
			'X-Content-Type-Options' => 'nosniff',
			'X-Robots-Tag'           => 'noindex, follow',
			'Cache-Control'          => 'public, max-age=' . $max_age,
		);
	}

	/**
	 * Checks content before it is saved.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $content Normalized content.
	 *
	 * @return  array{errors: string[], warnings: string[]}
	 */
	public static function validate( string $content ): array {
		if ( CrawlerFiles::MAX_BYTES < strlen( $content ) ) {
			return array(
				'errors'   => array(
					sprintf(
						/* translators: %s: maximum size, e.g. 500 KB */
						__( 'llms.txt is larger than %s. Keep it to a short index and link to longer pages instead.', 'a8csp-atlantis' ),
						size_format( CrawlerFiles::MAX_BYTES )
					),
				),
				'warnings' => array(),
			);
		}

		$warnings = array();
		if ( 1 !== preg_match( '/^# \S/', $content ) ) {
			$warnings[] = __( 'llms.txt should start with a title line, e.g. "# Site name".', 'a8csp-atlantis' );
		}

		return array(
			'errors'   => array(),
			'warnings' => $warnings,
		);
	}

	/**
	 * Builds starter content from the site name, tagline and top-level pages.
	 *
	 * Shown in the editor on request; nothing is saved until the user saves.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  string
	 */
	public static function get_starter_content(): string {
		$lines   = array( '# ' . self::plain( get_bloginfo( 'name' ) ), '' );
		$tagline = self::plain( get_bloginfo( 'description' ) );

		if ( '' !== $tagline ) {
			$lines[] = '> ' . $tagline;
			$lines[] = '';
		}

		$lines[] = '## Pages';
		$lines[] = '';
		$lines[] = '- [' . __( 'Home', 'a8csp-atlantis' ) . '](' . home_url( '/' ) . ')';

		$pages = get_pages(
			array(
				'parent'      => 0,
				'number'      => self::STARTER_PAGE_LIMIT,
				'sort_column' => 'menu_order,post_title',
				'post_status' => 'publish',
			)
		);

		foreach ( is_array( $pages ) ? $pages : array() as $page ) {
			$title     = str_replace( array( '[', ']' ), '', self::plain( get_the_title( $page ) ) );
			$permalink = get_permalink( $page );
			if ( '' === $title || false === $permalink ) {
				continue;
			}

			$lines[] = '- [' . $title . '](' . $permalink . ')';
		}

		return implode( "\n", $lines ) . "\n";
	}

	/**
	 * Converts a WordPress-formatted string to plain text.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $text The text.
	 *
	 * @return  string
	 */
	private static function plain( string $text ): string {
		return trim( html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	/**
	 * Sends the file and ends the request.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @SuppressWarnings(PHPMD.ExitExpression)
	 *
	 * @param   string $body The file content.
	 *
	 * @return  void
	 */
	private function send( string $body ): void {
		status_header( 200 );
		foreach ( self::get_response_headers() as $name => $value ) {
			header( $name . ': ' . $value );
		}

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- text/plain response with nosniff.
		exit;
	}

	// endregion

	// region HOOKS

	/**
	 * Answers /llms.txt requests.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public function maybe_serve(): void {
		if ( ! isset( $_SERVER['REQUEST_URI'] ) || headers_sent() ) {
			return;
		}

		$request_uri = sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) );
		$method      = isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : 'GET';

		$body = self::get_response_body( $request_uri, $method );
		if ( null === $body ) {
			return;
		}

		$this->send( $body );
	}

	/**
	 * Sanitizes and validates content on save.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   mixed $value The submitted value.
	 *
	 * @return  string
	 */
	public static function sanitize( mixed $value ): string {
		$content = CrawlerFiles::normalize_text( $value );
		if ( '' === $content ) {
			return '';
		}

		$report = self::validate( $content );

		if ( array() !== $report['errors'] ) {
			CrawlerFiles::add_notice(
				self::OPTION,
				'a8csp-llms-txt-invalid',
				CrawlerFiles::format_problems( __( 'llms.txt was not saved:', 'a8csp-atlantis' ), $report['errors'] ),
				'error'
			);
			CrawlerFiles::remember_rejected( self::OPTION, $content );

			return self::get_content();
		}

		if ( array() !== $report['warnings'] ) {
			CrawlerFiles::add_notice(
				self::OPTION,
				'a8csp-llms-txt-warnings',
				CrawlerFiles::format_problems( __( 'llms.txt was saved, but check this:', 'a8csp-atlantis' ), $report['warnings'] ),
				'warning'
			);
		}

		return $content;
	}

	// endregion
}
