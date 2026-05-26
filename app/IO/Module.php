<?php
/**
 * Import / Export module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\IO;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the CSV import/export admin page. Admin-only; no schema.
 */
class Module {

	/**
	 * Admin page and handlers.
	 *
	 * @var AdminPage
	 */
	private AdminPage $page;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->page = new AdminPage();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->page->register();
	}
}
