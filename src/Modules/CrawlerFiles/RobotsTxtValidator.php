<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles;

defined( 'ABSPATH' ) || exit;

/**
 * Checks robots.txt rules before they are saved.
 *
 * Errors block the save. Warnings are reported but the rules are saved.
 * `blocks_all` is true when `Disallow: /` applies to every crawler, Googlebot
 * or Bingbot; saving those needs explicit confirmation.
 *
 * Grouping follows RFC 9309: consecutive User-agent lines open a group and
 * the rules after them belong to it. Because these rules are appended after
 * the rest of robots.txt, a rule before the first User-agent line would join
 * whichever group happens to come last there, so that is an error.
 *
 * One instance validates one set of rules.
 *
 * @since   1.5.0
 * @version 1.5.0
 */
class RobotsTxtValidator {
	// region FIELDS AND CONSTANTS

	/**
	 * Directives that belong to a User-agent group.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string[]
	 */
	private const GROUP_DIRECTIVES = array( 'allow', 'disallow', 'crawl-delay', 'content-signal' );

	/**
	 * Every directive the validator recognizes. Anything else draws a warning.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string[]
	 */
	private const KNOWN_DIRECTIVES = array( 'user-agent', 'allow', 'disallow', 'crawl-delay', 'content-signal', 'sitemap', 'clean-param', 'host' );

	/**
	 * User agents for which `Disallow: /` needs explicit confirmation.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @var string[]
	 */
	private const SEARCH_AGENTS = array( '*', 'googlebot', 'bingbot' );

	/**
	 * Problems that block the save.
	 *
	 * @var string[]
	 */
	private array $errors = array();

	/**
	 * Problems reported alongside a successful save.
	 *
	 * @var string[]
	 */
	private array $warnings = array();

	/**
	 * Whether the rules stop search engines crawling the whole site.
	 *
	 * @var bool
	 */
	private bool $blocks_all = false;

	/**
	 * Lower-cased user agents of the group being read.
	 *
	 * @var string[]
	 */
	private array $group_agents = array();

	/**
	 * Whether the current group has had a rule, so the next User-agent starts a new group.
	 *
	 * @var bool
	 */
	private bool $in_rules = false;

	/**
	 * Whether any User-agent line has been read.
	 *
	 * @var bool
	 */
	private bool $seen_agent = false;

	/**
	 * The site's lower-cased host, for comparing Sitemap URLs.
	 *
	 * @var string
	 */
	private string $home_host;

	// endregion

	// region MAGIC METHODS

	/**
	 * Reads the site's host.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 */
	public function __construct() {
		$host            = wp_parse_url( home_url(), PHP_URL_HOST );
		$this->home_host = is_string( $host ) ? strtolower( $host ) : '';
	}

	// endregion

	// region METHODS

	/**
	 * Validates normalized rules.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $rules Normalized rules.
	 *
	 * @return  array{errors: string[], warnings: string[], blocks_all: bool}
	 */
	public function validate( string $rules ): array {
		if ( CrawlerFiles::MAX_BYTES < strlen( $rules ) ) {
			$this->errors[] = sprintf(
				/* translators: %s: maximum size, e.g. 500 KB */
				__( 'The rules are larger than %s. Search engines ignore anything past that point.', 'a8csp-atlantis' ),
				size_format( CrawlerFiles::MAX_BYTES )
			);

			return $this->report();
		}

		foreach ( explode( "\n", $rules ) as $index => $raw_line ) {
			$this->check_line( $index + 1, $raw_line );
		}

		return $this->report();
	}

	/**
	 * Checks one line.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   int    $number   The line number.
	 * @param   string $raw_line The line as submitted.
	 *
	 * @return  void
	 */
	private function check_line( int $number, string $raw_line ): void {
		$line = trim( (string) preg_replace( '/#.*$/', '', $raw_line ) );
		if ( '' === $line ) {
			return;
		}

		$colon = strpos( $line, ':' );
		if ( false === $colon ) {
			$this->warnings[] = sprintf(
				/* translators: 1: line number, 2: line content */
				__( 'Line %1$d is not a "Field: value" directive, so crawlers ignore it: %2$s', 'a8csp-atlantis' ),
				$number,
				$line
			);
			return;
		}

		$name  = trim( substr( $line, 0, $colon ) );
		$field = strtolower( $name );
		$value = trim( substr( $line, $colon + 1 ) );

		if ( ! in_array( $field, self::KNOWN_DIRECTIVES, true ) ) {
			$this->warnings[] = sprintf(
				/* translators: 1: line number, 2: directive name */
				__( 'Line %1$d uses "%2$s", which major crawlers do not recognize.', 'a8csp-atlantis' ),
				$number,
				$name
			);
			return;
		}

		if ( 'user-agent' === $field ) {
			$this->check_user_agent( $number, $value );
		} elseif ( 'sitemap' === $field ) {
			$this->check_sitemap( $number, $value );
		} elseif ( in_array( $field, self::GROUP_DIRECTIVES, true ) ) {
			$this->check_group_directive( $number, $field, $value );
		}
	}

