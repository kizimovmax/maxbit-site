<?php
defined( 'ABSPATH' ) || exit;

/**
 * Приём обращения. Успех — только после полной записи всех обязательных данных и их обратного чтения.
 * Защита анонимной формы: подписанный токен формы с временем выдачи, honeypot, минимальное время
 * заполнения, атомарный лимит по IP, атомарная блокировка по client_key/отпечатку (без гонок),
 * обнаружение содержательного дубля. Контакты не пишутся в журналы. Тип mb_lead непубличен.
 *
 * Идемпотентность: тот же client_key + тот же отпечаток содержимого → прежний request_id (duplicate).
 * Тот же client_key + другое содержимое → новая запись (посетитель изменил задачу; ничего не теряется).
 */

const MAXBIT_LEAD_TOKEN_TTL   = 12 * HOUR_IN_SECONDS;
const MAXBIT_LEAD_MIN_SECONDS = 3;
const MAXBIT_LEAD_RATE_LIMIT  = 5;
const MAXBIT_LEAD_RATE_WINDOW = 10 * MINUTE_IN_SECONDS;
const MAXBIT_LEAD_DUP_WINDOW  = 30 * MINUTE_IN_SECONDS;
const MAXBIT_LOCK_TTL         = 30; // секунд

function maxbit_lead_secret(): string {
	return hash( 'sha256', 'maxbit-lead|' . wp_salt( 'nonce' ) );
}

/** Версия текста согласия на обработку ПДн: меняется при изменении текста страницы «Согласие». */
if ( ! defined( 'MAXBIT_CONSENT_VERSION' ) ) {
	define( 'MAXBIT_CONSENT_VERSION', '2026-10-16' );
}

/** Токен формы: issued_at.hmac. Без персональных данных. $age — сдвиг времени выдачи назад (для перевыдачи). */
function maxbit_lead_token( int $age = 0 ): string {
	$ts  = (string) ( time() - $age );
	$sig = hash_hmac( 'sha256', $ts, maxbit_lead_secret() );
	return $ts . '.' . substr( $sig, 0, 32 );
}

function maxbit_lead_token_check( string $token ): array {
	if ( ! preg_match( '/^(\d{9,11})\.([a-f0-9]{32})$/', $token, $m ) ) {
		return array( false, 'token_invalid' );
	}
	$sig = substr( hash_hmac( 'sha256', $m[1], maxbit_lead_secret() ), 0, 32 );
	if ( ! hash_equals( $sig, $m[2] ) ) {
		return array( false, 'token_invalid' );
	}
	$ts = (int) $m[1];
	if ( time() - $ts > MAXBIT_LEAD_TOKEN_TTL ) {
		return array( false, 'token_expired' );
	}
	if ( time() - $ts < MAXBIT_LEAD_MIN_SECONDS ) {
		return array( false, 'too_fast' );
	}
	return array( true, '' );
}

function maxbit_client_ip_hash(): string {
	// Прокси-заголовкам без явной настройки хостинга не доверяем (MAXBIT_TRUSTED_PROXY = IP доверенного прокси).
	$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
	// Типичный хостинг: nginx перед Apache, REMOTE_ADDR = локальный/частный адрес прокси. Тогда — первый адрес XFF.
	$proxy_local = (bool) preg_match( '/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.|::1$|fc|fd)/', $ip );
	$trusted     = defined( 'MAXBIT_TRUSTED_PROXY' ) && MAXBIT_TRUSTED_PROXY && $ip === MAXBIT_TRUSTED_PROXY;
	if ( ( $proxy_local || $trusted ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$first = trim( explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] )[0] );
		if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
			$ip = $first;
		}
	}
	return substr( hash( 'sha256', $ip . '|' . maxbit_lead_secret() ), 0, 24 );
}

/* ---------- Атомарные примитивы на таблице options (уникальный option_name) ---------- */

