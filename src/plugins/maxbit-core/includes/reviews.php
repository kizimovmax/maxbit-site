<?php
defined( 'ABSPATH' ) || exit;

/**
 * Отзывы клиентов (ТЗ v4 §3.8, reviews/REVIEWS_FOR_CLAUDE.md).
 * Данные — опция maxbit_reviews (ставится комплектом содержания), изображения — uploads/maxbit-reviews/.
 * Карточка: клиент, редакционная тема (не цитата), миниатюра, «Смотреть отзыв». Сначала initial карточек,
 * «Показать все отзывы» раскрывает остальные здесь же. Просмотр — диалог <dialog>; без JS — ссылка на изображение.
 * Без рейтингов, звёзд и AggregateRating. Сканы не редактируются.
 */

function maxbit_reviews_data(): array {
	$d = get_option( 'maxbit_reviews', array() );
	return is_array( $d ) ? $d : array();
}

function maxbit_review_img( string $file ): array {
	$up   = wp_get_upload_dir();
	$file = basename( $file );
	$path = trailingslashit( $up['basedir'] ) . 'maxbit-reviews/' . $file;
	if ( ! is_file( $path ) ) {
		return array();
	}
	$size = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	return array( 'url' => trailingslashit( $up['baseurl'] ) . 'maxbit-reviews/' . $file, 'w' => $size[0] ?? 0, 'h' => $size[1] ?? 0 );
}

add_shortcode( 'maxbit_reviews', function ( $atts ) {
	$a = shortcode_atts( array( 'initial' => '3' ), $atts );
	$items = maxbit_reviews_data();
	if ( ! $items ) {
		return maxbit_can_preview() ? '<div class="mb-editor-note" role="note"><strong>Служебная заметка.</strong> Отзывы появятся после установки комплекта содержания v4.</div>' : '';
	}
	$initial = max( 1, (int) $a['initial'] );
	wp_enqueue_script( 'maxbit-reviews', MAXBIT_CORE_URL . 'assets/reviews.js', array(), MAXBIT_CORE_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	$cards = array();
	foreach ( $items as $i => $r ) {
		$docs = array();
		foreach ( (array) $r['docs'] as $d ) {
			$full  = maxbit_review_img( $d['full'] );
			$thumb = maxbit_review_img( $d['thumb'] );
			if ( $full && $thumb ) {
				$docs[] = array( 'title' => $d['title'], 'full' => $full, 'thumb' => $thumb );
			}
		}
		if ( ! $docs ) {
			continue;
		}
		$first = $docs[0];
		$alt   = ( 'Благодарственное письмо' === $first['title'] ? 'Благодарственное письмо компании ' : 'Отзыв компании ' ) . $r['client'];
		$data  = wp_json_encode( array_map( fn( $d ) => array( 't' => $d['title'], 'u' => $d['full']['url'], 'w' => $d['full']['w'], 'h' => $d['full']['h'] ), $docs ) );
		$more  = count( $docs ) > 1 ? ' · ' . count( $docs ) . ' документа' : '';
		$lazy  = $i >= $initial ? ' loading="lazy"' : ' loading="lazy"';
		$cards[] = '<article class="mb-review"' . ( $i >= $initial ? ' data-more' : '' ) . '>'
			. '<a class="mb-review__thumb" href="' . esc_url( $first['full']['url'] ) . '" data-review=\'' . esc_attr( $data ) . '\' data-client="' . esc_attr( $r['client'] ) . '" data-topic="' . esc_attr( $r['topic'] ) . '" aria-label="Смотреть отзыв: ' . esc_attr( $r['client'] ) . '"><img src="' . esc_url( $first['thumb']['url'] ) . '" width="' . (int) $first['thumb']['w'] . '" height="' . (int) $first['thumb']['h'] . '" alt="' . esc_attr( $alt ) . '"' . $lazy . ' decoding="async"></a>'
			. '<div class="mb-review__body"><p class="mb-review__label">' . esc_html( $first['title'] . $more ) . '</p><h3 class="mb-review__client">' . esc_html( $r['client'] ) . '</h3><p class="mb-review__topic">' . esc_html( $r['topic'] ) . '</p>'
			. '<p><a class="mb-btn mb-btn--link" href="' . esc_url( $first['full']['url'] ) . '" data-review=\'' . esc_attr( $data ) . '\' data-client="' . esc_attr( $r['client'] ) . '" data-topic="' . esc_attr( $r['topic'] ) . '">Смотреть отзыв</a></p></div></article>';
	}
	$hidden = max( 0, count( $cards ) - $initial );
	$out = '<div class="mb-reviews" data-initial="' . $initial . '"><div class="mb-reviews__grid">' . implode( '', $cards ) . '</div>';
	if ( $hidden ) {
		$out .= '<p class="mb-reviews__toggle-wrap"><button type="button" class="mb-btn mb-btn--secondary mb-reviews__toggle" aria-expanded="false" data-label-more="Показать все отзывы" data-label-less="Скрыть">Показать все отзывы</button></p>';
	}
	$out .= '<dialog class="mb-viewer" aria-labelledby="mb-viewer-title"><div class="mb-viewer__inner"><div class="mb-viewer__doc"><img alt="" src="data:," decoding="async"></div>'
		. '<div class="mb-viewer__side"><button type="button" class="mb-viewer__close" aria-label="Закрыть">✕</button><p class="mb-viewer__count"></p><h3 id="mb-viewer-title" class="mb-viewer__client"></h3><p class="mb-viewer__topic"></p>'
		. '<p class="mb-viewer__note">Тема — редакционная подпись по содержанию письма, не цитата.</p><p><a class="mb-btn mb-btn--link mb-viewer__open" href="#" target="_blank" rel="noopener">Открыть крупнее</a></p>'
		. '<div class="mb-viewer__nav"><button type="button" class="mb-btn mb-btn--secondary mb-viewer__prev">‹ Предыдущий</button><button type="button" class="mb-btn mb-btn--secondary mb-viewer__next">Следующий ›</button></div></div></div></dialog>';
	return $out . '</div>';
} );
