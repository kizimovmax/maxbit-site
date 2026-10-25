<?php
/**
 * Маршрутизатор встроенного сервера PHP (только локальная разработка).
 * Статика отдаётся: (а) из site/ и (б) из каталогов assets темы и модуля в src/, которые
 * подключены в site/wp-content симлинками (realpath уводит за пределы site/, поэтому
 * их разрешаем явно). Ничего другого из src/, docs/, logs/ и корня не отдаётся.
 */
$uri  = urldecode( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) );
$root = realpath( __DIR__ . '/../site' );
$allowed_static_roots = array_filter( array(
	$root,
	realpath( __DIR__ . '/../src/themes/maxbit/assets' ),
	realpath( __DIR__ . '/../src/plugins/maxbit-core/assets' ),
	realpath( __DIR__ . '/../src-tz/themes/maxbit/assets' ),      // рабочая копия под точечные ТЗ (если есть)
	realpath( __DIR__ . '/../src-tz/plugins/maxbit-core/assets' ),
) );
$mime = array(
	'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8', 'svg' => 'image/svg+xml',
	'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif', 'webp' => 'image/webp',
	'ico' => 'image/x-icon', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'json' => 'application/json', 'txt' => 'text/plain; charset=utf-8',
	'map' => 'application/json', 'html' => 'text/html; charset=utf-8',
);
if ( strpos( $uri, '..' ) === false && strpos( $uri, "\0" ) === false ) {
	$candidate = $root . $uri;
	$file      = realpath( $candidate );
	if ( $file && is_file( $file ) ) {
		$inside = false;
		foreach ( $allowed_static_roots as $base ) {
			if ( strpos( $file, $base . DIRECTORY_SEPARATOR ) === 0 ) {
				$inside = true;
				break;
			}
		}
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		if ( $inside && 'php' !== $ext && basename( $file )[0] !== '.' ) {
			if ( isset( $mime[ $ext ] ) ) {
				header( 'Content-Type: ' . $mime[ $ext ] );
				header( 'Content-Length: ' . filesize( $file ) );
				header( 'Cache-Control: no-cache' );
				readfile( $file );
				return true;
			}
			return false; // прочие типы — стандартная выдача сервера (только внутри site/)
		}
		if ( $inside && 'php' === $ext && strpos( $file, $root . DIRECTORY_SEPARATOR ) === 0 ) {
			// Прямой запуск разрешён только штатным точкам входа; остальные php (db.php, файлы тем/плагинов) — 404.
			$rel = substr( $file, strlen( $root ) );
			if ( preg_match( '#^/(index\.php|wp-login\.php|wp-cron\.php|xmlrpc\.php|wp-comments-post\.php|wp-admin/.*\.php)$#', $rel ) ) {
				return false;
			}
			http_response_code( 404 );
			return true;
		}
	}
	if ( $file && is_dir( $file ) && strpos( $file, $root . DIRECTORY_SEPARATOR ) === 0 && is_file( $file . '/index.php' ) && '/' !== $uri ) {
		$_SERVER['SCRIPT_NAME'] = rtrim( $uri, '/' ) . '/index.php';
		require $file . '/index.php';
		return true;
	}
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root . '/index.php';