	/**
	 * Tracks a User-agent line. One after a rule starts a new group.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   int    $number The line number.
	 * @param   string $value  The user agent.
	 *
	 * @return  void
	 */
	private function check_user_agent( int $number, string $value ): void {
		if ( $this->in_rules ) {
			$this->group_agents = array();
			$this->in_rules     = false;
		}

		$this->group_agents[] = strtolower( $value );
		$this->seen_agent     = true;

		if ( '' === $value ) {
			$this->warnings[] = sprintf(
				/* translators: %d: line number */
				__( 'Line %d has an empty User-agent.', 'a8csp-atlantis' ),
				$number
			);
		}
	}

	/**
	 * Checks a directive that belongs to a group.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   int    $number The line number.
	 * @param   string $field  The lower-cased directive.
	 * @param   string $value  The directive's value.
	 *
	 * @return  void
	 */
	private function check_group_directive( int $number, string $field, string $value ): void {
		$this->in_rules = true;

		if ( ! $this->seen_agent ) {
			$this->report_rule_before_user_agent( $number );
			return;
		}

		if ( 'allow' === $field || 'disallow' === $field ) {
			$this->check_path( $number, $value );
		}

		if ( 'disallow' === $field && in_array( $value, array( '/', '/*' ), true ) && array() !== array_intersect( $this->group_agents, self::SEARCH_AGENTS ) ) {
			$this->blocks_all = true;
		}
	}

	/**
	 * Reports the first rule that comes before any User-agent line.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   int $number The line number.
	 *
	 * @return  void
	 */
	private function report_rule_before_user_agent( int $number ): void {
		if ( array() !== $this->errors ) {
			return; // Once is enough.
		}

		$this->errors[] = sprintf(
			/* translators: %d: line number */
			__( 'Line %d comes before any User-agent line. These rules are added after the rest of robots.txt, so they would join whichever crawler group comes last there. Start with a User-agent line.', 'a8csp-atlantis' ),
			$number
		);
	}

	/**
	 * Warns about an Allow or Disallow path that does not start with / or *.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   int    $number The line number.
	 * @param   string $value  The path.
	 *
	 * @return  void
	 */
	private function check_path( int $number, string $value ): void {
		if ( '' === $value || in_array( $value[0], array( '/', '*' ), true ) ) {
			return;
		}

		$this->warnings[] = sprintf(
			/* translators: 1: line number, 2: path */
			__( 'Line %1$d: paths should start with "/" or "*", so crawlers may ignore "%2$s".', 'a8csp-atlantis' ),
			$number,
			$value
		);
	}

	/**
	 * Checks a Sitemap value: it must be an absolute URL, and a sitemap on
	 * another host is usually a staging or old domain left behind.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   int    $number The line number.
	 * @param   string $value  The sitemap URL.
	 *
	 * @return  void
	 */
	private function check_sitemap( int $number, string $value ): void {
		$scheme = wp_parse_url( $value, PHP_URL_SCHEME );
		$host   = wp_parse_url( $value, PHP_URL_HOST );

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || ! is_string( $host ) ) {
			$this->warnings[] = sprintf(
				/* translators: %d: line number */
				__( 'Line %d: a Sitemap must be a full URL starting with https://.', 'a8csp-atlantis' ),
				$number
			);
			return;
		}

		if ( '' !== $this->home_host && strtolower( $host ) !== $this->home_host ) {
			$this->warnings[] = sprintf(
				/* translators: 1: line number, 2: sitemap host, 3: site host */
				__( 'Line %1$d: the sitemap is on %2$s, but this site is %3$s. Check it is not a staging or old domain.', 'a8csp-atlantis' ),
				$number,
				$host,
				$this->home_host
			);
		}
	}

	/**
	 * Returns the findings.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  array{errors: string[], warnings: string[], blocks_all: bool}
	 */
	private function report(): array {
		return array(
			'errors'     => $this->errors,
			'warnings'   => $this->warnings,
			'blocks_all' => $this->blocks_all,
		);
	}

	// endregion
}
