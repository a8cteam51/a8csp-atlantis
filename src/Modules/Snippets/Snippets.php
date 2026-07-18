<?php declare( strict_types=1 );

namespace A8C\SpecialProjects\Atlantis\Modules\Snippets;

use A8C\SpecialProjects\Atlantis\Modules\AbstractModule;
use A8C\SpecialProjects\Atlantis\REST\Snippets_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Snippets module: fleet-wide remediation snippet runner.
 *
 * Lets a signed PHP remediation be deployed from OpsOasis / the team51 CLI to
 * every managed site running Atlantis, without cutting a plugin release. Each
 * deploy is pushed (never pulled), signature-verified, stored in the database
 * (the on-site source of truth), and materialized as an mu-plugin that loads
 * early and survives independently of Atlantis.
 *
 * This generalizes the hardcoded, release-gated core-compat filters in
 * {@see \A8C\SpecialProjects\Atlantis\Plugin::register_core_compat_filters()}.
 *
 * See the bundled design doc (docs/snippet-runner-design.md) for the full trust
 * model, threat analysis, and rollout protocol.
 *
 * @since   1.3.0
 * @version 1.3.0
 */
class Snippets extends AbstractModule {
	// region FIELDS AND CONSTANTS

	/**
	 * The custom table component.
	 *
	 * @var CustomTable
	 */
	private CustomTable $table;

	/**
	 * The snippet data store.
	 *
	 * @var SnippetStore
	 */
	private SnippetStore $store;

	/**
	 * The filesystem materializer / reconciler.
	 *
	 * @var Loader
	 */
	private Loader $loader;

	/**
	 * The REST receiver for pushed deployments.
	 *
	 * @var Snippets_Controller
	 */
	private Snippets_Controller $controller;

	// endregion

	// region MAGIC METHODS

	/**
	 * Constructor: wires the collaborators together.
	 */
	public function __construct() {
		$this->table      = new CustomTable();
		$this->store      = new SnippetStore();
		$this->loader     = new Loader( $this->store );
		$this->controller = new Snippets_Controller( $this->store, $this->loader );
	}

	// endregion

	// region GETTERS

	/**
	 * Returns the module name.
	 *
	 * @return  string
	 */
	public function get_name(): string {
		return __( 'Snippets', 'a8csp-atlantis' );
	}

	/**
	 * Returns the module description.
	 *
	 * @return  string
	 */
	public function get_description(): string {
		return __( 'Runs signed remediation snippets pushed from OpsOasis / the team51 CLI, so fleet-wide hotfixes can be deployed without a plugin release. Snippets are verified, stored, and loaded as must-use plugins.', 'a8csp-atlantis' );
	}

	/**
	 * Exposes the snippet data store (for the WP-CLI command).
	 *
	 * @return  SnippetStore
	 */
	public function get_store(): SnippetStore {
		return $this->store;
	}

	/**
	 * Exposes the loader (for the WP-CLI command).
	 *
	 * @return  Loader
	 */
	public function get_loader(): Loader {
		return $this->loader;
	}

	// endregion

	// region METHODS

	/**
	 * Initializes the module components. Only runs when the module is active.
	 *
	 * @return  void
	 */
	protected function initialize(): void {
		$this->table->initialize();

		// Reconcile the filesystem to the database once the table is guaranteed
		// to exist (CustomTable hooks `maybe_create_table` at the default priority).
		add_action( 'init', array( $this->loader, 'reconcile' ), 20 );

		$this->controller->initialize();
	}

	// endregion
}
