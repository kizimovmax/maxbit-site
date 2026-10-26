<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="icon" href="<?php echo esc_url( get_theme_file_uri( 'assets/brand/favicon.svg' ) ); ?>" type="image/svg+xml">
<link rel="icon" href="<?php echo esc_url( get_theme_file_uri( 'assets/brand/icon-192.png' ) ); ?>" type="image/png" sizes="192x192">
<link rel="apple-touch-icon" href="<?php echo esc_url( get_theme_file_uri( 'assets/brand/apple-touch-icon.png' ) ); ?>">
<link rel="manifest" href="<?php echo esc_url( get_theme_file_uri( 'assets/brand/site.webmanifest' ) ); ?>">
<meta name="theme-color" content="#0F172A">
<link rel="preload" href="<?php echo esc_url( get_theme_file_uri( 'assets/fonts/golos-text-cyrillic.woff2' ) ); ?>" as="font" type="font/woff2" crossorigin>
<?php wp_head(); ?>
<noscript><style>.mb-nav{display:flex!important;position:static;flex-direction:column;align-items:stretch;background:#fff;color:#0F172A;padding:8px 0 16px}.mb-nav__menu a{color:#0F172A!important;font-size:1rem!important;padding:10px 0!important}.mb-nav__toggle{display:none!important}.mb-reveal{opacity:1!important;transform:none!important}</style></noscript>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="mb-skip" href="#main">Перейти к содержанию</a>
<?php if ( 'production' !== maxbit_theme_env() ) : ?>
<div class="mb-envbar" role="note"><?php echo 'local' === maxbit_theme_env() ? 'Локальная тестовая среда' : 'Тестовый стенд'; ?> (<?php echo esc_html( maxbit_theme_env() ); ?>). Сайт не опубликован, обращения не отправляются MAXBIT.</div>
<?php endif; ?>
<header class="mb-header">
	<div class="mb-container mb-header__inner">
		<a class="mb-logo" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="MAXBIT — Ваша команда цифрового развития, на главную">
			<span class="mb-logo__full"><?php echo maxbit_brand_svg( 'v2/MAXBIT_compact_light.svg' ); ?><span class="mb-logo__tagline">Ваша команда цифрового развития</span></span>
			<span class="mb-logo__mark"><?php echo maxbit_brand_svg( 'v2/MAXBIT_compact_light.svg' ); ?></span>
			<span class="mb-logo__mark mb-logo__mark--dark" aria-hidden="true"><?php echo maxbit_brand_svg( 'v2/MAXBIT_compact_dark.svg' ); ?></span>
		</a>
		<a class="mb-btn mb-btn--primary mb-btn--small mb-header__cta-mobile" href="<?php echo esc_url( maxbit_cta_url() ); ?>">Обсудить</a>
		<button class="mb-nav__toggle" aria-expanded="false" aria-controls="mb-menu" type="button"><span class="mb-nav__bar" aria-hidden="true"></span><span class="mb-nav__label">Меню</span></button>
		<nav class="mb-nav" id="mb-menu" aria-label="Основное меню">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'mb-nav__menu',
				'depth'          => 1,
				'fallback_cb'    => false,
			) );
			?>
			<a class="mb-btn mb-btn--primary mb-nav__cta" href="<?php echo esc_url( maxbit_cta_url() ); ?>">Обсудить задачу за 30 минут</a>
			<?php
			$nav_contacts = array();
			foreach ( maxbit_theme_links( 'bots' ) as $l ) {
				$nav_contacts[] = '<a href="' . esc_url( $l['url'] ) . '" data-event="' . esc_attr( $l['event'] ) . '"' . ( str_starts_with( $l['url'], 'mailto:' ) ? '' : ' target="_blank" rel="noopener"' ) . '>' . esc_html( $l['label'] ) . ' ››</a>';
			}
			if ( $nav_contacts ) {
				echo '<div class="mb-nav__contacts">' . implode( '', $nav_contacts ) . '</div>'; // phpcs:ignore
			}
			?>
		</nav>
	</div>
</header>
<main id="main" class="mb-main">
