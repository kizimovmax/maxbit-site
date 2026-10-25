<?php
defined( 'ABSPATH' ) || exit;

/**
 * Уведомления о новом обращении: письмо и Telegram. Срабатывают после успешного сохранения
 * заявки (maxbit_lead_stored) и только в рабочем режиме формы; вне production письма WordPress
 * дополнительно блокируются фильтром pre_wp_mail (см. maxbit-core.php). Статус уведомлений
 * хранится в мета заявки и виден в панели «Обращения». Уведомление — не доставка: заявка
 * считается принятой по факту записи в базу.
 */

function maxbit_notify_recipients(): array {
	$s    = maxbit_get_settings();
	$list = array_filter( array_map( 'trim', explode( ',', (string) ( $s['notify_email'] ?: $s['email'] ) ) ) );
	return array_values( array_filter( $list, 'is_email' ) );
}

function maxbit_notify_enabled(): bool {
	return 'LOCAL_TEST' !== MAXBIT_FORM_MODE || ( defined( 'MAXBIT_NOTIFY_IN_TEST' ) && MAXBIT_NOTIFY_IN_TEST );
}

/** Текст уведомления (одинаковый для письма и Telegram, без HTML). */
function maxbit_notify_text( int $post_id ): string {
	$topics = maxbit_lead_topics();
	$topic  = (string) get_post_meta( $post_id, '_mb_topic', true );
	$rows   = array(
		'Номер'    => get_post_meta( $post_id, '_mb_request_id', true ),
		'Тема'     => $topics[ $topic ] ?? $topic,
		'Имя'      => get_post_meta( $post_id, '_mb_name', true ),
		'Контакт'  => get_post_meta( $post_id, '_mb_contact', true ),
		'Задача'   => get_post_meta( $post_id, '_mb_message', true ) ?: '—',
		'Страница' => get_post_meta( $post_id, '_mb_page', true ) ?: get_post_meta( $post_id, '_mb_source', true ),
		'Время'    => get_date_from_gmt( get_post( $post_id )->post_date_gmt, 'd.m.Y H:i' ) . ' (' . wp_timezone_string() . ')',
		'Согласие' => 'да, версия ' . get_post_meta( $post_id, '_mb_consent_version', true ),
	);
	$out = '';
	foreach ( $rows as $k => $v ) {
		$out .= $k . ': ' . trim( (string) $v ) . "\n";
	}
	$out .= "\nВ панели: " . admin_url( 'edit.php?post_type=mb_lead' ) . "\n";
	return $out;
}

/** Письмо. From — служебный адрес на домене сайта, чтобы проходить SPF/DMARC; Reply-To — почта посетителя, если она есть. */
function maxbit_notify_mail( int $post_id ): string {
	$to = maxbit_notify_recipients();
	if ( ! $to ) {
		return 'no_recipients';
	}
	$rid     = (string) get_post_meta( $post_id, '_mb_request_id', true );
	$topics  = maxbit_lead_topics();
	$topic   = (string) get_post_meta( $post_id, '_mb_topic', true );
	$subject = 'Заявка с сайта ' . $rid . ' — ' . ( $topics[ $topic ] ?? 'обращение' );
	$host    = preg_replace( '/^www\./', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	$from    = defined( 'MAXBIT_MAIL_FROM' ) && MAXBIT_MAIL_FROM ? MAXBIT_MAIL_FROM : 'site@' . $host;
	$headers = array( 'Content-Type: text/plain; charset=UTF-8', 'From: Сайт MAXBIT <' . $from . '>' );
	$c = function_exists( 'maxbit_split_contact' ) ? maxbit_split_contact( (string) get_post_meta( $post_id, '_mb_contact', true ) ) : array( 'email' => '' );
	if ( ! empty( $c['email'] ) && is_email( $c['email'] ) ) {
		$headers[] = 'Reply-To: ' . $c['email'];
	}
	$ok = wp_mail( $to, $subject, maxbit_notify_text( $post_id ), $headers );
	return $ok ? '' : 'wp_mail_failed';
}

/** Telegram: sendMessage через Bot API. Токен и чат — из настроек. */
function maxbit_notify_telegram( int $post_id ): string {
	$s = maxbit_get_settings();
	if ( empty( $s['tg_token'] ) || empty( $s['tg_chat'] ) ) {
		return 'not_configured';
	}
	$text = "Новая заявка с сайта\n" . maxbit_notify_text( $post_id );
	$resp = wp_remote_post( 'https://api.telegram.org/bot' . $s['tg_token'] . '/sendMessage', array(
		'timeout' => 5,
		'body'    => array( 'chat_id' => $s['tg_chat'], 'text' => mb_substr( $text, 0, 3900 ), 'disable_web_page_preview' => 'true' ),
	) );
	if ( is_wp_error( $resp ) ) {
		return $resp->get_error_code();
	}
	$json = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
	return ! empty( $json['ok'] ) ? '' : 'tg_' . (int) wp_remote_retrieve_response_code( $resp );
}

add_action( 'maxbit_lead_stored', function ( int $post_id ) {
	if ( ! maxbit_notify_enabled() ) {
		update_post_meta( $post_id, '_mb_notify_status', 'skipped_test_mode' );
		return;
	}
	$mail = maxbit_notify_mail( $post_id );
	$tg   = maxbit_notify_telegram( $post_id );
	update_post_meta( $post_id, '_mb_notify_status', 'mail:' . ( $mail ?: 'sent' ) . ' tg:' . ( $tg ?: 'sent' ) );
	update_post_meta( $post_id, '_mb_notified_at', gmdate( 'c' ) );
}, 20 );

/** Кнопка в карточке обращения: отправить уведомления повторно. */
add_action( 'admin_post_maxbit_renotify', function () {
	$id = (int) ( $_GET['id'] ?? 0 );
	if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( (string) ( $_GET['_wpnonce'] ?? '' ), 'maxbit_renotify_' . $id ) || 'mb_lead' !== get_post_type( $id ) ) {
		wp_die( 'Нет доступа.' );
	}
	$mail = maxbit_notify_mail( $id );
	$tg   = maxbit_notify_telegram( $id );
	update_post_meta( $id, '_mb_notify_status', 'mail:' . ( $mail ?: 'sent' ) . ' tg:' . ( $tg ?: 'sent' ) . ' (повтор)' );
	wp_safe_redirect( get_edit_post_link( $id, '' ) ?: admin_url( 'edit.php?post_type=mb_lead' ) );
	exit;
} );

/** Кнопка: повторить доставку в Битрикс24 (сбрасывает счётчик попыток). */
add_action( 'admin_post_maxbit_redeliver', function () {
	$id = (int) ( $_GET['id'] ?? 0 );
	if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( (string) ( $_GET['_wpnonce'] ?? '' ), 'maxbit_redeliver_' . $id ) || 'mb_lead' !== get_post_type( $id ) ) {
		wp_die( 'Нет доступа.' );
	}
	if ( 'delivered' !== get_post_meta( $id, '_mb_delivery_status', true ) ) {
		update_post_meta( $id, '_mb_delivery_attempts', 0 );
		update_post_meta( $id, '_mb_delivery_status', 'pending' );
		if ( function_exists( 'maxbit_deliver_lead' ) ) {
			maxbit_deliver_lead( $id );
		}
	}
	wp_safe_redirect( get_edit_post_link( $id, '' ) ?: admin_url( 'edit.php?post_type=mb_lead' ) );
	exit;
} );
