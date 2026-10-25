<?php
defined( 'ABSPATH' ) || exit;

/**
 * Правило: без флага допуска материал типа «Решение»/«Проект» для анонимного посетителя
 * не существует (404), не попадает в списки, поиск, RSS, REST, sitemap и меню.
 * Черновики/личные записи ядро и так не показывает; здесь закрываем остальные каналы.
 */

/** 1. Одиночная страница: недопущенное → 404 для анонимов. */
add_action( 'template_redirect', function () {
	if ( is_singular( array( 'mb_solution', 'mb_project' ) ) && ! maxbit_can_preview() ) {
		if ( ! maxbit_is_public_ok( get_queried_object_id() ) ) {
			maxbit_force_404();
		}
	}
	// Служебная родительская страница «Технологии» — не рубрика: перенаправляем на единственный вход.
	if ( is_page( 'tekhnologii' ) ) {
		$child = get_page_by_path( 'tekhnologii/bitrix24-dlya-proizvodstva' );
		if ( $child && 'publish' === $child->post_status ) {
			wp_safe_redirect( get_permalink( $child ), 301 );
			exit;
		}
	}
	// Учебный образец кейса не открывается анонимно даже при допуске.
	if ( is_singular( 'mb_project' ) && ! maxbit_can_preview() && '1' === get_post_meta( get_queried_object_id(), '_mb_is_sample', true ) ) {
		maxbit_force_404();
	}
} );

/** ID служебных страниц (родитель-редирект «Технологии»). */
function maxbit_service_page_ids(): array {
	$p = get_page_by_path( 'tekhnologii' );
	return $p ? array( (int) $p->ID ) : array();
}

/**
 * Вложения. Физические файлы в wp-content/uploads отдаёт веб-сервер напрямую — WordPress их не защищает.
 * Поэтому: закрытые клиентские материалы в медиатеку не загружать (предупреждение в редакторе проекта),
 * а всё, что привязано к недопущенному решению/проекту, скрывается из REST media и attachment-страниц.
 * Полная защита файлов требует хранения вне webroot и выдачи через проверку прав — отдельная задача (OPEN_ITEMS).
 */
function maxbit_attachment_closed( int $attachment_id ): bool {
	$a = get_post( $attachment_id );
	if ( ! $a || 'attachment' !== $a->post_type ) {
		return false;
	}
	if ( ! $a->post_parent ) {
		return false;
	}
	$parent = get_post( $a->post_parent );
	if ( ! $parent ) {
		return true;
	}
	if ( in_array( $parent->post_type, array( 'mb_solution', 'mb_project' ), true ) ) {
		return ! maxbit_is_publicly_visible( $parent->ID ) || '1' === get_post_meta( $parent->ID, '_mb_is_sample', true );
	}
	return 'publish' !== $parent->post_status;
}
/** ID закрытых родителей (недопущённые/образцы/неопубликованные решения и проекты). */
function maxbit_closed_parent_ids(): array {
	global $wpdb;
	$ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('mb_solution','mb_project') AND post_status <> %s", 'publish' ) );
	$pub = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('mb_solution','mb_project') AND post_status = 'publish'" );
	foreach ( $pub as $id ) {
		if ( ! maxbit_is_public_ok( (int) $id ) || '1' === get_post_meta( (int) $id, '_mb_is_sample', true ) ) {
			$ids[] = $id;
		}
	}
	return array_map( 'intval', $ids );
}
add_filter( 'rest_attachment_query', function ( array $args ) {
	if ( maxbit_can_preview() ) {
		return $args;
	}
	$closed = maxbit_closed_parent_ids();
	if ( ! $closed ) {
		return $args;
	}
	// WP_Query учитывает post_parent__not_in только без post_parent__in — поэтому при ?parent= вычитаем из него.
	if ( ! empty( $args['post_parent__in'] ) ) {
		$args['post_parent__in'] = array_values( array_diff( array_map( 'intval', (array) $args['post_parent__in'] ), $closed ) ) ?: array( 0 );
	} else {
		$args['post_parent__not_in'] = array_merge( (array) ( $args['post_parent__not_in'] ?? array() ), $closed );
	}
	return $args;
} );
add_action( 'admin_notices', function () {
	$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
	if ( $screen && 'mb_project' === $screen->post_type ) {
		echo '<div class="notice notice-warning"><p><strong>Файлы клиентов:</strong> не загружайте в медиатеку договоры, схемы и другие закрытые материалы. Файлы в uploads доступны по прямой ссылке независимо от допуска кейса. Публичные иллюстрации — только с разрешения клиента.</p></div>';
	}
} );

function maxbit_force_404(): void {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
	include get_query_template( '404' );
	exit;
}

