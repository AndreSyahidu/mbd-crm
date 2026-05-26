<?php
/**
 * Offer versioning, discount control, and the offer panel.
 *
 * @package MBD\CRM
 */

namespace MBD\CRM\Offers;

use MBD\CRM\Approval\Gate;
use MBD\CRM\Closing\ClosingRepository;
use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\Audit;
use MBD\CRM\Leads\Capabilities;
use MBD\CRM\Leads\LeadRepository;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Router;
use MBD\CRM\View;

defined( 'ABSPATH' ) || exit;

/**
 * Handles the offer lifecycle (create version, approve/reject discount, send,
 * accept/decline) and renders the offer panel on the lead detail page.
 *
 * Discount control: an offer whose effective discount exceeds the configured
 * threshold is parked in 'pending_approval' and cannot be sent until an
 * Owner/Admin approves it. Offers within authority go straight to 'draft'.
 */
class Controller {

	private const NONCE_ACTION = 'mbd_crm_offer';
	private const NONCE_FIELD  = 'mbd_crm_ofnonce';

	/**
	 * Lead persistence.
	 *
	 * @var LeadRepository
	 */
	private LeadRepository $leads;

	/**
	 * Offer persistence.
	 *
	 * @var OfferRepository
	 */
	private OfferRepository $repo;

