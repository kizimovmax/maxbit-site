<?php
defined( 'ABSPATH' ) || exit;

/**
 * Адаптер доставки обращения во внешнюю систему.
 * ВЫКЛЮЧЕН по умолчанию: вебхук не задан и/или режим формы LOCAL_TEST.
 * Протоколы:
 *  - 'bitrix24' (0.4.0): входящий вебхук портала; создаётся лид (crm.lead.add) или сделка+контакт
 *    (crm.contact.add + crm.deal.add) с картой полей; ORIGIN_ID = номер обращения — перед созданием
 *    проверяется, нет ли уже сущности с таким ORIGIN_ID (идемпотентность на стороне получателя);
 *    подтверждение — положительный ID в result;
 *  - 'test' (локальный приёмник MAXBIT_DELIVERY_TEST_URL): HTTP 2xx, JSON, ok === true, received === request_id;
 *  - 'raw' (константа MAXBIT_DELIVERY_PROTOCOL): прежний формат — JSON заявки на произвольный URL.
 * Всё остальное — не доставка. delivered — терминальный статус: повторы не выполняют сетевых вызовов
 * и не меняют его. Обработка каждой заявки — под блокировкой; ожидающие повторы после успеха снимаются.
 */

const MAXBIT_DELIVERY_TIMEOUT      = 5;
const MAXBIT_DELIVERY_MAX_ATTEMPTS = 3;

function maxbit_delivery_enabled(): bool {
	return (bool) maxbit_b24_webhook() && 'LOCAL_TEST' !== MAXBIT_FORM_MODE;
}

/** Тестовый приёмник можно задать даже в LOCAL_TEST (MAXBIT_DELIVERY_TEST_URL), протокол при этом — 'test'. */
function maxbit_delivery_url(): string {
	if ( defined( 'MAXBIT_DELIVERY_TEST_URL' ) && MAXBIT_DELIVERY_TEST_URL ) {
		return MAXBIT_DELIVERY_TEST_URL;
	}
	return maxbit_delivery_enabled() ? maxbit_b24_webhook() : '';
}

function maxbit_delivery_protocol(): string {
	if ( defined( 'MAXBIT_DELIVERY_TEST_URL' ) && MAXBIT_DELIVERY_TEST_URL ) {
		return 'test';
	}
	return defined( 'MAXBIT_DELIVERY_PROTOCOL' ) ? MAXBIT_DELIVERY_PROTOCOL : 'bitrix24';
}

/** Вызов метода REST Битрикс24 через входящий вебхук. Возвращает [code, json|null, error]. */
function maxbit_b24_call( string $method, array $params ): array {
	$base = rtrim( maxbit_b24_webhook(), '/' );
	if ( ! $base ) {
		return array( 0, null, 'no_webhook' );
	}
	$resp = wp_remote_post( $base . '/' . $method . '.json', array(
		'timeout' => MAXBIT_DELIVERY_TIMEOUT,
		'headers' => array( 'Content-Type' => 'application/json' ),
		'body'    => wp_json_encode( $params, JSON_UNESCAPED_UNICODE ),
	) );
	if ( is_wp_error( $resp ) ) {
		return array( 0, null, $resp->get_error_code() );
	}
	$code = (int) wp_remote_retrieve_response_code( $resp );
	$json = json_decode( (string) wp_remote_retrieve_body( $resp ), true );
	if ( ! is_array( $json ) ) {
		return array( $code, null, 'bad_json' );
	}
	if ( isset( $json['error'] ) ) {
		return array( $code, $json, 'b24_' . sanitize_key( (string) $json['error'] ) );
	}
	return array( $code, $json, '' );
}

/** Разбор контакта посетителя: телефон, почта или свободный текст. */
function maxbit_split_contact( string $contact ): array {
	$out = array( 'phone' => '', 'email' => '', 'other' => '' );
	if ( preg_match( '/[\w.+\-]+@[\w\-]+\.[\w.\-]+/u', $contact, $m ) ) {
		$out['email'] = $m[0];
	}
	$digits = preg_replace( '/[^\d+]/', '', $contact );
	if ( strlen( preg_replace( '/\D/', '', $digits ) ) >= 10 && preg_match( '/(\+?\d[\d\s\-()]{8,}\d)/', $contact, $m ) ) {
		$out['phone'] = trim( $m[1] );
	}
	if ( ! $out['phone'] && ! $out['email'] ) {
		$out['other'] = $contact;
	}
	return $out;
}

