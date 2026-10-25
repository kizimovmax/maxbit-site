<?php
defined( 'ABSPATH' ) || exit;

/**
 * Точка обновления темы и модуля MAXBIT без ручной загрузки ZIP через панель.
 *
 * REST (только администратор с правами install_plugins/install_themes; аутентификация — cookie+nonce
 * из админки или «пароль приложения» по HTTPS):
 *   GET  /maxbit/v1/update/status            — версии, среда, резервные копии, остатки в staging
 *   POST /maxbit/v1/update/fetch             — {url, sha256, type: plugin|theme, seed?: 0|1}
 *                                               сервер сам скачивает ZIP по https-ссылке (0.3.2) — один запрос
 *   POST /maxbit/v1/update/upload            — кусок ZIP в base64: {id, index, total, data} (запасной путь)
 *   POST /maxbit/v1/update/apply             — {id, type: plugin|theme, sha256?, seed?: 0|1}
 *   POST /maxbit/v1/update/cleanup           — удалить остатки *.part / *.zip из staging (0.3.2)
 *
 * Для fetch контрольная сумма SHA-256 обязательна; ссылка — только https (http допускается лишь в среде local),
 * только публичные адреса (wp_safe_remote_get), размер пакета — до MAXBIT_UPDATE_MAX_BYTES (20 МБ).
 * Перед заменой текущая папка темы/модуля копируется в wp-content/maxbit-backups/ (хранятся 5 последних).
 * ZIP принимается только если его корневая папка — maxbit-core (модуль) или maxbit (тема).
 * База данных не изменяется; наполнение запускается только по явному флагу seed=1.
 */

function maxbit_update_can(): bool {
	return current_user_can( 'install_plugins' ) && current_user_can( 'install_themes' ) && current_user_can( 'manage_options' );
}

function maxbit_update_dir(): string {
	$dir = WP_CONTENT_DIR . '/maxbit-updates';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		file_put_contents( $dir . '/index.php', "<?php // silence\n" );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
	}
	return $dir;
}

function maxbit_backup_dir(): string {
	$dir = WP_CONTENT_DIR . '/maxbit-backups';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	if ( ! file_exists( $dir . '/index.php' ) ) {
		file_put_contents( $dir . '/index.php', "<?php // silence\n" );
	}
	if ( ! file_exists( $dir . '/.htaccess' ) ) {
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
	}
	return $dir;
}

/** Рекурсивное копирование каталога (резервная копия перед заменой). */
function maxbit_copy_dir( string $from, string $to ): bool {
	if ( ! is_dir( $from ) ) {
		return false;
	}
	wp_mkdir_p( $to );
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $from, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::SELF_FIRST );
	foreach ( $it as $item ) {
		$target = $to . '/' . $it->getSubPathname();
		if ( $item->isDir() ) {
			wp_mkdir_p( $target );
		} else {
			copy( $item->getPathname(), $target );
		}
	}
	return true;
}

function maxbit_rrmdir( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
	foreach ( $it as $item ) {
		$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}
	rmdir( $dir );
}

function maxbit_update_backups(): array {
	$out = array();
	foreach ( glob( maxbit_backup_dir() . '/*', GLOB_ONLYDIR ) ?: array() as $d ) {
		$out[] = basename( $d );
	}
	rsort( $out );
	return $out;
}

/** Оставить не более 5 резервных копий на каждый тип. */
function maxbit_update_prune_backups(): void {
	foreach ( array( 'plugin', 'theme' ) as $t ) {
		$list = array_values( array_filter( maxbit_update_backups(), fn( $n ) => str_starts_with( $n, $t . '-' ) ) );
		foreach ( array_slice( $list, 5 ) as $old ) {
			maxbit_rrmdir( maxbit_backup_dir() . '/' . $old );
		}
	}
}

if ( ! defined( 'MAXBIT_UPDATE_MAX_BYTES' ) ) {
	define( 'MAXBIT_UPDATE_MAX_BYTES', 20 * 1024 * 1024 );
}

/** Остатки в staging: имена файлов *.part / *.zip (без путей). */
function maxbit_update_staging(): array {
	$dir = maxbit_update_dir();
	$out = array();
	foreach ( array_merge( glob( "$dir/*.part" ) ?: array(), glob( "$dir/*.zip" ) ?: array() ) as $f ) {
		$out[] = array( 'name' => basename( $f ), 'bytes' => filesize( $f ) );
	}
	return $out;
}

