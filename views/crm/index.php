<?php
/**
 * Front-end CRM route placeholder.
 *
 * @package MBD\CRM
 *
 * @var string $title Page title.
 */

defined( 'ABSPATH' ) || exit;

$mbd_crm_title = isset( $title ) ? $title : __( 'MBD CRM', 'mbd-crm' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php echo esc_html( $mbd_crm_title ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="mbd-crm-app">
	<main class="mbd-crm-app__main" role="main">
		<h1><?php echo esc_html( $mbd_crm_title ); ?></h1>
		<p><?php esc_html_e( 'The CRM application will load here.', 'mbd-crm' ); ?></p>
	</main>
	<?php wp_footer(); ?>
</body>
</html>
