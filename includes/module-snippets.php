<?php declare( strict_types=1 );

use A8C\SpecialProjects\Atlantis\Modules\Snippets\Snippets;
use A8C\SpecialProjects\Atlantis\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Returns the Snippets module instance if it is registered and initialised.
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @return  Snippets|null
 */
function a8csp_atlantis_get_snippets_module(): ?Snippets {
	$plugin = Plugin::get_instance();
	if ( ! isset( $plugin->modules ) ) {
		return null;
	}

	$module = $plugin->modules->modules['snippets'] ?? null;
	return $module instanceof Snippets ? $module : null;
}

/**
 * Records a runtime failure for a snippet so Atlantis can auto-quarantine it.
 *
 * Called by the mu-plugin loader drop-in when a snippet throws while loading.
 * Guarded so the loader can call it conditionally without hard-depending on
 * Atlantis being fully initialised.
 *
 * @since   1.3.0
 * @version 1.3.0
 *
 * @param   string $snippet_id The stable snippet slug.
 * @param   string $error      The error message.
 *
 * @return  void
 */
function a8csp_atlantis_record_snippet_failure( string $snippet_id, string $error ): void {
	$module = a8csp_atlantis_get_snippets_module();
	if ( null === $module ) {
		return;
	}

	$module->get_store()->record_failure( $snippet_id, $error );
}