/** 2. Списки, архивы, поиск, RSS: только допущенные. */
add_action( 'pre_get_posts', function ( WP_Query $q ) {
	// Все запросы во внешней части (главный и вторичные), кроме админки, WP-CLI и авторизованного просмотра.
	// Явный opt-out: аргумент mb_include_closed=true (только для внутренних служебных выборок).
	if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) || $q->get( 'mb_include_closed' ) || maxbit_can_preview() ) {
		return;
	}
	$types = (array) $q->get( 'post_type' );
	if ( in_array( 'any', $types, true ) || empty( $types ) ) {
		$types = array( 'any' );
	}
	$touches = array_intersect( $types, array( 'mb_solution', 'mb_project', 'any' ) ) || $q->is_search() || $q->is_feed();
	if ( ! $touches ) {
		return;
	}
	// Поиск и RSS: только страницы и статьи, без решений/проектов вообще (их и так мало, а утечка недопустима).
	if ( $q->is_search() || $q->is_feed() ) {
		$q->set( 'post_type', array( 'post', 'page' ) );
		$tekh = get_page_by_path( 'tekhnologii' );
		if ( $tekh ) {
			$q->set( 'post__not_in', array( $tekh->ID ) );   // служебная страница-редирект
		}
		return;
	}
	$meta = (array) $q->get( 'meta_query' );
	$meta[] = array( 'key' => '_mb_public_ok', 'value' => '1' );
	$meta[] = array(
		'relation' => 'OR',
		array( 'key' => '_mb_is_sample', 'compare' => 'NOT EXISTS' ),
		array( 'key' => '_mb_is_sample', 'value' => '1', 'compare' => '!=' ),
	);
	$q->set( 'meta_query', $meta );
} );

/** 3. REST API: анонимно не отдавать решения/проекты без допуска; заявки — никогда; пользователей — только авторизованным. */
add_filter( 'rest_mb_solution_query', 'maxbit_rest_public_only', 10, 2 );
add_filter( 'rest_mb_project_query', 'maxbit_rest_public_only', 10, 2 );
function maxbit_rest_public_only( array $args, WP_REST_Request $request ): array {
	if ( maxbit_can_preview() ) {
		return $args;
	}
	$args['meta_query'] = array(
		array( 'key' => '_mb_public_ok', 'value' => '1' ),
		array(
			'relation' => 'OR',
			array( 'key' => '_mb_is_sample', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_mb_is_sample', 'value' => '1', 'compare' => '!=' ),
		),
	);
	return $args;
}
add_filter( 'rest_prepare_mb_solution', 'maxbit_rest_hide_single', 10, 3 );
add_filter( 'rest_prepare_mb_project', 'maxbit_rest_hide_single', 10, 3 );
function maxbit_rest_hide_single( $response, WP_Post $post, WP_REST_Request $request ) {
	if ( ! maxbit_can_preview() && ( ! maxbit_is_public_ok( $post->ID ) || '1' === get_post_meta( $post->ID, '_mb_is_sample', true ) ) ) {
		return new WP_Error( 'rest_forbidden', 'Материал недоступен.', array( 'status' => 404 ) );
	}
	return $response;
}
add_filter( 'rest_pre_dispatch', function ( $result, $server, WP_REST_Request $request ) {
	// Одиночный материал решения/проекта по ID: анонимно 404, если нет допуска (ядро иначе отвечает 401 и подсказывает существование).
	if ( maxbit_can_preview() ) {
		return $result;
	}
	if ( preg_match( '#^/wp/v2/(mb_solution|mb_project)/(\d+)$#', $request->get_route(), $m ) ) {
		$id = (int) $m[2];
		if ( ! maxbit_is_publicly_visible( $id ) || '1' === get_post_meta( $id, '_mb_is_sample', true ) ) {
			return new WP_Error( 'rest_post_invalid_id', 'Материал недоступен.', array( 'status' => 404 ) );
		}
	}
	if ( preg_match( '#^/wp/v2/media/(\d+)$#', $request->get_route(), $m ) && maxbit_attachment_closed( (int) $m[1] ) ) {
		return new WP_Error( 'rest_post_invalid_id', 'Материал недоступен.', array( 'status' => 404 ) );
	}
	return $result;
}, 10, 3 );
/** REST-поиск /wp/v2/search использует отдельный WP_Query: анонимно ограничиваем страницами и статьями. */
add_filter( 'rest_post_search_query', function ( array $args, WP_REST_Request $request ) {
	if ( maxbit_can_preview() ) {
		return $args;
	}
	$args['post_type'] = array( 'post', 'page' );
	$args['post__not_in'] = array_merge( (array) ( $args['post__not_in'] ?? array() ), maxbit_service_page_ids() );
	return $args;
}, 10, 2 );
/** oEmbed: для решений/проектов и закрытого — нет данных (в т.ч. заголовка/ссылки). */
add_filter( 'oembed_response_data', function ( $data, $post ) {
	if ( in_array( $post->post_type, array( 'mb_solution', 'mb_project' ), true ) && ! maxbit_can_preview() ) {
		return false;
	}
	return $data;
}, 10, 2 );
add_filter( 'embed_oembed_discover', '__return_false' );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );
add_filter( 'rest_endpoints', function ( array $endpoints ) {
	// Список пользователей анонимно не нужен: убираем перечисление авторов.
	if ( ! is_user_logged_in() ) {
		unset( $endpoints['/wp/v2/users'], $endpoints['/wp/v2/users/(?P<id>[\d]+)'] );
	}
	return $endpoints;
} );
add_filter( 'rest_authentication_errors', function ( $result ) {
	// Ядро/редактор пользуются REST авторизованно; анонимный доступ к записям оставляем только для чтения публичных типов.
	return $result;
} );

