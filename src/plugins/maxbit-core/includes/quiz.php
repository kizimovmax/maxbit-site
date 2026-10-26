<?php
defined( 'ABSPATH' ) || exit;

/**
 * Самодиагностика участка (0.4.1) — лид-магнит внизу главной: 7 вопросов, результат на экране без сбора данных,
 * затем форма «получить материалы» (имя + контакт + согласия). Материалы выдаются именными ссылками,
 * действующими 7 дней; файлы лежат в комплекте модуля или в закрытой папке uploads/maxbit-private/.
 * Ответы хранятся в заявке только как цифры (без свободного текста) и попадают в уведомление владельцу.
 */

const MAXBIT_DL_TTL = 7 * DAY_IN_SECONDS;

function maxbit_quiz_questions(): array {
	return array(
		'Бывает, что цех делает не ту версию заказа, которую согласовали с клиентом?',
		'Чтобы ответить клиенту «где мой заказ?», нужно кому-то позвонить или сходить в цех?',
		'Срок в договоре ставится без проверки реальной загрузки производства?',
		'Один и тот же заказ вводят вручную в две и более системы — CRM, 1С, таблицы?',
		'Руководитель собирает картину по заказам из нескольких источников вручную?',
		'Изменения в заказе передаются устно или в мессенджере, без фиксации в системе?',
		'Срок прохождения заказа и долю переделок никто регулярно не считает?',
	);
}

/** Зоны результата: [мин, заголовок, описание, рекомендации, slug решения]. */
function maxbit_quiz_zones(): array {
	return array(
		array( 9, 'Заказ держится на людях', 'Порядок в заказах существует, пока конкретные сотрудники помнят договорённости. Любой отпуск, увольнение или рост числа заказов — и сроки поплывут. Начинать стоит с одного участка: пути заказа от заявки до отгрузки.',
			array( 'Выбрать один реальный заказ и пройти его путь вместе с теми, кто его ведёт', 'Зафиксировать, где теряются дни: версия спецификации, передача в цех, статус', 'Договориться об одном месте, где живёт заказ, и о правиле изменений' ), 'zakaz-pod-kontrolem' ),
		array( 4, 'Потери есть, но локальные', 'Процесс в целом работает, но на одном-двух переходах заказ «проваливается»: между продажами и цехом или между CRM и 1С. Обычно это закрывается настройкой того, что уже есть, без новой программы.',
			array( 'Найти переход, где чаще всего переделки или уточнения', 'Проверить, что умеют Битрикс24 и 1С на этом переходе', 'Ввести обязательные данные и ответственного для этого шага' ), 'integraciya-bitrix24-i-1c' ),
		array( 0, 'Процесс под контролем', 'Заказ проходит по понятным правилам, статус виден без звонков. Есть смысл закрепить это: измерять показатели регулярно и проверять исключения — чтобы порядок не зависел от людей.',
			array( 'Выбрать один-два показателя и считать их каждый месяц', 'Описать, что происходит при исключениях: срочный заказ, замена материала', 'Проверить, что новые сотрудники работают по тем же правилам' ), '' ),
	);
}

/** Итог по строке ответов «2,1,0,…»: балл, зона. */
function maxbit_quiz_eval( string $answers ): array {
	$vals = array_map( 'intval', array_filter( explode( ',', $answers ), 'strlen' ) );
	$score = 0;
	foreach ( $vals as $v ) {
		$score += max( 0, min( 2, $v ) );
	}
	foreach ( maxbit_quiz_zones() as $z ) {
		if ( $score >= $z[0] ) {
			return array( 'score' => $score, 'max' => 2 * count( maxbit_quiz_questions() ), 'zone' => $z );
		}
	}
	return array( 'score' => $score, 'max' => 14, 'zone' => maxbit_quiz_zones()[2] );
}

/** Краткая расшифровка для уведомления владельцу. */
function maxbit_quiz_summary( string $answers ): string {
	$e = maxbit_quiz_eval( $answers );
	$vals = array_filter( explode( ',', $answers ), 'strlen' );
	$yes = array();
	foreach ( array_values( $vals ) as $i => $v ) {
		if ( (int) $v === 2 ) {
			$yes[] = $i + 1;
		}
	}
	return $e['score'] . ' из ' . $e['max'] . ' — ' . $e['zone'][1] . ( $yes ? '; «да» на вопросы ' . implode( ', ', $yes ) : '' );
}