/** Атомарный инкремент счётчика с окном. Возвращает новое значение. */
function maxbit_atomic_counter( string $name, int $window, int $depth = 0 ): int {
	global $wpdb;
	$key = 'mb_ctr_' . $name;
	$now = time();
	$wpdb->suppress_errors( true );
	$inserted = $wpdb->query( $wpdb->prepare(
		"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
		$key, wp_json_encode( array( 'n' => 1, 'until' => $now + $window ) )
	) );
	$wpdb->suppress_errors( false );
	if ( $inserted ) {
		return 1;
	}
	$raw = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
	$val = json_decode( $raw, true );
	$new = ( ! is_array( $val ) || ( $val['until'] ?? 0 ) < $now )
		? array( 'n' => 1, 'until' => $now + $window )
		: array( 'n' => (int) $val['n'] + 1, 'until' => (int) $val['until'] );
	$ok = $wpdb->query( $wpdb->prepare(
		"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
		wp_json_encode( $new ), $key, $raw
	) );
	if ( $ok ) {
		return $new['n'];
	}
	return $depth < 20 ? maxbit_atomic_counter( $name, $window, $depth + 1 ) : $new['n']; // проиграли гонку — повтор CAS
}

/** Блокировка: INSERT уникальной строки. true — захватили; false — занято (и не истекла). */
function maxbit_lock_acquire( string $name, int $ttl = MAXBIT_LOCK_TTL ): bool {
	global $wpdb;
	$key = 'mb_lock_' . $name;
	$now = time();
	$wpdb->suppress_errors( true );
	$ok = $wpdb->query( $wpdb->prepare(
		"INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $key, (string) ( $now + $ttl )
	) );
	$wpdb->suppress_errors( false );
	if ( $ok ) {
		return true;
	}
	$until = (string) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
	if ( '' === $until ) {
		return false;
	}
	if ( (int) $until < $now ) { // просроченная — перехват атомарно (CAS)
		return (bool) $wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", (string) ( $now + $ttl ), $key, $until
		) );
	}
	return false;
}

function maxbit_lock_release( string $name ): void {
	global $wpdb;
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s", 'mb_lock_' . $name ) );
	wp_cache_delete( 'mb_lock_' . $name, 'options' );
	wp_cache_delete( 'alloptions', 'options' );
}

function maxbit_lead_rate_limited(): bool {
	return maxbit_atomic_counter( 'rl_' . maxbit_client_ip_hash(), MAXBIT_LEAD_RATE_WINDOW ) > MAXBIT_LEAD_RATE_LIMIT;
}

/** Поиск заявки по мета-ключу за окно (private-записи). */
function maxbit_find_lead_by_meta( string $key, string $value, int $window ): ?int {
	$ids = get_posts( array(
		'post_type' => 'mb_lead', 'post_status' => 'private', 'numberposts' => 1, 'fields' => 'ids',
		'meta_key' => $key, 'meta_value' => $value, 'orderby' => 'ID', 'order' => 'DESC',
		'date_query' => array( array( 'after' => gmdate( 'Y-m-d H:i:s', time() - $window ), 'column' => 'post_date_gmt' ) ),
	) );
	return $ids ? (int) $ids[0] : null;
}

