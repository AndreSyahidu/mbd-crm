<?php
/**
 * Deposit request handling and detail panel.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Deposit;

use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Processes deposit request / verify / reject / override actions and
 * renders the deposit panel on the lead detail page.
 */
class Controller {

	private const NONCE_ACTION = 'mbd_crm_deposit';
	private const NONCE_FIELD  = 'mbd_crm_depnonce';

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Deposit persistence.
	 *
	 * @var DepositRepository
	 */
	private DepositRepository $repo;

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
		$this->leads = new LeadRepository();
		$this->repo  = new DepositRepository();
		$this->view  = new View();
	}

	/**
	 * Handle a deposit action submitted from the leads route.
	 *
	 * @param string|null $result Result from earlier handlers.
	 * @param string      $action Submitted action.
	 * @return string|null
	 */
	public function handle( $result, string $action ) {
		$actions = array( 'request_deposit', 'verify_deposit', 'reject_deposit', 'override_deposit' );
		if ( ! in_array( $action, $actions, true ) ) {
			return $result;
		}

		$lead_id = isset( $_POST['lead_id'] ) ? absint( wp_unslash( $_POST['lead_id'] ) ) : 0;
		$lead    = $this->leads->find( $lead_id );

		if ( ! $lead ) {
			return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		$detail = Router::screen_url( 'leads' ) . '?lead=' . $lead_id;

		if ( 'request_deposit' === $action ) {
			$this->handle_request( $lead, $detail );
		}

		if ( 'override_deposit' === $action ) {
			// Rule: override requires capability.
			if ( ! Permissions::can_override() ) {
				return Components::blocked( __( 'You do not have permission to override the deposit gate.', 'mbd-crm' ) );
			}
			$this->handle_override( $lead, $detail );
		}

		// verify_deposit / reject_deposit (Finance).
		if ( ! Permissions::can_verify() ) {
			return Components::blocked( __( 'You do not have permission to verify deposits.', 'mbd-crm' ) );
		}

		$deposit = $this->repo->for_lead( $lead_id );
		if ( ! $deposit ) {
			$this->redirect( $detail );
		}

		if ( 'verify_deposit' === $action ) {
			$this->repo->verify( (int) $deposit->id, get_current_user_id() );
			$this->leads->set_stage( $lead_id, array( 'deposit_status' => 'valid' ) );
			$this->fire( $lead_id, $lead, (int) $deposit->id, 'verified', '' );
			$this->redirect( add_query_arg( 'dep', 'verified', $detail ) );
		}

		// reject_deposit. Rule: rejection requires a reason.
		$reason = $this->post_text( 'rejection_reason' );
		if ( '' === $reason ) {
			$this->redirect( add_query_arg( 'dep_error', 'reason', $detail ) );
		}

		$this->repo->reject( (int) $deposit->id, $reason );
		$this->leads->set_stage( $lead_id, array( 'deposit_status' => 'rejected' ) );
		$this->fire( $lead_id, $lead, (int) $deposit->id, 'rejected', $reason );
		$this->redirect( add_query_arg( 'dep', 'rejected', $detail ) );
	}

	/**
	 * Create or update the deposit request.
	 *
	 * @param object $lead   Lead row.
	 * @param string $detail Detail URL.
	 * @return void
	 */
	private function handle_request( object $lead, string $detail ): void {
		if ( ! Permissions::can_request( $lead ) ) {
			$this->redirect( $detail );
		}

		$required = $this->post_text( 'required_amount' );
		$paid     = $this->post_text( 'paid_amount' );

		foreach ( array( $required, $paid ) as $amount ) {
			if ( '' !== $amount && ( ! is_numeric( $amount ) || (float) $amount < 0 ) ) {
				$this->redirect( add_query_arg( 'dep_error', 'amount', $detail ) );
			}
		}

		$method = sanitize_key( $this->post_text( 'payment_method' ) );
		if ( '' !== $method && ! Options::is_method( $method ) ) {
			$method = 'other';
		}

		$data = array(
			'required_amount' => $required,
			'paid_amount'     => $paid,
			'payment_date'    => $this->post_text( 'payment_date' ),
			'payment_method'  => $method,
			'receipt_number'  => $this->post_text( 'receipt_number' ),
		);

		list( $attach_id, $attach_url ) = $this->upload_proof();

		$id    = $this->repo->request( (int) $lead->id, $data, $attach_id, $attach_url, get_current_user_id() );
		$event = $attach_id > 0 ? 'proof_uploaded' : 'requested';

		$this->fire( (int) $lead->id, $lead, $id, $event, '' );
		$this->redirect( add_query_arg( 'dep', 'requested', $detail ) );
	}

	/**
	 * Approve an owner/admin override.
	 *
	 * @param object $lead   Lead row.
	 * @param string $detail Detail URL.
	 * @return void
	 */
	private function handle_override( object $lead, string $detail ): void {
		// Rule: override requires a written reason (capability checked by caller).
		$reason = $this->post_text( 'override_reason' );
		if ( '' === $reason ) {
			$this->redirect( add_query_arg( 'dep_error', 'override_reason', $detail ) );
		}

		$deposit = $this->repo->for_lead( (int) $lead->id );
		if ( ! $deposit ) {
			// Allow an override even before a request exists by seeding one.
			$id = $this->repo->request( (int) $lead->id, $this->empty_request(), 0, '', get_current_user_id() );
		} else {
			$id = (int) $deposit->id;
		}

		$this->repo->set_override( $id, $reason );
		$this->leads->set_stage( (int) $lead->id, array( 'deposit_override' => 1 ) );
		$this->fire( (int) $lead->id, $lead, $id, 'override', $reason );
		$this->redirect( add_query_arg( 'dep', 'override', $detail ) );
	}

	/**
	 * Render the deposit panel for the lead detail sidebar.
	 *
	 * @param string $html Accumulated panel HTML.
	 * @param object $lead Lead row.
	 * @return string
	 */
	public function render_panel( string $html, object $lead ): string {
		$panel = $this->view->capture(
			'crm/deposit/panel',
			array(
				'lead'         => $lead,
				'deposit'      => $this->repo->for_lead( (int) $lead->id ),
				'can_request'  => Permissions::can_request( $lead ),
				'can_verify'   => Permissions::can_verify(),
				'can_override' => Permissions::can_override(),
				'can_plan'     => Gate::can_plan( $lead ),
				'nonce_field'  => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action'  => Router::screen_url( 'leads' ),
				'notice'       => $this->flash_notice(),
			)
		);

		return $html . $panel;
	}

	/**
	 * Fire the deposit-changed action with a fresh deposit row.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param object $lead    Lead row.
	 * @param int    $id      Deposit ID.
	 * @param string $event   Event key.
	 * @param string $reason  Reason (rejection/override).
	 * @return void
	 */
	private function fire( int $lead_id, object $lead, int $id, string $event, string $reason ): void {
		/**
		 * Fires when a deposit record changes.
		 *
		 * @param int    $lead_id Lead ID.
		 * @param object $lead    Lead row.
		 * @param object $deposit Deposit row.
		 * @param string $event   Event key.
		 * @param string $reason  Reason.
		 */
		do_action( 'mbd_crm_deposit_changed', $lead_id, $lead, $this->repo->find( $id ), $event, $reason );
	}

	/**
	 * Empty request payload (used when seeding a record for override).
	 *
	 * @return array<string, string>
	 */
	private function empty_request(): array {
		return array(
			'required_amount' => '',
			'paid_amount'     => '',
			'payment_date'    => '',
			'payment_method'  => '',
			'receipt_number'  => '',
		);
	}

	/**
	 * Handle an optional proof upload.
	 *
	 * @return array{0:int,1:string} [attachment_id, attachment_url].
	 */
	private function upload_proof(): array {
		if ( empty( $_FILES['proof']['name'] ) ) {
			return array( 0, '' );
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attach_id = media_handle_upload( 'proof', 0 );

		if ( is_wp_error( $attach_id ) ) {
			return array( 0, '' );
		}

		return array( (int) $attach_id, (string) wp_get_attachment_url( $attach_id ) );
	}

	/**
	 * Build a notice from the PRG redirect flags.
	 *
	 * @return string
	 */
	private function flash_notice(): string {
		if ( isset( $_GET['dep'] ) ) {
			$state = sanitize_key( wp_unslash( $_GET['dep'] ) );
			$map   = array(
				'requested' => array( __( 'Deposit request saved.', 'mbd-crm' ), 'success' ),
				'verified'  => array( __( 'Deposit verified. Planning is now unlocked.', 'mbd-crm' ), 'success' ),
				'rejected'  => array( __( 'Deposit rejected.', 'mbd-crm' ), 'warning' ),
				'override'  => array( __( 'Override approved. Planning is now unlocked.', 'mbd-crm' ), 'success' ),
			);
			if ( isset( $map[ $state ] ) ) {
				return Components::notice( $map[ $state ][0], $map[ $state ][1] );
			}
		}
		if ( isset( $_GET['dep_error'] ) ) {
			$code = sanitize_key( wp_unslash( $_GET['dep_error'] ) );
			if ( 'reason' === $code ) {
				return Components::notice( __( 'A reason is required to reject a deposit.', 'mbd-crm' ), 'danger' );
			}
			if ( 'override_reason' === $code ) {
				return Components::notice( __( 'An override requires a written reason.', 'mbd-crm' ), 'danger' );
			}
			return Components::notice( __( 'Enter valid amounts.', 'mbd-crm' ), 'danger' );
		}

		return '';
	}

	/**
	 * Read and unslash a scalar POST field as trimmed text.
	 *
	 * @param string $key POST key.
	 * @return string
	 */
	private function post_text( string $key ): string {
		return isset( $_POST[ $key ] ) ? trim( sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) ) : '';
	}

	/**
	 * Redirect and stop.
	 *
	 * @param string $url Destination URL.
	 * @return void
	 */
	private function redirect( string $url ): void {
		wp_safe_redirect( $url );
		exit;
	}
}
