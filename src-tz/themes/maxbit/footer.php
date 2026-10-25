</main>
<footer class="mb-footer">
	<div class="mb-container mb-footer__inner">
		<div class="mb-footer__brand">
			<?php echo maxbit_brand_svg( 'v2/MAXBIT_compact_dark.svg' ); ?>
			<p class="mb-footer__tagline">Ваша команда цифрового развития</p>
			<p>Внешняя команда цифрового развития производственных компаний: разбираемся в задачах, проектируем процессы, внедряем изменения в Битрикс24, 1С и других системах, проверяем результат. Работаем по всей России.</p>
		</div>
		<div class="mb-footer__nav">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'footer',
				'container'      => false,
				'menu_class'     => 'mb-footer__menu',
				'depth'          => 1,
				'fallback_cb'    => false,
			) );
			$s = function_exists( 'maxbit_get_settings' ) ? maxbit_get_settings() : array();
			$contacts = array();
			if ( ! empty( $s['phone'] ) ) {
				$contacts[] = '<a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $s['phone'] ) ) . '" data-contact="phone">' . esc_html( $s['phone'] ) . '</a>';
			}
			if ( ! empty( $s['email'] ) ) {
				$contacts[] = '<a href="mailto:' . esc_attr( $s['email'] ) . '" data-contact="email">' . esc_html( $s['email'] ) . '</a>';
			}
			if ( ! empty( $s['messenger_url'] ) ) {
				$contacts[] = '<a href="' . esc_url( $s['messenger_url'] ) . '" data-contact="messenger" rel="noopener">' . esc_html( $s['messenger'] ?: 'Мессенджер' ) . '</a>';
			}
			if ( $contacts ) {
				echo '<p class="mb-footer__contacts">' . implode( ' · ', $contacts ) . '</p>';
			} else {
				echo '<p class="mb-footer__contacts"><a href="' . esc_url( maxbit_cta_url() ) . '">Написать через форму</a></p>';
			}
			?>
		</div>
	</div>
	<div class="mb-container mb-footer__bottom">
		<p>© <?php echo esc_html( gmdate( 'Y' ) ); ?> MAXBIT</p>
	</div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
