<?php
defined( 'ABSPATH' ) || exit;

/**
 * Закрытые материалы (0.5.0, ТЗ v4 §8): коммерческое предложение и презентация MAXBIT.
 *
 * - Содержимое — непубличный тип записей mb_material: нет в поиске, RSS, REST, sitemap, oEmbed, архивах.
 * - Доступ — по ссылке /materialy/<kp|prezentaciya>/<токен>/. Токен: 256 бит из random_bytes, base64url.
 *   В базе хранится только SHA-256 токена, материал, номер заявки, время выдачи, отзыв и срок.
 *   Ссылка показывается сотруднику один раз при выдаче; утрачена — выдаётся новая.
 * - Проверка на сервере до вывода HTML. Без токена, с неверным, отозванным или просроченным — 404 без содержания.
 * - Заголовки: X-Robots-Tag noindex/nofollow/noarchive, Cache-Control private no-store, Referrer-Policy no-referrer.
 *   Счётчики, OG, canonical и JSON-LD на этих страницах не выводятся.
 * - Выдача только вручную из карточки обращения; автоматической отправки нет.
 */

const MAXBIT_MATERIALS = array(
	'kp'           => 'Коммерческое предложение',
	'prezentaciya' => 'Презентация MAXBIT',
);
const MAXBIT_TOKENS_OPTION = 'maxbit_material_tokens';

add_action( 'init', function () {
	register_post_type( 'mb_material', array(
		'labels'              => array( 'name' => 'Материалы по ссылке', 'singular_name' => 'Материал', 'edit_item' => 'Редактировать материал', 'menu_name' => 'Материалы по ссылке' ),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_nav_menus'   => false,
		'show_in_rest'        => false,   // редактор классический: содержимое не должно уходить в REST
		'has_archive'         => false,
		'rewrite'             => false,
		'query_var'           => false,
		'menu_icon'           => 'dashicons-lock',
		'menu_position'       => 24,
		'supports'            => array( 'title', 'editor', 'revisions' ),
		'capability_type'     => 'page',
		'map_meta_cap'        => true,
	) );
	add_rewrite_rule( '^materialy/(kp|prezentaciya)/([A-Za-z0-9_-]{40,64})/?$', 'index.php?mb_material=$matches[1]&mb_mtoken=$matches[2]', 'top' );
	add_rewrite_rule( '^materialy(/.*)?$', 'index.php?mb_material=none', 'top' );
} );
add_filter( 'query_vars', function ( array $v ) {
	$v[] = 'mb_material';
	$v[] = 'mb_mtoken';
	return $v;
} );