/** [maxbit_quiz heading="…" text="…"] — блок внизу главной. */
add_shortcode( 'maxbit_quiz', function ( $atts ) {
	$a = shortcode_atts( array(
		'heading' => 'Проверьте свой участок за минуту',
		'text'    => 'Семь вопросов о том, как заказ проходит у вас сейчас. Результат — сразу на экране, без регистрации. Материалы по результату — по желанию, за имя и контакт.',
		'collapsed' => '0', // 1 — свёрнуто до нажатия «Пройти самодиагностику» (без JS — развёрнуто)
	), $atts );
	$collapsed = '1' === (string) $a['collapsed'];
	$q = maxbit_quiz_questions();
	$zones = array_map( fn( $z ) => array( 'min' => $z[0], 'title' => $z[1], 'text' => $z[2], 'tips' => $z[3], 'url' => $z[4] ? ( ( $p = get_page_by_path( $z[4], OBJECT, 'mb_solution' ) ) && maxbit_is_publicly_visible( $p->ID ) ? get_permalink( $p ) : '' ) : '' ), maxbit_quiz_zones() );
	wp_enqueue_script( 'maxbit-quiz', MAXBIT_CORE_URL . 'assets/quiz.js', array( 'maxbit-form' ), MAXBIT_CORE_VERSION, array( 'strategy' => 'defer', 'in_footer' => true ) );
	wp_localize_script( 'maxbit-quiz', 'MAXBIT_QUIZ', array( 'zones' => $zones, 'max' => 2 * count( $q ) ) );
	ob_start();
	?>
	<section class="mb-quiz<?php echo $collapsed ? ' mb-quiz--collapsible' : ''; ?>" id="diagnostic" aria-labelledby="mb-quiz-h"<?php echo $collapsed ? ' data-collapsible' : ''; ?>>
		<div class="mb-quiz__head">
			<h2 id="mb-quiz-h"><?php echo esc_html( $a['heading'] ); ?></h2>
			<p><?php echo esc_html( $a['text'] ); ?></p>
			<?php if ( $collapsed ) : ?><p class="mb-quiz__open-wrap"><button type="button" class="mb-btn mb-btn--primary mb-quiz__open" aria-expanded="false" aria-controls="mb-quiz-body">Пройти самодиагностику</button></p><?php endif; ?>
		</div>
		<div class="mb-quiz__body" id="mb-quiz-body">
		<form class="mb-quiz__form" data-quiz-form>
			<ol class="mb-quiz__list">
				<?php foreach ( $q as $i => $text ) : $n = $i + 1; ?>
				<li class="mb-quiz__item">
					<p class="mb-quiz__q" id="mb-q<?php echo $n; ?>"><?php echo esc_html( $text ); ?></p>
					<div class="mb-quiz__opts" role="radiogroup" aria-labelledby="mb-q<?php echo $n; ?>">
						<?php foreach ( array( 2 => 'Да', 1 => 'Иногда', 0 => 'Нет' ) as $v => $l ) : ?>
						<label class="mb-quiz__opt"><input type="radio" name="q<?php echo $n; ?>" value="<?php echo $v; ?>"><span><?php echo $l; ?></span></label>
						<?php endforeach; ?>
					</div>
				</li>
				<?php endforeach; ?>
			</ol>
			<div class="mb-quiz__actions">
				<button type="submit" class="mb-btn mb-btn--primary">Показать результат</button>
				<p class="mb-quiz__hint" data-quiz-hint>Ответьте на все вопросы — результат появится здесь же.</p>
			</div>
		</form>
		<div class="mb-quiz__result" data-quiz-result hidden>
			<div class="mb-quiz__score">
				<span class="mb-quiz__score-num" data-quiz-score>0</span>
				<h3 data-quiz-title></h3>
				<p data-quiz-text></p>
				<ul data-quiz-tips></ul>
				<p data-quiz-link hidden><a class="mb-btn mb-btn--ghost mb-btn--small" href="#">Смотреть решение</a></p>
			</div>
			<?php
			echo maxbit_render_form( array(
				'topic'   => 'diagnostic',
				'variant' => 'magnet',
				'id'      => 'materials',
				'heading' => 'Получить материалы по результату',
				'text'    => 'Чек-лист и подборка разборов под вашу ситуацию. Ответим по указанному контакту, если захотите обсудить.',
				'button'  => 'Получить материалы',
			) ); // phpcs:ignore WordPress.Security.EscapeOutput
			?>
		</div>
		<noscript><p class="mb-quiz__hint">Для подсчёта результата нужен JavaScript. Материалы можно запросить через форму «Обсудить задачу» ниже.</p></noscript>
	</div>
	</section>
	<?php
	return ob_get_clean();
} );

