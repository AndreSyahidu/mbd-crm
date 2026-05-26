<?php
/**
 * CRM application shell.
 *
 * @package MBD\CRM
 *
 * @var string                                                                             $app_name     Application name.
 * @var string                                                                             $title        Active screen title.
 * @var string                                                                             $current_slug Active screen slug.
 * @var array|null                                                                         $current      Active screen meta.
 * @var array<int, array{slug:string,label:string,icon:string,url:string,active:bool}>     $nav          Navigation items.
 * @var string                                                                             $content      Pre-rendered, trusted screen HTML.
 * @var string                                                                             $user_name    Current user's display name.
 */

defined( 'ABSPATH' ) || exit;

$mbd_icon = isset( $current['icon'] ) ? $current['icon'] : 'dashicons-screenoptions';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex,nofollow" />
	<title><?php echo esc_html( $title . ' · ' . $app_name ); ?></title>
	<?php wp_head(); ?>
</head>
<body class="mbd-crm mbd-screen--<?php echo esc_attr( $current_slug ); ?>">
<div class="mbd-app" data-mbd-app>
	<a class="mbd-skip-link" href="#mbd-content"><?php esc_html_e( 'Skip to content', 'mbd-crm' ); ?></a>

	<aside class="mbd-sidebar" id="mbd-sidebar">
		<div class="mbd-brand">
			<span class="mbd-brand__mark dashicons dashicons-groups" aria-hidden="true"></span>
			<span class="mbd-brand__name"><?php echo esc_html( $app_name ); ?></span>
		</div>

		<nav class="mbd-nav" aria-label="<?php esc_attr_e( 'CRM sections', 'mbd-crm' ); ?>">
			<ul class="mbd-nav__list">
				<?php foreach ( $nav as $item ) : ?>
					<li class="mbd-nav__item">
						<a
							class="mbd-nav__link<?php echo $item['active'] ? ' is-active' : ''; ?>"
							href="<?php echo esc_url( $item['url'] ); ?>"
							<?php echo $item['active'] ? ' aria-current="page"' : ''; ?>
							data-mbd-nav-link
						>
							<span class="mbd-nav__icon dashicons <?php echo esc_attr( $item['icon'] ); ?>" aria-hidden="true"></span>
							<span class="mbd-nav__label"><?php echo esc_html( $item['label'] ); ?></span>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>
	</aside>

	<div class="mbd-backdrop" data-mbd-close hidden></div>

	<div class="mbd-main">
		<header class="mbd-topbar">
			<button
				type="button"
				class="mbd-nav-toggle"
				aria-controls="mbd-sidebar"
				aria-expanded="false"
				data-mbd-toggle
			>
				<span class="dashicons dashicons-menu" aria-hidden="true"></span>
				<span class="screen-reader-text"><?php esc_html_e( 'Toggle navigation', 'mbd-crm' ); ?></span>
			</button>

			<h1 class="mbd-topbar__title">
				<span class="mbd-topbar__icon dashicons <?php echo esc_attr( $mbd_icon ); ?>" aria-hidden="true"></span>
				<?php echo esc_html( $title ); ?>
			</h1>

			<div class="mbd-topbar__user" title="<?php echo esc_attr( $user_name ); ?>">
				<span class="dashicons dashicons-admin-users" aria-hidden="true"></span>
				<span class="mbd-topbar__user-name"><?php echo esc_html( $user_name ); ?></span>
			</div>
		</header>

		<main class="mbd-content" id="mbd-content" role="main">
			<?php
			// $content is built from trusted templates and the Components
			// helper, which escape their own inputs.
			echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			?>
		</main>
	</div>
</div>
<?php wp_footer(); ?>
</body>
</html>