/** Запись материала по ключу (kp | prezentaciya). */
function maxbit_material_post( string $key ): ?WP_Post {
	$ids = get_posts( array( 'post_type' => 'mb_material', 'post_status' => array( 'publish', 'private', 'draft' ), 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_mb_material_key', 'meta_value' => $key, 'suppress_filters' => true ) );
	return $ids ? get_post( (int) $ids[0] ) : null;
}

function maxbit_tokens(): array {
	$t = get_option( MAXBIT_TOKENS_OPTION, array() );
	return is_array( $t ) ? $t : array();
}
function maxbit_tokens_save( array $t ): void {
	update_option( MAXBIT_TOKENS_OPTION, $t, false );
}
function maxbit_token_hash( string $token ): string {
	return hash( 'sha256', $token );
}

/** Выдать ссылку: возвращает полный URL (единственный раз) и id записи. */
function maxbit_material_issue( string $key, int $lead_id ): array {
	if ( ! isset( MAXBIT_MATERIALS[ $key ] ) ) {
		return array();
	}
	$token = rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ); // 256 бит, 43 символа
	$ttl   = (int) maxbit_get_settings()['material_ttl_days'];
	$all   = maxbit_tokens();
	$id    = substr( bin2hex( random_bytes( 6 ) ), 0, 10 );
	$all[ maxbit_token_hash( $token ) ] = array(
		'id' => $id, 'm' => $key, 'lead' => $lead_id, 'c' => time(), 'r' => 0,
		'e' => $ttl > 0 ? time() + $ttl * DAY_IN_SECONDS : 0, 'by' => get_current_user_id(), 'hits' => 0, 'last' => 0,
	);
	maxbit_tokens_save( $all );
	return array( 'url' => home_url( '/materialy/' . $key . '/' . $token . '/' ), 'id' => $id );
}

/** Отозвать по служебному id (не по токену). */
function maxbit_material_revoke( string $id ): bool {
	$all = maxbit_tokens();
	foreach ( $all as $h => $row ) {
		if ( ( $row['id'] ?? '' ) === $id && empty( $row['r'] ) ) {
			$all[ $h ]['r'] = time();
			maxbit_tokens_save( $all );
			return true;
		}
	}
	return false;
}

/** Проверка токена: запись или null. */
function maxbit_material_check( string $key, string $token ): ?array {
	if ( ! preg_match( '/^[A-Za-z0-9_-]{40,64}$/', $token ) ) {
		return null;
	}
	$h   = maxbit_token_hash( $token );
	$all = maxbit_tokens();
	if ( ! isset( $all[ $h ] ) ) {
		return null;
	}
	$row = $all[ $h ];
	if ( ! hash_equals( (string) $row['m'], $key ) || ! empty( $row['r'] ) || ( ! empty( $row['e'] ) && time() > (int) $row['e'] ) ) {
		return null;
	}
	return $row + array( 'hash' => $h );
}

/** Флаг «идёт вывод закрытого материала»: модуль не выводит OG/canonical/JSON-LD/счётчики. */
function maxbit_is_private_material(): bool {
	return ! empty( $GLOBALS['maxbit_private_material'] );
}

/** Любой запрос под /materialy/ (в т.ч. 404 по неверной или отозванной ссылке): адрес может содержать токен. */
function maxbit_is_material_path(): bool {
	$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
	$home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	return 0 === strpos( '/' . ltrim( substr( $path, strlen( rtrim( $home, '/' ) ) ), '/' ), '/materialy/' );
}

function maxbit_material_headers(): void {
	nocache_headers();
	header( 'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0', true );
	header( 'X-Robots-Tag: noindex, nofollow, noarchive, nosnippet', true );
	header( 'Referrer-Policy: no-referrer', true );
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
		define( 'DONOTCACHEPAGE', true ); // кеширующие плагины хостинга
	}
}

add_action( 'template_redirect', function () {
	$key = (string) get_query_var( 'mb_material' );
	if ( '' === $key ) {
		return;
	}
	maxbit_material_headers();
	$token = (string) get_query_var( 'mb_mtoken' );
	$row   = isset( MAXBIT_MATERIALS[ $key ] ) ? maxbit_material_check( $key, $token ) : null;
	$post  = $row ? maxbit_material_post( $key ) : null;
	if ( ! $row || ! $post || 'trash' === $post->post_status ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		add_filter( 'pre_get_document_title', fn() => 'Страница не найдена — MAXBIT', 30 );
		include get_query_template( '404' );
		exit;
	}
	// Учёт открытий без IP и без токена.
	$all = maxbit_tokens();
	$all[ $row['hash'] ]['hits'] = (int) ( $all[ $row['hash'] ]['hits'] ?? 0 ) + 1;
	$all[ $row['hash'] ]['last'] = time();
	maxbit_tokens_save( $all );

	$GLOBALS['maxbit_private_material'] = $key;
	status_header( 200 );
	global $wp_query;
	$wp_query->is_404  = false;
	$wp_query->is_home = false;
	$GLOBALS['post'] = $post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
	setup_postdata( $post );
	add_filter( 'pre_get_document_title', fn() => $post->post_title . ' — MAXBIT', 30 );
	add_filter( 'wp_robots', fn() => array( 'noindex' => true, 'nofollow' => true, 'noarchive' => true ), 99 );
	add_filter( 'body_class', fn( array $c ) => array_merge( $c, array( 'mb-material-page', 'mb-material-' . $key ) ) );
	add_action( 'wp_head', function () {
		echo '<meta name="referrer" content="no-referrer">' . "\n";
	}, 0 );
	$tpl = locate_template( array( 'template-material.php' ) );
	if ( $tpl ) {
		include $tpl;
	} else {
		get_header();
		echo '<main class="mb-container">' . apply_filters( 'the_content', $post->post_content ) . '</main>'; // phpcs:ignore
		get_footer();
	}
	exit;
}, 0 );

