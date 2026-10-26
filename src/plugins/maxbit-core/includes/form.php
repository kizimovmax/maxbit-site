<?php
defined( 'ABSPATH' ) || exit;

/**
 * Одна общая форма для всего сайта. Тема обращения передаётся параметром и проверяется
 * по серверному белому списку. Вывод: шорткод [maxbit_form topic="ai_practicum"] или
 * функция maxbit_render_form() из темы. Отправка: fetch → REST maxbit/v1/lead; без JS —
 * обычный POST на admin-post.php с возвратом на страницу.
 */

function maxbit_render_form( array $args = array() ): string {
	$args = wp_parse_args( $args, array(
		'topic'    => 'general',
		'heading'  => 'Что сейчас мешает работе вашей компании?',
		'text'     => 'Расскажите о задаче. Обсудим текущий процесс и определим, с чего разумно начать.',
		'button'   => 'Отправить заявку',
		'id'       => 'contact',
		'purposes' => '0',         // 1 — посетитель выбирает цель: обсудить задачу / КП / презентация (ТЗ v4 §3.11)
		'purpose'  => 'discuss',   // цель по умолчанию или скрытая цель страницы (b24, tariff, hourly)
		'src'      => '',          // логическая метка источника (kp, presentation) — без адреса страницы с токеном
		'variant'  => 'full',      // full — задача и мессенджеры; magnet — имя + контакт, выдача материалов
		'quiz'     => '',          // итог самодиагностики (только цифры), уходит в заявку
	) );
	$magnet = 'magnet' === $args['variant'];
	$s_all  = maxbit_get_settings();
	$transfer = '1' === (string) $s_all['transfer_consent'];
	$topic = array_key_exists( $args['topic'], maxbit_lead_topics() ) ? $args['topic'] : 'general';
	$purposes = maxbit_lead_purposes();
	$purpose  = array_key_exists( $args['purpose'], $purposes ) ? $args['purpose'] : 'discuss';
	$choose   = '1' === (string) $args['purposes'] && ! $magnet;
	$src_tag  = in_array( $args['src'], array( 'kp', 'presentation' ), true ) ? $args['src'] : '';
	$uid   = 'mbf-' . substr( md5( $topic . $args['id'] ), 0, 6 );
	$state = maxbit_form_state_from_request();
	if ( $state && ! empty( $state['values']['topic'] ) && $state['values']['topic'] !== $topic ) {
		$state = array(); // чек относится к другой форме на странице
	}
	ob_start();
	?>
	<section class="mb-form" id="<?php echo esc_attr( $args['id'] ); ?>" data-topic="<?php echo esc_attr( $topic ); ?>">
		<div class="mb-form__intro">
			<h2 class="mb-form__heading"><?php echo esc_html( $args['heading'] ); ?></h2>
			<p><?php echo esc_html( $args['text'] ); ?></p>
			<?php
			if ( ! $magnet ) {
				// Тема выводит здесь «Написать в Telegram / MAX» (два равноправных входа рядом с формой).
				if ( has_action( 'maxbit_form_intro_after' ) ) {
					do_action( 'maxbit_form_intro_after', $topic );
				} else {
					echo maxbit_messengers_html( 'bots', 'buttons', 'Или напишите нам:' ); // phpcs:ignore
				}
			}
			?>
		</div>
		<form class="mb-form__form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" novalidate data-endpoint="<?php echo esc_url( rest_url( 'maxbit/v1/lead' ) ); ?>">
			<input type="hidden" name="action" value="maxbit_lead">
			<input type="hidden" name="topic" value="<?php echo esc_attr( $topic ); ?>">
			<input type="hidden" name="token" value="<?php echo esc_attr( maxbit_lead_token() ); ?>">
			<input type="hidden" name="client_key" value="">
			<input type="hidden" name="source" value="<?php echo esc_attr( maxbit_form_source() . ( $src_tag ? ' [' . $src_tag . ']' : '' ) ); ?>">
			<?php if ( ! $choose && ! $magnet ) : ?><input type="hidden" name="purpose" value="<?php echo esc_attr( $purpose ); ?>" data-purpose><?php endif; ?>
			<?php if ( $magnet ) : ?><input type="hidden" name="quiz" value="<?php echo esc_attr( preg_replace( '/[^0-9,]/', '', (string) $args['quiz'] ) ); ?>" data-quiz><input type="hidden" name="variant" value="magnet"><?php endif; ?>
			<input type="hidden" name="redirect" value="<?php echo esc_attr( maxbit_current_path() . '#' . $args['id'] ); ?>">
			<p class="mb-form__hp" aria-hidden="true"><label>Не заполняйте это поле <input type="text" name="website" tabindex="-1" autocomplete="off"></label></p>

			<div class="mb-form__status" role="status" aria-live="polite">
				<?php if ( $state ) : ?>
					<div class="mb-notice mb-notice--<?php echo $state['ok'] ? 'ok' : 'error'; ?>"><?php echo esc_html( $state['message'] ); ?></div>
				<?php endif; ?>
			</div>

			<?php if ( $choose ) : ?>
			<fieldset class="mb-form__row mb-form__purposes">
				<legend>Цель обращения</legend>
				<?php /* Презентация в форме не предлагается (решение владельца 26.10); ссылку можно выдать вручную из заявки. */ foreach ( array( 'discuss', 'kp' ) as $pk ) : ?>
				<label class="mb-chip"><input type="radio" name="purpose" value="<?php echo esc_attr( $pk ); ?>" <?php checked( ( $state['values']['purpose'] ?? $purpose ), $pk ); ?>><span><?php echo esc_html( $purposes[ $pk ] ); ?></span></label>
				<?php endforeach; ?>
			</fieldset>
			<?php endif; ?>
			<div class="mb-form__row">
				<label for="<?php echo $uid; ?>-name">Как к вам обращаться <span aria-hidden="true">*</span></label>
				<input id="<?php echo $uid; ?>-name" name="name" type="text" required maxlength="100" autocomplete="name" aria-required="true" aria-describedby="<?php echo $uid; ?>-name-err" value="<?php echo esc_attr( $state['values']['name'] ?? '' ); ?>">
				<p class="mb-form__error" id="<?php echo $uid; ?>-name-err"><?php echo esc_html( $state['fields']['name'] ?? '' ); ?></p>
			</div>
			<div class="mb-form__row">
				<label for="<?php echo $uid; ?>-contact">Удобный контакт <span aria-hidden="true">*</span></label>
				<input id="<?php echo $uid; ?>-contact" name="contact" type="text" required maxlength="200" autocomplete="email" aria-required="true" aria-describedby="<?php echo $uid; ?>-contact-hint <?php echo $uid; ?>-contact-err" value="<?php echo esc_attr( $state['values']['contact'] ?? '' ); ?>">
				<p class="mb-form__hint" id="<?php echo $uid; ?>-contact-hint">Email или имя пользователя в Telegram/MAX</p>
				<p class="mb-form__error" id="<?php echo $uid; ?>-contact-err"><?php echo esc_html( $state['fields']['contact'] ?? '' ); ?></p>
			</div>
			<?php if ( ! $magnet ) : ?>
			<div class="mb-form__row">
				<label for="<?php echo $uid; ?>-message">Коротко о задаче</label>
				<textarea id="<?php echo $uid; ?>-message" name="message" rows="4" maxlength="2000"><?php echo esc_textarea( $state['values']['message'] ?? '' ); ?></textarea>
			</div>
			<?php endif; ?>
			<div class="mb-form__row mb-form__row--consent">
				<label class="mb-check" for="<?php echo $uid; ?>-consent">
					<input id="<?php echo $uid; ?>-consent" name="consent" type="checkbox" value="1" required aria-required="true" aria-describedby="<?php echo $uid; ?>-consent-err" <?php checked( ! empty( $state['values']['consent'] ) ); ?>>
					<span>Даю <a href="<?php echo esc_url( maxbit_legal_url( 'consent' ) ); ?>" target="_blank" rel="noopener">согласие на обработку персональных данных</a>. <a href="<?php echo esc_url( maxbit_legal_url( 'policy' ) ); ?>" target="_blank" rel="noopener">Политика обработки персональных данных</a></span>
				</label>
				<p class="mb-form__error" id="<?php echo $uid; ?>-consent-err"><?php echo esc_html( $state['fields']['consent'] ?? '' ); ?></p>
				<?php if ( $transfer ) : ?>
				<label class="mb-check" for="<?php echo $uid; ?>-transfer">
					<input id="<?php echo $uid; ?>-transfer" name="consent_transfer" type="checkbox" value="1" required aria-required="true" aria-describedby="<?php echo $uid; ?>-transfer-err" <?php checked( ! empty( $state['values']['consent_transfer'] ) ); ?>>
					<span>Даю согласие на передачу моих данных <?php echo esc_html( $s_all['transfer_to'] ?: 'третьим лицам, обрабатывающим обращения по поручению MAXBIT' ); ?> — только для ответа на это обращение.</span>
				</label>
				<p class="mb-form__error" id="<?php echo $uid; ?>-transfer-err"><?php echo esc_html( $state['fields']['consent_transfer'] ?? '' ); ?></p>
				<?php endif; ?>
			</div>
			<div class="mb-form__actions">
				<button type="submit" class="mb-btn mb-btn--primary"><?php echo esc_html( $args['button'] ); ?></button>
				<?php if ( $choose || in_array( $purpose, array( 'kp', 'presentation' ), true ) ) : ?><p class="mb-form__note mb-form__note--inline">Если вы запросили коммерческое предложение, отправим ссылку после получения заявки.</p><?php endif; ?>
			</div>
			<?php if ( $magnet ) : ?>
				<p class="mb-form__note">Материалы откроются сразу после отправки; ссылки именные и действуют 7 дней. Пишем только по этому обращению, без рассылок.</p>
			<?php endif; ?>
			<?php if ( 'LOCAL_TEST' === MAXBIT_FORM_MODE ) : ?>
				<p class="mb-form__note mb-form__note--test"><strong>Тестовый стенд.</strong> Не вводите реальные данные: заявка сохраняется только на стенде и не отправляется MAXBIT.</p>
			<?php endif; ?>
			<?php if ( $magnet && $state && $state['ok'] && ! empty( $state['materials'] ) ) : ?>
				<ul class="mb-quiz__files"><?php foreach ( $state['materials'] as $m ) : ?><li><a href="<?php echo esc_url( $m['url'] ); ?>"><?php echo esc_html( $m['title'] ); ?></a></li><?php endforeach; ?></ul>
			<?php endif; ?>
		</form>
	</section>
	<?php
	return ob_get_clean();
}
add_shortcode( 'maxbit_form', function ( $atts ) {
	return maxbit_render_form( (array) $atts );
} );