/** Основная функция приёма. Возвращает массив для ответа клиенту. */
function maxbit_lead_process( array $in ): array {
	$fail = static fn( string $code, string $msg, array $fields = array() ) => array(
		'ok' => false, 'code' => $code, 'message' => $msg, 'fields' => $fields,
	);

	if ( ! empty( $in['website'] ) ) {
		return $fail( 'spam', 'Не удалось отправить обращение.' );
	}
	[ $ok, $why ] = maxbit_lead_token_check( (string) ( $in['token'] ?? '' ) );
	if ( ! $ok ) {
		if ( 'token_expired' === $why ) {
			// Новый токен выдаём «возрастом» больше минимального: немедленный повтор не получит too_fast.
			return array( 'ok' => false, 'code' => 'token_expired', 'message' => 'Форма была открыта слишком давно. Мы обновили её — отправьте ещё раз.', 'fields' => array(), 'new_token' => maxbit_lead_token( MAXBIT_LEAD_MIN_SECONDS + 1 ) );
		}
		return $fail( $why, 'Не удалось проверить форму. Обновите страницу и попробуйте снова.' );
	}

	$name    = trim( mb_substr( sanitize_text_field( $in['name'] ?? '' ), 0, 100 ) );
	$contact = trim( mb_substr( sanitize_text_field( $in['contact'] ?? '' ), 0, 200 ) );
	$message = trim( mb_substr( sanitize_textarea_field( $in['message'] ?? '' ), 0, 2000 ) );
	$topic   = sanitize_key( $in['topic'] ?? 'general' );
	$source  = mb_substr( sanitize_text_field( $in['source'] ?? '' ), 0, 200 );
	$client_key = substr( preg_replace( '/[^a-zA-Z0-9\-]/', '', (string) ( $in['client_key'] ?? '' ) ), 0, 64 );

	$errors = array();
	if ( mb_strlen( $name ) < 2 ) {
		$errors['name'] = 'Укажите, как к вам обращаться (не короче 2 символов).';
	}
	$contact_compact = preg_replace( '/[\s\-\(\)\.]/u', '', $contact );
	if ( mb_strlen( $contact ) < 5 || ! preg_match( '/(\d{6,}|@|https?:\/\/|t\.me|[a-zA-Z0-9_]{4,})/u', $contact_compact ) ) {
		$errors['contact'] = 'Укажите телефон, почту или другой контакт, по которому можно ответить.';
	}
	if ( ! array_key_exists( $topic, maxbit_lead_topics() ) ) {
		$errors['topic'] = 'Недопустимая тема обращения.';
	}
	$consent = in_array( (string) ( $in['consent'] ?? '' ), array( '1', 'on', 'true', 'yes' ), true );
	if ( ! $consent ) {
		$errors['consent'] = 'Нужно согласие на обработку персональных данных — без него мы не можем принять обращение.';
	}
	if ( $errors ) {
		return $fail( 'validation', 'Проверьте заполнение полей.', $errors );
	}

	$fingerprint = hash( 'sha256', mb_strtolower( $name . '|' . $contact . '|' . $message . '|' . $topic ) );
	$lock_name   = $client_key ? 'key_' . substr( hash( 'sha256', $client_key ), 0, 40 ) : 'fp_' . substr( $fingerprint, 0, 40 );

	// Атомарная блокировка на ключ/отпечаток: параллельные повторы ждут и получают тот же результат.
	$waited = 0;
	while ( ! maxbit_lock_acquire( $lock_name ) ) {
		usleep( 150000 );
		if ( ++$waited > 40 ) { // 6 секунд
			return $fail( 'busy', 'Обращение обрабатывается. Подождите несколько секунд и повторите.' );
		}
	}
	try {
		if ( $client_key ) {
			$existing = maxbit_find_lead_by_meta( '_mb_client_key', $client_key, DAY_IN_SECONDS );
			if ( $existing ) {
				if ( get_post_meta( $existing, '_mb_fingerprint', true ) === $fingerprint ) {
					return maxbit_lead_success( $existing, true );
				}
				// Тот же ключ, другое содержимое: посетитель изменил задачу — сохраняем как новое обращение.
				$client_key = $client_key . '-r' . substr( $fingerprint, 0, 6 );
			}
		}
		$dup = maxbit_find_lead_by_meta( '_mb_fingerprint', $fingerprint, MAXBIT_LEAD_DUP_WINDOW );
		if ( $dup ) {
			return maxbit_lead_success( $dup, true );
		}
		if ( maxbit_lead_rate_limited() ) {
			return $fail( 'rate_limited', 'Слишком много попыток. Подождите несколько минут и повторите.' );
		}
		return maxbit_lead_store( compact( 'name', 'contact', 'message', 'topic', 'source', 'client_key', 'fingerprint' ) + array( 'page' => mb_substr( sanitize_text_field( $in['page'] ?? '' ), 0, 300 ) ) );
	} finally {
		maxbit_lock_release( $lock_name );
	}
}

/**
 * Запись заявки целиком. Любая неудача обязательного шага → откат (удаление незавершённой записи) и storage.
 * Событие maxbit_lead_stored — только после успешного обратного чтения всех обязательных полей.
 */
