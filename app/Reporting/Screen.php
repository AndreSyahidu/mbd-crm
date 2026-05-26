<?php
/**
 * Reports & analytics screen and CSV export.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Reporting;

use MBD\CRM\Dashboard\Period;
use MBD\CRM\IO\Csv;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Renders the manager-facing Reports screen (conversion funnel, win/loss,
 * sales performance, source effectiveness) and streams CSV exports of each
 * dataset. Read-only; reporting is gated to users who can view all leads.
 */
class Screen {

	private const NONCE_ACTION = 'mbd_crm_report_export';

	/**
	 * Template renderer.
	 *
	 * @var View
	 */
	private View $view;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->view = new View();
	}

	/**
	 * Register the screen and its export handler.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'mbd_crm_screens', array( $this, 'register_screen' ) );
		add_filter( 'mbd_crm_screen_content', array( $this, 'maybe_render' ), 20, 3 );
	}

	/**
	 * Add the Reports screen to the registry.
	 *
	 * @param array<string, array{label:string, icon:string, cap:string}> $screens Screens.
	 * @return array<string, array{label:string, icon:string, cap:string}>
	 */
	public function register_screen( array $screens ): array {
		$screens['reports'] = array(
			'label' => __( 'Reports', 'mbd-crm' ),
			'icon'  => 'dashicons-chart-bar',
			'cap'   => \MBD\CRM\Leads\Capabilities::VIEW_ALL_LEADS,
		);

		return $screens;
	}

	/**
	 * Provide content for the reports screen.
	 *
	 * @param string|null $content Existing content.
	 * @param string      $slug    Active screen slug.
	 * @param array|null  $meta    Screen meta.
	 * @return string|null
	 */
	public function maybe_render( $content, string $slug, $meta ) {
		unset( $meta );

		if ( 'reports' !== $slug ) {
			return $content;
		}

		$report = new Report(
			Permissions::can_view_all() ? 'all' : 'own',
			get_current_user_id(),
			Period::from_key( isset( $_GET['period'] ) ? sanitize_key( wp_unslash( $_GET['period'] ) ) : 'all' )
		);

		// CSV export short-circuits the screen render (verified nonce + cap).
		if ( isset( $_GET['export'] ) ) {
			$this->maybe_export( sanitize_key( wp_unslash( $_GET['export'] ) ), $report );
		}

		$base = Router::screen_url( 'reports' ) . '?period=' . $report->period()->key;

		return $this->view->capture(
			'crm/reports/screen',
			array(
				'period'      => $report->period(),
				'periods'     => Period::presets(),
				'period_base' => Router::screen_url( 'reports' ),
				'funnel'      => $report->funnel(),
				'win_loss'    => $report->win_loss(),
				'lost'        => $report->lost_reasons(),
				'owners'      => $report->by_owner(),
				'sources'     => $report->by_source(),
				'export_base' => $base . '&_wpnonce=' . wp_create_nonce( self::NONCE_ACTION ),
			)
		);
	}

	/**
	 * Stream a dataset as CSV when the export nonce is valid.
	 *
	 * @param string $dataset Dataset key.
	 * @param Report $report  Report service.
	 * @return void
	 */
	private function maybe_export( string $dataset, Report $report ): void {
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return; // Fall through to the normal screen render.
		}

		$suffix = $report->period()->key;

		switch ( $dataset ) {
			case 'funnel':
				$rows = array( array( __( 'Stage', 'mbd-crm' ), __( 'Count', 'mbd-crm' ), __( 'From previous %', 'mbd-crm' ), __( 'Of top %', 'mbd-crm' ) ) );
				foreach ( $report->funnel() as $r ) {
					$rows[] = array( $r['stage'], $r['count'], $r['from_prev'], $r['of_top'] );
				}
				Csv::send_download( 'mbd-funnel-' . $suffix . '.csv', $rows );
				break;
			case 'owners':
				$rows = array( array( __( 'Owner', 'mbd-crm' ), __( 'Leads', 'mbd-crm' ), __( 'Qualified', 'mbd-crm' ), __( 'Won', 'mbd-crm' ), __( 'Win rate %', 'mbd-crm' ), __( 'Won value', 'mbd-crm' ) ) );
				foreach ( $report->by_owner() as $r ) {
					$rows[] = array( $r['owner'], $r['leads'], $r['qualified'], $r['won'], $r['win_rate'], round( $r['value'] ) );
				}
				Csv::send_download( 'mbd-sales-performance-' . $suffix . '.csv', $rows );
				break;
			case 'sources':
				$rows = array( array( __( 'Source', 'mbd-crm' ), __( 'Leads', 'mbd-crm' ), __( 'Won', 'mbd-crm' ), __( 'Conversion %', 'mbd-crm' ) ) );
				foreach ( $report->by_source() as $r ) {
					$rows[] = array( $r['source'], $r['leads'], $r['won'], $r['conversion'] );
				}
				Csv::send_download( 'mbd-source-effectiveness-' . $suffix . '.csv', $rows );
				break;
			case 'winloss':
				$rows = array( array( __( 'Metric', 'mbd-crm' ), __( 'Value', 'mbd-crm' ) ) );
				foreach ( $report->win_loss() as $label => $value ) {
					$rows[] = array( $label, $value );
				}
				Csv::send_download( 'mbd-win-loss-' . $suffix . '.csv', $rows );
				break;
		}
	}
}
