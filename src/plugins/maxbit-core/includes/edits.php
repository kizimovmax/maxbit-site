<?php
defined( 'ABSPATH' ) || exit;

/**
 * Правки владельца 28.10–02.11 (А1–А6), выполнены 03.11: перенос в базу уже установленного сайта.
 *
 * — главная: убрать подпись «Первый разговор — бесплатно» у мессенджеров первого экрана (А1),
 *   полосу цикла и строку «Основной формат…» в «Как мы работаем» (А2), подпись под «Реализованные проекты» (А3),
 *   блок материалов → «База знаний» без подписи (А4);
 * — длительность встречи 30 → 45 минут во всех страницах, решениях, материалах и кейсах (А6). Меняются только
 *   фразы о встрече («примерно на 30 минут», «примерно 30 минут», «за 30 минут»); время ответа в чате по тарифу
 *   («ответ в чате 30 мин», «Время ответа в чате — 30 минут») не трогается;
 * — политика и согласие: состав данных формы — имя, телефон, по желанию почта или мессенджер (А5).
 *
 * Перед записью — резервная копия post_content изменяемых записей в опции maxbit_edits_backup_<время>;
 * откат — maxbit_edits_restore(). Повторный запуск ничего не меняет (все замены идемпотентны).
 * Запуск: REST POST maxbit/v1/update/edits-1103 {dry_run}, откат: update/edits-restore {backup, confirm:"restore"};
 * WP-CLI: wp maxbit edits-1103 [--dry-run].
 */

/** Правила: [вид, что, на что]. str — точная подстрока, re — регулярное выражение (u). */
function maxbit_edits_1103_rules(): array {
	$home = array(
		array( 'str', 'label="Первый разговор — бесплатно"', 'label=""' ),
		array( 're', '~<!-- wp:html -->\s*<ol class="mb-cycle mb-cycle--dark"[^>]*>.*?</ol>\s*<!-- /wp:html -->\s*~su', '' ),
		array( 're', '~<!-- wp:paragraph \{[^}]*mb-how__note[^}]*\} -->\s*<p class="mb-how__note">.*?</p>\s*<!-- /wp:paragraph -->\s*~su', '' ),
		array( 're', '~<!-- wp:paragraph -->\s*<p>Задачи компаний, внесённые изменения и работа решений в реальных процессах\.</p>\s*<!-- /wp:paragraph -->\s*~u', '' ),
		array( 're', '~<!-- wp:paragraph -->\s*<p>Статьи и чек-листы о процессах, данных и цифровых инструментах\. Их можно использовать в работе самостоятельно\.</p>\s*<!-- /wp:paragraph -->\s*~u', '' ),
		array( 'str', '>Материалы для самостоятельного разбора</h2>', '>База знаний</h2>' ),
	);
	$legal = array(
		array( 'str', 'имя (как к вам обращаться), контакт для ответа (телефон, адрес электронной почты или ссылка на мессенджер)', 'имя, номер телефона и, по желанию, адрес электронной почты или имя пользователя в мессенджере' ),
		array( 'str', 'Имя (как ко мне обращаться), контакт для связи (телефон, адрес электронной почты или ссылка на мессенджер)', 'Имя, номер телефона и, по моему желанию, адрес электронной почты или имя пользователя в мессенджере' ),
		array( 'str', 'по указанному контакту', 'по указанному телефону или другому указанному контакту' ),
		array( 'str', 'Редакция от 16.10.2026.', 'Редакция от 03.11.2026.' ),
	);
	return array(
		'home'  => $home,
		'legal' => $legal,
		'all'   => array( array( 're', '~(примерно на|примерно|за) 30 минут~u', '$1 45 минут' ) ),
	);
}

function maxbit_edits_1103_apply_rules( string $c, array $rules, array &$hits ): string {
	foreach ( $rules as $i => [ $kind, $from, $to ] ) {
		$n = 0;
		if ( 'str' === $kind ) {
			$c = str_replace( $from, $to, $c, $n );
		} else {
			$c = (string) preg_replace( $from, $to, $c, -1, $n );
		}
		if ( $n ) {
			$hits[] = $i . '×' . $n;
		}
	}
	return $c;
}