function maxbit_lead_store( array $d ): array {
	$fail = static fn() => array( 'ok' => false, 'code' => 'storage', 'message' => 'Не удалось сохранить обращение. Попробуйте позже.', 'fields' => array() );

	$request_id = maxbit_new_request_id();
	$post_id = wp_insert_post( array(
		'post_type'   => 'mb_lead',
		'post_status' => 'draft', // черновик до полной записи; private — только когда всё сохранено
		'post_title'  => $request_id . ' · ' . maxbit_lead_topics()[ $d['topic'] ],
		'post_author' => 0,
	), true );
	if ( is_wp_error( $post_id ) || ! $post_id ) {
		return $fail();
	}
	$required = array(
		'_mb_request_id'  => $request_id,
		'_mb_name'        => $d['name'],
		'_mb_contact'     => $d['contact'],
		'_mb_topic'       => $d['topic'],
		'_mb_fingerprint' => $d['fingerprint'],
		'_mb_consent'     => '1',
		'_mb_consent_version' => MAXBIT_CONSENT_VERSION,
		'_mb_consent_at'  => gmdate( 'c' ),
	);
	$optional = array(
		'_mb_message'     => $d['message'],
		'_mb_page'        => $d['page'] ?? '',
		'_mb_source'      => $d['source'],
		'_mb_client_key'  => $d['client_key'],
		'_mb_mode'        => MAXBIT_FORM_MODE,
		'_mb_ip_hash'     => maxbit_client_ip_hash(),
		'_mb_delivery_status'   => maxbit_delivery_url() ? 'pending' : 'disabled',
		'_mb_delivery_attempts' => 0,
	);
	$rollback = static function () use ( $post_id ) {
		wp_delete_post( $post_id, true );
	};
	foreach ( $required + $optional as $k => $v ) {
		$v = apply_filters( 'maxbit_lead_meta_value', $v, $k, $post_id ); // точка инъекции отказа в тестах
		if ( false === $v ) {
			$rollback();
			return $fail();
		}
		if ( ! add_post_meta( $post_id, $k, $v, true ) ) {
			$rollback();
			return $fail();
		}
	}
	wp_cache_delete( $post_id, 'post_meta' ); // обратное чтение — из БД, не из кеша
	foreach ( $required as $k => $v ) {
		if ( (string) get_post_meta( $post_id, $k, true ) !== (string) $v ) {
			$rollback();
			return $fail();
		}
	}
	$done = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'private' ), true );
	if ( is_wp_error( $done ) || get_post_status( $post_id ) !== 'private' ) {
		$rollback();
		return $fail();
	}
	do_action( 'maxbit_lead_stored', $post_id, $request_id );
	return maxbit_lead_success( $post_id, false );
}

function maxbit_lead_success( int $post_id, bool $duplicate ): array {
	return array(
		'ok'         => true,
		'code'       => $duplicate ? 'duplicate' : 'accepted',
		'request_id' => get_post_meta( $post_id, '_mb_request_id', true ),
		'mode'       => MAXBIT_FORM_MODE,
		'message'    => $duplicate
			? 'Это обращение уже принято ранее — повторная запись не создана.'
			: ( 'LOCAL_TEST' === MAXBIT_FORM_MODE ? 'Обращение записано.' : 'Спасибо, обращение принято. Ответим по указанному контакту в ближайший рабочий день.' ),
	);
}

function maxbit_new_request_id(): string {
	return 'MB-' . gmdate( 'ymd' ) . '-' . strtoupper( substr( bin2hex( random_bytes( 4 ) ), 0, 6 ) );
}

/** Незавершённые черновики заявок (оборванный процесс) старше 10 минут — удаляются ежечасно. */
add_action( 'maxbit_hourly', function () {
	foreach ( get_posts( array( 'post_type' => 'mb_lead', 'post_status' => 'draft', 'numberposts' => 50, 'fields' => 'ids', 'date_query' => array( array( 'before' => '10 minutes ago' ) ) ) ) as $id ) {
		wp_delete_post( $id, true );
	}
} );
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'maxbit_hourly' ) ) {
		wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'maxbit_hourly' );
	}
} );

/** Только для WP-CLI-тестов: токен «возрастом» 10 секунд. */
function maxbit_lead_token_check_bypass(): string {
	if ( ! ( defined( 'WP_CLI' ) && WP_CLI ) ) {
		return '';
	}
	return maxbit_lead_token( 10 );
}

