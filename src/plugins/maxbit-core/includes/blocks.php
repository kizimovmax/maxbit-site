<?php
defined( 'ABSPATH' ) || exit;

/**
 * Шорткоды содержания v4 (0.5.0). Вывод нейтральный; тема может заменить разметку фильтрами
 * maxbit_messengers_html и maxbit_project_card_html.
 */

/** Ссылки на ботов / каналы из настроек. kind=bots|channels; style=buttons|inline; label — подпись слева. */
function maxbit_messenger_links( string $kind ): array {
	$s = maxbit_get_settings();
	$out = array();
	if ( 'channels' === $kind ) {
		if ( $s['tg_channel_url'] ) {
			$out[] = array( 'url' => $s['tg_channel_url'], 'label' => 'Канал в Telegram', 'short' => 'Telegram', 'icon' => 'tg', 'event' => 'telegram_channel_click' );
		}
		if ( $s['max_channel_url'] ) {
			$out[] = array( 'url' => $s['max_channel_url'], 'label' => 'Канал в MAX', 'short' => 'MAX', 'icon' => 'max', 'event' => 'max_channel_click' );
		}
		return $out;
	}
	if ( $s['tg_bot_url'] ) {
		$out[] = array( 'url' => $s['tg_bot_url'], 'label' => 'Написать в Telegram', 'short' => 'Telegram', 'icon' => 'tg', 'event' => 'telegram_bot_click' );
	}
	if ( $s['max_bot_url'] ) {
		$out[] = array( 'url' => $s['max_bot_url'], 'label' => 'Написать в MAX', 'short' => 'MAX', 'icon' => 'max', 'event' => 'max_bot_click' );
	}
	$mail = maxbit_public_email();
	if ( 'bots' === $kind && $mail ) {
		$out[] = array( 'url' => 'mailto:' . $mail, 'label' => $mail, 'short' => 'Почта', 'icon' => 'mail', 'event' => 'email_click' );
	}
	return $out;
}

function maxbit_messengers_html( string $kind = 'bots', string $style = 'buttons', string $label = '', bool $dark = false ): string {
	$links = maxbit_messenger_links( $kind );
	$custom = apply_filters( 'maxbit_messengers_html', null, $links, $kind, $style, $label, $dark );
	if ( is_string( $custom ) ) {
		return $custom;
	}
	if ( ! $links ) {
		return '';
	}
	$out = '<p class="mb-msg-plain">' . ( $label ? esc_html( $label ) . ' ' : '' );
	foreach ( $links as $l ) {
		$out .= '<a href="' . esc_url( $l['url'] ) . '" data-event="' . esc_attr( $l['event'] ) . '" target="_blank" rel="noopener">' . esc_html( $l['label'] ) . '</a> ';
	}
	return $out . '</p>';
}
add_shortcode( 'maxbit_messengers', function ( $atts ) {
	$a = shortcode_atts( array( 'kind' => 'bots', 'style' => 'buttons', 'label' => '' ), $atts );
	return maxbit_messengers_html( 'channels' === $a['kind'] ? 'channels' : 'bots', 'inline' === $a['style'] ? 'inline' : 'buttons', (string) $a['label'] );
} );

/** Проекты по slug (порядок сохраняется). Только видимые текущему посетителю. */
function maxbit_projects_by_slugs( array $slugs ): array {
	$out = array();
	foreach ( $slugs as $slug ) {
		$slug = sanitize_title( $slug );
		if ( ! $slug ) {
			continue;
		}
		$p = get_posts( array( 'post_type' => 'mb_project', 'name' => $slug, 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => 1, 'mb_include_closed' => true, 'suppress_filters' => false ) );
		if ( ! $p ) {
			continue;
		}
		$p = $p[0];
		if ( maxbit_is_publicly_visible( $p->ID ) || ( maxbit_can_preview() && 'trash' !== $p->post_status ) ) {
			$out[] = $p;
		}
	}
	return $out;
}

/** Все допущенные проекты по порядку (menu_order). */
function maxbit_public_projects( int $limit = -1, array $exclude = array() ): array {
	return get_posts( array(
		'post_type' => 'mb_project', 'post_status' => 'publish', 'numberposts' => $limit, 'orderby' => 'menu_order title', 'order' => 'ASC',
		'post__not_in' => $exclude, 'suppress_filters' => false,
		'meta_query' => array(
			array( 'key' => '_mb_public_ok', 'value' => '1' ),
			array( 'relation' => 'OR', array( 'key' => '_mb_is_sample', 'compare' => 'NOT EXISTS' ), array( 'key' => '_mb_is_sample', 'value' => '1', 'compare' => '!=' ) ),
		),
	) );
}