function maxbit_update_status(): array {
	$theme = wp_get_theme( 'maxbit' );
	return array(
		'ok'             => true,
		'env'            => MAXBIT_ENV,
		'plugin_version' => MAXBIT_CORE_VERSION,
		'theme_version'  => $theme->exists() ? $theme->get( 'Version' ) : null,
		'theme_active'   => get_stylesheet() === 'maxbit',
		'seed_version'   => get_option( 'maxbit_seed_version', '' ),
		'wp_version'     => get_bloginfo( 'version' ),
		'php_version'    => PHP_VERSION,
		'backups'        => maxbit_update_backups(),
		'staging'        => maxbit_update_staging(),
		'fs_writable'    => wp_is_writable( WP_PLUGIN_DIR ) && wp_is_writable( get_theme_root() ),
		'capabilities'   => array( 'fetch', 'upload', 'apply', 'cleanup', 'seed' ),
	);
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'maxbit/v1', '/update/status', array(
		'methods'             => 'GET',
		'permission_callback' => 'maxbit_update_can',
		'callback'            => fn() => new WP_REST_Response( maxbit_update_status(), 200 ),
	) );
	register_rest_route( 'maxbit/v1', '/update/upload', array(
		'methods'             => 'POST',
		'permission_callback' => 'maxbit_update_can',
		'callback'            => 'maxbit_update_upload',
	) );
	register_rest_route( 'maxbit/v1', '/update/apply', array(
		'methods'             => 'POST',
		'permission_callback' => 'maxbit_update_can',
		'callback'            => 'maxbit_update_apply',
	) );
	register_rest_route( 'maxbit/v1', '/update/fetch', array(
		'methods'             => 'POST',
		'permission_callback' => 'maxbit_update_can',
		'callback'            => 'maxbit_update_fetch',
	) );
	register_rest_route( 'maxbit/v1', '/update/migrate', array(
		'methods'             => 'POST',
		'permission_callback' => 'maxbit_update_can',
		'callback'            => 'maxbit_update_migrate',
	) );
	register_rest_route( 'maxbit/v1', '/update/cleanup', array(
		'methods'             => 'POST',
		'permission_callback' => 'maxbit_update_can',
		'callback'            => 'maxbit_update_cleanup',
	) );
} );

/** Удаление остатков staging (части прерванных передач и собранные ZIP). Темы/модуль/базу не трогает. */
function maxbit_update_cleanup() {
	$dir     = maxbit_update_dir();
	$removed = array();
	foreach ( array_merge( glob( "$dir/*.part" ) ?: array(), glob( "$dir/*.zip" ) ?: array() ) as $f ) {
		if ( @unlink( $f ) ) {
			$removed[] = basename( $f );
		}
	}
	return new WP_REST_Response( array( 'ok' => true, 'removed' => $removed, 'left' => maxbit_update_staging() ), 200 );
}

