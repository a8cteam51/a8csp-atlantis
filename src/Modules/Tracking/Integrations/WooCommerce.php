<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Tracking\Integrations;

use A8C\SpecialProjects\Atlantis\Modules\Tracking\AbstractIntegration;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce Integration class.
 *
 * @since   1.0.0
 * @version 1.5.2
 */
class WooCommerce extends AbstractIntegration {
	/**
	 * WooCommerce settings field type that shows the forced tracking setting.
	 *
	 * @since   1.5.2
	 * @version 1.5.2
	 *
	 * @var string
	 */
	public const FORCED_TRACKING_FIELD_TYPE = 'a8csp_atlantis_forced_tracking';

	/**
	 * Whether the store's own choice is being read, past the override.
	 *
	 * @since   1.5.2
	 * @version 1.5.2
	 *
	 * @var bool
	 */
	private static bool $reading_stored_choice = false;

	/**
	 * {@inheritDoc}
	 *
	 * Only on a managed site: overriding the store owner's own tracking choice is a team decision,
	 * not something a site inherits by installing the plugin.
	 *
	 * @since   1.0.0
	 * @version 1.5.0
	 */
	public function is_active(): bool {
		if ( ! a8csp_atlantis_is_managed_site() ) {
			return false;
		}

		return ! defined( 'WPCOMSP_WC_TRACKING' ) || WPCOMSP_WC_TRACKING;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @since   1.0.0
	 * @version 1.5.2
	 */
	protected function initialize(): void {
		add_filter( 'option_woocommerce_allow_tracking', array( $this, 'filter_allow_tracking' ), PHP_INT_MAX );

		// Say so where the store owner would turn tracking off, instead of showing a checkbox that
		// does nothing.
		add_filter( 'woocommerce_com_integration_settings', array( $this, 'filter_tracking_setting' ) );
		add_action( 'woocommerce_admin_field_' . self::FORCED_TRACKING_FIELD_TYPE, array( $this, 'output_forced_tracking_field' ) );
	}

	/**
	 * Turns WooCommerce usage tracking on, whatever the store chose.
	 *
	 * Not while WooCommerce saves its settings: it compares the stored choice with what was
	 * posted, and the override would make an unchanged choice look like the store opting out.
	 *
	 * @since   1.5.2
	 * @version 1.5.2
	 *
	 * @param   mixed $value The store's own choice.
	 *
	 * @return  mixed
	 */
	public function filter_allow_tracking( $value ) {
		if ( self::$reading_stored_choice || doing_action( 'woocommerce_settings_save_advanced' ) ) {
			return $value;
		}

		return 'yes';
	}

	/**
	 * Replaces WooCommerce's tracking checkbox with one that shows tracking is on and why.
	 *
	 * The replacement has no `id`, so WooCommerce never saves it, and the store's own choice is
	 * kept as it is.
	 *
	 * @since   1.5.2
	 * @version 1.5.2
	 *
	 * @param   mixed $settings The fields of WooCommerce's Advanced > WooCommerce.com section.
	 *
	 * @return  mixed
	 */
	public function filter_tracking_setting( $settings ) {
		if ( ! \is_array( $settings ) ) {
			return $settings;
		}

		foreach ( $settings as $index => $field ) {
			if ( ! \is_array( $field ) || 'woocommerce_allow_tracking' !== ( $field['id'] ?? null ) ) {
				continue;
			}

			$settings[ $index ] = array(
				'type'  => self::FORCED_TRACKING_FIELD_TYPE,
				'title' => $field['title'] ?? __( 'Enable tracking', 'a8csp-atlantis' ),
				'desc'  => $field['desc'] ?? '',
			);
		}

		return $settings;
	}

	/**
	 * Outputs the tracking setting as a checkbox that is ticked and cannot be changed, with the
	 * reason beneath it.
	 *
	 * The store's own choice is posted back unchanged, because WooCommerce reads the posted value
	 * to tell whether the store opted out.
	 *
	 * @since   1.5.2
	 * @version 1.5.2
	 *
	 * @param   array<string, mixed> $field The field.
	 *
	 * @return  void
	 */
	public function output_forced_tracking_field( array $field ): void {
		$title = \is_string( $field['title'] ?? null ) ? $field['title'] : '';
		$label = \is_string( $field['desc'] ?? null ) ? $field['desc'] : '';
		?>
		<tr>
			<th scope="row" class="titledesc"><?php echo esc_html( $title ); ?></th>
			<td class="forminp forminp-checkbox">
				<fieldset>
					<legend class="screen-reader-text"><span><?php echo esc_html( $title ); ?></span></legend>
					<label for="a8csp-atlantis-woocommerce-allow-tracking">
						<input type="checkbox" id="a8csp-atlantis-woocommerce-allow-tracking" checked="checked" disabled="disabled" aria-describedby="a8csp-atlantis-woocommerce-allow-tracking-description" />
						<?php echo esc_html( $label ); ?>
					</label>
					<input type="hidden" name="woocommerce_allow_tracking" value="<?php echo esc_attr( $this->get_stored_choice() ); ?>" />
					<p class="description" id="a8csp-atlantis-woocommerce-allow-tracking-description">
						<?php esc_html_e( 'Usage tracking is turned on for this store by the Atlantis plugin, because this site is managed by the WordPress Special Projects team. Contact them with any questions about it.', 'a8csp-atlantis' ); ?>
					</p>
				</fieldset>
			</td>
		</tr>
		<?php
	}

	/**
	 * Returns the tracking choice the store saved, which the override hides.
	 *
	 * @since   1.5.2
	 * @version 1.5.2
	 *
	 * @return  string Either `yes` or `no`.
	 */
	public function get_stored_choice(): string {
		self::$reading_stored_choice = true;

		try {
			$choice = get_option( 'woocommerce_allow_tracking', 'no' );
		} finally {
			self::$reading_stored_choice = false;
		}

		return 'yes' === $choice ? 'yes' : 'no';
	}
}
