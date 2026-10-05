<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\CrawlerFiles;

defined( 'ABSPATH' ) || exit;

/**
 * What the editor shows instead of its form while Yoast SEO is active.
 *
 * Yoast has its own robots.txt editor (Tools → File editor, which writes a
 * physical robots.txt) and its own llms.txt generator (which writes a
 * physical llms.txt). Rather than compete with those files, the module
 * serves nothing and sends people to Yoast. Anything saved here earlier is
 * kept and shown read-only so it can be copied across.
 *
 * @since   1.5.0
 * @version 1.5.0
 */
class YoastHandoff {
	// region METHODS

	/**
	 * Renders the hand-off.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  void
	 */
	public static function render(): void {
		EditorFields::render_inline_notice( __( 'Yoast SEO is active on this site, so it manages robots.txt and llms.txt. Atlantis is not adding robots.txt rules or serving llms.txt while Yoast is active.', 'a8csp-atlantis' ), 'info' );

		echo '<h2>' . esc_html__( 'robots.txt', 'a8csp-atlantis' ) . '</h2>';
		if ( self::can_use_file_editor() ) {
			printf(
				'<p><a class="button" href="%1$s">%2$s</a></p>',
				esc_url( admin_url( 'admin.php?page=wpseo_tools&tool=file-editor' ) ),
				esc_html__( 'Edit robots.txt in Yoast SEO → Tools → File editor', 'a8csp-atlantis' )
			);
		} else {
			echo '<p>' . esc_html__( 'Yoast\'s robots.txt editor (Yoast SEO → Tools → File editor) is not available here. Yoast only offers it to users who can edit files, and not on multisite.', 'a8csp-atlantis' ) . '</p>';
		}

		echo '<h2>' . esc_html__( 'llms.txt', 'a8csp-atlantis' ) . '</h2>';
		printf(
			'<p><a class="button" href="%1$s">%2$s</a></p>',
			esc_url( admin_url( 'admin.php?page=wpseo_page_settings#/llms-txt' ) ),
			esc_html__( 'Set up llms.txt in Yoast SEO → Settings → llms.txt', 'a8csp-atlantis' )
		);

		self::render_saved_content( RobotsTxt::OPTION, __( 'robots.txt rules saved in Atlantis (kept, not served)', 'a8csp-atlantis' ) );
		self::render_saved_content( LlmsTxt::OPTION, __( 'llms.txt content saved in Atlantis (kept, not served)', 'a8csp-atlantis' ) );
	}

	/**
	 * Shows previously saved content read-only, if there is any.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @param   string $option The content option.
	 * @param   string $label  The unescaped label.
	 *
	 * @return  void
	 */
	private static function render_saved_content( string $option, string $label ): void {
		$saved = get_option( $option, '' );
		if ( ! is_string( $saved ) || '' === $saved ) {
			return;
		}

		printf(
			'<details><summary>%1$s</summary><textarea readonly rows="8" class="large-text code">%2$s</textarea></details>',
			esc_html( $label ),
			esc_textarea( $saved )
		);
	}

	/**
	 * Whether Yoast will show its file editor to the current user.
	 *
	 * Mirrors the check in Yoast's admin/pages/tools.php.
	 *
	 * @since   1.5.0
	 * @version 1.5.0
	 *
	 * @return  bool
	 */
	private static function can_use_file_editor(): bool {
		if ( is_multisite() ) {
			return false;
		}

		return (bool) apply_filters( 'wpseo_allow_system_file_edit', current_user_can( 'edit_files' ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Yoast's filter.
	}

	// endregion
}