/** Колонки в списке обращений (только для администратора). */
add_filter( 'manage_mb_lead_posts_columns', function ( array $cols ) {
	return array(
		'cb' => $cols['cb'], 'title' => 'Обращение', 'mb_topic' => 'Тема', 'mb_name' => 'Имя',
		'mb_mode' => 'Режим', 'mb_delivery' => 'Доставка', 'date' => 'Дата',
	);
} );
add_action( 'manage_mb_lead_posts_custom_column', function ( string $col, int $id ) {
	switch ( $col ) {
		case 'mb_topic':
			echo esc_html( maxbit_lead_topics()[ get_post_meta( $id, '_mb_topic', true ) ] ?? '—' );
			break;
		case 'mb_name':
			echo esc_html( get_post_meta( $id, '_mb_name', true ) );
			break;
		case 'mb_mode':
			echo esc_html( get_post_meta( $id, '_mb_mode', true ) );
			break;
		case 'mb_delivery':
			echo esc_html( get_post_meta( $id, '_mb_delivery_status', true ) ) . ' (' . (int) get_post_meta( $id, '_mb_delivery_attempts', true ) . ')';
			break;
	}
}, 10, 2 );

add_action( 'add_meta_boxes_mb_lead', function () {
	add_meta_box( 'mb_lead_view', 'Содержание обращения', function ( WP_Post $post ) {
		$mode = get_post_meta( $post->ID, '_mb_mode', true );
		$rows = array(
			'Идентификатор' => get_post_meta( $post->ID, '_mb_request_id', true ),
			'Режим'         => $mode . ( 'LOCAL_TEST' === $mode ? ' — тестовое обращение, MAXBIT не отправлялось' : '' ),
			'Тема'          => maxbit_lead_topics()[ get_post_meta( $post->ID, '_mb_topic', true ) ] ?? '',
			'Имя'           => get_post_meta( $post->ID, '_mb_name', true ),
			'Контакт'       => get_post_meta( $post->ID, '_mb_contact', true ),
			'Задача'        => get_post_meta( $post->ID, '_mb_message', true ),
			'Страница'      => get_post_meta( $post->ID, '_mb_page', true ),
			'Источник'      => get_post_meta( $post->ID, '_mb_source', true ),
			'Согласие ПДн'  => '1' === get_post_meta( $post->ID, '_mb_consent', true ) ? 'да, версия ' . get_post_meta( $post->ID, '_mb_consent_version', true ) . ', ' . get_post_meta( $post->ID, '_mb_consent_at', true ) . ' UTC' : 'нет отметки (заявка до версии 0.4.0)',
			'Уведомления'   => get_post_meta( $post->ID, '_mb_notify_status', true ) ?: '—',
			'Доставка'      => get_post_meta( $post->ID, '_mb_delivery_status', true ) . ', попыток: ' . (int) get_post_meta( $post->ID, '_mb_delivery_attempts', true ) . ' ' . get_post_meta( $post->ID, '_mb_delivery_last_error', true ) . ( get_post_meta( $post->ID, '_mb_b24_id', true ) ? ' · Битрикс24 ID ' . (int) get_post_meta( $post->ID, '_mb_b24_id', true ) : '' ),
		);
		echo '<table class="widefat striped">';
		foreach ( $rows as $k => $v ) {
			echo '<tr><th style="width:160px">' . esc_html( $k ) . '</th><td>' . nl2br( esc_html( (string) $v ) ) . '</td></tr>';
		}
		echo '</table>';
		$renotify  = wp_nonce_url( admin_url( 'admin-post.php?action=maxbit_renotify&id=' . $post->ID ), 'maxbit_renotify_' . $post->ID );
		$redeliver = wp_nonce_url( admin_url( 'admin-post.php?action=maxbit_redeliver&id=' . $post->ID ), 'maxbit_redeliver_' . $post->ID );
		echo '<p style="margin-top:10px"><a class="button" href="' . esc_url( $renotify ) . '">Отправить уведомления повторно</a> ';
		if ( 'delivered' !== get_post_meta( $post->ID, '_mb_delivery_status', true ) && maxbit_delivery_url() ) {
			echo '<a class="button" href="' . esc_url( $redeliver ) . '">Повторить доставку в Битрикс24</a>';
		}
		echo '</p>';
	}, 'mb_lead', 'normal', 'high' );
} );
