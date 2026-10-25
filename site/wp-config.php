<?php
/**
 * MAXBIT — локальная конфигурация (LOCAL_TEST).
 * Секретов здесь нет: соли генерируются в wp-config-local.php при первом запуске.
 * База — SQLite (плагин sqlite-database-integration), файл wp-content/database/.ht.sqlite.
 */

// Значения БД нужны ядру только формально: работает SQLite через wp-content/db.php.
define( 'DB_NAME', 'maxbit_local' );
define( 'DB_USER', 'local' );
define( 'DB_PASSWORD', 'local' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
$table_prefix = 'mb_';

// Режим окружения сайта: local | staging | production.
// Черновики, noindex и адаптеры зависят от него (см. maxbit-core).
// Для локальных проверок среду можно переопределить переменной окружения (только этот конфиг разработки).
define( 'MAXBIT_ENV', getenv( 'MAXBIT_ENV' ) ?: 'local' );
if ( ! empty( $_SERVER['HTTP_HOST'] ) && preg_match( '/^(localhost|127\.0\.0\.1)(:\d+)?$/', $_SERVER['HTTP_HOST'] ) ) {
	// Этот wp-config — только для локальной разработки: адрес сайта следует за localhost-портом запроса
	// (проверки на других портах и в режиме production-эмуляции не редиректят). На хостинге — свой конфиг.
	define( 'WP_HOME', 'http://' . $_SERVER['HTTP_HOST'] );
	define( 'WP_SITEURL', WP_HOME );
}
define( 'MAXBIT_FORM_MODE', getenv( 'MAXBIT_FORM_MODE' ) ?: 'LOCAL_TEST' );   // единственный разрешённый режим на этой итерации
define( 'MAXBIT_B24_WEBHOOK_URL', '' );               // пусто = адаптер доставки выключен
if ( getenv( 'MAXBIT_DELIVERY_TEST_URL' ) ) {
	define( 'MAXBIT_DELIVERY_TEST_URL', getenv( 'MAXBIT_DELIVERY_TEST_URL' ) );   // локальный тестовый приёмник
}
define( 'MAXBIT_ANALYTICS_ENABLED', false );          // реальные счётчики выключены

// Соли/ключи — локальный файл вне Git (создаётся scripts/setup.sh).
if ( file_exists( __DIR__ . '/wp-config-local.php' ) ) {
	require_once __DIR__ . '/wp-config-local.php';
}
foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $k ) {
	if ( ! defined( $k ) ) {
		if ( 'local' !== MAXBIT_ENV ) {
			exit( 'Секреты не настроены: создайте wp-config-local.php с солями (scripts/setup.sh или вручную).' );
		}
		define( $k, 'local-only-please-run-scripts-setup-' . $k );
	}
}

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', __DIR__ . '/../logs/debug.log' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'DISALLOW_FILE_EDIT', true );
define( 'DISALLOW_FILE_MODS', true );   // никаких установок плагинов из панели
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_AUTO_UPDATE_CORE', false );
define( 'WP_POST_REVISIONS', 10 );
define( 'WP_ENVIRONMENT_TYPE', 'local' );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
require_once ABSPATH . 'wp-settings.php';