/** Адреса юридических страниц (создаются наполнением; если страницы нет — якорь на подвал). */
function maxbit_legal_url( string $which ): string {
	$slug = 'consent' === $which ? 'soglasie-na-obrabotku-personalnyh-dannyh' : 'politika-obrabotki-personalnyh-dannyh';
	$page = get_page_by_path( $slug );
	return $page && 'publish' === $page->post_status ? get_permalink( $page ) : home_url( '/' . $slug . '/' );
}

function maxbit_current_path(): string {
	$path = wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH );
	return $path ? esc_url_raw( $path ) : '/';
}

/** Путь страницы без секретной части: /materialy/kp/<токен>/ → /materialy/kp/ (в заявки, аналитику и CRM токен не попадает). */
function maxbit_mask_private_path( string $path ): string {
	return (string) preg_replace( '#^(/materialy/[a-z]+/)[^/?\s]*/?#', '$1', $path );
}

/** Источник: путь страницы (без query) + очищенные UTM с ограничением длины. Без свободного текста. */
function maxbit_form_source(): string {
	$parts = array( maxbit_mask_private_path( maxbit_current_path() ) );
	foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $k ) {
		if ( ! empty( $_GET[ $k ] ) ) {
			$v = preg_replace( '/[^a-zA-Z0-9_\-]/', '', (string) $_GET[ $k ] );
			if ( $v ) {
				$parts[] = $k . '=' . substr( $v, 0, 40 );
			}
		}
	}
	return substr( implode( ' ', $parts ), 0, 200 );
}

