<?php
defined( 'ABSPATH' ) || exit;

/**
 * Статусы, компетенции и награды MAXBIT (решение владельца 27.10). Файлы — assets/certs/ (изображения, сделанные из PDF).
 * Документы выданы на прежнее название компании — «Департамент Бизнеса»; это поясняется рядом с ними.
 * Срок действия: после valid_to документ скрывается автоматически (статус не выдаётся за действующий).
 * Компетенция «Бизнес-процессы» (01.04.2021–01.04.2022) истекла и не публикуется.
 * Вывод: [maxbit_certificates service="bitrix24|wazzup" heading="…"], строка статуса для карточки сервиса — maxbit_cert_status_line().
 */

function maxbit_certificates( string $service = '' ): array {
	$all = array(
		array( 'id' => 'b24-platinum-2026', 'service' => 'bitrix24', 'kind' => 'Статус партнёра', 'title' => 'Платиновый партнёр 1С-Битрикс', 'short' => 'Платиновый партнёр Битрикс24', 'issuer' => '1С-Битрикс', 'valid_to' => '2026-11-01', 'note' => 'Действует до 01.11.2026' ),
		array( 'id' => 'b24-vibecode-2026', 'service' => 'bitrix24', 'kind' => 'Компетенция', 'title' => 'Компетенция «Вайбкод-разработка»', 'short' => 'компетенция «Вайбкод-разработка»', 'issuer' => '1С-Битрикс', 'valid_to' => '2027-09-17', 'note' => 'Действует до 17.09.2027' ),
		array( 'id' => 'wazzup-top-partner', 'service' => 'wazzup', 'kind' => 'Статус партнёра', 'title' => 'ТОП-партнёр Wazzup', 'short' => 'ТОП-партнёр Wazzup', 'issuer' => 'Wazzup', 'valid_to' => '', 'note' => '' ),
		array( 'id' => 'bitrix-awards-2022', 'service' => 'bitrix24', 'kind' => 'Награда', 'title' => 'Bitrix Awards 2022: 5 место «Лидер продаж»', 'short' => '', 'issuer' => '1С-Битрикс', 'valid_to' => '', 'note' => '«1С-Битрикс24» облачная версия, Сибирский федеральный округ' ),
	);
	$all   = (array) apply_filters( 'maxbit_certificates', $all );
	$today = wp_date( 'Y-m-d' );
	$out   = array();
	foreach ( $all as $c ) {
		if ( '' !== $c['valid_to'] && $c['valid_to'] < $today ) {
			continue; // истёк — не показываем
		}
		if ( $service && $service !== $c['service'] ) {
			continue;
		}
		$c['full']  = MAXBIT_CORE_URL . 'assets/certs/' . $c['id'] . '-full.webp';
		$c['thumb'] = MAXBIT_CORE_URL . 'assets/certs/' . $c['id'] . '-thumb.webp';
		$p          = MAXBIT_CORE_DIR . 'assets/certs/' . $c['id'] . '-full.webp';
		$s          = is_file( $p ) ? @getimagesize( $p ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
		$t          = is_file( MAXBIT_CORE_DIR . 'assets/certs/' . $c['id'] . '-thumb.webp' ) ? @getimagesize( MAXBIT_CORE_DIR . 'assets/certs/' . $c['id'] . '-thumb.webp' ) : false; // phpcs:ignore
		if ( ! $s || ! $t ) {
			continue;
		}
		$c['fw'] = $s[0]; $c['fh'] = $s[1]; $c['tw'] = $t[0]; $c['th'] = $t[1];
		$out[] = $c;
	}
	return $out;
}

/** Строка для карточки сервиса: «Платиновый партнёр Битрикс24 · компетенция «Вайбкод-разработка»». */
function maxbit_cert_status_line( string $service ): string {
	$parts = array_filter( array_map( fn( $c ) => $c['short'], maxbit_certificates( $service ) ) );
	return implode( ' · ', $parts );
}

/** Сетка документов с просмотром в окне (разметка и скрипт отзывов: .mb-reviews, reviews.js). */
add_shortcode( 'maxbit_certificates', function ( $atts ) {
	$a     = shortcode_atts( array( 'service' => '', 'heading' => '' ), $atts );
	$items = maxbit_certificates( (string) $a['service'] );
	if ( ! $items ) {
		return '';
	}
	wp_enqueue_script( 'maxbit-reviews', MAXBIT_CORE_URL . 'assets/reviews.js', array(), MAXBIT_CORE_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	$cards = '';
	foreach ( $items as $c ) {
		$data   = wp_json_encode( array( array( 't' => $c['kind'], 'u' => $c['full'], 'w' => $c['fw'], 'h' => $c['fh'] ) ) );
		$attr   = ' data-review=\'' . esc_attr( $data ) . '\' data-client="' . esc_attr( $c['title'] ) . '" data-topic="' . esc_attr( $c['issuer'] . ( $c['note'] ? ' · ' . $c['note'] : '' ) ) . '"';
		$cards .= '<article class="mb-review mb-cert">'
			. '<a class="mb-review__thumb" href="' . esc_url( $c['full'] ) . '"' . $attr . ' aria-label="Открыть документ: ' . esc_attr( $c['title'] ) . '"><img src="' . esc_url( $c['thumb'] ) . '" width="' . (int) $c['tw'] . '" height="' . (int) $c['th'] . '" alt="' . esc_attr( $c['title'] . ' — документ' ) . '" loading="lazy" decoding="async"></a>'
			. '<div class="mb-review__body"><p class="mb-review__label">' . esc_html( $c['kind'] ) . '</p><h3 class="mb-review__client">' . esc_html( $c['title'] ) . '</h3><p class="mb-review__topic">' . esc_html( $c['issuer'] . ( $c['note'] ? ' · ' . $c['note'] : '' ) ) . '</p>'
			. '<p><a class="mb-btn mb-btn--link" href="' . esc_url( $c['full'] ) . '"' . $attr . '>Смотреть документ</a></p></div></article>';
	}
	$out  = '<div class="mb-certs"' . ( '' === $a['service'] && $a['heading'] ? ' id="certificates"' : '' ) . '>' . ( $a['heading'] ? '<h2 class="wp-block-heading mb-certs__heading">' . esc_html( $a['heading'] ) . '</h2>' : '' );
	$out .= '<div class="mb-reviews mb-reviews--certs"><div class="mb-reviews__grid">' . $cards . '</div>';
	$out .= '<dialog class="mb-viewer" aria-labelledby="mb-viewer-cert"><div class="mb-viewer__inner"><div class="mb-viewer__doc"><img alt="" src="data:," decoding="async"></div>'
		. '<div class="mb-viewer__side"><button type="button" class="mb-viewer__close" aria-label="Закрыть">✕</button><p class="mb-viewer__count"></p><h3 id="mb-viewer-cert" class="mb-viewer__client"></h3><p class="mb-viewer__topic"></p>'
		. '<p class="mb-viewer__note">Документ выдан на прежнее название компании — «Департамент Бизнеса».</p><p><a class="mb-btn mb-btn--link mb-viewer__open" href="#" target="_blank" rel="noopener">Открыть крупнее</a></p>'
		. '<div class="mb-viewer__nav"><button type="button" class="mb-btn mb-btn--secondary mb-viewer__prev">‹ Предыдущий</button><button type="button" class="mb-btn mb-btn--secondary mb-viewer__next">Следующий ›</button></div></div></div></dialog></div>';
	$out .= '<p class="mb-small mb-certs__note">Документы выданы на прежнее название компании — «Департамент Бизнеса».</p></div>';
	return $out;
} );
