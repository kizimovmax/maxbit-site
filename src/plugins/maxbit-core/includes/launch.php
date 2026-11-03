<?php
defined( 'ABSPATH' ) || exit;

/**
 * Запуск на боевом домене (03.11): одна последовательность вместо ручных шагов.
 *
 * Выполняется на БОЕВОМ сайте. Источник — база dev на том же сервере MySQL (те же учётные данные из wp-config,
 * пароли не передаются). Шаги:
 *  1. Проверка: среда production, адрес сайта, версии, доступ к базе-источнику, одинаковый префикс таблиц.
 *  2. Содержание: записи, изменённые на dev после копирования (и новые), переносятся с метаданными, рубриками
 *     и связями. Обращения (mb_lead) не переносятся: на dev они тестовые.
 *  3. Настройки MAXBIT (maxbit_settings): берутся с dev — вместе с вебхуком, без вывода секретов.
 *  4. Файлы загрузок, которых нет на боевом сайте, копируются из папки dev.
 *  5. Адреса dev.maxbitpro.ru → maxbitpro.ru во всей базе (maxbit_update_migrate).
 *  6. Итог: сколько упоминаний dev осталось; правила адресов пересобраны.
 * Индексирование здесь НЕ открывается — отдельный шаг update/indexing {open:true, confirm:"index"}.
 * Проверка по живым адресам — update/smoke.
 *
 * Перед записью — резервная копия затронутых записей и настроек боевого сайта (опция maxbit_launch_backup_<время>),
 * откат: update/launch-restore {backup, confirm:"restore"}. Повторный запуск переносит только новые изменения.
 * REST: POST maxbit/v1/update/launch {source_db, source_dir?, dry_run} → запись только с confirm:"launch".
 */

const MAXBIT_LAUNCH_FROM = 'dev.maxbitpro.ru';

function maxbit_launch_src( string $db ) {
	if ( ! preg_match( '/^[A-Za-z0-9_]{1,64}$/', $db ) ) {
		return new WP_Error( 'src', 'Имя базы-источника: только латиница, цифры и _.' );
	}
	if ( ! defined( 'DB_USER' ) || ! class_exists( 'wpdb' ) || defined( 'DB_ENGINE' ) ) {
		return new WP_Error( 'src', 'Нужна база MySQL (на локальном стенде с SQLite запуск не выполняется).' );
	}
	global $wpdb;
	if ( ! $wpdb->get_var( $wpdb->prepare( 'SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = %s', $db ) ) ) {
		return new WP_Error( 'src', 'База-источник ' . $db . ' не видна текущему пользователю MySQL.' );
	}
	$src = new wpdb( DB_USER, DB_PASSWORD, $db, DB_HOST );
	$src->suppress_errors( true );
	if ( ! empty( $src->error ) || ! $src->dbh ) {
		return new WP_Error( 'src', 'База-источник ' . $db . ' недоступна с текущими учётными данными.' );
	}
	$src->set_prefix( $wpdb->prefix );
	return $src;
}