/** Поля заявки для Битрикс24. */
function maxbit_b24_fields( int $post_id, string $request_id ): array {
	$s       = maxbit_get_settings();
	$topic   = (string) get_post_meta( $post_id, '_mb_topic', true );
	$topics  = maxbit_lead_topics();
	$name    = (string) get_post_meta( $post_id, '_mb_name', true );
	$contact = (string) get_post_meta( $post_id, '_mb_contact', true );
	$msg     = (string) get_post_meta( $post_id, '_mb_message', true );
	$source  = (string) get_post_meta( $post_id, '_mb_source', true );
	$page    = (string) get_post_meta( $post_id, '_mb_page', true );
	$c       = maxbit_split_contact( $contact );
	$lines   = array_filter( array(
		'Заявка с сайта ' . wp_parse_url( home_url(), PHP_URL_HOST ) . ', номер ' . $request_id,
		'Цель: ' . ( maxbit_lead_purposes()[ (string) get_post_meta( $post_id, '_mb_purpose', true ) ] ?? 'Обсудить задачу' ),
		'Тема: ' . ( $topics[ $topic ] ?? $topic ),
		'Контакт (как ввёл посетитель): ' . $contact,
		$msg ? "Задача:\n" . $msg : '',
		'Страница: ' . ( $page ?: $source ),
		$source && $page ? 'Источник: ' . $source : '',
		'Согласие на обработку ПДн: да, версия ' . get_post_meta( $post_id, '_mb_consent_version', true ) . ', ' . get_post_meta( $post_id, '_mb_consent_at', true ) . ' UTC',
	) );
	$fields = array(
		'TITLE'       => 'Сайт: ' . ( $topics[ $topic ] ?? 'обращение' ) . ' — ' . $name,
		'NAME'        => $name,
		'SOURCE_ID'   => 'WEB',
		'SOURCE_DESCRIPTION' => 'maxbitpro.ru / ' . $topic,
		'ORIGIN_ID'   => $request_id,
		'ORIGINATOR_ID' => 'maxbit-site',
		'COMMENTS'    => implode( "\n", $lines ),
		'UTM_SOURCE'  => preg_match( '/utm_source=([\w\-]+)/', $source, $m ) ? $m[1] : '',
		'UTM_MEDIUM'  => preg_match( '/utm_medium=([\w\-]+)/', $source, $m ) ? $m[1] : '',
		'UTM_CAMPAIGN' => preg_match( '/utm_campaign=([\w\-]+)/', $source, $m ) ? $m[1] : '',
	);
	if ( $c['phone'] ) {
		$fields['PHONE'] = array( array( 'VALUE' => $c['phone'], 'VALUE_TYPE' => 'WORK' ) );
	}
	if ( $c['email'] ) {
		$fields['EMAIL'] = array( array( 'VALUE' => $c['email'], 'VALUE_TYPE' => 'WORK' ) );
	}
	if ( ! empty( $s['b24_assigned'] ) ) {
		$fields['ASSIGNED_BY_ID'] = (int) $s['b24_assigned'];
	}
	return array_filter( $fields, fn( $v ) => '' !== $v && null !== $v );
}

