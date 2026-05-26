<?php
/**
 * Site Health integration.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Health;

use MBD\CRM\Router;

defined( 'ABSPATH' ) || exit;

/**
 * Adds an MBD CRM section to the WordPress Site Health info screen and
 * exposes a reusable status report for diagnostics.
 */
class HealthCheck {

	/**
	 * Hook into WordPress.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'debug_information', array( $this, 'add_debug_information' ) );
	}

	/**
	 * Append plugin diagnostics to the Site Health "Info" tab.
	 *
	 * @param array<string, mixed> $info Existing debug information.
	 * @return array<string, mixed>
	 */
	public function add_debug_information( array $info ): array {
		$report = $this->run();

		$fields = array();
		foreach ( $report as $key => $check ) {
			$fields[ $key ] = array(
				'label' => $check['label'],
				'value' => $check['ok']
					? __( 'OK', 'mbd-crm' )
					: __( 'Attention required', 'mbd-crm' ),
				'debug' => $check['detail'],
			);
		}

		$info['mbd_crm'] = array(
			'label'  => __( 'MBD CRM', 'mbd-crm' ),
			'fields' => $fields,
		);

		return $info;
	}

	/**
	 * Run the health checks and return a structured report.
	 *
	 * Each entry: [ 'label' => string, 'ok' => bool, 'detail' => string ].
	 *
	 * @return array<string, array{label: string, ok: bool, detail: string}>
	 */
	public function run(): array {
		return array(
			'version'      => array(
				'label'  => __( 'Version', 'mbd-crm' ),
				'ok'     => true,
				'detail' => MBD_CRM_VERSION,
			),
			'rewrite_rule' => array(
				'label'  => __( 'CRM route', 'mbd-crm' ),
				'ok'     => $this->is_route_registered(),
				'detail' => '/' . MBD_CRM_SLUG,
			),
		);
	}

	/**
	 * Whether the /crm rewrite rule is present.
	 *
	 * @return bool
	 */
	private function is_route_registered(): bool {
		$rules = get_option( 'rewrite_rules' );

		if ( ! is_array( $rules ) ) {
			return false;
		}

		foreach ( array_values( $rules ) as $target ) {
			if ( is_string( $target ) && false !== strpos( $target, Router::QUERY_VAR ) ) {
				return true;
			}
		}

		return false;
	}
}