/** Скачивание пакета по ссылке самим сервером и установка — один запрос. */
function maxbit_update_fetch( WP_REST_Request $r ) {
	$url  = trim( (string) $r->get_param( 'url' ) );
	$type = (string) $r->get_param( 'type' );
	$sha  = strtolower( trim( (string) $r->get_param( 'sha256' ) ) );
	$seed = '1' === (string) $r->get_param( 'seed' );
	if ( ! in_array( $type, array( 'plugin', 'theme' ), true ) ) {
		return new WP_Error( 'bad_request', 'Нужен type (plugin|theme).', array( 'status' => 400 ) );
	}
	if ( ! preg_match( '/^[0-9a-f]{64}$/', $sha ) ) {
		return new WP_Error( 'bad_request', 'Для fetch обязательна sha256 (64 hex-символа).', array( 'status' => 400 ) );
	}
	$scheme = strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) );
	if ( 'https' !== $scheme && ! ( 'http' === $scheme && 'local' === MAXBIT_ENV ) ) {
		return new WP_Error( 'bad_url', 'Ссылка должна быть https.', array( 'status' => 400 ) );
	}
	if ( ! wp_http_validate_url( $url ) ) {
		return new WP_Error( 'bad_url', 'Ссылка не прошла проверку (закрытые адреса и нестандартные порты запрещены).', array( 'status' => 400 ) );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	$tmp = download_url( $url, 300 );
	if ( is_wp_error( $tmp ) ) {
		return new WP_Error( 'download_failed', 'Не удалось скачать пакет: ' . $tmp->get_error_message(), array( 'status' => 502 ) );
	}
	$size = (int) filesize( $tmp );
	if ( $size < 1024 || $size > MAXBIT_UPDATE_MAX_BYTES ) {
		@unlink( $tmp );
		return new WP_Error( 'bad_size', 'Размер пакета вне допустимого (1 КБ … ' . size_format( MAXBIT_UPDATE_MAX_BYTES ) . '): ' . size_format( $size ), array( 'status' => 400 ) );
	}
	$zip = maxbit_update_dir() . '/fetch-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false, false ) . '.zip';
	if ( ! @rename( $tmp, $zip ) && ! ( copy( $tmp, $zip ) && @unlink( $tmp ) ) ) {
		@unlink( $tmp );
		return new WP_Error( 'fs', 'Не удалось сохранить пакет в staging.', array( 'status' => 500 ) );
	}
	$log = array( 'Скачано: ' . size_format( $size ) . ' с ' . wp_parse_url( $url, PHP_URL_HOST ) );
	return maxbit_update_install_zip( $zip, $type, $sha, $seed, $log );
}

/** Приём куска ZIP (base64). Куски складываются в файл <id>.part; порядок — по index. */
function maxbit_update_upload( WP_REST_Request $r ) {
	$id    = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $r->get_param( 'id' ) ) );
	$index = (int) $r->get_param( 'index' );
	$total = (int) $r->get_param( 'total' );
	$data  = (string) $r->get_param( 'data' );
	if ( ! $id || strlen( $id ) < 6 || $index < 0 || $total < 1 || $index >= $total || '' === $data ) {
		return new WP_Error( 'bad_request', 'Неверные параметры куска.', array( 'status' => 400 ) );
	}
	$bin = base64_decode( $data, true );
	if ( false === $bin || strlen( $bin ) > 2 * 1024 * 1024 ) {
		return new WP_Error( 'bad_chunk', 'Кусок не декодируется или слишком велик.', array( 'status' => 400 ) );
	}
	$dir  = maxbit_update_dir();
	$part = "$dir/$id.$index.part";
	if ( false === file_put_contents( $part, $bin ) ) {
		return new WP_Error( 'fs', 'Не удалось записать кусок.', array( 'status' => 500 ) );
	}
	$have = count( glob( "$dir/$id.*.part" ) ?: array() );
	return new WP_REST_Response( array( 'ok' => true, 'received' => $have, 'total' => $total ), 200 );
}

/** Сборка ZIP из кусков, проверка, резервная копия, установка штатным установщиком. */
function maxbit_update_apply( WP_REST_Request $r ) {
	$id     = preg_replace( '/[^a-z0-9\-]/', '', strtolower( (string) $r->get_param( 'id' ) ) );
	$type   = (string) $r->get_param( 'type' );
	$sha    = strtolower( (string) $r->get_param( 'sha256' ) );
	$seed   = '1' === (string) $r->get_param( 'seed' );
	$log    = array();
	if ( ! $id || ! in_array( $type, array( 'plugin', 'theme' ), true ) ) {
		return new WP_Error( 'bad_request', 'Нужны id и type (plugin|theme).', array( 'status' => 400 ) );
	}
	$dir   = maxbit_update_dir();
	$parts = glob( "$dir/$id.*.part" ) ?: array();
	if ( ! $parts ) {
		return new WP_Error( 'no_parts', 'Куски не найдены — загрузите пакет заново.', array( 'status' => 400 ) );
	}
	usort( $parts, fn( $a, $b ) => (int) explode( '.', basename( $a ) )[1] <=> (int) explode( '.', basename( $b ) )[1] );
	$zip = "$dir/$id.zip";
	$fh  = fopen( $zip, 'wb' );
	foreach ( $parts as $p ) {
		fwrite( $fh, (string) file_get_contents( $p ) );
		unlink( $p );
	}
	fclose( $fh );
	$log[] = 'Пакет собран: ' . size_format( filesize( $zip ) );
	return maxbit_update_install_zip( $zip, $type, $sha, $seed, $log );
}

