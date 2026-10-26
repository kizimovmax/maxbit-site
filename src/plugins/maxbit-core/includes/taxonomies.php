<?php
defined( 'ABSPATH' ) || exit;

/**
 * Классификаторы модели содержания (0.4.1): роль читателя, процесс, система. Общие для статей, решений,
 * кейсов и страниц — связывают материалы между собой без ручных ссылок (хабы, «связанные материалы»).
 * Архивов у рубрик нет (без лишних страниц в индексе): списки собираются шорткодом [maxbit_list].
 */
function maxbit_taxonomies(): array {
	return array(
		'mb_role'    => array( 'Роль читателя', 'Роли читателя', array( 'director' => 'Директор / собственник', 'production' => 'Производство', 'sales' => 'Продажи', 'it' => 'ИТ / 1С' ) ),
		'mb_process' => array( 'Процесс', 'Процессы', array( 'orders' => 'Заказы', 'specs' => 'Спецификации', 'planning' => 'Планирование', 'shipping' => 'Отгрузка и оплата', 'reporting' => 'Отчётность руководителя' ) ),
		'mb_system'  => array( 'Система', 'Системы', array( 'bitrix24' => 'Битрикс24', '1c' => '1С', 'excel' => 'Таблицы', 'integration' => 'Интеграции' ) ),
	);
}

add_action( 'init', function () {
	foreach ( maxbit_taxonomies() as $tax => $cfg ) {
		register_taxonomy( $tax, array( 'post', 'mb_solution', 'mb_project', 'page' ), array(
			'labels'            => array( 'name' => $cfg[1], 'singular_name' => $cfg[0], 'menu_name' => $cfg[1] ),
			'public'            => false,
			'publicly_queryable' => false,
			'show_ui'           => true,
			'show_in_rest'      => true,
			'show_admin_column' => true,
			'hierarchical'      => true,   // флажки в редакторе, без свободного ввода
			'rewrite'           => false,
			'query_var'         => false,
		) );
	}
}, 5 );

/** Начальный набор терминов — создаётся один раз (при наполнении или первом открытии админки). */
function maxbit_seed_terms(): void {
	foreach ( maxbit_taxonomies() as $tax => $cfg ) {
		foreach ( $cfg[2] as $slug => $name ) {
			if ( ! term_exists( $slug, $tax ) ) {
				wp_insert_term( $name, $tax, array( 'slug' => $slug ) );
			}
		}
	}
}
add_action( 'admin_init', function () {
	if ( get_option( 'maxbit_terms_seeded' ) !== '1' ) {
		maxbit_seed_terms();
		update_option( 'maxbit_terms_seeded', '1', false );
	}
} );

/** Подпись типа материала для карточек. */
function maxbit_type_label( WP_Post $p ): string {
	switch ( $p->post_type ) {
		case 'mb_solution':
			return 'Решение';
		case 'mb_project':
			return 'Кейс';
		case 'post':
			return 'chek-list-peredacha-zakaza-v-proizvodstvo' === $p->post_name ? 'Чек-лист' : 'Статья';
		default:
			return 'Страница';
	}
}

/** Тема обращения по материалу: решения — по slug, иначе общая. */
function maxbit_topic_for_post( int $post_id ): string {
	$slug = (string) get_post_field( 'post_name', $post_id );
	$map  = array( 'zakaz-pod-kontrolem' => 'order_control', 'integraciya-bitrix24-i-1c' => 'integration', 'ii-praktikum' => 'ai_practicum', 'bitrix24-dlya-proizvodstva' => 'bitrix24' );
	return $map[ $slug ] ?? 'general';
}

/** Время чтения по тексту записи (≈180 слов в минуту). */
function maxbit_reading_time( int $post_id ): string {
	$words = str_word_count( wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $post_id ) ) ), 0, 'абвгдеёжзийклмнопрстуфхцчшщъыьэюяАБВГДЕЁЖЗИЙКЛМНОПРСТУФХЦЧШЩЪЫЬЭЮЯ' );
	$min = max( 1, (int) ceil( $words / 180 ) );
	$n = $min % 100;
	$word = ( $n % 10 === 1 && $n !== 11 ) ? 'минута' : ( ( in_array( $n % 10, array( 2, 3, 4 ), true ) && ! in_array( $n, array( 12, 13, 14 ), true ) ) ? 'минуты' : 'минут' );
	return $min . ' ' . $word . ' чтения';
}