function maxbit_launch( array $o ): array {
	global $wpdb;
	$dry      = ! empty( $o['dry'] );
	$rehearse = ! empty( $o['rehearse'] ); // репетиция на dev: только проверка, без записи
	$log      = array();
	$fail     = function ( string $m ) use ( &$log ) {
		$log[] = '✗ ' . $m;
		return array( 'ok' => false, 'log' => $log );
	};
	$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

	/* 1. Проверка */
	$log[] = 'Сайт: ' . $host . ', среда ' . MAXBIT_ENV . ', режим формы ' . MAXBIT_FORM_MODE . ', модуль ' . MAXBIT_CORE_VERSION . ', тема ' . wp_get_theme()->get( 'Version' ) . ', база ' . DB_NAME;
	if ( ! $rehearse ) {
		if ( 'production' !== MAXBIT_ENV || 'maxbitpro.ru' !== $host ) {
			return $fail( 'Запуск выполняется только на боевом сайте (production, maxbitpro.ru). Для проверки на dev — rehearse:true.' );
		}
	} elseif ( ! $dry ) {
		return $fail( 'Репетиция — только без записи.' );
	}
	$src_db = (string) ( $o['source_db'] ?? '' );
	if ( $src_db === DB_NAME ) {
		return $fail( 'База-источник совпадает с текущей.' );
	}
	$src = maxbit_launch_src( $src_db );
	if ( is_wp_error( $src ) ) {
		return $fail( $src->get_error_message() );
	}
	if ( ! $src->get_var( "SHOW TABLES LIKE '{$src->posts}'" ) ) {
		return $fail( 'В базе ' . $src_db . ' нет таблицы ' . $src->posts . ' (другой префикс?).' );
	}
	$log[] = '✓ База-источник ' . $src_db . ': ' . (int) $src->get_var( "SELECT COUNT(*) FROM {$src->posts}" ) . ' записей; siteurl ' . $src->get_var( "SELECT option_value FROM {$src->options} WHERE option_name='siteurl'" );

	/* 2. Содержание: что изменилось на источнике */
	$s_rows = $src->get_results( "SELECT ID, post_modified_gmt, post_type, post_status, post_title FROM {$src->posts} WHERE post_type <> 'mb_lead'", OBJECT_K );
	$d_rows = $wpdb->get_results( "SELECT ID, post_modified_gmt, post_type FROM {$wpdb->posts}", OBJECT_K );
	$changed = array();
	$new     = array();
	$clash   = array();
	foreach ( $s_rows as $id => $r ) {
		if ( ! isset( $d_rows[ $id ] ) ) {
			$new[] = (int) $id;
		} elseif ( $d_rows[ $id ]->post_type !== $r->post_type ) {
			$clash[] = (int) $id; // на боевом под этим ID другая запись (например, обращение) — не трогаем
		} elseif ( $d_rows[ $id ]->post_modified_gmt !== $r->post_modified_gmt ) {
			$changed[] = (int) $id;
		}
	}
	$gone = array_values( array_diff( array_map( 'intval', array_keys( array_filter( (array) $d_rows, fn( $r ) => 'mb_lead' !== $r->post_type ) ) ), array_map( 'intval', array_keys( $s_rows ) ) ) );
	$titles = function ( array $ids ) use ( $s_rows ) {
		$t = array();
		foreach ( $ids as $id ) {
			$r = $s_rows[ $id ];
			if ( 'revision' !== $r->post_type ) {
				$t[] = '#' . $id . ' ' . $r->post_type . ' «' . mb_substr( $r->post_title, 0, 60 ) . '» (' . $r->post_status . ')';
			}
		}
		return $t;
	};
	$sync = array_merge( $changed, $new );
	$log[] = sprintf( 'Содержание: изменено на dev %d, новых %d (без ревизий: %d)%s%s', count( $changed ), count( $new ), count( $titles( $sync ) ), $clash ? ', конфликт ID ' . implode( ',', $clash ) . ' — пропущено' : '', $gone ? ', есть только на боевом: ' . count( $gone ) . ' (не удаляются)' : '' );
	foreach ( array_slice( $titles( $sync ), 0, 40 ) as $t ) {
		$log[] = '  · ' . $t;
	}

	/* 3. Настройки */
	$s_set = maybe_unserialize( (string) $src->get_var( "SELECT option_value FROM {$src->options} WHERE option_name='maxbit_settings'" ) );
	$d_set = get_option( 'maxbit_settings', array() );
	$diff  = array();
	if ( is_array( $s_set ) ) {
		foreach ( $s_set as $k => $v ) {
			if ( ( $d_set[ $k ] ?? null ) !== $v ) {
				$diff[] = $k;
			}
		}
	}
	$log[] = is_array( $s_set ) ? ( $diff ? 'Настройки MAXBIT: отличаются ' . implode( ', ', $diff ) . ' — будут взяты с dev' : 'Настройки MAXBIT совпадают' ) : '? Настроек MAXBIT на dev нет — не трогаем';
	if ( is_array( $s_set ) && empty( $s_set['b24_webhook'] ) ) {
		$log[] = '? На dev не задан вебхук Битрикс24';
	}

	/* 4. Файлы загрузок */
	$up      = wp_get_upload_dir()['basedir'];
	$src_dir = (string) ( $o['source_dir'] ?? '' );
	if ( '' === $src_dir ) {
		$src_dir = dirname( ABSPATH ) . '/' . MAXBIT_LAUNCH_FROM . '/wp-content/uploads';
	}
	$files = array();
	if ( is_dir( $src_dir ) && realpath( $src_dir ) !== realpath( $up ) ) {
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $src_dir, FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $f ) {
			$rel = substr( $f->getPathname(), strlen( $src_dir ) + 1 );
			if ( $f->isFile() && ! str_starts_with( $rel, 'maxbit-' ) && ! file_exists( $up . '/' . $rel ) ) {
				$files[] = $rel;
			}
		}
		$log[] = 'Файлы загрузок: недостаёт на боевом ' . count( $files ) . ( $files ? ' (' . implode( ', ', array_slice( $files, 0, 8 ) ) . ( count( $files ) > 8 ? ', …' : '' ) . ')' : '' );
	} else {
		$log[] = '? Папка загрузок dev не найдена (' . $src_dir . ') — файлы не сравниваются';
	}

	/* 5. Адреса */
	$like  = '%' . $wpdb->esc_like( MAXBIT_LAUNCH_FROM ) . '%';
	$log[] = 'Упоминаний ' . MAXBIT_LAUNCH_FROM . ' сейчас в записях боевого сайта: ' . (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s", $like ) ) . ', после переноса содержания будет заменено автоматически';

	if ( $dry ) {
		$log[] = $rehearse ? 'Репетиция: ничего не записано.' : 'Проверка: ничего не записано. Для запуска — confirm:"launch".';
		return array( 'ok' => ! preg_grep( '/^✗/u', $log ), 'dry_run' => true, 'log' => $log );
	}

	/* Резервная копия затронутого на боевом */
	$bk = array( 'created' => gmdate( 'c' ), 'settings' => $d_set, 'posts' => array(), 'meta' => array(), 'rel' => array(), 'new' => $new, 'files' => $files );
	foreach ( $changed as $id ) {
		$bk['posts'][ $id ] = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->posts} WHERE ID=%d", $id ), ARRAY_A );
		$bk['meta'][ $id ]  = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id=%d", $id ), ARRAY_A );
		$bk['rel'][ $id ]   = $wpdb->get_results( $wpdb->prepare( "SELECT term_taxonomy_id, term_order FROM {$wpdb->term_relationships} WHERE object_id=%d", $id ), ARRAY_A );
	}
	$bname = 'maxbit_launch_backup_' . gmdate( 'Ymd_His' );
	add_option( $bname, $bk, '', 'no' );
	if ( ! is_array( get_option( $bname ) ) ) {
		return $fail( 'Не удалось сохранить резервную копию — ничего не изменено.' );
	}
	$log[] = 'Резервная копия: ' . $bname;

	/* Запись: таксономии целиком (малые таблицы), затем записи */
	foreach ( array( 'terms' => 'term_id', 'term_taxonomy' => 'term_taxonomy_id', 'termmeta' => 'meta_id' ) as $t => $pk ) {
		foreach ( $src->get_results( "SELECT * FROM {$src->$t}", ARRAY_A ) as $row ) {
			$wpdb->replace( $wpdb->$t, $row );
		}
	}
	$n = 0;
	foreach ( $sync as $id ) {
		$row = $src->get_row( $src->prepare( "SELECT * FROM {$src->posts} WHERE ID=%d", $id ), ARRAY_A );
		if ( ! $row ) {
			continue;
		}
		$wpdb->replace( $wpdb->posts, $row );
		$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $id ) );
		foreach ( $src->get_results( $src->prepare( "SELECT meta_key, meta_value FROM {$src->postmeta} WHERE post_id=%d", $id ), ARRAY_A ) as $m ) {
			$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $id ) + $m );
		}
		$wpdb->delete( $wpdb->term_relationships, array( 'object_id' => $id ) );
		foreach ( $src->get_results( $src->prepare( "SELECT term_taxonomy_id, term_order FROM {$src->term_relationships} WHERE object_id=%d", $id ), ARRAY_A ) as $tr ) {
			$wpdb->insert( $wpdb->term_relationships, array( 'object_id' => $id ) + $tr );
		}
		clean_post_cache( $id );
		$n++;
	}
	$log[] = '✓ Перенесено записей: ' . $n;
	if ( is_array( $s_set ) && $diff ) {
		update_option( 'maxbit_settings', $s_set );
		$log[] = '✓ Настройки MAXBIT взяты с dev';
	}
	$copied = 0;
	foreach ( $files as $rel ) {
		wp_mkdir_p( dirname( $up . '/' . $rel ) );
		$copied += (int) copy( $src_dir . '/' . $rel, $up . '/' . $rel );
	}
	if ( $files ) {
		$log[] = '✓ Скопировано файлов: ' . $copied . ' из ' . count( $files );
	}

	/* 5. Адреса во всей базе */
	$req = new WP_REST_Request( 'POST' );
	$req->set_param( 'from', MAXBIT_LAUNCH_FROM );
	$req->set_param( 'to', $host );
	$req->set_param( 'confirm', 'migrate' );
	$m = maxbit_update_migrate( $req );
	if ( is_wp_error( $m ) ) {
		$log[] = '✗ Замена адресов: ' . $m->get_error_message();
		return array( 'ok' => false, 'backup' => $bname, 'log' => $log );
	}
	$md    = $m->get_data();
	$log[] = '✓ Адреса ' . MAXBIT_LAUNCH_FROM . ' → ' . $host . ': изменено строк ' . (int) $md['rows_changed'] . ', осталось в записях ' . (int) $md['remaining'];
	wp_cache_flush();
	flush_rewrite_rules( false );
	update_option( 'maxbit_last_launch', array( 'at' => gmdate( 'c' ), 'source' => $src_db, 'posts' => $n, 'backup' => $bname ), false );
	$log[] = 'Готово. Дальше: update/smoke, тестовая заявка, затем update/indexing.';
	return array( 'ok' => true, 'backup' => $bname, 'log' => $log );
}