/** REST-приём (для JS). Анонимный доступ намеренный; полномочия и антиспам — внутри процесса. */
add_action( 'rest_api_init', function () {
	register_rest_route( 'maxbit/v1', '/lead', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $r ) {
			$params = $r->get_json_params() ?: $r->get_body_params();
			$result = maxbit_lead_process( is_array( $params ) ? $params : array() );
			$status = $result['ok'] ? 200 : ( in_array( $result['code'], array( 'storage', 'busy' ), true ) ? 503 : ( 'rate_limited' === $result['code'] ? 429 : 422 ) );
			$resp = new WP_REST_Response( $result, $status );
			$resp->header( 'Cache-Control', 'no-store' );
			return $resp;
		},
	) );
	register_rest_route( 'maxbit/v1', '/lead/token', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function () {
			$resp = new WP_REST_Response( array( 'token' => maxbit_lead_token( MAXBIT_LEAD_MIN_SECONDS + 1 ) ) );
			$resp->header( 'Cache-Control', 'no-store' );
			return $resp;
		},
	) );
} );

/**
 * Без JS: POST admin-post.php?action=maxbit_lead → результат кладётся в краткоживущий серверный
 * «чек» (transient, 10 минут, одноразовый), в URL уходит только его случайный идентификатор.
 * Подделать ?mbr=… нельзя: без записи на сервере сообщение не показывается. При ошибке допустимые
 * поля (имя, контакт, задача, тема) возвращаются в форму из чека, не через URL.
 */