/**
 * Общая часть: проверка SHA-256 и структуры ZIP, резервная копия, установка штатным установщиком.
 * ZIP удаляется в любом исходе. $sha пустая — проверка пропускается (только для upload/apply).
 */
function maxbit_update_install_zip( string $zip, string $type, string $sha, bool $seed, array $log ) {
	if ( $sha && hash_file( 'sha256', $zip ) !== $sha ) {
		unlink( $zip );
		return new WP_Error( 'sha_mismatch', 'Контрольная сумма пакета не совпадает — установка отменена.', array( 'status' => 400 ) );
	}
	$log[] = $sha ? 'SHA-256 совпадает' : 'SHA-256 не передан (проверка пропущена)';

	/* Корневая папка внутри ZIP должна быть ожидаемой */
	$expect = 'plugin' === $type ? 'maxbit-core' : 'maxbit';
	$za = new ZipArchive();
	if ( true !== $za->open( $zip ) ) {
		unlink( $zip );
		return new WP_Error( 'bad_zip', 'ZIP не открывается.', array( 'status' => 400 ) );
	}
	$roots = array();
	for ( $i = 0; $i < $za->numFiles; $i++ ) {
		$n = $za->getNameIndex( $i );
		if ( str_contains( $n, '..' ) ) {
			$za->close(); unlink( $zip );
			return new WP_Error( 'bad_zip', 'Недопустимый путь в архиве.', array( 'status' => 400 ) );
		}
		$roots[ explode( '/', $n )[0] ] = true;
	}
	$main_ok = 'plugin' === $type ? $za->locateName( 'maxbit-core/maxbit-core.php' ) !== false : $za->locateName( 'maxbit/style.css' ) !== false;
	$za->close();
	if ( array_keys( $roots ) !== array( $expect ) || ! $main_ok ) {
		unlink( $zip );
		return new WP_Error( 'wrong_package', "Архив должен содержать единственную папку «{$expect}» с главным файлом.", array( 'status' => 400 ) );
	}

	/* Резервная копия текущей версии */
	$current = 'plugin' === $type ? WP_PLUGIN_DIR . '/maxbit-core' : get_theme_root() . '/maxbit';
	if ( is_link( $current ) ) {
		/* Штатный установщик удаляет старую версию «сквозь» симлинк — потеряется исходник (локальная разработка). */
		unlink( $zip );
		return new WP_Error( 'symlink', 'Текущая папка — символическая ссылка; обновление через точку недоступно, используйте исходники напрямую.', array( 'status' => 409 ) );
	}
	$ver_before = 'plugin' === $type
		? MAXBIT_CORE_VERSION
		: ( is_file( $current . '/style.css' ) ? ( get_file_data( $current . '/style.css', array( 'v' => 'Version' ) )['v'] ?: '0' ) : '0' );
	$bk = maxbit_backup_dir() . '/' . $type . '-' . $ver_before . '-' . gmdate( 'Ymd-His' );
	if ( is_dir( $current ) ) {
		maxbit_copy_dir( $current, $bk );
		$log[] = 'Резервная копия: ' . basename( $bk );
	}

	/* Установка штатным установщиком с перезаписью */
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	require_once ABSPATH . 'wp-admin/includes/theme.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	if ( ! WP_Filesystem() ) {
		return new WP_Error( 'fs', 'Файловая система недоступна для записи (нужен прямой доступ без FTP).', array( 'status' => 500 ) );
	}
	$skin = new Automatic_Upgrader_Skin();
	ob_start();
	if ( 'plugin' === $type ) {
		$was_active = is_plugin_active( 'maxbit-core/maxbit-core.php' );
		$up = new Plugin_Upgrader( $skin );
		$res = $up->install( $zip, array( 'overwrite_package' => true, 'clear_update_cache' => true ) );
		if ( true === $res || ( is_array( $res ) && ! is_wp_error( $res ) ) ) {
			if ( $was_active || ! is_plugin_active( 'maxbit-core/maxbit-core.php' ) ) {
				$act = activate_plugin( 'maxbit-core/maxbit-core.php', '', false, true );
				$log[] = is_wp_error( $act ) ? 'Активация: ' . $act->get_error_message() : 'Модуль активен';
			}
		}
	} else {
		$up = new Theme_Upgrader( $skin );
		$res = $up->install( $zip, array( 'overwrite_package' => true, 'clear_update_cache' => true ) );
		if ( ( true === $res || ( is_array( $res ) && ! is_wp_error( $res ) ) ) && get_stylesheet() !== 'maxbit' ) {
			switch_theme( 'maxbit' );
			$log[] = 'Тема активирована';
		}
	}
	ob_end_clean();
	@unlink( $zip );
	$messages = array_map( 'wp_strip_all_tags', (array) $skin->get_upgrade_messages() );
	$log = array_merge( $log, array_filter( $messages ) );
	if ( is_wp_error( $res ) || false === $res || null === $res ) {
		$err = is_wp_error( $res ) ? $res->get_error_message() : 'установщик вернул отказ';
		return new WP_REST_Response( array( 'ok' => false, 'error' => $err, 'log' => $log, 'backup' => basename( $bk ) ), 500 );
	}
	maxbit_update_prune_backups();

	$seed_log = array();
	if ( $seed && 'plugin' === $type ) {
		/* новая версия кода ещё не загружена в этом процессе — наполнение выполнит отдельный запрос */
		$seed_log[] = 'Наполнение запускается отдельным вызовом /maxbit/v1/update/seed после обновления.';
	}
	$ver_after = 'plugin' === $type
		? ( get_file_data( WP_PLUGIN_DIR . '/maxbit-core/maxbit-core.php', array( 'v' => 'Version' ) )['v'] ?? '?' )
		: ( get_file_data( get_theme_root() . '/maxbit/style.css', array( 'v' => 'Version' ) )['v'] ?? '?' );
	$log[] = "Версия: {$ver_before} → {$ver_after}";
	return new WP_REST_Response( array( 'ok' => true, 'type' => $type, 'before' => $ver_before, 'after' => $ver_after, 'backup' => basename( $bk ), 'log' => array_merge( $log, $seed_log ) ), 200 );
}

