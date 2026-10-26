</main>
<?php
$s = maxbit_theme_settings();
$solutions = get_posts( array( 'post_type' => 'mb_solution', 'post_status' => 'publish', 'numberposts' => 6, 'orderby' => 'menu_order title', 'order' => 'ASC', 'meta_query' => array( array( 'key' => '_mb_public_ok', 'value' => '1' ), array( 'key' => '_mb_test', 'compare' => 'NOT EXISTS' ) ) ) );
/* В колонке «Компания» — только страницы компании: решения и юридические страницы показаны в других колонках. */
add_filter( 'wp_nav_menu_objects', function ( array $items, $args ) {
	if ( 'footer' !== ( $args->theme_location ?? '' ) ) {
		return $items;
	}
	$drop = array( 'bitrix24-dlya-proizvodstva', 'politika-obrabotki-personalnyh-dannyh', 'soglasie-na-obrabotku-personalnyh-dannyh' );
	return array_values( array_filter( $items, function ( $i ) use ( $drop ) {
		if ( 'mb_solution' === $i->object ) {
			return false;
		}
		return ! ( 'page' === $i->object && in_array( get_post_field( 'post_name', (int) $i->object_id ), $drop, true ) );
	} ) );
}, 30, 2 );
$b24 = get_page_by_path( 'tekhnologii/bitrix24-dlya-proizvodstva' );
$posts_page = (int) get_option( 'page_for_posts' );
?>
<footer class="mb-footer">
	<div class="mb-container mb-footer__inner">
		<div class="mb-footer__brand">
			<?php echo maxbit_brand_svg( 'v2/MAXBIT_compact_dark.svg' ); ?>
			<p class="mb-footer__tagline">Ваша команда цифрового развития</p>
			<p>Наводим порядок в заказах и процессах производственных компаний: разбираемся в задаче, проектируем процесс, внедряем изменения в Битрикс24, 1С и других системах, проверяем результат. Работаем по всей России.</p>
		</div>
		<div class="mb-footer__col">
			<p class="mb-footer__col-title">Решения</p>
			<ul class="mb-footer__menu">
				<?php foreach ( $solutions as $sp ) : ?><li><a href="<?php echo esc_url( get_permalink( $sp ) ); ?>"><?php echo esc_html( get_the_title( $sp ) ); ?></a></li><?php endforeach; ?>
				<?php if ( $b24 && 'publish' === $b24->post_status ) : ?><li><a href="<?php echo esc_url( get_permalink( $b24 ) ); ?>"><?php echo esc_html( get_the_title( $b24 ) ); ?></a></li><?php endif; ?>
				<li><a href="<?php echo esc_url( get_post_type_archive_link( 'mb_solution' ) ); ?>">Все решения ››</a></li>
			</ul>
		</div>
		<div class="mb-footer__col">
			<p class="mb-footer__col-title">Компания</p>
			<?php
			wp_nav_menu( array(
				'theme_location' => 'footer',
				'container'      => false,
				'menu_class'     => 'mb-footer__menu',
				'depth'          => 1,
				'fallback_cb'    => function () use ( $posts_page ) {
					echo '<ul class="mb-footer__menu">';
					foreach ( array( 'kak-rabotaem' => 'Как работаем', 'o-kompanii' => 'О компании' ) as $slug => $label ) {
						$pg = get_page_by_path( $slug );
						if ( $pg && 'publish' === $pg->post_status ) {
							echo '<li><a href="' . esc_url( get_permalink( $pg ) ) . '">' . esc_html( $label ) . '</a></li>';
						}
					}
					if ( $posts_page ) {
						echo '<li><a href="' . esc_url( get_permalink( $posts_page ) ) . '">Практика</a></li>';
					}
					echo '</ul>';
				},
			) );
			?>
		</div>
		<div class="mb-footer__col">
			<p class="mb-footer__col-title">Связь</p>
			<div class="mb-footer__contacts">
				<?php if ( ! empty( $s['email'] ) ) : ?><a href="mailto:<?php echo esc_attr( $s['email'] ); ?>" data-contact="email"><?php echo esc_html( $s['email'] ); ?></a><?php endif; ?>
				<?php if ( ! empty( $s['city'] ) ) : ?><span><?php echo esc_html( $s['city'] ); ?></span><?php endif; ?>
				<?php if ( empty( $s['email'] ) && empty( $s['tg_bot_url'] ) && empty( $s['max_bot_url'] ) ) : ?><a href="<?php echo esc_url( maxbit_cta_url() ); ?>">Написать через форму ››</a><?php endif; ?>
			</div>
			<?php echo maxbit_theme_msg( 'bots', true ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php if ( ! empty( $s['tg_channel_url'] ) || ! empty( $s['max_channel_url'] ) ) : ?>
			<p class="mb-footer__col-title" style="margin-top: 14px">Каналы с материалами</p>
			<?php echo maxbit_theme_msg( 'channels', true ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
			<?php endif; ?>
		</div>
	</div>
	<div class="mb-container mb-footer__bottom">
		<p>© <?php echo esc_html( gmdate( 'Y' ) ); ?> MAXBIT<?php if ( ! empty( $s['city'] ) ) : ?> · <?php echo esc_html( $s['city'] ); ?><?php endif; ?></p>
		<?php if ( function_exists( 'maxbit_legal_url' ) ) : ?>
		<p class="mb-footer__legal"><a href="<?php echo esc_url( maxbit_legal_url( 'policy' ) ); ?>">Политика обработки персональных данных</a><a href="<?php echo esc_url( maxbit_legal_url( 'consent' ) ); ?>">Согласие на обработку данных</a><?php if ( function_exists( 'maxbit_cookie_consent_enabled' ) && maxbit_cookie_consent_enabled() ) : ?><button type="button" data-mb-cookie-open>Настройки cookie</button><?php endif; ?></p>
		<?php endif; ?>
		<?php if ( function_exists( 'maxbit_requisites_line' ) && ! empty( $s['show_requisites'] ) && '1' === (string) $s['show_requisites'] && maxbit_requisites_line() ) : ?>
		<p class="mb-footer__requisites"><?php echo esc_html( maxbit_requisites_line() ); ?></p>
		<?php endif; ?>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