function maxbit_lead_admin_post(): void {
	$in = wp_unslash( $_POST );
	$in = is_array( $in ) ? $in : array();
	$result = maxbit_lead_process( $in );
	$redirect = isset( $in['redirect'] ) ? wp_validate_redirect( home_url( (string) $in['redirect'] ), home_url( '/' ) ) : home_url( '/' );
	$receipt = bin2hex( random_bytes( 12 ) );
	$flash = array( 'message' => $result['ok'] ? (string) ( $result['message'] ?? '' ) : '', 'ok' => $result['ok'], 'code' => $result['code'], 'request_id' => $result['request_id'] ?? '', 'fields' => $result['fields'] ?? array(), 'values' => array(), 'materials' => $result['materials'] ?? array() );
	if ( ! $result['ok'] && ! in_array( $result['code'], array( 'spam' ), true ) ) {
		$flash['values'] = array(
			'name'    => mb_substr( sanitize_text_field( $in['name'] ?? '' ), 0, 100 ),
			'contact' => mb_substr( sanitize_text_field( $in['contact'] ?? '' ), 0, 200 ),
			'message' => mb_substr( sanitize_textarea_field( $in['message'] ?? '' ), 0, 2000 ),
			'topic'   => sanitize_key( $in['topic'] ?? '' ),
			'purpose' => sanitize_key( $in['purpose'] ?? '' ),
		);
	}
	set_transient( 'mb_flash_' . $receipt, $flash, 10 * MINUTE_IN_SECONDS );
	nocache_headers();
	wp_safe_redirect( add_query_arg( array( 'mbr' => $receipt ), $redirect ) );
	exit;
}
add_action( 'admin_post_nopriv_maxbit_lead', 'maxbit_lead_admin_post' );
add_action( 'admin_post_maxbit_lead', 'maxbit_lead_admin_post' );

/** Состояние формы после безJS-редиректа: только из серверного чека. */
function maxbit_form_state_from_request(): array {
	static $state = null;
	if ( null !== $state ) {
		return $state;
	}
	$state = array();
	$receipt = preg_replace( '/[^a-f0-9]/', '', (string) ( $_GET['mbr'] ?? '' ) );
	if ( strlen( $receipt ) !== 24 ) {
		return $state;
	}
	$flash = get_transient( 'mb_flash_' . $receipt );
	if ( ! is_array( $flash ) ) {
		return $state; // подделанный/использованный чек — ничего не показываем
	}
	delete_transient( 'mb_flash_' . $receipt ); // одноразовый
	nocache_headers();
	$rid  = $flash['request_id'] ?: '';
	$msgs = maxbit_form_messages();
	$message = ! empty( $flash['message'] ) ? (string) $flash['message'] : sprintf( $msgs[ $flash['code'] ] ?? $msgs['generic'], $rid );
	$state = array( 'ok' => (bool) $flash['ok'], 'message' => $message, 'fields' => (array) $flash['fields'], 'values' => (array) $flash['values'], 'materials' => (array) ( $flash['materials'] ?? array() ) );
	return $state;
}