function maxbit_edits_1103( bool $dry = false ): array {
	global $wpdb;
	$rules = maxbit_edits_1103_rules();
	$front = (int) get_option( 'page_on_front' );
	$legal = array_filter( array_map( fn( $s ) => ( $p = get_page_by_path( $s ) ) ? $p->ID : 0, array( 'politika-obrabotki-personalnyh-dannyh', 'soglasie-na-obrabotku-personalnyh-dannyh' ) ) );
	$ids   = array_map( 'intval', $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_type IN ('page','mb_solution','mb_material','mb_project','post') AND post_status IN ('publish','private','draft','pending','future') AND post_content LIKE '%30 минут%'" ) );
	$ids   = array_values( array_unique( array_merge( $ids, array_filter( array( $front ) ), $legal ) ) );
	$log   = array();
	$new   = array();
	$old   = array();
	foreach ( $ids as $id ) {
		$p = get_post( $id );
		if ( ! $p ) {
			continue;
		}
		$c    = $p->post_content;
		$hits = array();
		$set  = $rules['all'];
		if ( $id === $front ) {
			$set = array_merge( $rules['home'], $set );
		}
		if ( in_array( $id, $legal, true ) ) {
			$set = array_merge( $rules['legal'], $set );
		}
		$n = maxbit_edits_1103_apply_rules( $c, $set, $hits );
		if ( $n !== $c ) {
			$old[ $id ] = $c;
			$new[ $id ] = $n;
			$log[]      = sprintf( '#%d %s /%s/: правил %s', $id, $p->post_type, $p->post_name, implode( ', ', $hits ) );
		}
	}
	if ( $front && ! isset( $new[ $front ] ) ) {
		$log[] = 'главная: изменений нет (уже применено или содержание отличается)';
	}
	if ( $dry || ! $new ) {
		return array( 'ok' => true, 'dry_run' => $dry, 'changed' => count( $new ), 'log' => $log ?: array( 'изменений нет' ) );
	}
	$name = 'maxbit_edits_backup_' . gmdate( 'Ymd_His' );
	add_option( $name, array( 'content' => $old, 'created' => gmdate( 'c' ) ), '', 'no' );
	if ( get_option( $name )['content'] ?? null ) {
		kses_remove_filters(); // разметка блоков переносится как есть (как при наполнении)
		foreach ( $new as $id => $c ) {
			wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $c ) ) ); // с ревизией
		}
	} else {
		return array( 'ok' => false, 'error' => 'Не удалось сохранить резервную копию — изменения не внесены.' );
	}
	$log[] = 'Резервная копия: ' . $name;
	return array( 'ok' => true, 'changed' => count( $new ), 'backup' => $name, 'log' => $log );
}

function maxbit_edits_restore( string $name ): array {
	$b = get_option( $name );
	if ( ! str_starts_with( $name, 'maxbit_edits_backup_' ) || ! is_array( $b ) || empty( $b['content'] ) ) {
		return array( 'ok' => false, 'error' => 'Резервная копия не найдена.' );
	}
	kses_remove_filters();
	foreach ( $b['content'] as $id => $c ) {
		wp_update_post( wp_slash( array( 'ID' => (int) $id, 'post_content' => $c ) ) );
	}
	return array( 'ok' => true, 'restored' => $name, 'posts' => array_keys( $b['content'] ) );
}

add_action( 'rest_api_init', function () {
	$perm = fn() => current_user_can( 'manage_options' );
	register_rest_route( 'maxbit/v1', '/update/edits-1103', array(
		'methods' => 'POST', 'permission_callback' => $perm,
		'callback' => fn( WP_REST_Request $r ) => rest_ensure_response( maxbit_edits_1103( (bool) $r->get_param( 'dry_run' ) ) ),
	) );
	register_rest_route( 'maxbit/v1', '/update/edits-restore', array(
		'methods' => 'POST', 'permission_callback' => $perm,
		'callback' => function ( WP_REST_Request $r ) {
			if ( 'restore' !== $r->get_param( 'confirm' ) ) {
				return new WP_Error( 'confirm', 'Нужно confirm: "restore"', array( 'status' => 400 ) );
			}
			return rest_ensure_response( maxbit_edits_restore( (string) $r->get_param( 'backup' ) ) );
		},
	) );
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'maxbit edits-1103', function ( $args, $assoc ) {
		$r = maxbit_edits_1103( isset( $assoc['dry-run'] ) );
		foreach ( $r['log'] ?? array() as $l ) {
			WP_CLI::log( $l );
		}
		$r['ok'] ? WP_CLI::success( $r['backup'] ?? 'проверка' ) : WP_CLI::error( $r['error'] );
	} );
}