/** Служебный префикс закрыт и в robots.txt (не как защита — защита на сервере). */
add_filter( 'robots_txt', function ( string $out ) {
	if ( false === strpos( $out, 'Disallow: /materialy/' ) && false === strpos( $out, "Disallow: /\n" ) ) {
		$out = preg_replace( '/(User-agent: \*\n)/', "$1Disallow: /materialy/\n", $out );
		$out = preg_replace( '/(User-agent: Yandex\n)/', "$1Disallow: /materialy/\n", $out );
	}
	return $out;
}, 20 );

/* ------------------------------------------------------------------ админка: выдача и отзыв из карточки обращения */
add_action( 'add_meta_boxes_mb_lead', function () {
	add_meta_box( 'mb_material_links', 'Материалы по ссылке', 'maxbit_material_metabox', 'mb_lead', 'side', 'high' );
} );

function maxbit_material_metabox( WP_Post $lead ): void {
	$fresh = get_transient( 'mb_mlink_' . get_current_user_id() . '_' . $lead->ID );
	if ( $fresh ) {
		delete_transient( 'mb_mlink_' . get_current_user_id() . '_' . $lead->ID ); // показываем один раз
		echo '<div class="notice notice-info inline" style="margin:0 0 10px;padding:8px"><p><strong>Новая ссылка — скопируйте сейчас, повторно не показывается.</strong></p><input type="text" readonly class="widefat" value="' . esc_attr( $fresh ) . '" onclick="this.select()"><p class="description">Отправьте клиенту вручную по его контакту.</p></div>';
	}
	$purpose = (string) get_post_meta( $lead->ID, '_mb_purpose', true );
	if ( in_array( $purpose, array( 'kp', 'presentation' ), true ) ) {
		echo '<p>Клиент запросил: <strong>' . esc_html( maxbit_lead_purposes()[ $purpose ] ) . '</strong></p>';
	}
	$missing = array();
	foreach ( MAXBIT_MATERIALS as $k => $label ) {
		if ( ! maxbit_material_post( $k ) ) {
			$missing[] = $label;
		}
	}
	if ( $missing ) {
		echo '<p style="color:#b32d2e">Нет содержимого: ' . esc_html( implode( ', ', $missing ) ) . '. Выдача недоступна.</p>';
	}
	foreach ( MAXBIT_MATERIALS as $k => $label ) {
		if ( ! maxbit_material_post( $k ) ) {
			continue;
		}
		$url = wp_nonce_url( admin_url( 'admin-post.php?action=maxbit_material_issue&m=' . $k . '&lead=' . $lead->ID ), 'mb_mat_' . $lead->ID );
		echo '<p><a class="button" href="' . esc_url( $url ) . '">Выдать ссылку: ' . esc_html( $label ) . '</a></p>';
	}
	$rows = array_filter( maxbit_tokens(), fn( $r ) => (int) ( $r['lead'] ?? 0 ) === $lead->ID );
	if ( $rows ) {
		echo '<table class="widefat striped" style="margin-top:8px"><tbody>';
		foreach ( $rows as $r ) {
			$state = ! empty( $r['r'] ) ? 'отозвана' : ( ! empty( $r['e'] ) && time() > $r['e'] ? 'истекла' : 'действует' );
			$act = 'действует' === $state ? '<a href="' . esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=maxbit_material_revoke&id=' . $r['id'] . '&lead=' . $lead->ID ), 'mb_mat_' . $lead->ID ) ) . '" style="color:#b32d2e">Отозвать</a>' : '';
			echo '<tr><td>' . esc_html( MAXBIT_MATERIALS[ $r['m'] ] ?? $r['m'] ) . '<br><small>' . esc_html( wp_date( 'd.m.Y H:i', (int) $r['c'] ) ) . ' · открытий: ' . (int) ( $r['hits'] ?? 0 ) . '</small></td><td>' . esc_html( $state ) . '<br>' . $act . '</td></tr>'; // phpcs:ignore
		}
		echo '</tbody></table>';
	}
	echo '<p class="description">В базе хранится только отпечаток ссылки. Утерянную ссылку нельзя показать снова — выдайте новую и отзовите старую.</p>';
}

