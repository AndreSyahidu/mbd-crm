<?php
/**
 * Duplicate detection & merge module bootstrap.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Duplicates;

defined( 'ABSPATH' ) || exit;

/**
 * Wires the duplicate panel, review screen, and merge handler. Uses the
 * leads table only; it has no schema of its own.
 */
class Module {

	/**
	 * Duplicate/merge handler.
	 *
	 * @var Controller
	 */
	private Controller $controller;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->controller = new Controller();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->controller->register();
	}
}
