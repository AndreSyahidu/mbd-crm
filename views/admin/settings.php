<?php
/**
 * Admin settings page template.
 *
 * @package MBD\CRM
 *
 * @var string $page_slug Settings page slug.
 * @var string $title     Page title.
 */

defined( 'ABSPATH' ) || exit;

$mbd_crm_page_slug = isset( $page_slug ) ? $page_slug : MBD_CRM_SLUG;
$mbd_crm_title     = isset( $title ) ? $title : __( 'MBD CRM', 'mbd-crm' );
?>
<div class="wrap mbd-crm-settings">
	<h1><?php echo esc_html( $mbd_crm_title ); ?></h1>

	<form action="options.php" method="post">
		<?php
		settings_fields( $mbd_crm_page_slug );
		do_settings_sections( $mbd_crm_page_slug );
		submit_button();
		?>
	</form>
</div>