/** Доставка в Битрикс24: лид или сделка+контакт. Возвращает '' при подтверждении, иначе причина. */
function maxbit_b24_deliver( int $post_id, string $request_id ): string {
	$s      = maxbit_get_settings();
	$fields = maxbit_b24_fields( $post_id, $request_id );
	$entity = 'deal' === $s['b24_entity'] ? 'deal' : 'lead';
	// Идемпотентность на стороне портала: уже есть сущность с таким ORIGIN_ID — считаем доставленным.
	[ $code, $json, $err ] = maxbit_b24_call( 'crm.' . $entity . '.list', array( 'filter' => array( 'ORIGIN_ID' => $request_id ), 'select' => array( 'ID' ) ) );
	if ( '' === $err && ! empty( $json['result'][0]['ID'] ) ) {
		update_post_meta( $post_id, '_mb_b24_id', (int) $json['result'][0]['ID'] );
		return '';
	}
	if ( $err && 0 === $code ) {
		return $err; // сеть/таймаут — повторим позже
	}
	if ( 'lead' === $entity ) {
		[ $code, $json, $err ] = maxbit_b24_call( 'crm.lead.add', array( 'fields' => $fields, 'params' => array( 'REGISTER_SONET_EVENT' => 'Y' ) ) );
		if ( $err ) {
			return $err;
		}
		$id = (int) ( $json['result'] ?? 0 );
		if ( $id <= 0 ) {
			return 'not_confirmed';
		}
		update_post_meta( $post_id, '_mb_b24_id', $id );
		return '';
	}
	// Сделка: сначала контакт, затем сделка с привязкой.
	$contact_fields = array_intersect_key( $fields, array_flip( array( 'NAME', 'PHONE', 'EMAIL', 'SOURCE_ID', 'SOURCE_DESCRIPTION', 'COMMENTS', 'ASSIGNED_BY_ID' ) ) );
	$contact_fields['ORIGIN_ID'] = $request_id . '-c';
	[ $code, $json, $err ] = maxbit_b24_call( 'crm.contact.add', array( 'fields' => $contact_fields ) );
	$contact_id = $err ? 0 : (int) ( $json['result'] ?? 0 );
	$deal_fields = array_intersect_key( $fields, array_flip( array( 'TITLE', 'SOURCE_ID', 'SOURCE_DESCRIPTION', 'ORIGIN_ID', 'ORIGINATOR_ID', 'COMMENTS', 'ASSIGNED_BY_ID', 'UTM_SOURCE', 'UTM_MEDIUM', 'UTM_CAMPAIGN' ) ) );
	if ( $contact_id > 0 ) {
		$deal_fields['CONTACT_ID'] = $contact_id;
	}
	if ( ! empty( $s['b24_category'] ) ) {
		$deal_fields['CATEGORY_ID'] = (int) $s['b24_category'];
	}
	[ $code, $json, $err ] = maxbit_b24_call( 'crm.deal.add', array( 'fields' => $deal_fields, 'params' => array( 'REGISTER_SONET_EVENT' => 'Y' ) ) );
	if ( $err ) {
		return $err;
	}
	$id = (int) ( $json['result'] ?? 0 );
	if ( $id <= 0 ) {
		return 'not_confirmed';
	}
	update_post_meta( $post_id, '_mb_b24_id', $id );
	return '';
}

add_action( 'maxbit_lead_stored', function ( int $post_id ) {
	if ( ! maxbit_delivery_url() ) {
		return; // выключено — статус 'disabled'
	}
	maxbit_deliver_lead( $post_id );
} );

/** Строгая проверка ответа получателя. Возвращает '' при подтверждении, иначе код причины. */
function maxbit_delivery_confirm( int $code, string $body, string $request_id, string $protocol ): string {
	if ( $code < 200 || $code >= 300 ) {
		return 'http_' . $code;
	}
	$json = json_decode( $body, true );
	if ( ! is_array( $json ) ) {
		return 'bad_json';
	}
	if ( 'test' === $protocol ) {
		if ( ( $json['ok'] ?? null ) !== true ) {
			return 'not_confirmed';
		}
		if ( ( $json['received'] ?? null ) !== $request_id ) {
			return 'request_id_mismatch';
		}
		return '';
	}
	// raw: произвольный JSON-приёмник с result > 0
	if ( isset( $json['error'] ) || ! array_key_exists( 'result', $json ) ) {
		return 'not_confirmed';
	}
	$r = $json['result'];
	if ( is_int( $r ) && $r > 0 ) {
		return '';
	}
	if ( is_string( $r ) && ctype_digit( $r ) && (int) $r > 0 ) {
		return '';
	}
	if ( is_array( $r ) && ! empty( $r ) && isset( $r['ID'] ) && (int) $r['ID'] > 0 ) {
		return '';
	}
	return 'not_confirmed';
}

/**
 * Одна попытка доставки под блокировкой. true — подтверждённый успех (в т.ч. уже достигнутый ранее).
 */
