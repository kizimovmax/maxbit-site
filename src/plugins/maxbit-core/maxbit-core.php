<?php
/**
 * Plugin Name: MAXBIT Core
 * Description: Типы материалов, связи, SEO-поля, допуск к публикации и серверный приём обращений для сайта MAXBIT. Не зависит от темы.
 * Version: 0.7.0
 * Requires at least: 6.6
 * Requires PHP: 8.1
 * Author: MAXBIT
 * Text Domain: maxbit
 */

defined( 'ABSPATH' ) || exit;

define( 'MAXBIT_CORE_VERSION', '0.7.0' );
define( 'MAXBIT_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAXBIT_CORE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Среда: константа в wp-config.php имеет приоритет. Без константы берётся настройка
 * «MAXBIT → Среда» (local|staging); значение production через настройку недоступно —
 * только явной константой в конфиге хостинга. По умолчанию — staging (noindex, тестовый режим).
 */
if ( defined( 'MAXBIT_ENV' ) ) {
	define( 'MAXBIT_ENV_FROM_CONFIG', true );
} else {
	$maxbit_env_opt = get_option( 'maxbit_env', 'staging' );
	define( 'MAXBIT_ENV', in_array( $maxbit_env_opt, array( 'local', 'staging' ), true ) ? $maxbit_env_opt : 'staging' );
	unset( $maxbit_env_opt );
}
function maxbit_env(): string {
	return MAXBIT_ENV;
}
if ( ! defined( 'MAXBIT_FORM_MODE' ) ) {
	define( 'MAXBIT_FORM_MODE', 'LOCAL_TEST' );   // рабочий режим — только константой в конфиге
}
/** Почтовые отправки WordPress вне production выключены (настройка «MAXBIT → Среда»). */
add_filter( 'pre_wp_mail', function ( $short_circuit, $atts ) {
	if ( 'production' !== MAXBIT_ENV && '1' === (string) get_option( 'maxbit_disable_mail', '1' ) ) {
		return true; // письмо не отправляется, wp_mail() возвращает true
	}
	return $short_circuit;
}, 10, 2 );
if ( ! defined( 'MAXBIT_B24_WEBHOOK_URL' ) ) {
	define( 'MAXBIT_B24_WEBHOOK_URL', '' );
}
if ( ! defined( 'MAXBIT_ANALYTICS_ENABLED' ) ) {
	define( 'MAXBIT_ANALYTICS_ENABLED', false );
}

/** Допустимые темы обращения (серверный белый список). */
function maxbit_lead_topics(): array {
	return array(
		'general'       => 'Общая задача',
		'order_control' => 'Управление заказами',
		'bitrix24'      => 'Битрикс24 для производства',
		'integration'   => 'Интеграция Битрикс24 и 1С',
		'ai_practicum'  => 'ИИ-Практикум',
		'article'       => 'После статьи',
		'checklist'     => 'После чек-листа',
		'about'         => 'Со страницы компании',
		'diagnostic'    => 'Самодиагностика участка',
		'pilot'         => 'Пилотный проект',
		'project'       => 'Со страницы проекта',
		'b24'           => 'Обзор Битрикс24',
		'material'      => 'Со страницы материала (КП/презентация)',
	);
}

/**
 * Цели обращения (v4): посетитель выбирает одну из трёх на общей форме; остальные подставляются
 * контекстом страницы (обзор Битрикс24, КП). Ключ — в заявку и события, подпись — в карточку обращения.
 */
function maxbit_lead_purposes(): array {
	return array(
		'discuss'      => 'Обсудить задачу',
		'kp'           => 'Получить коммерческое предложение',
		'presentation' => 'Получить презентацию MAXBIT',
		'b24'          => 'Обсудить внедрение Битрикс24',
		'tariff'       => 'Обсудить тариф',
		'hourly'       => 'Обсудить почасовую работу',
		'pilot'        => 'Пилотный проект за наш счёт',
	);
}

/** Допустимые направления для событий аналитики. */
function maxbit_event_directions(): array {
	return array( 'general', 'order_control', 'bitrix24', 'integration', 'ai_practicum', 'article', 'checklist', 'about', 'diagnostic', 'project', 'b24', 'material' );
}

/** Признак: текущий пользователь может видеть закрытые материалы. */
function maxbit_can_preview(): bool {
	return is_user_logged_in() && current_user_can( 'edit_posts' );
}

/** Отдельный флаг допуска материала к публичному показу (не выводится из статуса). */
function maxbit_is_public_ok( int $post_id ): bool {
	return '1' === get_post_meta( $post_id, '_mb_public_ok', true );
}

/** Публично ли доступен материал прямо сейчас (статус + допуск). */
function maxbit_is_publicly_visible( int $post_id ): bool {
	$post = get_post( $post_id );
	if ( ! $post || 'publish' !== $post->post_status ) {
		return false;
	}
	if ( in_array( $post->post_type, array( 'mb_solution', 'mb_project' ), true ) ) {
		return maxbit_is_public_ok( $post_id );
	}
	return true;
}

require_once MAXBIT_CORE_DIR . 'includes/post-types.php';
require_once MAXBIT_CORE_DIR . 'includes/meta.php';
require_once MAXBIT_CORE_DIR . 'includes/access.php';
require_once MAXBIT_CORE_DIR . 'includes/seo.php';
require_once MAXBIT_CORE_DIR . 'includes/settings.php';
require_once MAXBIT_CORE_DIR . 'includes/leads.php';
require_once MAXBIT_CORE_DIR . 'includes/delivery.php';
require_once MAXBIT_CORE_DIR . 'includes/notify.php';
require_once MAXBIT_CORE_DIR . 'includes/form.php';
require_once MAXBIT_CORE_DIR . 'includes/events.php';
require_once MAXBIT_CORE_DIR . 'includes/taxonomies.php';
require_once MAXBIT_CORE_DIR . 'includes/consent.php';
require_once MAXBIT_CORE_DIR . 'includes/og.php';
require_once MAXBIT_CORE_DIR . 'includes/materials.php';
require_once MAXBIT_CORE_DIR . 'includes/blocks.php';
require_once MAXBIT_CORE_DIR . 'includes/reviews.php';
require_once MAXBIT_CORE_DIR . 'includes/quiz.php';
require_once MAXBIT_CORE_DIR . 'includes/seed.php';
require_once MAXBIT_CORE_DIR . 'includes/update.php';
require_once MAXBIT_CORE_DIR . 'includes/v4.php';
require_once MAXBIT_CORE_DIR . 'includes/kb.php';
require_once MAXBIT_CORE_DIR . 'includes/certificates.php';
require_once MAXBIT_CORE_DIR . 'includes/catalog.php';

register_activation_hook( __FILE__, function () {
	maxbit_register_post_types();
	maxbit_add_roles_caps();
	flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/** После обновления модуля (без переактивации) правила адресов перечитываются один раз. */
add_action( 'init', function () {
	if ( get_option( 'maxbit_rewrite_version' ) !== MAXBIT_CORE_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'maxbit_rewrite_version', MAXBIT_CORE_VERSION, false );
	}
}, 99 );