/** 4. Sitemap ядра: только допущенные и не помеченные noindex. */
add_filter( 'wp_sitemaps_post_types', function ( array $types ) {
	unset( $types['attachment'] );
	return $types;
} );
add_filter( 'wp_sitemaps_add_provider', function ( $provider, string $name ) {
	return 'users' === $name ? false : $provider;   // провайдер авторов не регистрируется (авторские страницы — 404)
}, 10, 2 );
add_filter( 'wp_sitemaps_taxonomies', '__return_empty_array' );
add_filter( 'wp_sitemaps_posts_query_args', function ( array $args, string $post_type ) {
	$meta = array(
		'relation' => 'AND',
		array(
			'relation' => 'OR',
			array( 'key' => '_mb_noindex', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_mb_noindex', 'value' => '1', 'compare' => '!=' ),
		),
	);
	if ( in_array( $post_type, array( 'mb_solution', 'mb_project' ), true ) ) {
		$meta[] = array( 'key' => '_mb_public_ok', 'value' => '1' );
		$meta[] = array(
			'relation' => 'OR',
			array( 'key' => '_mb_is_sample', 'compare' => 'NOT EXISTS' ),
			array( 'key' => '_mb_is_sample', 'value' => '1', 'compare' => '!=' ),
		);
	}
	$args['meta_query'] = $meta;
	return $args;
}, 10, 2 );
add_filter( 'wp_sitemaps_enabled', function ( $enabled ) {
	// Карта сайта только при совокупности: входное разрешение ядра, production и blog_public=1.
	return (bool) $enabled && 'production' === MAXBIT_ENV && '1' === (string) get_option( 'blog_public' );
} );

/** 5. Меню: пункты на недопущенные материалы не показываются анонимно. */
add_filter( 'wp_nav_menu_objects', function ( array $items ) {
	if ( maxbit_can_preview() ) {
		return $items;
	}
	return array_values( array_filter( $items, function ( $item ) {
		if ( 'post_type' !== $item->type ) {
			return true;
		}
		$id = (int) $item->object_id;
		if ( in_array( $item->object, array( 'mb_solution', 'mb_project' ), true ) ) {
			return maxbit_is_publicly_visible( $id );
		}
		$p = get_post( $id );
		return $p && 'publish' === $p->post_status;
	} ) );
} );

/** 6. Общая гигиена: XML-RPC, версия, эмодзи-скрипт, oEmbed-обнаружение, автор в URL. */
add_filter( 'xmlrpc_enabled', '__return_false' );
add_filter( 'xmlrpc_methods', '__return_empty_array' );
add_action( 'init', function () {
	if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
		status_header( 403 );
		exit( 'XML-RPC disabled' );
	}
}, 1 );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
add_action( 'template_redirect', function () {
	if ( is_author() || is_attachment() ) {
		maxbit_force_404();   // раньше redirect_canonical (приоритет 10): страница вложения не редиректит на файл
	}
}, 1 );
add_filter( 'redirect_canonical', function ( $redirect, $requested ) {
	// Не подсказывать существование черновиков через "умные" редиректы по ID/slug.
	if ( is_404() ) {
		return false;
	}
	return $redirect;
}, 10, 2 );

/** 7. Ссылки на закрытые проекты не должны попадать в rel=prev/next и связанные записи. */
add_filter( 'get_next_post_where', 'maxbit_adjacent_where', 10, 5 );
add_filter( 'get_previous_post_where', 'maxbit_adjacent_where', 10, 5 );
function maxbit_adjacent_where( string $where, $in_same_term, $excluded, $taxonomy, $post ): string {
	if ( ! $post || ! in_array( $post->post_type, array( 'mb_solution', 'mb_project' ), true ) ) {
		return $where;
	}
	return $where . ' AND 1=0';
}