add_action( 'admin_post_maxbit_material_issue', function () {
	$lead = (int) ( $_GET['lead'] ?? 0 );
	$key  = sanitize_key( $_GET['m'] ?? '' );
	if ( ! current_user_can( 'edit_post', $lead ) || 'mb_lead' !== get_post_type( $lead ) ) {
		wp_die( 'Недостаточно прав.' );
	}
	check_admin_referer( 'mb_mat_' . $lead );
	$res = maxbit_material_issue( $key, $lead );
	if ( $res ) {
		set_transient( 'mb_mlink_' . get_current_user_id() . '_' . $lead, $res['url'], 5 * MINUTE_IN_SECONDS );
	}
	wp_safe_redirect( admin_url( 'post.php?post=' . $lead . '&action=edit' ) );
	exit;
} );
add_action( 'admin_post_maxbit_material_revoke', function () {
	$lead = (int) ( $_GET['lead'] ?? 0 );
	if ( ! current_user_can( 'edit_post', $lead ) ) {
		wp_die( 'Недостаточно прав.' );
	}
	check_admin_referer( 'mb_mat_' . $lead );
	maxbit_material_revoke( preg_replace( '/[^a-f0-9]/', '', (string) ( $_GET['id'] ?? '' ) ) );
	wp_safe_redirect( admin_url( 'post.php?post=' . $lead . '&action=edit' ) );
	exit;
} );

/** Страница «Материалы по ссылке → Выданные ссылки»: общий список с отзывом (без самих ссылок). */
add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=mb_material', 'Выданные ссылки', 'Выданные ссылки', 'edit_pages', 'maxbit-material-links', function () {
		if ( isset( $_POST['mb_revoke'] ) && check_admin_referer( 'mb_mat_list' ) ) {
			maxbit_material_revoke( preg_replace( '/[^a-f0-9]/', '', (string) $_POST['mb_revoke'] ) );
			echo '<div class="notice notice-success"><p>Ссылка отозвана.</p></div>';
		}
		echo '<div class="wrap"><h1>Выданные ссылки на материалы</h1><form method="post">';
		wp_nonce_field( 'mb_mat_list' );
		echo '<table class="widefat striped"><thead><tr><th>Материал</th><th>Обращение</th><th>Выдана</th><th>Открытий</th><th>Статус</th><th></th></tr></thead><tbody>';
		$rows = maxbit_tokens();
		uasort( $rows, fn( $a, $b ) => (int) $b['c'] <=> (int) $a['c'] );
		foreach ( $rows as $r ) {
			$state = ! empty( $r['r'] ) ? 'отозвана ' . wp_date( 'd.m.Y', (int) $r['r'] ) : ( ! empty( $r['e'] ) && time() > $r['e'] ? 'истекла' : 'действует' );
			$lead  = (int) $r['lead'];
			echo '<tr><td>' . esc_html( MAXBIT_MATERIALS[ $r['m'] ] ?? $r['m'] ) . '</td><td>' . ( $lead ? '<a href="' . esc_url( get_edit_post_link( $lead ) ) . '">' . esc_html( get_post_meta( $lead, '_mb_request_id', true ) ?: '#' . $lead ) . '</a>' : '—' ) . '</td><td>' . esc_html( wp_date( 'd.m.Y H:i', (int) $r['c'] ) ) . '</td><td>' . (int) ( $r['hits'] ?? 0 ) . '</td><td>' . esc_html( $state ) . '</td><td>' . ( 'действует' === $state ? '<button class="button-link" style="color:#b32d2e" name="mb_revoke" value="' . esc_attr( $r['id'] ) . '">Отозвать</button>' : '' ) . '</td></tr>';
		}
		if ( ! $rows ) {
			echo '<tr><td colspan="6">Ссылок ещё не выдавали.</td></tr>';
		}
		echo '</tbody></table></form><p class="description">Ссылки выдаются из карточки обращения («Обращения» → обращение → блок «Материалы по ссылке»).</p></div>';
	} );
} );

/** Предупреждение в редакторе материала. */
add_action( 'edit_form_after_title', function ( WP_Post $p ) {
	if ( 'mb_material' === $p->post_type ) {
		echo '<div class="notice notice-warning inline"><p><strong>Закрытый материал.</strong> Страница открывается только по выданной ссылке из карточки обращения. Не вставляйте сюда персональные данные клиентов и ссылки на файлы из медиатеки — они доступны по прямому адресу.</p></div>';
	}
} );
