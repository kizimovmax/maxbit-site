<?php
defined( 'ABSPATH' ) || exit;

/**
 * «Практика» → «База знаний» (решение владельца 27.10): адрес раздела /baza-znaniy/, статьи /baza-znaniy/{slug}/.
 * Старые адреса /praktika/… постоянно перенаправляются на новые. Там же — главное меню с пунктом «Сервисы»
 * (Решения · Сервисы · Проекты · Как работаем · База знаний · О компании). Переименование в базе — maxbit_kb_migrate()
 * (REST update/kb-rename, WP-CLI `wp maxbit kb-rename`), с резервной копией и откатом maxbit_kb_restore().
 */

const MAXBIT_KB_SLUG = 'baza-znaniy';
const MAXBIT_KB_OLD  = 'praktika';

/** Старые адреса → новые (301). Срабатывает до шаблона 404. */
add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$home = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	$rel  = '/' . ltrim( substr( $path, strlen( $home ) ), '/' );
	if ( preg_match( '#^/' . MAXBIT_KB_OLD . '(/.*)?$#', $rel, $m ) ) {
		$to = home_url( '/' . MAXBIT_KB_SLUG . ( $m[1] ?? '/' ) );
		wp_safe_redirect( $to, 301 );
		exit;
	}
}, 1 );

/** Главное меню после 27.10 (решение владельца). */
function maxbit_kb_menu(): array {
	return array( array( 'Решения', '/resheniya/' ), array( 'Сервисы', '/tekhnologii/' ), array( 'Проекты', '/proekty/' ), array( 'Как работаем', '/kak-rabotaem/' ), array( 'База знаний', '/' . MAXBIT_KB_SLUG . '/' ), array( 'О компании', '/o-kompanii/' ) );
}

function maxbit_kb_menu_set( int $menu_id, array $items ): void {
	foreach ( wp_get_nav_menu_items( $menu_id ) ?: array() as $i ) {
		wp_delete_post( $i->ID, true );
	}
	foreach ( $items as $n => $args ) {
		wp_update_nav_menu_item( $menu_id, 0, $args + array( 'menu-item-status' => 'publish', 'menu-item-position' => $n + 1 ) );
	}
}

function maxbit_kb_migrate( bool $dry = false ): array {
	global $wpdb;
	$log  = array();
	$page = get_post( (int) get_option( 'page_for_posts' ) );
	if ( ! $page ) {
		return array( 'ok' => false, 'error' => 'Не найдена страница записей (page_for_posts).' );
	}
	$backup = array(
		'permalink' => get_option( 'permalink_structure' ),
		'page'      => array( 'ID' => $page->ID, 'post_title' => $page->post_title, 'post_name' => $page->post_name ),
		'menu'      => array(),
		'content'   => array(),
	);
	// Пункты меню с подписью «Практика»
	foreach ( wp_get_nav_menus() as $menu ) {
		foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $it ) {
			if ( 'Практика' === $it->title || 'Практика' === get_post_field( 'post_title', $it->ID ) ) {
				$backup['menu'][ $it->ID ] = get_post_field( 'post_title', $it->ID );
			}
		}
	}
	// Ссылки /praktika/ в содержании опубликованных записей, страниц, проектов, решений и навигации
	$rows = $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') AND post_type IN ('post','page','mb_solution','mb_project','wp_navigation','wp_block') AND post_content LIKE '%/" . MAXBIT_KB_OLD . "/%'" ); // phpcs:ignore
	foreach ( $rows as $r ) {
		$backup['content'][ (int) $r->ID ] = $r->post_content;
	}
	$locs    = get_nav_menu_locations();
	$menu_id = (int) ( $locs['primary'] ?? 0 );
	if ( $menu_id ) {
		$backup['primary'] = array( 'id' => $menu_id, 'items' => array_map( fn( $i ) => array( 'title' => $i->title, 'type' => $i->type, 'object' => $i->object, 'object_id' => (int) $i->object_id, 'url' => $i->url ), wp_get_nav_menu_items( $menu_id ) ?: array() ) );
		$log[] = 'Главное меню #' . $menu_id . ': ' . implode( ' · ', wp_list_pluck( $backup['primary']['items'], 'title' ) ) . ' → ' . implode( ' · ', array_column( maxbit_kb_menu(), 0 ) );
	}
	$log[] = 'Страница раздела #' . $page->ID . ': «' . $page->post_title . '» /' . $page->post_name . '/ → «База знаний» /' . MAXBIT_KB_SLUG . '/';
	$log[] = 'Пермалинки: ' . $backup['permalink'] . ' → /' . MAXBIT_KB_SLUG . '/%postname%/';
	$log[] = 'Пункты меню: ' . count( $backup['menu'] ) . ', записи со ссылками /' . MAXBIT_KB_OLD . '/: ' . count( $backup['content'] );
	if ( $dry ) {
		return array( 'ok' => true, 'dry_run' => true, 'log' => $log );
	}
	$name = 'maxbit_kb_backup_' . gmdate( 'Ymd_His' );
	add_option( $name, $backup, '', false );
	wp_update_post( array( 'ID' => $page->ID, 'post_title' => 'База знаний', 'post_name' => MAXBIT_KB_SLUG ) );
	foreach ( array_keys( $backup['menu'] ) as $mid ) {
		wp_update_post( array( 'ID' => $mid, 'post_title' => 'База знаний' ) );
	}
	foreach ( $backup['content'] as $id => $c ) {
		$new = str_replace( array( '/' . MAXBIT_KB_OLD . '/', '>Все материалы<' ), array( '/' . MAXBIT_KB_SLUG . '/', '>Перейти в базу знаний<' ), $c );
		$wpdb->update( $wpdb->posts, array( 'post_content' => $new ), array( 'ID' => $id ) ); // без ревизий: замена адреса, откат — из резервной копии
		clean_post_cache( $id );
	}
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( '/' . MAXBIT_KB_SLUG . '/%postname%/' );
	flush_rewrite_rules( false );
	if ( ! empty( $backup['primary'] ) ) {
		maxbit_kb_menu_set( (int) $backup['primary']['id'], array_map( function ( $row ) {
			[ $label, $url ] = $row;
			if ( '/resheniya/' === $url || '/proekty/' === $url ) {
				return array( 'menu-item-title' => $label, 'menu-item-type' => 'post_type_archive', 'menu-item-object' => '/resheniya/' === $url ? 'mb_solution' : 'mb_project' );
			}
			$p = get_page_by_path( trim( $url, '/' ) );
			return $p ? array( 'menu-item-title' => $label, 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $p->ID ) : array( 'menu-item-title' => $label, 'menu-item-type' => 'custom', 'menu-item-url' => home_url( $url ) );
		}, maxbit_kb_menu() ) );
	}
	$log[] = 'Резервная копия: ' . $name;
	return array( 'ok' => true, 'backup' => $name, 'log' => $log );
}

