<?php
defined( 'ABSPATH' ) || exit;

/**
 * Проверка связи с Битрикс24 до запуска (03.11).
 *
 * На стенде (MAXBIT_FORM_MODE = LOCAL_TEST) заявки посетителей в Битрикс24 не уходят — это не меняется.
 * Здесь — две явные проверки администратора:
 *  1) «Проверить подключение» — только чтение: вебхук отвечает, есть право crm, источник WEB существует,
 *     ответственный найден (если у вебхука есть право user). Ничего не создаёт.
 *  2) «Отправить тестовую заявку» — создаёт ОДИН лид с пометкой «ТЕСТ» из последнего обращения в панели
 *     (те же поля, что у рабочей доставки). Обращение на сайте не меняется. ID созданных лидов — в журнале.
 * Сам вебхук не выводится: только адрес портала.
 * REST: POST maxbit/v1/update/b24-check {send:false|true}.
 */

function maxbit_b24_portal_host(): string {
	return (string) wp_parse_url( maxbit_b24_webhook(), PHP_URL_HOST );
}

function maxbit_b24_check(): array {
	$log = array();
	if ( ! maxbit_b24_webhook() ) {
		return array( 'ok' => false, 'log' => array( 'Вебхук не задан: MAXBIT → Состояние → «Входящий вебхук Битрикс24».' ) );
	}
	$log[] = 'Портал: ' . maxbit_b24_portal_host();
	[ $code, $json, $err ] = maxbit_b24_call( 'crm.lead.fields', array() );
	if ( $err ) {
		$hint = array(
			'b24_insufficient_scope' => 'у вебхука нет права crm',
			'b24_invalid_credentials' => 'вебхук недействителен (удалён или код неверный)',
			'b24_no_auth_found'      => 'вебхук недействителен',
		)[ $err ] ?? ( 0 === $code ? 'портал недоступен с сервера сайта' : 'ответ портала: ' . $err );
		$log[] = '✗ Подключение: ' . $hint . ' (HTTP ' . $code . ')';
		return array( 'ok' => false, 'log' => $log );
	}
	$log[] = '✓ Вебхук отвечает, право crm есть';
	foreach ( array( 'TITLE', 'PHONE', 'EMAIL', 'ORIGIN_ID', 'SOURCE_ID', 'COMMENTS' ) as $f ) {
		if ( empty( $json['result'][ $f ] ) ) {
			$log[] = '✗ В лиде нет поля ' . $f;
		}
	}
	[ , $st, $e2 ] = maxbit_b24_call( 'crm.status.list', array( 'filter' => array( 'ENTITY_ID' => 'SOURCE', 'STATUS_ID' => 'WEB' ) ) );
	$log[] = $e2 ? '? Источник WEB не проверен (' . $e2 . ')' : ( empty( $st['result'] ) ? '✗ Источника «Веб-сайт» (WEB) нет в справочнике' : '✓ Источник: «' . $st['result'][0]['NAME'] . '» (WEB)' );
	$s = maxbit_get_settings();
	$log[] = 'Создаётся: ' . ( 'deal' === $s['b24_entity'] ? 'сделка + контакт' : 'лид' );
	if ( ! empty( $s['b24_assigned'] ) ) {
		[ , $u, $e3 ] = maxbit_b24_call( 'user.get', array( 'ID' => (int) $s['b24_assigned'] ) );
		if ( $e3 ) {
			$log[] = '? Ответственный ID ' . (int) $s['b24_assigned'] . ': не проверен — у вебхука нет права user (это не мешает доставке)';
		} elseif ( empty( $u['result'][0] ) ) {
			$log[] = '✗ Ответственный ID ' . (int) $s['b24_assigned'] . ' не найден в портале';
		} else {
			$log[] = '✓ Ответственный: ' . trim( $u['result'][0]['NAME'] . ' ' . $u['result'][0]['LAST_NAME'] ) . ' (ID ' . (int) $s['b24_assigned'] . ')';
		}
	} else {
		$log[] = 'Ответственный не задан — по правилам портала';
	}
	$log[] = maxbit_delivery_enabled() ? 'Рабочая доставка заявок: включена' : 'Рабочая доставка заявок: выключена (режим формы ' . MAXBIT_FORM_MODE . ') — на боевом сайте включится сама';
	return array( 'ok' => ! preg_grep( '/^✗/u', $log ), 'log' => $log );
}

