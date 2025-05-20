<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis;

use A8C\SpecialProjects\Atlantis\Modules\Colophon;
use A8C\SpecialProjects\Atlantis\Modules\Messages;

defined( 'ABSPATH' ) || exit;

/**
 * Modules class.
 *
 * @since   1.0.0
 * @version 1.0.0
 */
class Modules {
	// region FIELDS AND CONSTANTS

	/**
	 * Colophon module.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public Colophon $colophon;

	/**
	 * Messages module.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 */
	public Messages $messages;

	// endregion

	// region METHODS

	/**
	 * Registers the plugin settings.
	 *
	 * @since   1.0.0
	 * @version 1.0.0
	 *
	 * @return  void
	 */
	public function initialize(): void {
		$this->colophon = new Colophon();
		$this->colophon->maybe_initialize();

		$this->messages = new Messages();
		$this->messages->initialize();
	}

	// endregion
}
