<?php
/**
 * Lightweight template renderer.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM;

defined( 'ABSPATH' ) || exit;

/**
 * Renders PHP templates stored under the plugin's views/ directory.
 */
class View {

	/**
	 * Absolute path to the views directory (with trailing slash).
	 *
	 * @var string
	 */
	private string $base_path;

	/**
	 * Constructor.
	 *
	 * @param string|null $base_path Optional override for the views directory.
	 */
	public function __construct( ?string $base_path = null ) {
		$this->base_path = $base_path ?? MBD_CRM_PATH . 'views/';
	}

	/**
	 * Render a template to the output buffer.
	 *
	 * @param string               $template Template name relative to views/, without extension.
	 * @param array<string, mixed> $data     Variables exposed to the template.
	 * @return void
	 */
	public function render( string $template, array $data = array() ): void {
		echo $this->capture( $template, $data ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Render a template and return it as a string.
	 *
	 * @param string               $template Template name relative to views/, without extension.
	 * @param array<string, mixed> $data     Variables exposed to the template.
	 * @return string
	 */
	public function capture( string $template, array $data = array() ): string {
		$file = $this->resolve( $template );

		if ( null === $file ) {
			return '';
		}

		ob_start();

		// Expose data as named variables inside the template scope.
		extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
		require $file;

		return (string) ob_get_clean();
	}

	/**
	 * Resolve a template name to a readable file path.
	 *
	 * Guards against directory traversal so only files inside the
	 * views directory can be loaded.
	 *
	 * @param string $template Template name relative to views/, without extension.
	 * @return string|null Absolute path, or null when not found.
	 */
	private function resolve( string $template ): ?string {
		$template = ltrim( str_replace( array( '..', "\0" ), '', $template ), '/' );
		$file     = $this->base_path . $template . '.php';

		return is_readable( $file ) ? $file : null;
	}
}