	/**
	 * Closing persistence (for proposal/value sync).
	 *
	 * @var ClosingRepository
	 */
	private ClosingRepository $closings;

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
		$this->leads    = new LeadRepository();
		$this->repo     = new OfferRepository();
		$this->closings = new ClosingRepository();
		$this->view     = new View();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_filter( 'mbd_crm_leads_post_action', array( $this, 'handle' ), 10, 2 );
		add_filter( 'mbd_crm_lead_detail_panels', array( $this, 'render_panel' ), 65, 2 );
	}

	/**
	 * Handle an offer action submitted from the leads route.
	 *
	 * @param string|null $result Result from earlier handlers.
	 * @param string      $action Submitted action.
	 * @return string|null
	 */
	public function handle( $result, string $action ) {
		$actions = array( 'create_offer', 'approve_offer', 'reject_offer', 'send_offer', 'accept_offer', 'decline_offer' );
		if ( ! in_array( $action, $actions, true ) ) {
			return $result;
		}

		$lead_id = isset( $_POST['lead_id'] ) ? absint( wp_unslash( $_POST['lead_id'] ) ) : 0;
		$lead    = $this->leads->find( $lead_id );

		if ( ! $lead ) {
			return Components::error_state( __( 'Lead not found', 'mbd-crm' ), __( 'This lead no longer exists.', 'mbd-crm' ) );
		}

		check_admin_referer( self::NONCE_ACTION, self::NONCE_FIELD );

		// Rule: offers belong to the closing stage, which needs planning approved.
		if ( ! Gate::is_approved( $lead ) ) {
			return Components::blocked( __( 'Offers are blocked until planning is approved by the client.', 'mbd-crm' ) );
		}

		$is_discount_decision = in_array( $action, array( 'approve_offer', 'reject_offer' ), true );

		if ( $is_discount_decision ) {
			// Rule: only Owner/Admin may approve or reject an over-threshold discount.
			if ( ! current_user_can( Capabilities::APPROVE_DISCOUNT ) ) {
				return Components::blocked( __( 'You are not authorised to approve discounts.', 'mbd-crm' ) );
			}
		} elseif ( ! Permissions::can_edit( $lead ) ) {
			return Components::blocked( __( 'You do not have permission to manage offers for this lead.', 'mbd-crm' ) );
		}

		$detail = Router::screen_url( 'leads' ) . '?lead=' . $lead_id;

		switch ( $action ) {
			case 'create_offer':
				$this->create_offer( $lead, $detail );
				break;
			case 'approve_offer':
				$this->decide_discount( $lead, $detail, true );
				break;
			case 'reject_offer':
				$this->decide_discount( $lead, $detail, false );
				break;
			case 'send_offer':
				$this->send_offer( $lead, $detail );
				break;
			case 'accept_offer':
				$this->finalise_offer( $lead, $detail, true );
				break;
			case 'decline_offer':
				$this->finalise_offer( $lead, $detail, false );
				break;
		}

		$this->redirect( $detail );
	}

	/**
	 * Create a new offer version, applying the discount-approval gate.
	 *
	 * @param object $lead   Lead row.
	 * @param string $detail Detail URL.
	 * @return void
	 */
	private function create_offer( object $lead, string $detail ): void {
		$base = $this->post_text( 'base_price' );
		if ( '' === $base || ! is_numeric( $base ) || (float) $base <= 0 ) {
			$this->redirect( add_query_arg( 'of_error', 'base', $detail ) );
		}

		$type  = 'percent' === sanitize_key( $this->post_text( 'discount_type' ) ) ? 'percent' : 'amount';
		$value = $this->post_text( 'discount_value' );
		if ( '' !== $value && ( ! is_numeric( $value ) || (float) $value < 0 ) ) {
			$this->redirect( add_query_arg( 'of_error', 'discount', $detail ) );
		}

		$calc              = DiscountPolicy::compute( (float) $base, $type, (float) $value );
		$requires_approval = DiscountPolicy::requires_approval( $calc['percent'] );
		$status            = $requires_approval ? 'pending_approval' : 'draft';

		// Tie the offer to the lead's closing record (create lazily).
		$closing    = $this->closings->for_lead( (int) $lead->id );
		$closing_id = $closing ? (int) $closing->id : $this->closings->create( (int) $lead->id, get_current_user_id() );

		$this->repo->supersede_open( (int) $lead->id );

		$version  = $this->repo->next_version( (int) $lead->id );
		$offer_id = $this->repo->create(
			array(
				'lead_id'           => (int) $lead->id,
				'closing_id'        => $closing_id,
				'version'           => $version,
				'base_price'        => (float) $base,
				'discount_type'     => $type,
				'discount_value'    => (float) $value,
				'discount_percent'  => $calc['percent'],
				'final_value'       => $calc['final'],
				'valid_until'       => $this->post_text( 'valid_until' ),
				'scope'             => $this->post_textarea( 'scope' ),
				'status'            => $status,
				'approval_required' => $requires_approval ? 1 : 0,
				'created_by'        => get_current_user_id(),
			)
		);

		$this->audit( (int) $lead->id, 'created', $version );
		$this->fire( (int) $lead->id, $lead, $offer_id, 'created' );
		$this->redirect( add_query_arg( 'of', $requires_approval ? 'pending' : 'created', $detail ) );
	}

	/**
	 * Approve or reject a pending discount.
	 *
	 * @param object $lead    Lead row.
	 * @param string $detail  Detail URL.
	 * @param bool   $approve Whether to approve.
	 * @return void
	 */
	private function decide_discount( object $lead, string $detail, bool $approve ): void {
		$offer = $this->load_offer( $lead, $detail );

		if ( 'pending_approval' !== $offer->status ) {
			$this->redirect( add_query_arg( 'of_error', 'state', $detail ) );
		}

		if ( $approve ) {
			$this->repo->set_status(
				(int) $offer->id,
				'approved',
				array(
					'approved_by' => get_current_user_id(),
					'approved_at' => current_time( 'mysql' ),
				)
			);
			$this->audit( (int) $lead->id, 'approved', (int) $offer->version );
			$this->fire( (int) $lead->id, $lead, (int) $offer->id, 'approved' );
			$this->redirect( add_query_arg( 'of', 'approved', $detail ) );
		}

		// Rejection requires a reason.
		$reason = $this->post_text( 'decision_reason' );
		if ( '' === $reason ) {
			$this->redirect( add_query_arg( 'of_error', 'reason', $detail ) );
		}

		$this->repo->set_status(
			(int) $offer->id,
			'rejected',
			array(
				'approved_by'     => get_current_user_id(),
				'approved_at'     => current_time( 'mysql' ),
				'decision_reason' => $reason,
			)
		);
		$this->audit( (int) $lead->id, 'rejected', (int) $offer->version );
		$this->fire( (int) $lead->id, $lead, (int) $offer->id, 'rejected' );
		$this->redirect( add_query_arg( 'of', 'rejected', $detail ) );
	}

	/**
	 * Send an offer to the client, enforcing the discount gate.
	 *
	 * @param object $lead   Lead row.
	 * @param string $detail Detail URL.
	 * @return void
	 */
	private function send_offer( object $lead, string $detail ): void {
		$offer = $this->load_offer( $lead, $detail );

		// Rule: over-threshold discounts must be approved before sending.
		if ( ! in_array( $offer->status, array( 'draft', 'approved' ), true ) ) {
			$this->redirect( add_query_arg( 'of_error', 'send', $detail ) );
		}

		$this->repo->set_status( (int) $offer->id, 'sent' );

		// Sync the closing record's proposal flag and offered value.
		$closing_id = (int) $offer->closing_id;
		if ( $closing_id > 0 ) {
			$this->closings->update(
				$closing_id,
				array(
					'proposal_sent'    => 1,
					'proposal_sent_at' => current_time( 'mysql' ),
					'offered_value'    => (float) $offer->final_value,
					'status'           => 'negotiating',
				)
			);
		}
		$this->leads->set_stage( (int) $lead->id, array( 'closing_status' => 'negotiating' ) );

		$this->audit( (int) $lead->id, 'sent', (int) $offer->version );
		$this->fire( (int) $lead->id, $lead, (int) $offer->id, 'sent' );
		$this->redirect( add_query_arg( 'of', 'sent', $detail ) );
	}

	/**
	 * Accept or decline a sent offer.
	 *
	 * @param object $lead   Lead row.
	 * @param string $detail Detail URL.
	 * @param bool   $accept Whether the client accepted.
	 * @return void
	 */
	private function finalise_offer( object $lead, string $detail, bool $accept ): void {
		$offer = $this->load_offer( $lead, $detail );

		if ( 'sent' !== $offer->status ) {
			$this->redirect( add_query_arg( 'of_error', 'state', $detail ) );
		}

		if ( $accept ) {
			$this->repo->set_status( (int) $offer->id, 'accepted' );
			if ( (int) $offer->closing_id > 0 ) {
				$this->closings->update( (int) $offer->closing_id, array( 'final_value' => (float) $offer->final_value ) );
			}
			$this->audit( (int) $lead->id, 'accepted', (int) $offer->version );
			$this->fire( (int) $lead->id, $lead, (int) $offer->id, 'accepted' );
			$this->redirect( add_query_arg( 'of', 'accepted', $detail ) );
		}

		$reason = $this->post_text( 'decision_reason' );
		$this->repo->set_status( (int) $offer->id, 'rejected', array( 'decision_reason' => $reason ) );
		$this->audit( (int) $lead->id, 'declined', (int) $offer->version );
		$this->fire( (int) $lead->id, $lead, (int) $offer->id, 'declined' );
		$this->redirect( add_query_arg( 'of', 'declined', $detail ) );
	}

	/**
	 * Load the posted offer and confirm it belongs to the lead.
	 *
	 * @param object $lead   Lead row.
	 * @param string $detail Detail URL.
	 * @return object
	 */
	private function load_offer( object $lead, string $detail ): object {
		$offer_id = isset( $_POST['offer_id'] ) ? absint( wp_unslash( $_POST['offer_id'] ) ) : 0;
		$offer    = $this->repo->find( $offer_id );

		if ( ! $offer || (int) $offer->lead_id !== (int) $lead->id ) {
			$this->redirect( add_query_arg( 'of_error', 'missing', $detail ) );
		}

		return $offer;
	}

	/**
	 * Render the offer panel for the lead detail sidebar.
	 *
	 * @param string $html Accumulated panel HTML.
	 * @param object $lead Lead row.
	 * @return string
	 */
	public function render_panel( string $html, object $lead ): string {
		$panel = $this->view->capture(
			'crm/offers/panel',
			array(
				'lead'         => $lead,
				'current'      => $this->repo->current( (int) $lead->id ),
				'history'      => $this->repo->for_lead( (int) $lead->id ),
				'threshold'    => DiscountPolicy::threshold(),
				'approved'     => Gate::is_approved( $lead ),
				'can_edit'     => Permissions::can_edit( $lead ),
				'can_approve'  => current_user_can( Capabilities::APPROVE_DISCOUNT ),
				'nonce_field'  => wp_nonce_field( self::NONCE_ACTION, self::NONCE_FIELD, true, false ),
				'form_action'  => Router::screen_url( 'leads' ),
				'notice'       => $this->flash_notice(),
			)
		);

		return $html . $panel;
	}

	/**
	 * Record an offer audit entry.
	 *
	 * @param int    $lead_id Lead ID.
	 * @param string $event   Event key.
	 * @param int    $version Offer version.
	 * @return void
	 */
	private function audit( int $lead_id, string $event, int $version ): void {
		Audit::record(
			$lead_id,
			Audit::OFFER,
			array(
				'event'   => $event,
				'version' => $version,
			)
		);
	}

	/**
	 * Fire the offer-changed action.
	 *
	 * @param int    $lead_id  Lead ID.
	 * @param object $lead     Lead row.
	 * @param int    $offer_id Offer ID.
	 * @param string $event    Event key.
	 * @return void
	 */
	private function fire( int $lead_id, object $lead, int $offer_id, string $event ): void {
		/**
		 * Fires when an offer changes.
		 *
		 * @param int    $lead_id  Lead ID.
		 * @param object $lead     Lead row.
		 * @param object $offer    Offer row.
		 * @param string $event    Event key.
		 */
		do_action( 'mbd_crm_offer_changed', $lead_id, $lead, $this->repo->find( $offer_id ), $event );

		// Offers move commercial signals (deposit readiness, value); rescore.
		do_action( 'mbd_crm_lead_rescore', $lead_id );
	}

	/**
	 * Build a notice from the PRG redirect flags.
	 *
	 * @return string
	 */
	private function flash_notice(): string {
		if ( isset( $_GET['of'] ) ) {
			$state = sanitize_key( wp_unslash( $_GET['of'] ) );
			$map   = array(
				'created'  => array( __( 'Offer version created.', 'mbd-crm' ), 'success' ),
				'pending'  => array( __( 'Offer created — discount exceeds the threshold and needs approval before sending.', 'mbd-crm' ), 'warning' ),
				'approved' => array( __( 'Discount approved. The offer can now be sent.', 'mbd-crm' ), 'success' ),
				'rejected' => array( __( 'Discount rejected.', 'mbd-crm' ), 'warning' ),
				'sent'     => array( __( 'Offer sent to the client.', 'mbd-crm' ), 'success' ),
				'accepted' => array( __( 'Offer accepted.', 'mbd-crm' ), 'success' ),
				'declined' => array( __( 'Offer declined.', 'mbd-crm' ), 'warning' ),
			);
			if ( isset( $map[ $state ] ) ) {
				return Components::notice( $map[ $state ][0], $map[ $state ][1] );
			}
		}
		if ( isset( $_GET['of_error'] ) ) {
			$code = sanitize_key( wp_unslash( $_GET['of_error'] ) );
			$map  = array(
				'base'     => __( 'Enter a base price greater than zero.', 'mbd-crm' ),
				'discount' => __( 'Enter a valid discount value.', 'mbd-crm' ),
				'reason'   => __( 'A reason is required to reject the discount.', 'mbd-crm' ),
				'send'     => __( 'This offer cannot be sent until its discount is approved.', 'mbd-crm' ),
				'state'    => __( 'That action is not valid for the offer’s current status.', 'mbd-crm' ),
				'missing'  => __( 'Offer not found.', 'mbd-crm' ),
			);
			return Components::notice( $map[ $code ] ?? __( 'Please complete the required fields.', 'mbd-crm' ), 'danger' );
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
	 * Read and unslash a textarea POST field.
	 *
	 * @param string $key POST key.
	 * @return string
	 */
	private function post_textarea( string $key ): string {
		return isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
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