function maxbit_deliver_lead( int $post_id ): bool {
	$post = get_post( $post_id );
	if ( ! $post || 'mb_lead' !== $post->post_type || 'private' !== $post->post_status ) {
		return false;
	}
	if ( 'delivered' === get_post_meta( $post_id, '_mb_delivery_status', true ) ) {
		return true; // терминальный статус: без сетевого вызова и без изменения
	}
	$url = maxbit_delivery_url();
	if ( ! $url ) {
		return false;
	}
	if ( ! maxbit_lock_acquire( 'deliver_' . $post_id, MAXBIT_DELIVERY_TIMEOUT + 10 ) ) {
		return false; // уже обрабатывается параллельно
	}
	try {
		wp_cache_delete( $post_id, 'post_meta' );
		if ( 'delivered' === get_post_meta( $post_id, '_mb_delivery_status', true ) ) {
			return true;
		}
		$attempts = (int) get_post_meta( $post_id, '_mb_delivery_attempts', true );
		if ( $attempts >= MAXBIT_DELIVERY_MAX_ATTEMPTS ) {
			update_post_meta( $post_id, '_mb_delivery_status', 'failed' );
			return false;
		}
		update_post_meta( $post_id, '_mb_delivery_attempts', $attempts + 1 );
		update_post_meta( $post_id, '_mb_delivery_status', 'pending' );

		$request_id = (string) get_post_meta( $post_id, '_mb_request_id', true );
		if ( 'bitrix24' === maxbit_delivery_protocol() ) {
			$why = maxbit_b24_deliver( $post_id, $request_id );
			if ( '' !== $why ) {
				return maxbit_delivery_failed( $post_id, $why );
			}
			update_post_meta( $post_id, '_mb_delivery_status', 'delivered' );
			update_post_meta( $post_id, '_mb_delivery_last_error', '' );
			update_post_meta( $post_id, '_mb_delivered_at', gmdate( 'c' ) );
			wp_clear_scheduled_hook( 'maxbit_delivery_retry', array( $post_id ) );
			do_action( 'maxbit_lead_delivered', $post_id, $request_id );
			return true;
		}
		$payload = array(
			'request_id' => $request_id,
			'topic'      => get_post_meta( $post_id, '_mb_topic', true ),
			'name'       => get_post_meta( $post_id, '_mb_name', true ),
			'contact'    => get_post_meta( $post_id, '_mb_contact', true ),
			'message'    => get_post_meta( $post_id, '_mb_message', true ),
			'source'     => get_post_meta( $post_id, '_mb_source', true ),
			'mode'       => MAXBIT_FORM_MODE,
			'attempt'    => $attempts + 1,
		);
		$resp = wp_remote_post( $url, array(
			'timeout' => MAXBIT_DELIVERY_TIMEOUT,
			'headers' => array( 'Content-Type' => 'application/json', 'X-Request-Id' => $request_id ),
			'body'    => wp_json_encode( $payload, JSON_UNESCAPED_UNICODE ),
		) );
		if ( is_wp_error( $resp ) ) {
			return maxbit_delivery_failed( $post_id, $resp->get_error_code() );
		}
		$why = maxbit_delivery_confirm( (int) wp_remote_retrieve_response_code( $resp ), (string) wp_remote_retrieve_body( $resp ), (string) $request_id, maxbit_delivery_protocol() );
		if ( '' !== $why ) {
			return maxbit_delivery_failed( $post_id, $why );
		}
		update_post_meta( $post_id, '_mb_delivery_status', 'delivered' );
		update_post_meta( $post_id, '_mb_delivery_last_error', '' );
		update_post_meta( $post_id, '_mb_delivered_at', gmdate( 'c' ) );
		wp_clear_scheduled_hook( 'maxbit_delivery_retry', array( $post_id ) ); // оставшиеся повторы снимаем
		do_action( 'maxbit_lead_delivered', $post_id, $request_id );
		return true;
	} finally {
		maxbit_lock_release( 'deliver_' . $post_id );
	}
}

function maxbit_delivery_failed( int $post_id, string $reason ): bool {
	update_post_meta( $post_id, '_mb_delivery_last_error', sanitize_key( $reason ) );
	$attempts = (int) get_post_meta( $post_id, '_mb_delivery_attempts', true );
	if ( $attempts >= MAXBIT_DELIVERY_MAX_ATTEMPTS ) {
		update_post_meta( $post_id, '_mb_delivery_status', 'failed' );
		wp_clear_scheduled_hook( 'maxbit_delivery_retry', array( $post_id ) );
	} else {
		update_post_meta( $post_id, '_mb_delivery_status', 'retry' );
		wp_clear_scheduled_hook( 'maxbit_delivery_retry', array( $post_id ) ); // одна задача повтора на заявку
		wp_schedule_single_event( time() + 60 * $attempts, 'maxbit_delivery_retry', array( $post_id ) );
	}
	return false;
}
add_action( 'maxbit_delivery_retry', 'maxbit_deliver_lead' );