function maxbit_project_card_html( WP_Post $p, bool $featured = false ): string {
	$custom = apply_filters( 'maxbit_project_card_html', null, $p, $featured );
	if ( is_string( $custom ) ) {
		return $custom;
	}
	$client = get_post_meta( $p->ID, '_mb_pf_client', true );
	$title  = get_post_meta( $p->ID, '_mb_card_title', true ) ?: get_the_title( $p );
	$text   = get_post_meta( $p->ID, '_mb_card_text', true ) ?: get_the_excerpt( $p );
	return '<article class="mb-card mb-card--project"><p class="mb-card__label">' . esc_html( $client ) . '</p><h3 class="mb-card__title"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( $title ) . '</a></h3><p class="mb-card__text">' . esc_html( $text ) . '</p></article>';
}

add_shortcode( 'maxbit_projects_cards', function ( $atts ) {
	$a = shortcode_atts( array( 'ids' => '', 'heading' => '', 'columns' => '3' ), $atts );
	$posts = $a['ids'] ? maxbit_projects_by_slugs( explode( ',', $a['ids'] ) ) : maxbit_public_projects();
	if ( ! $posts ) {
		return '';
	}
	$cols = in_array( $a['columns'], array( '2', '3' ), true ) ? $a['columns'] : '3';
	$out = $a['heading'] ? '<h2 class="wp-block-heading mb-projects__heading">' . esc_html( $a['heading'] ) . '</h2>' : '';
	$out .= '<div class="mb-projects mb-grid mb-grid--' . $cols . '">';
	foreach ( $posts as $i => $p ) {
		$out .= maxbit_project_card_html( $p, 0 === $i && ! $a['heading'] );
	}
	return $out . '</div>';
} );

/** Карточки «Практики» на главной: чек-лист и статья (по одной), плюс приглашение к самодиагностике. */
add_shortcode( 'maxbit_practice_cards', function ( $atts ) {
	$a = shortcode_atts( array( 'diagnostic' => '0' ), $atts );
	$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 4, 'orderby' => 'date', 'order' => 'DESC' ) );
	$out = '<div class="mb-practice-cards">';
	foreach ( array_slice( $posts, 0, 2 ) as $p ) {
		$label = 'chek-list-peredacha-zakaza-v-proizvodstvo' === $p->post_name ? 'Чек-лист' : 'Статья';
		$out .= '<article class="mb-card mb-card--practice"><p class="mb-card__label">' . $label . '</p><h3 class="mb-card__title"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></h3>' . ( has_excerpt( $p ) ? '<p class="mb-card__text">' . esc_html( get_the_excerpt( $p ) ) . '</p>' : '' ) . '</article>';
	}
	if ( '1' === $a['diagnostic'] && shortcode_exists( 'maxbit_quiz' ) ) {
		$out .= '<article class="mb-card mb-card--diagnostic"><p class="mb-card__label">Самодиагностика · 3 минуты</p><h3 class="mb-card__title">Где теряются данные и договорённости на вашем участке</h3><p class="mb-card__text">7 вопросов, результат сразу на экране. Материалы для разбора — по ссылке после ввода контакта.</p><p><a class="mb-btn mb-btn--primary" href="#diagnostic" data-mb-open="diagnostic">Пройти самодиагностику</a></p></article>';
	}
	return $out . '</div>';
} );

/**
 * Тарифы лицензий Битрикс24 (ТЗ v4 §19.3): компонент готов, но пока цен нет (opt maxbit_b24_prices пуст),
 * посетителю не выводится ничего — ни пустой таблицы, ни кнопки. Администратору — служебная заметка.
 */
add_shortcode( 'maxbit_b24_prices', function () {
	$rows = get_option( 'maxbit_b24_prices', array() );
	if ( ! is_array( $rows ) || ! $rows ) {
		return maxbit_can_preview() ? '<div class="mb-editor-note" role="note"><strong>Служебная заметка (видна только редакторам).</strong> Блок «Тарифы Битрикс24» (#mb-b24-prices) и кнопка «Официальные тарифы» появятся после получения цен лицензий от владельца. Цены услуг MAXBIT сюда не входят.</div>' : '';
	}
	$out = '<div class="mb-b24-prices" id="mb-b24-prices"><h3>Тарифы Битрикс24</h3><p>Подходящий тариф зависит от числа сотрудников и необходимых инструментов.</p><table><tbody>';
	foreach ( $rows as $r ) {
		$out .= '<tr><th scope="row">' . esc_html( $r['name'] ?? '' ) . '</th><td>' . esc_html( $r['price'] ?? '' ) . '</td><td>' . esc_html( $r['note'] ?? '' ) . '</td></tr>';
	}
	return $out . '</tbody></table><p class="mb-small">Лицензия Битрикс24, дополнительные подписки и услуги MAXBIT рассчитываются отдельно.</p></div>';
} );
