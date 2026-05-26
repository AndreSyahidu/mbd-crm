<?php
/**
 * Lead list view.
 *
 * @package MBD\CRM
 *
 * @var array<int, object>  $leads      Lead rows.
 * @var array<int, string>  $names      Assignee id => display name.
 * @var bool                $can_create Whether the user may create leads.
 * @var string              $scope      'all' or 'own'.
 * @var string              $notice     Flash notice HTML.
 * @var string              $new_url      URL of the create form.
 * @var int                 $highlight_id Newly created lead ID to highlight.
 */

use MBD\CRM\Frontend\Components;
use MBD\CRM\Leads\Options;
use MBD\CRM\Leads\Permissions;
use MBD\CRM\Leads\Sla;
use MBD\CRM\Leads\Stage;
use MBD\CRM\Router;
use MBD\CRM\Scoring\Scorer;

defined( 'ABSPATH' ) || exit;
?>
<div class="mbd-page">
	<?php echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

	<div class="mbd-toolbar">
		<p class="mbd-page__lead">
			<?php
			echo 'own' === $scope
				? esc_html__( 'Leads assigned to or created by you.', 'mbd-crm' )
				: esc_html__( 'All leads across the team.', 'mbd-crm' );
			?>
		</p>
		<?php if ( $can_create ) : ?>
			<a class="mbd-btn mbd-btn--primary" href="<?php echo esc_url( $new_url ); ?>">
				<span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
				<?php esc_html_e( 'New Lead', 'mbd-crm' ); ?>
			</a>
		<?php endif; ?>
	</div>

	<?php if ( empty( $leads ) ) : ?>
		<?php
		echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			__( 'No leads yet', 'mbd-crm' ),
			$can_create
				? __( 'Create your first lead to get started.', 'mbd-crm' )
				: __( 'Leads will appear here once they are created.', 'mbd-crm' ),
			'dashicons-groups'
		);
		?>
	<?php else : ?>
		<div class="mbd-table-wrap">
			<table class="mbd-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Lead', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Score', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Status', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Quality', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Stage age', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Assigned', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'Next follow-up', 'mbd-crm' ); ?></th>
						<th><?php esc_html_e( 'SLA', 'mbd-crm' ); ?></th>
						<th class="mbd-table__actions"><?php esc_html_e( 'Actions', 'mbd-crm' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach ( $leads as $lead ) :
						$view_url = Router::screen_url( 'leads' ) . '?lead=' . (int) $lead->id;
						$edit_url = Router::screen_url( 'leads' ) . '?action=edit&lead=' . (int) $lead->id;
						$sla       = Sla::display( $lead );
						$assignee  = $names[ (int) $lead->assigned_to ] ?? __( 'Unassigned', 'mbd-crm' );
						$mbd_stale = Stage::staleness( $lead );
						?>
						<tr class="<?php echo (int) $highlight_id === (int) $lead->id ? 'is-new' : ''; ?>">
							<td data-label="<?php esc_attr_e( 'Lead', 'mbd-crm' ); ?>">
								<a class="mbd-table__primary" href="<?php echo esc_url( $view_url ); ?>">
									<?php echo esc_html( '' !== $lead->name ? $lead->name : __( '(no name)', 'mbd-crm' ) ); ?>
								</a>
							</td>
							<td data-label="<?php esc_attr_e( 'Score', 'mbd-crm' ); ?>">
								<strong><?php echo (int) ( $lead->score ?? 0 ); ?></strong>
								<?php echo Components::chip( Scorer::temperature_label( (string) ( $lead->temperature ?? 'low_fit' ) ), Scorer::temperature_variant( (string) ( $lead->temperature ?? 'low_fit' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Status', 'mbd-crm' ); ?>"><?php echo Components::chip( Options::label( 'statuses', $lead->status ), Options::status_variant( $lead->status ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td data-label="<?php esc_attr_e( 'Quality', 'mbd-crm' ); ?>"><?php echo Components::chip( $lead->quality, Options::quality_variant( $lead->quality ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td data-label="<?php esc_attr_e( 'Stage age', 'mbd-crm' ); ?>" title="<?php echo esc_attr( $mbd_stale['stale'] ? $mbd_stale['reason'] : '' ); ?>"><?php echo Components::chip( Stage::aging_label( $lead ), $mbd_stale['stale'] ? 'danger' : 'muted' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td data-label="<?php esc_attr_e( 'Assigned', 'mbd-crm' ); ?>"><?php echo esc_html( $assignee ); ?></td>
							<td data-label="<?php esc_attr_e( 'Next follow-up', 'mbd-crm' ); ?>"><?php echo esc_html( $lead->next_follow_up ? $lead->next_follow_up : '—' ); ?></td>
							<td data-label="<?php esc_attr_e( 'SLA', 'mbd-crm' ); ?>"><?php echo Components::chip( $sla['label'], $sla['variant'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
							<td data-label="<?php esc_attr_e( 'Actions', 'mbd-crm' ); ?>" class="mbd-table__actions">
								<a href="<?php echo esc_url( $view_url ); ?>"><?php esc_html_e( 'View', 'mbd-crm' ); ?></a>
								<?php if ( Permissions::can_edit( $lead ) ) : ?>
									<a href="<?php echo esc_url( $edit_url ); ?>"><?php esc_html_e( 'Edit', 'mbd-crm' ); ?></a>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</div>