/** Тексты сообщений формы: зависят от режима (LOCAL_TEST-фраза — только в тестовом режиме). */
function maxbit_form_messages(): array {
	return array(
		'accepted'      => 'LOCAL_TEST' === MAXBIT_FORM_MODE ? 'Тестовая заявка сохранена. Это стенд: обращение не отправлено MAXBIT.' : 'Спасибо, заявка получена. Свяжемся с вами по указанному контакту.',
		'duplicate'     => 'Это обращение уже было принято ранее (номер %s). Повторная запись не создана.',
		'validation'    => 'Проверьте заполнение обязательных полей.',
		'token_expired' => 'Форма была открыта слишком давно. Отправьте её ещё раз.',
		'token_invalid' => 'Не удалось проверить форму. Обновите страницу и попробуйте снова.',
		'too_fast'      => 'Форма отправлена слишком быстро. Попробуйте ещё раз.',
		'rate_limited'  => 'Слишком много попыток. Подождите несколько минут.',
		'storage'       => 'Не удалось отправить заявку. Попробуйте ещё раз позже.',
		'busy'          => 'Обращение обрабатывается. Подождите несколько секунд и повторите.',
		'spam'          => 'Не удалось отправить заявку. Попробуйте ещё раз позже.',
		'generic'       => 'Не удалось отправить заявку. Попробуйте ещё раз позже.',
	);
}