/* ---------- Выдача материалов по именным ссылкам ---------- */

function maxbit_private_dir(): string {
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'maxbit-private';
	if ( ! is_dir( $dir ) && wp_mkdir_p( $dir ) ) {
		file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
		file_put_contents( $dir . '/index.php', "<?php // silence\n" ); // phpcs:ignore
	}
	return $dir;
}

function maxbit_material_path( string $file ): string {
	if ( str_starts_with( $file, 'module:' ) ) {
		$name = basename( substr( $file, 7 ) );
		return MAXBIT_CORE_DIR . 'assets/files/' . $name;
	}
	return maxbit_private_dir() . '/' . basename( $file );
}

/** Токен: lead|index|exp + HMAC на секрете заявок. */
function maxbit_material_token( int $lead_id, int $index, int $exp ): string {
	$data = $lead_id . '.' . $index . '.' . $exp;
	return $data . '.' . substr( hash_hmac( 'sha256', $data, maxbit_lead_secret() ), 0, 24 );
}

function maxbit_material_links( int $lead_id ): array {
	$out = array();
	$exp = time() + MAXBIT_DL_TTL;
	foreach ( maxbit_materials() as $i => $m ) {
		if ( ! is_file( maxbit_material_path( $m[1] ) ) ) {
			continue;
		}
		$out[] = array( 'title' => $m[0], 'url' => rest_url( 'maxbit/v1/dl/' . maxbit_material_token( $lead_id, $i, $exp ) ) );
	}
	return $out;
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'maxbit/v1', '/dl/(?P<token>[0-9]+\.[0-9]+\.[0-9]+\.[a-f0-9]{24})', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'callback'            => function ( WP_REST_Request $r ) {
			$token = (string) $r['token'];
			[ $lead, $index, $exp, $sig ] = explode( '.', $token );
			$expected = maxbit_material_token( (int) $lead, (int) $index, (int) $exp );
			if ( ! hash_equals( $expected, $token ) ) {
				return new WP_Error( 'bad_token', 'Ссылка недействительна.', array( 'status' => 403 ) );
			}
			if ( (int) $exp < time() ) {
				return new WP_Error( 'expired', 'Срок действия ссылки истёк. Запросите материалы ещё раз.', array( 'status' => 410 ) );
			}
			if ( 'mb_lead' !== get_post_type( (int) $lead ) ) {
				return new WP_Error( 'bad_lead', 'Ссылка недействительна.', array( 'status' => 403 ) );
			}
			$materials = maxbit_materials();
			if ( ! isset( $materials[ (int) $index ] ) ) {
				return new WP_Error( 'no_file', 'Материал больше не доступен.', array( 'status' => 404 ) );
			}
			$path = maxbit_material_path( $materials[ (int) $index ][1] );
			if ( ! is_file( $path ) ) {
				return new WP_Error( 'no_file', 'Файл не найден.', array( 'status' => 404 ) );
			}
			update_post_meta( (int) $lead, '_mb_dl_count', (int) get_post_meta( (int) $lead, '_mb_dl_count', true ) + 1 );
			update_post_meta( (int) $lead, '_mb_dl_last', gmdate( 'c' ) );
			nocache_headers();
			header( 'X-Robots-Tag: noindex, nofollow', true );
			header( 'Content-Type: ' . ( wp_check_filetype( $path )['type'] ?: 'application/octet-stream' ) );
			header( 'Content-Length: ' . filesize( $path ) );
			header( 'Content-Disposition: attachment; filename="' . basename( $path ) . '"' );
			readfile( $path ); // phpcs:ignore
			exit;
		},
	) );
} );