function maxbit_launch_restore( string $name ): array {
	global $wpdb;
	$b = get_option( $name );
	if ( ! str_starts_with( $name, 'maxbit_launch_backup_' ) || ! is_array( $b ) ) {
		return array( 'ok' => false, 'error' => 'Резервная копия не найдена.' );
	}
	foreach ( $b['posts'] as $id => $row ) {
		$wpdb->replace( $wpdb->posts, $row );
		$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $id ) );
		foreach ( $b['meta'][ $id ] as $m ) {
			$wpdb->insert( $wpdb->postmeta, array( 'post_id' => $id ) + $m );
		}
		$wpdb->delete( $wpdb->term_relationships, array( 'object_id' => $id ) );
		foreach ( $b['rel'][ $id ] as $tr ) {
			$wpdb->insert( $wpdb->term_relationships, array( 'object_id' => $id ) + $tr );
		}
	}
	foreach ( $b['new'] as $id ) {
		$wpdb->delete( $wpdb->posts, array( 'ID' => $id ) );
		$wpdb->delete( $wpdb->postmeta, array( 'post_id' => $id ) );
		$wpdb->delete( $wpdb->term_relationships, array( 'object_id' => $id ) );
	}
	update_option( 'maxbit_settings', $b['settings'] );
	wp_cache_flush();
	return array( 'ok' => true, 'restored' => $name, 'note' => 'Записи и настройки возвращены. Замену адресов откат не отменяет (dev-адреса на боевом не нужны).' );
}