function maxbit_b24_test_send(): array {
	$chk = maxbit_b24_check();
	if ( ! $chk['ok'] ) {
		return $chk + array( 'sent' => false );
	}
	$ids = get_posts( array( 'post_type' => 'mb_lead', 'post_status' => 'private', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_mb_phone', 'meta_compare' => 'EXISTS' ) );
	if ( ! $ids ) {
		$chk['log'][] = '✗ Нет обращения с телефоном: отправьте тестовую заявку через форму сайта и повторите';
		return array( 'ok' => false, 'sent' => false, 'log' => $chk['log'] );
	}
	$rid    = 'test-' . gmdate( 'Ymd-His' );
	$fields = maxbit_b24_fields( (int) $ids[0], $rid );
	$fields['TITLE']    = 'ТЕСТ ' . wp_parse_url( home_url(), PHP_URL_HOST ) . ' — можно удалить. ' . $fields['TITLE'];
	$fields['COMMENTS'] = "Тестовая отправка из настроек сайта, не обращение клиента.\n\n" . $fields['COMMENTS'];
	[ , $json, $err ] = maxbit_b24_call( 'crm.lead.add', array( 'fields' => $fields ) );
	$id = $err ? 0 : (int) ( $json['result'] ?? 0 );
	if ( $id <= 0 ) {
		$chk['log'][] = '✗ Тестовый лид не создан: ' . ( $err ?: 'нет ID в ответе' );
		return array( 'ok' => false, 'sent' => false, 'log' => $chk['log'] );
	}
	$journal   = (array) get_option( 'maxbit_b24_tests', array() );
	$journal[] = array( 'lead' => $id, 'at' => gmdate( 'c' ), 'from' => (int) $ids[0], 'host' => maxbit_b24_portal_host() );
	update_option( 'maxbit_b24_tests', array_slice( $journal, -20 ), false );
	$chk['log'][] = '✓ Создан тестовый лид ID ' . $id . ' (из обращения #' . (int) $ids[0] . '): https://' . maxbit_b24_portal_host() . '/crm/lead/details/' . $id . '/';
	return array( 'ok' => true, 'sent' => true, 'lead' => $id, 'log' => $chk['log'] );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'maxbit/v1', '/update/b24-check', array(
		'methods' => 'POST', 'permission_callback' => fn() => current_user_can( 'manage_options' ),
		'callback' => fn( WP_REST_Request $r ) => rest_ensure_response( $r->get_param( 'send' ) ? maxbit_b24_test_send() : maxbit_b24_check() ),
	) );
} );

add_action( 'admin_post_maxbit_b24_check', function () {
	if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'maxbit_b24_check' ) ) {
		wp_die( 'Нет доступа' );
	}
	$r = ! empty( $_POST['send'] ) ? maxbit_b24_test_send() : maxbit_b24_check();
	set_transient( 'maxbit_b24_check_' . get_current_user_id(), $r, 600 );
	wp_safe_redirect( wp_get_referer() ?: admin_url() );
	exit;
} );

add_action( 'maxbit_settings_after', function () {
	$r = get_transient( 'maxbit_b24_check_' . get_current_user_id() );
	delete_transient( 'maxbit_b24_check_' . get_current_user_id() );
	?>
	<h2 id="b24-check">Проверка Битрикс24</h2>
	<p class="description">«Проверить подключение» ничего не создаёт. «Отправить тестовую заявку» создаёт один лид с пометкой «ТЕСТ» из последнего обращения в панели. Заявки посетителей на стенде в Битрикс24 не уходят.</p>
	<?php if ( $r ) : ?>
		<div class="notice <?php echo $r['ok'] ? 'notice-success' : 'notice-error'; ?> inline"><p><?php echo implode( '<br>', array_map( fn( $l ) => make_clickable( esc_html( $l ) ), $r['log'] ) ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="maxbit_b24_check">
		<?php wp_nonce_field( 'maxbit_b24_check' ); ?>
		<?php submit_button( 'Проверить подключение', 'secondary', 'check', false ); ?>
		&nbsp;<?php submit_button( 'Отправить тестовую заявку', 'secondary', 'send', false ); ?>
	</form>
	<?php
	$j = (array) get_option( 'maxbit_b24_tests', array() );
	if ( $j ) {
		echo '<p class="description">Созданные тестовые лиды: ' . esc_html( implode( ', ', array_map( fn( $t ) => 'ID ' . $t['lead'], $j ) ) ) . ' — их можно удалить в Битрикс24.</p>';
	}
} );