/** Наполнение по запросу (после обновления модуля) — тот же код, что кнопка «MAXBIT → Наполнение». */
add_action( 'rest_api_init', function () {
	register_rest_route( 'maxbit/v1', '/update/seed', array(
		'methods'             => 'POST',
		'permission_callback' => 'maxbit_update_can',
		'callback'            => function () {
			if ( ! function_exists( 'maxbit_seed_run' ) ) {
				return new WP_Error( 'no_seed', 'Функция наполнения недоступна.', array( 'status' => 500 ) );
			}
			$log = maxbit_seed_run();
			return new WP_REST_Response( array( 'ok' => true, 'log' => $log ), 200 );
		},
	) );
} );

/**
 * Перенос адреса сайта в базе (0.4.0): замена старого хоста на новый в options, posts (content/excerpt/guid)
 * и postmeta с учётом сериализованных значений. {from, to, dry_run?: 1, confirm?: "migrate"}.
 * Сухой прогон — всегда без записи. Запись — только с confirm=migrate и после резервной копии (ответственность владельца).
 */
function maxbit_update_migrate( WP_REST_Request $r ) {
	global $wpdb;
	$from = strtolower( trim( (string) $r->get_param( 'from' ) ) );
	$to   = strtolower( trim( (string) $r->get_param( 'to' ) ) );
	$dry  = '0' !== (string) $r->get_param( 'dry_run' ) && 'migrate' !== (string) $r->get_param( 'confirm' );
	$host_re = '/^[a-z0-9.-]+\.[a-z]{2,}$/';
	if ( ! preg_match( $host_re, $from ) || ! preg_match( $host_re, $to ) || $from === $to || str_contains( $to, $from ) ) {
		return new WP_Error( 'bad_request', 'Нужны from и to — имена хостов (например dev.maxbitpro.ru → maxbitpro.ru), разные, новый не содержит старый.', array( 'status' => 400 ) );
	}
	$replace = function ( $value ) use ( $from, $to, &$replace ) {
		if ( is_string( $value ) ) {
			if ( is_serialized( $value ) ) {
				$un = @unserialize( $value, array( 'allowed_classes' => false ) );
				if ( false !== $un || 'b:0;' === $value ) {
					return serialize( $replace( $un ) );
				}
			}
			return str_replace( $from, $to, $value );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = $replace( $v );
			}
		}
		return $value;
	};
	$like   = '%' . $wpdb->esc_like( $from ) . '%';
	$report = array( 'from' => $from, 'to' => $to, 'dry_run' => $dry, 'tables' => array() );
	$total  = 0;
	if ( ! $dry ) {
		$wpdb->query( 'START TRANSACTION' );
	}
	try {
		/* options */
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_id, option_name, option_value FROM {$wpdb->options} WHERE option_value LIKE %s", $like ) );
		$n = 0;
		foreach ( $rows as $row ) {
			$new = $replace( $row->option_value );
			if ( $new !== $row->option_value ) {
				$n++;
				if ( ! $dry ) {
					$wpdb->update( $wpdb->options, array( 'option_value' => $new ), array( 'option_id' => $row->option_id ) );
				}
			}
		}
		$report['tables']['options'] = $n; $total += $n;
		/* posts */
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT ID, post_content, post_excerpt, guid FROM {$wpdb->posts} WHERE post_content LIKE %s OR post_excerpt LIKE %s OR guid LIKE %s", $like, $like, $like ) );
		$n = 0;
		foreach ( $rows as $row ) {
			$upd = array();
			foreach ( array( 'post_content', 'post_excerpt', 'guid' ) as $f ) {
				$new = str_replace( $from, $to, $row->$f );
				if ( $new !== $row->$f ) {
					$upd[ $f ] = $new;
				}
			}
			if ( $upd ) {
				$n++;
				if ( ! $dry ) {
					$wpdb->update( $wpdb->posts, $upd, array( 'ID' => $row->ID ) );
					clean_post_cache( (int) $row->ID );
				}
			}
		}
		$report['tables']['posts'] = $n; $total += $n;
		/* postmeta */
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT meta_id, post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_value LIKE %s", $like ) );
		$n = 0;
		foreach ( $rows as $row ) {
			$new = $replace( $row->meta_value );
			if ( $new !== $row->meta_value ) {
				$n++;
				if ( ! $dry ) {
					$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $new ), array( 'meta_id' => $row->meta_id ) );
				}
			}
		}
		$report['tables']['postmeta'] = $n; $total += $n;
		/* termmeta, usermeta — редко, но дёшево */
		foreach ( array( 'termmeta' => array( 'meta_id', 'meta_value' ), 'usermeta' => array( 'umeta_id', 'meta_value' ) ) as $t => $cols ) {
			$table = $wpdb->$t;
			$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT {$cols[0]} AS id, {$cols[1]} AS v FROM {$table} WHERE {$cols[1]} LIKE %s", $like ) );
			$n = 0;
			foreach ( $rows as $row ) {
				$new = $replace( $row->v );
				if ( $new !== $row->v ) {
					$n++;
					if ( ! $dry ) {
						$wpdb->update( $table, array( $cols[1] => $new ), array( $cols[0] => $row->id ) );
					}
				}
			}
			$report['tables'][ $t ] = $n; $total += $n;
		}
		if ( ! $dry ) {
			$wpdb->query( 'COMMIT' );
			wp_cache_flush();
			update_option( 'maxbit_last_migration', array( 'at' => gmdate( 'c' ), 'from' => $from, 'to' => $to, 'rows' => $total ), false );
			flush_rewrite_rules( false );
		}
	} catch ( Throwable $e ) {
		if ( ! $dry ) {
			$wpdb->query( 'ROLLBACK' );
		}
		return new WP_Error( 'migrate_failed', 'Перенос прерван, изменения отменены: ' . $e->getMessage(), array( 'status' => 500 ) );
	}
	$report['rows_changed'] = $total;
	$report['remaining']    = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_content LIKE %s", $like ) );
	$report['home']         = home_url( '/' );
	$report['note']         = $dry ? 'Сухой прогон: ничего не записано. Для записи передайте confirm=migrate (после резервной копии базы).' : 'Записано. Проверьте страницы, canonical и sitemap; при WP_HOME/WP_SITEURL в wp-config они имеют приоритет.';
	return new WP_REST_Response( $report, 200 );
}