/** Открыть/закрыть индексирование. Только production и только с confirm:"index". */
function maxbit_launch_indexing( bool $open ): array {
	if ( 'production' !== MAXBIT_ENV ) {
		return array( 'ok' => false, 'error' => 'Индексирование открывается только на боевом сайте.' );
	}
	update_option( 'blog_public', $open ? '1' : '0' );
	return array( 'ok' => true, 'blog_public' => get_option( 'blog_public' ) );
}

/** Проверка по живым адресам с самого сервера: коды, переадресации, следы dev, noindex, Метрика. */
function maxbit_launch_smoke(): array {
	$paths = array( '/', '/resheniya/', '/resheniya/zakaz-pod-kontrolem/', '/tekhnologii/', '/proekty/', '/baza-znaniy/', '/o-kompanii/', '/politika-obrabotki-personalnyh-dannyh/', '/robots.txt', '/sitemap.xml', '/llms.txt', '/net-takoj-stranicy-404/' );
	$s     = maxbit_get_settings();
	$out   = array();
	foreach ( $paths as $p ) {
		$r = wp_remote_get( home_url( $p ), array( 'redirection' => 0, 'timeout' => 15, 'sslverify' => true, 'headers' => array( 'Cache-Control' => 'no-cache' ) ) );
		if ( is_wp_error( $r ) ) {
			$out[] = '✗ ' . $p . ': ' . $r->get_error_message();
			continue;
		}
		$code = (int) wp_remote_retrieve_response_code( $r );
		$body = (string) wp_remote_retrieve_body( $r );
		$loc  = (string) wp_remote_retrieve_header( $r, 'location' );
		$note = array();
		if ( $loc ) {
			$note[] = '→ ' . $loc;
		}
		if ( str_contains( $body, MAXBIT_LAUNCH_FROM ) ) {
			$note[] = 'есть ссылки на dev';
		}
		if ( str_ends_with( $p, '/' ) && 200 === $code ) {
			$note[] = preg_match( '/<meta name=[\'"]robots[\'"][^>]*noindex/i', $body ) ? 'noindex' : 'индексируется';
			if ( '/' === $p ) {
				$note[] = $s['metrika_id'] && str_contains( $body, (string) $s['metrika_id'] ) ? 'Метрика есть' : 'Метрики нет';
				$note[] = $s['yandex_verification'] && str_contains( $body, 'yandex-verification' ) ? 'код Вебмастера есть' : 'кода Вебмастера нет';
			}
		}
		$want = '/net-takoj-stranicy-404/' === $p ? 404 : 200;
		$out[] = ( $code === $want && ! $loc ? '✓ ' : '✗ ' ) . $p . ': ' . $code . ( $note ? ' (' . implode( '; ', $note ) . ')' : '' );
	}
	return array( 'ok' => ! preg_grep( '/^✗/u', $out ), 'home' => home_url( '/' ), 'log' => $out );
}

