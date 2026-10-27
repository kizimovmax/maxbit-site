</main>
<?php
/* Подвал по карте навигации v4: Решения (4) · Компания (5) · Связь (боты, отдельно каналы). Телефона нет; почта — после проверки ящика. */
$mb_link = function ( string $path, string $label ) {
	$path = trim( $path, '/' );
	$ok = true;
	if ( 'resheniya' === $path || 'proekty' === $path ) {
		$ok = 'proekty' !== $path || ( function_exists( 'maxbit_has_public_projects' ) && maxbit_has_public_projects() );
	} elseif ( str_starts_with( $path, 'resheniya/' ) ) {
		$p = get_posts( array( 'post_type' => 'mb_solution', 'name' => substr( $path, 10 ), 'post_status' => 'publish', 'numberposts' => 1 ) );
		$ok = $p && ( ! function_exists( 'maxbit_is_publicly_visible' ) || maxbit_is_publicly_visible( $p[0]->ID ) );
	} elseif ( '' !== $path && 'praktika' !== $path ) {
		$p = get_page_by_path( $path );
		$ok = $p && 'publish' === $p->post_status;
	}
	return $ok ? '<li><a href="' . esc_url( home_url( '' === $path ? '/' : '/' . $path . '/' ) ) . '">' . esc_html( $label ) . '</a></li>' : '';
};
$bots     = maxbit_theme_msg( 'bots', true );
$channels = maxbit_theme_msg( 'channels', true );
?>
<footer class="mb-footer">
	<div class="mb-container mb-footer__inner">
		<div class="mb-footer__brand">
			<?php echo maxbit_brand_svg( 'v2/MAXBIT_compact_dark.svg' ); // phpcs:ignore ?>
			<p class="mb-footer__tagline">Ваша команда цифрового развития</p>
			<p>Внешняя команда цифрового развития производственных компаний. Работаем с клиентами по всей России.</p>
		</div>
		<nav class="mb-footer__col" aria-label="Решения">
			<p class="mb-footer__col-title">Решения</p>
			<ul class="mb-footer__menu">
				<?php echo $mb_link( 'resheniya/zakaz-pod-kontrolem', 'Управление заказами и исполнением' ) . $mb_link( 'bitrix24', 'Битрикс24: возможности и внедрение' ) . $mb_link( 'tekhnologii/bitrix24-dlya-proizvodstva', 'Битрикс24 для производства' ) . $mb_link( 'resheniya/integraciya-bitrix24-i-1c', 'Интеграция Битрикс24 и 1С' ) . ( function_exists( 'maxbit_tech_hub_active' ) && maxbit_tech_hub_active() ? $mb_link( 'tekhnologii', 'Сервисы и интеграции' ) : '' ); // phpcs:ignore ?>
			</ul>
		</nav>
		<nav class="mb-footer__col" aria-label="Компания">
			<p class="mb-footer__col-title">Компания</p>
			<ul class="mb-footer__menu">
				<?php echo $mb_link( '', 'Главная' ) . $mb_link( 'kak-rabotaem', 'Как работаем' ) . $mb_link( 'proekty', 'Проекты' ) . $mb_link( 'praktika', 'Практика' ) . $mb_link( 'o-kompanii', 'О компании' ); // phpcs:ignore ?>
			</ul>
		</nav>
		<div class="mb-footer__col">
			<p class="mb-footer__col-title">Связь</p>
			<?php echo $bots ?: '<p class="mb-footer__contacts"><a href="' . esc_url( home_url( '/#contact' ) ) . '">Оставить заявку ››</a></p>'; // phpcs:ignore ?>
			<?php if ( $channels ) : ?>
			<p class="mb-footer__col-title mb-footer__col-title--sub">Наши каналы</p>
			<?php echo $channels; // phpcs:ignore ?>
			<?php endif; ?>
		</div>
	</div>
	<div class="mb-container mb-footer__bottom">
		<p>© <?php echo esc_html( gmdate( 'Y' ) ); ?> MAXBIT</p>
		<?php if ( function_exists( 'maxbit_legal_url' ) ) : ?>
		<p class="mb-footer__legal"><a href="<?php echo esc_url( maxbit_legal_url( 'policy' ) ); ?>">Политика обработки персональных данных</a><a href="<?php echo esc_url( maxbit_legal_url( 'consent' ) ); ?>">Согласие на обработку данных</a><?php if ( function_exists( 'maxbit_cookie_consent_enabled' ) && maxbit_cookie_consent_enabled() ) : ?><button type="button" data-mb-cookie-open>Настройки cookie</button><?php endif; ?></p>
		<?php endif; ?>
		<?php $s = maxbit_theme_settings(); if ( function_exists( 'maxbit_requisites_line' ) && ! empty( $s['show_requisites'] ) && '1' === (string) $s['show_requisites'] && maxbit_requisites_line() ) : ?>
		<p class="mb-footer__requisites"><?php echo esc_html( maxbit_requisites_line() ); ?></p>
		<?php endif; ?>
		<p class="mb-footer__former">«Департамент бизнеса» — прежнее название компании MAXBIT. В отзывах сохранено название, использовавшееся на дату их написания.</p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