/** Скрипт формы и событий: подключается только на страницах с формой. */
add_action( 'wp_enqueue_scripts', function () {
	wp_register_script( 'maxbit-form', MAXBIT_CORE_URL . 'assets/form.js', array(), MAXBIT_CORE_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	wp_localize_script( 'maxbit-form', 'MAXBIT_CFG', array(
		'mode'       => MAXBIT_FORM_MODE,
		'analytics'  => (bool) MAXBIT_ANALYTICS_ENABLED,
		'directions' => maxbit_event_directions(),
		'tokenUrl'   => rest_url( 'maxbit/v1/lead/token' ),
		'timeoutMs'  => 15000,
		'messages'   => maxbit_form_messages(),
	) );
	wp_enqueue_script( 'maxbit-form' );
} );

/** Карточка решения по slug — выводится только если материал доступен текущему посетителю. */
add_shortcode( 'maxbit_solution_card', function ( $atts ) {
	$atts = shortcode_atts( array( 'slug' => '', 'label' => 'Решение', 'title' => '', 'text' => '', 'link' => '' ), $atts );
	$posts = get_posts( array( 'post_type' => 'mb_solution', 'name' => sanitize_title( $atts['slug'] ), 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'numberposts' => 1 ) );
	if ( ! $posts ) {
		return '';
	}
	$p = $posts[0];
	$visible = maxbit_can_preview() ? true : maxbit_is_publicly_visible( $p->ID );
	if ( ! $visible ) {
		return '';
	}
	$closed = ! maxbit_is_publicly_visible( $p->ID );
	$title  = $atts['title'] ?: get_the_title( $p );
	$text   = $atts['text'] ?: get_the_excerpt( $p );
	$link   = $atts['link'] ?: 'Подробнее';
	$url    = 'publish' === $p->post_status ? get_permalink( $p ) : get_preview_post_link( $p );
	$out  = '<div class="mb-solution-card' . ( $closed ? ' mb-solution-card--closed' : '' ) . '">';
	$out .= '<p class="mb-eyebrow">' . esc_html( $atts['label'] ) . '</p>';
	$out .= '<h3>' . esc_html( $title ) . '</h3><p>' . esc_html( $text ) . '</p>';
	$out .= '<p><a class="mb-btn mb-btn--link" href="' . esc_url( $url ) . '">' . esc_html( $link ) . '</a></p>';
	if ( $closed ) {
		$out .= '<p class="mb-badge mb-badge--draft">Закрытый просмотр: черновик предложения, допуск выключен</p>';
	}
	return $out . '</div>';
} );

/**
 * Нейтральная карточка материала. Тема может подменить вывод фильтром maxbit_render_card;
 * без темы MAXBIT используется этот безопасный HTML (модуль не зависит от функций темы).
 */
function maxbit_render_card( WP_Post $p, string $label = '' ): string {
	$custom = apply_filters( 'maxbit_render_card', null, $p, $label );
	if ( is_string( $custom ) ) {
		return $custom;
	}
	$out  = '<article class="mb-card">';
	if ( $label ) {
		$out .= '<p class="mb-card__label">' . esc_html( $label ) . '</p>';
	}
	$out .= '<h3 class="mb-card__title"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></h3>';
	if ( has_excerpt( $p ) ) {
		$out .= '<p class="mb-card__text">' . esc_html( get_the_excerpt( $p ) ) . '</p>';
	}
	return $out . '</article>';
}

/**
 * Блок проектов: только допущенные кейсы, карточка «задача → что сделали → что изменилось → подробнее».
 * Без допущенных кейсов посетитель не видит ничего; редактор видит служебную подсказку.
 */
add_shortcode( 'maxbit_projects_block', function ( $atts ) {
	$atts = shortcode_atts( array( 'heading' => 'Как это решали в проектах' ), $atts );
	$q = new WP_Query( array(
		'post_type' => 'mb_project', 'post_status' => 'publish', 'posts_per_page' => 3, 'no_found_rows' => true,
		'meta_query' => array(
			array( 'key' => '_mb_public_ok', 'value' => '1' ),
			array( 'relation' => 'OR', array( 'key' => '_mb_is_sample', 'compare' => 'NOT EXISTS' ), array( 'key' => '_mb_is_sample', 'value' => '1', 'compare' => '!=' ) ),
		),
	) );
	if ( ! $q->have_posts() ) {
		if ( maxbit_can_preview() ) {
			return '<div class="mb-editor-note" role="note"><strong>Служебная заметка (видна только редакторам).</strong> Блок проектов пуст: публичных кейсов с допуском нет. Шаблон готов — «задача → что сделали → что изменилось → подробнее». Кейсы публикуются только после проверки фактов и разрешения клиента.</div>';
		}
		return '';
	}
	$out = ( $atts['heading'] ? '<div class="mb-section__head"><h2>' . esc_html( $atts['heading'] ) . '</h2></div>' : '' ) . '<div class="mb-grid mb-grid--3 mb-proof">';
	foreach ( $q->posts as $p ) {
		$id = $p->ID;
		$client = get_post_meta( $id, '_mb_pf_client', true );
		$task   = get_post_meta( $id, '_mb_pf_task', true );
		$done   = get_post_meta( $id, '_mb_pf_implemented', true ) ?: get_post_meta( $id, '_mb_pf_solution', true );
		$effect = get_post_meta( $id, '_mb_pf_effect', true );
		$out .= '<article class="mb-proof__card">';
		$out .= '<p class="mb-card__label">' . esc_html( $client ?: 'Проект' ) . '</p>';
		$out .= '<h3 class="mb-card__title"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_the_title( $p ) ) . '</a></h3>';
		$out .= '<dl class="mb-proof__rows">';
		foreach ( array( 'Задача' => $task, 'Что сделали' => $done, 'Что изменилось' => $effect ) as $k => $v ) {
			if ( '' !== trim( (string) $v ) ) {
				$out .= '<div><dt>' . $k . '</dt><dd>' . esc_html( wp_trim_words( $v, 28, '…' ) ) . '</dd></div>';
			}
		}
		$out .= '</dl><p><a class="mb-btn mb-btn--link" href="' . esc_url( get_permalink( $p ) ) . '">Подробнее о проекте</a></p></article>';
	}
	return $out . '</div>';
} );

/** Служебная заметка: видна только тем, кто может смотреть закрытые материалы. Посетителям — пусто. */
add_shortcode( 'maxbit_editor_note', function ( $atts, $content = '' ) {
	if ( ! maxbit_can_preview() || '' === trim( (string) $content ) ) {
		return '';
	}
	return '<div class="mb-editor-note" role="note"><strong>Служебная заметка (видна только редакторам).</strong> ' . wp_kses_post( $content ) . '</div>';
} );

/** Раздел с заголовком, который показывается только если внутри есть содержимое (пустые заголовки посетителю не видны). */
add_shortcode( 'maxbit_section', function ( $atts, $content = '' ) {
	$atts  = shortcode_atts( array( 'heading' => '', 'level' => '2' ), $atts );
	$inner = do_shortcode( (string) $content );
	if ( '' === trim( wp_strip_all_tags( $inner ) ) ) {
		return '';
	}
	$l = in_array( $atts['level'], array( '2', '3' ), true ) ? $atts['level'] : '2';
	return ( $atts['heading'] ? "<h$l class=\"wp-block-heading\">" . esc_html( $atts['heading'] ) . "</h$l>" : '' ) . $inner;
} );

/** Файл из комплекта модуля (assets/files) — свободное скачивание, без формы. */
add_shortcode( 'maxbit_file', function ( $atts ) {
	$atts = shortcode_atts( array( 'name' => '', 'label' => 'Скачать' ), $atts );
	$name = basename( sanitize_file_name( $atts['name'] ) );
	$path = MAXBIT_CORE_DIR . 'assets/files/' . $name;
	if ( ! $name || ! file_exists( $path ) ) {
		return maxbit_can_preview() ? '<p class="mb-badge mb-badge--draft">Файл ' . esc_html( $name ) . ' не найден в комплекте модуля.</p>' : '';
	}
	$size = size_format( filesize( $path ) );
	return '<p class="mb-download"><a class="mb-btn mb-btn--primary" href="' . esc_url( MAXBIT_CORE_URL . 'assets/files/' . $name ) . '" download data-event="file_download">' . esc_html( $atts['label'] ) . '</a> <span class="mb-download__meta">PDF · ' . esc_html( $size ) . ' · без регистрации</span></p>';
} );

/**
 * Блок «Практика» для главной и профиля: последние опубликованные статьи, карточка чек-листа,
 * подписка на канал (только если ссылка задана в настройках). compact="1" — только список статей.
 */
add_shortcode( 'maxbit_practice_block', function ( $atts ) {
	$atts = shortcode_atts( array( 'compact' => '0', 'count' => 3 ), $atts );
	$cl_post = '1' !== $atts['compact'] ? get_page_by_path( 'chek-list-peredacha-zakaza-v-proizvodstvo', OBJECT, 'post' ) : null;
	$q = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => (int) $atts['count'], 'no_found_rows' => true, 'ignore_sticky_posts' => true, 'post__not_in' => $cl_post ? array( $cl_post->ID ) : array() ) );
	$out = '<div class="mb-practice' . ( '1' === $atts['compact'] ? ' mb-practice--compact' : '' ) . '">';
	$out .= '<div class="mb-practice__posts mb-grid mb-grid--3">';
	foreach ( $q->posts as $p ) {
		$out .= maxbit_render_card( $p, 'Статья' );
	}
	$out .= '</div>';
	if ( '1' !== $atts['compact'] ) {
		$s = maxbit_get_settings();
		$out .= '<div class="mb-practice__aside">';
		$cl = get_page_by_path( 'chek-list-peredacha-zakaza-v-proizvodstvo', OBJECT, 'post' );
		if ( $cl && 'publish' === $cl->post_status ) {
			$out .= '<div class="mb-practice__magnet"><p class="mb-eyebrow">Чек-лист</p><h3>12 пунктов перед передачей заказа в производство</h3><p>Одна страница. Распечатайте и проверьте свой следующий заказ — без регистрации и без формы.</p><p><a class="mb-btn mb-btn--primary" href="' . esc_url( get_permalink( $cl ) ) . '">Открыть чек-лист</a></p></div>';
		}
		$channel = $s['telegram'] ?: $s['messenger_url'];
		if ( $channel ) {
			$out .= '<div class="mb-practice__channel"><p class="mb-eyebrow">' . esc_html( $s['telegram'] ? 'Telegram' : ( $s['messenger'] ?: 'Канал' ) ) . '</p><p>Короткие заметки о порядке в заказах, настройках Битрикс24 и 1С. Без рекламы.</p><p><a class="mb-btn mb-btn--link" href="' . esc_url( $channel ) . '" rel="noopener" data-contact="messenger">Подписаться</a></p></div>';
		} elseif ( maxbit_can_preview() ) {
			$out .= '<div class="mb-editor-note" role="note"><strong>Служебная заметка.</strong> Блок канала появится, когда в «MAXBIT → Контакты» будет задана ссылка мессенджера.</div>';
		}
		$out .= '</div>';
	}
	$out .= '<p class="mb-practice__all"><a class="mb-btn mb-btn--link" href="' . esc_url( get_permalink( (int) get_option( 'page_for_posts' ) ) ) . '">Все материалы</a></p></div>';
	return $out;
} );
