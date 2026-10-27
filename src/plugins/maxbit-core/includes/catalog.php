<?php
defined( 'ABSPATH' ) || exit;

/**
 * Каталог решений по типичным проблемам (решение владельца 27.10): путь заказа (заявки → заказ → снабжение →
 * отгрузка и документы → сервис), внутренние процессы, данные и системы. Направление = запись mb_solution с
 * кратким описанием; в админке у записи блок «Каталог решений»: этап, проблемы (по строке), проекты (slug).
 * Страницы наполняются постепенно. Создание направлений и страницы пилота — maxbit_catalog_migrate()
 * (REST update/catalog, откат update/catalog-restore), с резервной копией.
 */

function maxbit_catalog_stages(): array {
	return array(
		'order'    => array( 'Путь заказа', 'От первой заявки до закрывающих документов и сервиса.' ),
		'internal' => array( 'Внутренние процессы', 'Документы, согласования, поручения и отчёты.' ),
		'systems'  => array( 'Данные и системы', 'Когда программы есть, а общей работы нет.' ),
	);
}

/** Метабокс «Каталог решений» у решения. */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'maxbit_catalog', 'Каталог решений', function ( WP_Post $p ) {
		wp_nonce_field( 'mb_catalog_' . $p->ID, 'maxbit_catalog_nonce' );
		$stage = (string) get_post_meta( $p->ID, '_mb_stage', true );
		echo '<p><label>Раздел каталога<br><select name="mb_stage" style="width:100%"><option value="">— не показывать в каталоге —</option>';
		foreach ( maxbit_catalog_stages() as $k => $s ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $stage, $k, false ) . '>' . esc_html( $s[0] ) . '</option>';
		}
		echo '</select></label></p>';
		echo '<p><label>Проблемы клиента (каждая с новой строки)<br><textarea name="mb_problems" rows="5" style="width:100%">' . esc_textarea( (string) get_post_meta( $p->ID, '_mb_problems', true ) ) . '</textarea></label></p>';
		echo '<p><label>Проекты (адреса кейсов через запятую, например gk-vpk, ngsk)<br><input type="text" name="mb_cases" style="width:100%" value="' . esc_attr( (string) get_post_meta( $p->ID, '_mb_solution_cases', true ) ) . '"></label></p>';
		echo '<p class="description">Порядок в каталоге — поле «Порядок» в атрибутах записи. Краткое описание — «Отрывок».</p>';
	}, 'mb_solution', 'side', 'default' );
} );
add_action( 'save_post_mb_solution', function ( int $id ) {
	if ( ! isset( $_POST['maxbit_catalog_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['maxbit_catalog_nonce'] ) ), 'mb_catalog_' . $id ) || ! current_user_can( 'edit_post', $id ) ) {
		return;
	}
	$stage = sanitize_key( wp_unslash( $_POST['mb_stage'] ?? '' ) );
	update_post_meta( $id, '_mb_stage', array_key_exists( $stage, maxbit_catalog_stages() ) ? $stage : '' );
	update_post_meta( $id, '_mb_problems', mb_substr( sanitize_textarea_field( wp_unslash( $_POST['mb_problems'] ?? '' ) ), 0, 2000 ) );
	update_post_meta( $id, '_mb_solution_cases', implode( ',', array_filter( array_map( 'sanitize_title', explode( ',', (string) wp_unslash( $_POST['mb_cases'] ?? '' ) ) ) ) ) );
} );

function maxbit_lines( string $s ): array {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R/u', $s ) ) ) );
}

/** Направления каталога по разделам. Страница «Битрикс24 для производства» входит в «Данные и системы». */
function maxbit_solution_catalog(): array {
	$out = array();
	foreach ( maxbit_catalog_stages() as $k => $s ) {
		$out[ $k ] = array( 'title' => $s[0], 'lead' => $s[1], 'items' => array() );
	}
	$posts = get_posts( array( 'post_type' => 'mb_solution', 'post_status' => 'publish', 'numberposts' => 50, 'orderby' => 'menu_order title', 'order' => 'ASC', 'meta_key' => '_mb_stage', 'meta_compare' => '!=', 'meta_value' => '' ) );
	$extra = get_page_by_path( 'tekhnologii/bitrix24-dlya-proizvodstva' );
	if ( $extra && 'publish' === $extra->post_status && get_post_meta( $extra->ID, '_mb_stage', true ) ) {
		$posts[] = $extra;
	}
	usort( $posts, fn( $a, $b ) => $a->menu_order <=> $b->menu_order );
	foreach ( $posts as $p ) {
		if ( 'mb_solution' === $p->post_type && ! maxbit_is_publicly_visible( $p->ID ) && ! maxbit_can_preview() ) {
			continue;
		}
		$st = (string) get_post_meta( $p->ID, '_mb_stage', true );
		if ( ! isset( $out[ $st ] ) ) {
			continue;
		}
		$out[ $st ]['items'][] = array(
			'post'     => $p,
			'title'    => (string) ( get_post_meta( $p->ID, '_mb_catalog_title', true ) ?: get_the_title( $p ) ),
			'excerpt'  => has_excerpt( $p ) ? get_the_excerpt( $p ) : '',
			'problems' => maxbit_lines( (string) get_post_meta( $p->ID, '_mb_problems', true ) ),
			'cases'    => function_exists( 'maxbit_projects_by_slugs' ) ? maxbit_projects_by_slugs( explode( ',', (string) get_post_meta( $p->ID, '_mb_solution_cases', true ) ) ) : array(),
		);
	}
	return array_filter( $out, fn( $g ) => $g['items'] );
}

/** [maxbit_solution_problems] — проблемы текущего решения списком «Узнаёте?». */
add_shortcode( 'maxbit_solution_problems', function () {
	$items = maxbit_lines( (string) get_post_meta( get_the_ID(), '_mb_problems', true ) );
	return $items ? '<ul class="mb-sol-problems">' . implode( '', array_map( fn( $x ) => '<li>' . esc_html( $x ) . '</li>', $items ) ) . '</ul>' : '';
} );
/** [maxbit_solution_projects heading="…"] — карточки проектов, где применялось решение. */
add_shortcode( 'maxbit_solution_projects', function ( $atts ) {
	$a    = shortcode_atts( array( 'heading' => 'Проекты, где это решали' ), $atts );
	$ids  = (string) get_post_meta( get_the_ID(), '_mb_solution_cases', true );
	if ( '' === $ids || ! shortcode_exists( 'maxbit_projects_cards' ) ) {
		return '';
	}
	return do_shortcode( '[maxbit_projects_cards ids="' . esc_attr( $ids ) . '" heading="' . esc_attr( $a['heading'] ) . '" columns="2"]' );
} );

/* ---------------------------------------------------------------- миграция 27.10 */

function maxbit_catalog_data(): array {
	$new = array(
		array( 'zayavki-i-prodazhi', 'Заявки и продажи', 10, 'order', 'Заявки из всех каналов — в CRM, история общения с клиентом сохраняется, предложения готовятся быстрее.',
			"Заявки приходят с сайта, почты, телефона, мессенджеров и выставок — часть теряется.\nНет истории общения с клиентом: ушёл менеджер — ушла и история.\nКоммерческое предложение и расчёт готовят долго и каждый раз заново.",
			'Подключаем каналы заявок к Битрикс24, настраиваем воронку продаж под ваш процесс, шаблоны предложений и напоминания менеджерам.', 'gk-vpk,lentochka,paritet-alyans,ngsk',
			'Заявки и продажи в Битрикс24 для производственных компаний | MAXBIT', 'Заявки из сайта, почты, телефона и мессенджеров — в одной CRM. История общения с клиентом и быстрые коммерческие предложения.' ),
		array( 'snabzhenie-i-sklad', 'Снабжение и склад', 30, 'order', 'Закупки и склад связаны с заказами: видно, что заказано, что пришло и что зарезервировано.',
			"Закупка под заказ не связана с самим заказом.\nОстатки и резервы приходится уточнять вручную.",
			'Настраиваем заявки на закупку из заказа, контроль поставок и учёт остатков в связке Битрикс24 и 1С.', 'paritet-alyans',
			'Снабжение и склад: закупки под заказ и остатки | MAXBIT', 'Связываем закупки и склад с заказами клиентов: заявки на закупку, контроль поставок, остатки и резервы.' ),
		array( 'otgruzka-dokumenty-oplaty', 'Отгрузка, документы и оплаты', 40, 'order', 'Отгрузка, закрывающие документы и оплаты — в одной цепочке с заказом.',
			"Отгрузку и доставку согласуют в переписке.\nЗакрывающие документы (УПД, акты) готовят вручную, подписанные экземпляры теряются.\nНе видно, кто не оплатил и что с дебиторкой.",
			'Настраиваем этап отгрузки в заказе, формирование и отправку документов через ЭДО и контроль оплат из 1С.', 'lentochka,avtospeccentr,cupol365',
			'Отгрузка, закрывающие документы и оплаты в Битрикс24 | MAXBIT', 'Отгрузка, УПД и акты через ЭДО, контроль оплат и дебиторки — в одной цепочке с заказом.' ),
		array( 'servis-i-garantiya', 'Сервис и гарантия', 50, 'order', 'Сервисные и гарантийные обращения — с историей заказа и понятным ответственным.',
			"Гарантийные и сервисные обращения не связаны с историей заказа.\nНепонятно, кто отвечает за обращение и на какой оно стадии.",
			'Выделяем сервис в отдельный процесс: приём обращения, диагностика, работы, закрытие — с историей по клиенту и изделию.', 'gk-vpk',
			'Сервис и гарантийные обращения в Битрикс24 | MAXBIT', 'Приём и обработка сервисных и гарантийных обращений с историей заказа, ответственными и стадиями.' ),
		array( 'dokumenty-i-soglasovaniya', 'Документы и согласования', 60, 'internal', 'Договоры, счета и акты собираются по шаблонам, согласуются по маршруту и хранятся в одном месте.',
			"Договоры, счета и акты делают вручную по старым файлам.\nСогласование договоров, счетов и закупок идёт в переписке, непонятно, на ком документ.\nДокументы лежат где придётся, актуальную версию не найти.",
			'Настраиваем шаблоны документов из карточки сделки, маршруты согласования с ответственными и сроками, единое хранилище с правами доступа.', 'lentochka,cupol365',
			'Документы и согласования: договоры, счета, акты | MAXBIT', 'Шаблоны договоров, счетов и актов, маршруты согласования и единое хранилище документов в Битрикс24.' ),
		array( 'porucheniya-i-otchety', 'Поручения и отчёты руководителю', 70, 'internal', 'Поручения — с ответственными и сроками, отчёты — из системы, а не из таблиц.',
			"Поручения руководителя теряются, контроля исполнения нет.\nОтчёты для руководителя собирают вручную.",
			'Настраиваем задачи и контроль исполнения, регулярные отчёты и сводки по продажам, заказам и оплатам.', 'helper-med,ngsk',
			'Поручения и отчёты для руководителя в Битрикс24 | MAXBIT', 'Контроль поручений с ответственными и сроками, отчёты по продажам, заказам и оплатам без ручных сводок.' ),
	);
	$existing = array(
		'zakaz-pod-kontrolem'        => array( 20, 'order', "Не видно, на каком этапе заказ.\nИзменения по заказу не доходят до производства и склада.\nО сорванных сроках узнают последними.", 'gk-vpk,avtospeccentr,helper-med,cupol365', 'Заказ и производство' ),
		'integraciya-bitrix24-i-1c'  => array( 80, 'systems', "Одни и те же данные вводят в 1С и в CRM.\nМенеджеры не видят оплат и отгрузок без запроса в бухгалтерию.", 'lentochka,gk-vpk', '' ),
	);
	$page = array( 'tekhnologii/bitrix24-dlya-proizvodstva', 90, 'systems', "Вместо системы — таблицы, которые ведут разные люди.\nКомпания выросла, а правила работы держатся на отдельных сотрудниках.", 'paritet-alyans,cupol365,ngsk', 'Единая система в Битрикс24' );
	return compact( 'new', 'existing', 'page' );
}

function maxbit_catalog_content( array $n ): string {
	[ , $title, , , $excerpt, , $do ] = $n;
	$p = fn( $t ) => "<!-- wp:paragraph -->\n<p>" . esc_html( $t ) . "</p>\n<!-- /wp:paragraph -->\n";
	$h = fn( $t, $l = 2 ) => '<!-- wp:heading' . ( 2 !== $l ? ' {"level":' . $l . '}' : '' ) . " -->\n<h{$l} class=\"wp-block-heading\">" . esc_html( $t ) . "</h{$l}>\n<!-- /wp:heading -->\n";
	$sc = fn( $t ) => "<!-- wp:shortcode -->\n{$t}\n<!-- /wp:shortcode -->\n";
	return $h( $title, 1 ) . $p( $excerpt ) . $h( 'Узнаёте?' ) . $sc( '[maxbit_solution_problems]' ) . $h( 'Что делаем' ) . $p( $do )
		. $p( 'Описание направления дополняется. Расскажите о своей ситуации — обсудим её на бесплатной встрече примерно на 30 минут.' ) . $sc( '[maxbit_solution_projects]' );
}

function maxbit_pilot_page_content(): string {
	$p  = fn( $t, $c = '' ) => '<!-- wp:paragraph' . ( $c ? ' {"className":"' . $c . '"}' : '' ) . " -->\n<p" . ( $c ? ' class="' . $c . '"' : '' ) . '>' . $t . "</p>\n<!-- /wp:paragraph -->\n";
	$h  = fn( $t ) => "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . esc_html( $t ) . "</h2>\n<!-- /wp:heading -->\n";
	$ul = fn( array $a ) => "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . implode( '', array_map( fn( $x ) => "<!-- wp:list-item -->\n<li>" . esc_html( $x ) . "</li>\n<!-- /wp:list-item -->", $a ) ) . "</ul>\n<!-- /wp:list -->\n";
	$ol = fn( array $a ) => "<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\">" . implode( '', array_map( fn( $x ) => "<!-- wp:list-item -->\n<li>" . esc_html( $x ) . "</li>\n<!-- /wp:list-item -->", $a ) ) . "</ol>\n<!-- /wp:list -->\n";
	return $p( 'Чтобы вы могли оценить нашу работу на деле, а не по обещаниям, одну небольшую задачу мы можем выполнить за свой счёт. Это пилот: одна задача, заранее согласованный объём и понятный результат.', 'mb-lead' )
		. $h( 'Кому подходит' )
		. $ul( array( 'Производственным и торгово-производственным компаниям в России.', 'Компаниям, которые уже работают в Битрикс24 или приняли решение о внедрении.', 'Если со стороны компании есть ответственный, который сможет участвовать в пилоте.', 'Если после пилота вы готовы обсуждать дальнейшую работу.' ) )
		. $h( 'Какие задачи подходят' )
		. $ul( array( 'Приём заявок из одного канала — сайта, почты или мессенджера — в CRM.', 'Одна воронка сделок под реальный процесс продаж.', 'Шаблон договора или счёта, который собирается из карточки сделки.', 'Один маршрут согласования — например, договора или счёта.', 'Один отчёт для руководителя.' ) )
		. $h( 'Как это устроено' )
		. $ol( array( 'Вы заполняете анкету ниже.', 'Проводим бесплатную встречу примерно на 30 минут и разбираем задачу.', 'Сообщаем решение: подходит ли задача для пилота. Если нет — объясним почему и предложим другой формат.', 'Фиксируем задачу, объём, срок и критерий готовности в договоре.', 'Выполняем задачу и показываем результат.', 'Обсуждаем, нужна ли дальнейшая работа.' ) )
		. $h( 'Условия' )
		. $ul( array( 'Решение об участии в пилоте принимает MAXBIT. Мы можем отказать без объяснения коммерческих причин.', 'Пилот оформляется договором с символической оплатой 1 ₽.', 'В пилот входит одна задача в согласованном объёме. Всё, что выходит за его рамки, обсуждается отдельно.', 'Количество пилотов в месяц ограничено.' ) )
		. "<!-- wp:shortcode -->\n[maxbit_form variant=\"pilot\" id=\"pilot\" heading=\"Анкета пилотного проекта\" text=\"Ответьте на несколько вопросов — они помогут нам понять, подходит ли задача для пилота. Свяжемся с вами по указанному контакту.\" button=\"Отправить анкету\"]\n<!-- /wp:shortcode -->\n";
}

function maxbit_catalog_migrate( bool $dry = false ): array {
	$d      = maxbit_catalog_data();
	$log    = array();
	$backup = array( 'created' => array(), 'meta' => array(), 'menu_order' => array() );
	foreach ( $d['new'] as $n ) {
		$ex  = get_posts( array( 'post_type' => 'mb_solution', 'name' => $n[0], 'post_status' => 'any', 'numberposts' => 1 ) );
		$log[] = ( $ex ? 'есть: ' : 'создать: ' ) . '/resheniya/' . $n[0] . '/ — ' . $n[1];
	}
	foreach ( $d['existing'] as $slug => $e ) {
		$log[] = 'дополнить: /resheniya/' . $slug . '/ (раздел, проблемы, проекты)';
	}
	$log[] = 'дополнить: /' . $d['page'][0] . '/ (раздел «Данные и системы»)';
	$pilot = get_page_by_path( 'pilotnyj-proekt' );
	$log[] = ( $pilot ? 'есть: ' : 'создать: ' ) . '/pilotnyj-proekt/ — «Пилотный проект за наш счёт»';
	if ( $dry ) {
		return array( 'ok' => true, 'dry_run' => true, 'log' => $log );
	}
	$keys = array( '_mb_stage', '_mb_problems', '_mb_solution_cases', '_mb_catalog_title' );
	foreach ( $d['new'] as $n ) {
		[ $slug, $title, $order, $stage, $excerpt, $problems, , $cases, $seo_t, $seo_d ] = $n;
		if ( get_posts( array( 'post_type' => 'mb_solution', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1 ) ) ) {
			continue;
		}
		$id = wp_insert_post( wp_slash( array( 'post_type' => 'mb_solution', 'post_status' => 'publish', 'post_title' => $title, 'post_name' => $slug, 'post_excerpt' => $excerpt, 'menu_order' => $order, 'post_content' => maxbit_catalog_content( $n ) ) ), true );
		if ( is_wp_error( $id ) ) {
			$log[] = 'ошибка: ' . $slug . ' — ' . $id->get_error_message();
			continue;
		}
		foreach ( array( '_mb_stage' => $stage, '_mb_problems' => $problems, '_mb_solution_cases' => $cases, '_mb_public_ok' => '1', '_mb_seo_title' => $seo_t, '_mb_seo_description' => $seo_d, '_mb_catalog' => '2026-10-27' ) as $k => $v ) {
			update_post_meta( $id, $k, wp_slash( $v ) );
		}
		$backup['created'][] = $id;
	}
	$touch = function ( WP_Post $p, int $order, string $stage, string $problems, string $cases, string $ctitle ) use ( &$backup, $keys ) {
		$backup['meta'][ $p->ID ]       = array_combine( $keys, array_map( fn( $k ) => metadata_exists( 'post', $p->ID, $k ) ? get_post_meta( $p->ID, $k, true ) : null, $keys ) );
		$backup['menu_order'][ $p->ID ] = $p->menu_order;
		update_post_meta( $p->ID, '_mb_stage', $stage );
		update_post_meta( $p->ID, '_mb_problems', $problems );
		update_post_meta( $p->ID, '_mb_solution_cases', $cases );
		if ( $ctitle ) {
			update_post_meta( $p->ID, '_mb_catalog_title', $ctitle );
		}
		wp_update_post( array( 'ID' => $p->ID, 'menu_order' => $order ) );
	};
	foreach ( $d['existing'] as $slug => $e ) {
		$p = get_posts( array( 'post_type' => 'mb_solution', 'name' => $slug, 'post_status' => 'any', 'numberposts' => 1 ) );
		if ( $p ) {
			$touch( $p[0], $e[0], $e[1], $e[2], $e[3], $e[4] );
		}
	}
	$pg = get_page_by_path( $d['page'][0] );
	if ( $pg ) {
		$touch( $pg, $d['page'][1], $d['page'][2], $d['page'][3], $d['page'][4], $d['page'][5] );
	}
	if ( ! $pilot ) {
		$pid = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Пилотный проект за наш счёт', 'post_name' => 'pilotnyj-proekt', 'post_content' => maxbit_pilot_page_content() ) ), true );
		if ( ! is_wp_error( $pid ) ) {
			update_post_meta( $pid, '_mb_seo_title', 'Пилотный проект за наш счёт — одна задача в Битрикс24 | MAXBIT' );
			update_post_meta( $pid, '_mb_seo_description', 'Одну небольшую задачу выполним за свой счёт, чтобы вы оценили работу MAXBIT на деле. Анкета, условия и порядок участия.' );
			$backup['created'][] = $pid;
		}
	}
	$name = 'maxbit_catalog_backup_' . gmdate( 'Ymd_His' );
	add_option( $name, $backup, '', false );
	$log[] = 'Резервная копия: ' . $name;
	return array( 'ok' => true, 'backup' => $name, 'log' => $log );
}

function maxbit_catalog_restore( string $name ): array {
	$b = get_option( $name );
	if ( ! is_array( $b ) || ! str_starts_with( $name, 'maxbit_catalog_backup_' ) ) {
		return array( 'ok' => false, 'error' => 'Резервная копия не найдена.' );
	}
	foreach ( $b['created'] as $id ) {
		wp_trash_post( (int) $id );
	}
	foreach ( $b['meta'] as $id => $m ) {
		foreach ( $m as $k => $v ) {
			null === $v ? delete_post_meta( (int) $id, $k ) : update_post_meta( (int) $id, $k, $v );
		}
		wp_update_post( array( 'ID' => (int) $id, 'menu_order' => (int) ( $b['menu_order'][ $id ] ?? 0 ) ) );
	}
	return array( 'ok' => true, 'restored' => $name );
}

add_action( 'rest_api_init', function () {
	$perm = fn() => current_user_can( 'manage_options' );
	register_rest_route( 'maxbit/v1', '/update/catalog', array( 'methods' => 'POST', 'permission_callback' => $perm,
		'callback' => fn( WP_REST_Request $r ) => rest_ensure_response( maxbit_catalog_migrate( (bool) $r->get_param( 'dry_run' ) ) ) ) );
	register_rest_route( 'maxbit/v1', '/update/catalog-restore', array( 'methods' => 'POST', 'permission_callback' => $perm,
		'callback' => function ( WP_REST_Request $r ) {
			return 'restore' === $r->get_param( 'confirm' ) ? rest_ensure_response( maxbit_catalog_restore( (string) $r->get_param( 'backup' ) ) ) : new WP_Error( 'confirm', 'Нужно confirm: "restore"', array( 'status' => 400 ) );
		} ) );
} );
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'maxbit catalog', function ( $args, $assoc ) {
		$r = maxbit_catalog_migrate( isset( $assoc['dry-run'] ) );
		foreach ( $r['log'] as $l ) {
			WP_CLI::log( $l );
		}
		WP_CLI::success( $r['backup'] ?? 'проверка' );
	} );
}