/**
 * Связанные по общим рубрикам материалы: сначала совпадение по процессу, затем по системе и роли.
 * Только публично видимые (допущенные) записи; исключения — текущая и уже показанные.
 */
function maxbit_related_by_terms( int $post_id, int $limit = 3, array $exclude = array() ): array {
	if ( $limit < 1 ) {
		return array();
	}
	$tax_query = array( 'relation' => 'OR' );
	foreach ( array_keys( maxbit_taxonomies() ) as $tax ) {
		$ids = wp_get_object_terms( $post_id, $tax, array( 'fields' => 'ids' ) );
		if ( $ids && ! is_wp_error( $ids ) ) {
			$tax_query[] = array( 'taxonomy' => $tax, 'field' => 'term_id', 'terms' => $ids );
		}
	}
	if ( count( $tax_query ) < 2 ) {
		return array();
	}
	$q = new WP_Query( array(
		'post_type'      => array( 'post', 'mb_solution', 'mb_project' ),
		'post_status'    => 'publish',
		'posts_per_page' => $limit + 6,
		'post__not_in'   => array_map( 'intval', $exclude ),
		'no_found_rows'  => true,
		'tax_query'      => $tax_query,
		'orderby'        => 'date',
		'order'          => 'DESC',
	) );
	$out = array();
	foreach ( $q->posts as $p ) {
		if ( ! maxbit_is_publicly_visible( $p->ID ) || '1' === get_post_meta( $p->ID, '_mb_is_sample', true ) ) {
			continue;
		}
		$out[] = $p;
		if ( count( $out ) >= $limit ) {
			break;
		}
	}
	return $out;
}

/**
 * [maxbit_list type="post|mb_solution|mb_project|any" role="director" process="orders" system="bitrix24" count="6" heading="…"]
 * Список карточек по рубрикам — для страниц-хабов («Для директора», «Битрикс24 для производства»). Только допущенные.
 */
add_shortcode( 'maxbit_list', function ( $atts ) {
	$a = shortcode_atts( array( 'type' => 'any', 'role' => '', 'process' => '', 'system' => '', 'count' => 6, 'heading' => '' ), $atts );
	$types = 'any' === $a['type'] ? array( 'post', 'mb_solution', 'mb_project' ) : array_values( array_intersect( array_map( 'trim', explode( ',', $a['type'] ) ), array( 'post', 'mb_solution', 'mb_project', 'page' ) ) );
	$tax_query = array( 'relation' => 'AND' );
	foreach ( array( 'role' => 'mb_role', 'process' => 'mb_process', 'system' => 'mb_system' ) as $k => $tax ) {
		if ( $a[ $k ] ) {
			$tax_query[] = array( 'taxonomy' => $tax, 'field' => 'slug', 'terms' => array_map( 'sanitize_title', explode( ',', $a[ $k ] ) ) );
		}
	}
	$args = array( 'post_type' => $types ?: 'post', 'post_status' => 'publish', 'posts_per_page' => min( 24, max( 1, (int) $a['count'] ) ) + 6, 'no_found_rows' => true );
	if ( count( $tax_query ) > 1 ) {
		$args['tax_query'] = $tax_query;
	}
	$q = new WP_Query( $args );
	$cards = '';
	$n = 0;
	foreach ( $q->posts as $p ) {
		if ( ! maxbit_is_publicly_visible( $p->ID ) || '1' === get_post_meta( $p->ID, '_mb_is_sample', true ) ) {
			continue;
		}
		$cards .= maxbit_render_card( $p, maxbit_type_label( $p ) );
		if ( ++$n >= (int) $a['count'] ) {
			break;
		}
	}
	if ( ! $cards ) {
		return maxbit_can_preview() ? '<div class="mb-editor-note" role="note"><strong>Служебная заметка.</strong> Список пуст: нет допущенных материалов с такими рубриками.</div>' : '';
	}
	return ( $a['heading'] ? '<h2 class="mb-h2--plain">' . esc_html( $a['heading'] ) . '</h2>' : '' ) . '<div class="mb-grid mb-grid--3">' . $cards . '</div>';
} );
