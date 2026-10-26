<?php
defined( 'ABSPATH' ) || exit;

/**
 * Скрытая страница подробных цен (0.4.1). Решение владельца 26.10.2026: подробный прайс не доступен с сайта
 * напрямую — ссылку клиент получает в чате. Реализация: страница в статусе «Личное» (private) — WordPress сам
 * не показывает её анонимам, в меню, поиске, REST, RSS и sitemap; читается только по адресу /price/<ключ>/.
 * Ключ — в «MAXBIT → Согласия и закрытые материалы», меняется одной галочкой. Ответ: noindex (X-Robots-Tag и meta),
 * без кеширования, без хлебных крошек и JSON-LD (страница отдаётся вне основного запроса).
 */

add_action( 'init', function () {
	add_rewrite_rule( '^price/([a-f0-9]{16,32})/?$', 'index.php?mb_price_key=$matches[1]', 'top' );
} );
add_filter( 'query_vars', function ( array $vars ) {
	$vars[] = 'mb_price_key';
	return $vars;
} );

/** Страница цен: private и slug из настроек. */
function maxbit_price_page(): ?WP_Post {
	$slug = (string) maxbit_get_settings()['price_page'];
	$page = $slug ? get_page_by_path( $slug, OBJECT, 'page' ) : null;
	return ( $page && 'private' === $page->post_status ) ? $page : null;
}

add_action( 'template_redirect', function () {
	$key = (string) get_query_var( 'mb_price_key' );
	if ( '' === $key ) {
		return;
	}
	nocache_headers();
	header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet', true );
	$page = maxbit_price_page();
	if ( ! $page || ! hash_equals( maxbit_price_key(), $key ) ) {
		status_header( 404 );
		add_filter( 'pre_get_document_title', fn() => 'Страница не найдена — MAXBIT', 30 );
		get_template_part( '404' );
		exit;
	}
	status_header( 200 );
	global $post, $wp_query;
	$post = $page; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	setup_postdata( $post );
	$wp_query->is_404 = false;
	add_filter( 'pre_get_document_title', fn() => $page->post_title . ' — MAXBIT', 30 );
	add_filter( 'private_title_format', fn() => '%s' ); // без префикса «Private:» для читателя по ссылке
	add_filter( 'wp_robots', function ( array $r ) {
		$r['noindex'] = true;
		$r['nofollow'] = true;
		unset( $r['index'], $r['follow'] );
		return $r;
	}, 99 );
	add_filter( 'body_class', fn( array $c ) => array_merge( $c, array( 'mb-private-page' ) ) );
	$tpl = locate_template( array( 'template-private.php', 'page.php' ) );
	if ( $tpl ) {
		include $tpl;
	} else {
		echo '<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><title>' . esc_html( get_the_title( $page ) ) . '</title></head><body>' . apply_filters( 'the_content', $page->post_content ) . '</body></html>'; // phpcs:ignore
	}
	exit;
} );

/** Ссылка на скрытую страницу для администратора (панель и карточка страницы). */
add_action( 'admin_notices', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( ! $screen || 'page' !== $screen->id || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$id = (int) ( $_GET['post'] ?? 0 );
	$page = maxbit_price_page();
	if ( $page && $id === $page->ID ) {
		echo '<div class="notice notice-info"><p><strong>Скрытая страница цен.</strong> Статус «Личное» — анонимам недоступна. Ссылка для клиентов: <code>' . esc_url( home_url( '/price/' . maxbit_price_key() . '/' ) ) . '</code> (сменить ключ — в «MAXBIT → Согласия и закрытые материалы»).</p></div>';
	}
} );
