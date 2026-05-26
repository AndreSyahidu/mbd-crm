<?php
/**
 * Projects screen: won deals and their handoff status.
 *
 * @package MBD\CRM
 *
 * @var array<int, array{name:string,status:string,done:int,total:int,value:float,url:string}> $rows Handoff rows.
 */

use MBD\CRM\Dashboard\Formulas;
use MBD\CRM\Frontend\Components;

defined( 'ABSPATH' ) || exit;

$mbd_variant = static function ( string $status ): string {
	$map = array( 'completed' => 'success', 'ready' => 'info', 'in_progress' => 'warning', 'draft' => 'muted' );

	return $map[ $status ] ?? 'muted';
};
?>
<div class="mbd-page">
	<p class="mbd-page__lead"><?php esc_html_e( 'Won deals and their handoff to delivery.', 'mbd-crm' ); ?></p>

	<?php if ( empty( $rows ) ) : ?>
		<?php
		echo Components::empty_state( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			__( 'No projects yet', 'mbd-crm' ),
			__( 'Won deals appear here for handoff to the delivery team.', 'mbd-crm' ),
			'dashicons-hammer'
		);
		?>
	<?php else : ?>
		<table class="mbd-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Project', 'mbd-crm' ); ?></th>
					<th><?php esc_html_e( 'Status', 'mbd-crm' ); ?></th>
					<th><?php esc_html_e( 'Checklist', 'mbd-crm' ); ?></th>
					<th><?php esc_html_e( 'Value', 'mbd-crm' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><a class="mbd-table__primary" href="<?php echo esc_url( $row['url'] ); ?>"><?php echo esc_html( $row['name'] ); ?></a></td>
						<td><?php echo Components::chip( ucfirst( str_replace( '_', ' ', $row['status'] ) ), $mbd_variant( $row['status'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
						<td><?php echo esc_html( $row['done'] . '/' . $row['total'] ); ?></td>
						<td><?php echo esc_html( Formulas::idr( (float) $row['value'] ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