function maxbit_kb_restore( string $name ): array {
	global $wpdb;
	$b = get_option( $name );
	if ( ! is_array( $b ) || ! str_starts_with( $name, 'maxbit_kb_backup_' ) ) {
		return array( 'ok' => false, 'error' => 'Резервная копия не найдена.' );
	}
	wp_update_post( array( 'ID' => $b['page']['ID'], 'post_title' => $b['page']['post_title'], 'post_name' => $b['page']['post_name'] ) );
	foreach ( $b['menu'] as $mid => $t ) {
		wp_update_post( array( 'ID' => (int) $mid, 'post_title' => $t ) );
	}
	foreach ( $b['content'] as $id => $c ) {
		$wpdb->update( $wpdb->posts, array( 'post_content' => $c ), array( 'ID' => (int) $id ) );
		clean_post_cache( (int) $id );
	}
	global $wp_rewrite;
	$wp_rewrite->set_permalink_structure( $b['permalink'] );
	flush_rewrite_rules( false );
	if ( ! empty( $b['primary'] ) ) {
		maxbit_kb_menu_set( (int) $b['primary']['id'], array_map( function ( $i ) {
			$a = array( 'menu-item-title' => $i['title'], 'menu-item-type' => $i['type'], 'menu-item-object' => $i['object'] );
			if ( 'custom' === $i['type'] ) {
				$a['menu-item-url'] = $i['url'];
			} elseif ( 'post_type' === $i['type'] ) {
				$a['menu-item-object-id'] = $i['object_id'];
			}
			return $a;
		}, $b['primary']['items'] ) );
	}
	return array( 'ok' => true, 'restored' => $name );
}

add_action( 'rest_api_init', function () {
	$perm = fn() => current_user_can( 'manage_options' );
	register_rest_route( 'maxbit/v1', '/update/kb-rename', array(
		'methods' => 'POST', 'permission_callback' => $perm,
		'callback' => fn( WP_REST_Request $r ) => rest_ensure_response( maxbit_kb_migrate( (bool) $r->get_param( 'dry_run' ) ) ),
	) );
	register_rest_route( 'maxbit/v1', '/update/kb-restore', array(
		'methods' => 'POST', 'permission_callback' => $perm,
		'callback' => function ( WP_REST_Request $r ) {
			if ( 'restore' !== $r->get_param( 'confirm' ) ) {
				return new WP_Error( 'confirm', 'Нужно confirm: "restore"', array( 'status' => 400 ) );
			}
			return rest_ensure_response( maxbit_kb_restore( (string) $r->get_param( 'backup' ) ) );
		},
	) );
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'maxbit kb-rename', function ( $args, $assoc ) {
		$r = maxbit_kb_migrate( isset( $assoc['dry-run'] ) );
		foreach ( $r['log'] ?? array() as $l ) {
			WP_CLI::log( $l );
		}
		$r['ok'] ? WP_CLI::success( $r['backup'] ?? 'проверка' ) : WP_CLI::error( $r['error'] );
	} );
}