add_action( 'rest_api_init', function () {
	$perm = fn() => current_user_can( 'manage_options' );
	register_rest_route( 'maxbit/v1', '/update/launch', array(
		'methods' => 'POST', 'permission_callback' => $perm,
		'callback' => fn( WP_REST_Request $r ) => rest_ensure_response( maxbit_launch( array(
			'source_db'  => (string) $r->get_param( 'source_db' ),
			'source_dir' => (string) $r->get_param( 'source_dir' ),
			'rehearse'   => (bool) $r->get_param( 'rehearse' ),
			'dry'        => 'launch' !== (string) $r->get_param( 'confirm' ),
		) ) ),
	) );
	register_rest_route( 'maxbit/v1', '/update/launch-restore', array(
		'methods' => 'POST', 'permission_callback' => $perm,
		'callback' => fn( WP_REST_Request $r ) => 'restore' === $r->get_param( 'confirm' ) ? rest_ensure_response( maxbit_launch_restore( (string) $r->get_param( 'backup' ) ) ) : new WP_Error( 'confirm', 'Нужно confirm: "restore"', array( 'status' => 400 ) ),
	) );
	register_rest_route( 'maxbit/v1', '/update/indexing', array(
		'methods' => 'POST', 'permission_callback' => $perm,
		'callback' => fn( WP_REST_Request $r ) => 'index' === $r->get_param( 'confirm' ) ? rest_ensure_response( maxbit_launch_indexing( (bool) $r->get_param( 'open' ) ) ) : new WP_Error( 'confirm', 'Нужно confirm: "index"', array( 'status' => 400 ) ),
	) );
	register_rest_route( 'maxbit/v1', '/update/smoke', array(
		'methods' => 'POST', 'permission_callback' => $perm,
		'callback' => fn() => rest_ensure_response( maxbit_launch_smoke() ),
	) );
} );
