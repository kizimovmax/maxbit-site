<?php
defined( 'ABSPATH' ) || exit;

/**
 * Новые направления (задание владельца 03.11, «01_TASK»): три страницы решений, входы на главной и вопросы в FAQ.
 *
 * — /resheniya/ii-praktikum/ — существующий черновик «ИИ-Практикум»: публикуется как «Обучение сотрудников работе с ИИ»
 *   (раздел «Внутренние процессы»); текст страницы сохраняется, меняются надзаголовок, заголовок и вводный абзац;
 * — /resheniya/koordinaciya-podryadchikov/ — новая страница (раздел «Внутренние процессы»);
 * — /resheniya/cifrovaya-sreda-kompanii/ — новая страница «Связываем программы и работу сотрудников» (раздел «Данные и системы»);
 * — главная: ситуация «Программы есть, а общей работы нет» → «Программы работают отдельно» со ссылкой на цифровую среду,
 *   две новые ситуации (подрядчики, ИИ), три вопроса в FAQ.
 *
 * Кейсы к новым направлениям не привязываются (опыт не приписывается автоматически). Цен, сроков и результатов нет.
 * Перед записью — резервная копия (maxbit_directions_backup_<время>): содержимое и поля изменённых записей, список
 * созданных. Откат — maxbit_directions_restore(): созданные записи — в корзину, остальное — как было.
 * REST: POST maxbit/v1/update/directions {dry_run}, update/directions-restore {backup, confirm:"restore"};
 * WP-CLI: wp maxbit directions [--dry-run].
 */

function maxbit_dir_blocks(): array {
	$e  = fn( $t ) => esc_html( $t );
	$p  = fn( $t, $c = '' ) => '<!-- wp:paragraph' . ( $c ? ' {"className":"' . $c . '"}' : '' ) . " -->\n<p" . ( $c ? ' class="' . $c . '"' : '' ) . '>' . $t . "</p>\n<!-- /wp:paragraph -->\n";
	$h  = fn( $t, $l = 2, $id = '' ) => '<!-- wp:heading' . ( 2 !== $l ? ' {"level":' . $l . '}' : '' ) . " -->\n<h{$l} class=\"wp-block-heading\"" . ( $id ? ' id="' . $id . '"' : '' ) . '>' . esc_html( $t ) . "</h{$l}>\n<!-- /wp:heading -->\n";
	$ul = fn( array $a ) => "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . implode( '', array_map( fn( $x ) => "<!-- wp:list-item -->\n<li>" . $x . "</li>\n<!-- /wp:list-item -->", $a ) ) . "</ul>\n<!-- /wp:list -->\n";
	$ol = fn( array $a ) => "<!-- wp:list {\"ordered\":true} -->\n<ol class=\"wp-block-list\">" . implode( '', array_map( fn( $x ) => "<!-- wp:list-item -->\n<li>" . $x . "</li>\n<!-- /wp:list-item -->", $a ) ) . "</ol>\n<!-- /wp:list -->\n";
	$sc = fn( $t ) => "<!-- wp:shortcode -->\n{$t}\n<!-- /wp:shortcode -->\n";
	$qa = fn( $q, $a ) => "<!-- wp:details -->\n<details class=\"wp-block-details\"><summary>" . esc_html( $q ) . "</summary><!-- wp:paragraph -->\n<p>" . esc_html( $a ) . "</p>\n<!-- /wp:paragraph -->\n</details>\n<!-- /wp:details -->\n";
	$b  = fn( $lead, $rest ) => '<strong>' . esc_html( $lead ) . '</strong> ' . esc_html( $rest );
	$links = fn( array $a ) => $p( implode( ' ', array_map( fn( $l ) => '<a class="mb-btn mb-btn--link" href="' . esc_url( $l[0] ) . '">' . esc_html( $l[1] ) . '</a>', $a ) ), 'mb-links' );
	return compact( 'e', 'p', 'h', 'ul', 'ol', 'sc', 'qa', 'b', 'links' );
}

/**
 * [maxbit_next_steps items="sol:slug,page:slug,services"] — «Что ещё посмотреть»: карточки связанных решений и разделов
 * (правка владельца 03.11: вместо строки ссылок и отдельной плашки сервисов). Неопубликованные пропускаются.
 */
add_shortcode( 'maxbit_next_steps', function ( $atts ) {
	$a     = shortcode_atts( array( 'items' => '', 'heading' => 'Что ещё посмотреть' ), $atts );
	$cards = array();
	foreach ( array_filter( array_map( 'trim', explode( ',', $a['items'] ) ) ) as $ref ) {
		[ $type, $slug ] = array_pad( explode( ':', $ref, 2 ), 2, '' );
		if ( 'services' === $type ) {
			$svc = get_page_by_path( 'tekhnologii' );
			if ( $svc && 'publish' === $svc->post_status ) {
				$cards[] = array( 'Сервисы', 'Сервисы для задач компании', 'CRM, 1С, ЭДО, телефония и мессенджеры: какие сервисы подходят под задачу и как их связать.', get_permalink( $svc ) );
			}
			continue;
		}
		$p = 'sol' === $type ? maxbit_dir_find( $slug ) : ( 'page' === $type ? get_page_by_path( $slug ) : null );
		if ( ! $p || 'publish' !== $p->post_status || ( 'sol' === $type && ! maxbit_is_publicly_visible( $p->ID ) ) ) {
			continue;
		}
		$title = (string) ( get_post_meta( $p->ID, '_mb_catalog_title', true ) ?: get_the_title( $p ) );
		$text  = has_excerpt( $p ) ? get_the_excerpt( $p ) : (string) get_post_meta( $p->ID, '_mb_seo_description', true );
		$text  = wp_trim_words( wp_strip_all_tags( $text ), 18, '…' );
		$cards[] = array( 'sol' === $type ? 'Решение' : 'MAXBIT', $title, $text, get_permalink( $p ) );
	}
	if ( ! $cards ) {
		return '';
	}
	$out = '<section class="mb-next" aria-label="' . esc_attr( $a['heading'] ) . '"><h2 class="mb-h2--plain mb-next__h">' . esc_html( $a['heading'] ) . '</h2><div class="mb-next__grid">';
	foreach ( $cards as $c ) {
		$out .= '<a class="mb-next__card" href="' . esc_url( $c[3] ) . '"><span class="mb-next__label">' . esc_html( $c[0] ) . '</span><span class="mb-next__title">' . esc_html( $c[1] ) . '</span>' . ( $c[2] ? '<span class="mb-next__text">' . esc_html( $c[2] ) . '</span>' : '' ) . '</a>';
	}
	return $out . '</div></section>';
} );

/** Правка 03.11: «Связанные задачи» (строка ссылок) → карточки [maxbit_next_steps]; у цифровой среды — ссылки из середины в конец. */
function maxbit_dir_next_fix( string $slug, string $c ): string {
	$sc_coord = "<!-- wp:shortcode -->\n[maxbit_next_steps items=\"sol:cifrovaya-sreda-kompanii,sol:porucheniya-i-otchety,page:kak-rabotaem,services\"]\n<!-- /wp:shortcode -->\n";
	$sc_env   = "<!-- wp:shortcode -->\n[maxbit_next_steps items=\"sol:integraciya-bitrix24-i-1c,page:tekhnologii/bitrix24-dlya-proizvodstva,sol:koordinaciya-podryadchikov,services\"]\n<!-- /wp:shortcode -->\n";
	if ( false !== strpos( $c, '[maxbit_next_steps' ) ) {
		return $c;
	}
	$links = '<!-- wp:paragraph \{"className":"mb-links"\} -->\s*<p class="mb-links">.*?</p>\s*<!-- /wp:paragraph -->\s*';
	if ( 'koordinaciya-podryadchikov' === $slug ) {
		return (string) preg_replace( '~<!-- wp:heading -->\s*<h2 class="wp-block-heading">Связанные задачи</h2>\s*<!-- /wp:heading -->\s*' . $links . '~su', $sc_coord, $c, 1 );
	}
	if ( 'cifrovaya-sreda-kompanii' === $slug ) {
		$c = (string) preg_replace( '~' . $links . '~su', '', $c, 1 );
		return rtrim( $c ) . "\n" . $sc_env;
	}
	return $c;
}

function maxbit_dir_data(): array {
	extract( maxbit_dir_blocks() ); // phpcs:ignore WordPress.PHP.DontExtract

	$coord = $h( 'Координация ИТ- и диджитал-подрядчиков', 1 )
		. $p( 'Сайт, реклама, CRM и интеграции делают разные исполнители, а согласовывать их работу приходится руководителю. Берём на себя координацию согласованного участка: общая картина задач, ответственные, зависимости, требования к результату и понятная сводка для вас.', 'mb-lead' )
		. $h( 'Узнаёте?' ) . $sc( '[maxbit_solution_problems]' )
		. $h( 'Что делает MAXBIT' )
		. $ul( array(
			$b( 'Собираем общую картину.', 'Какие работы идут у каждого исполнителя, что от чего зависит и где задачи пересекаются.' ),
			$b( 'Фиксируем ответственных и сроки.', 'Кто что делает, кто от кого ждёт данные или доступы, что блокирует работу.' ),
			$b( 'Формулируем требования к результату.', 'До начала работ договариваемся, что должно получиться и как это будет проверено.' ),
			$b( 'Организуем проверку.', 'Сверяем выполненное с требованиями и следим, чтобы результат одного подрядчика стыковался с работой других.' ),
			$b( 'Готовим сводку для руководителя.', 'Что сделано, что задерживается и какие решения нужны от вас.' ),
		) )
		. $h( 'Границы участия' )
		. $p( esc_html( 'Подрядчики выполняют свои работы и отвечают за их качество. MAXBIT координирует согласованный участок. Бюджеты, выбор и замена исполнителей и приёмка работ остаются за вами, если другие полномочия не согласованы отдельно.' ) )
		. $h( 'Как начинаем' )
		. $ol( array(
			esc_html( 'На бесплатной встрече примерно на 45 минут обсуждаем, какие работы и исполнители сейчас задействованы и что мешает.' ),
			esc_html( 'Разбираем текущие договорённости, задачи и доступы, определяем участок для координации и полномочия.' ),
			esc_html( 'Составляем перечень работ с ответственными, зависимостями и требованиями к результату.' ),
			esc_html( 'Договариваемся о регулярном формате: встречи с подрядчиками, порядок проверки и сводка для руководителя.' ),
		) )
		. $h( 'Что остаётся у компании' )
		. $ul( array( esc_html( 'Перечень текущих работ с ответственными и сроками.' ), esc_html( 'Требования к результату и порядок проверки.' ), esc_html( 'Регулярная сводка: что сделано, что задерживается, какие решения нужны.' ) ) )
		. $h( 'Ответы на вопросы' )
		. $qa( 'Вы заменяете наших подрядчиков?', 'Нет. Подрядчики продолжают выполнять свои работы. Мы координируем согласованный участок: задачи, сроки, зависимости и проверку результата.' )
		. $qa( 'Кто принимает работы и решает по бюджету?', 'Вы. Приёмка, бюджеты и выбор исполнителей остаются за заказчиком, если другие полномочия не согласованы отдельно.' )
		. $qa( 'Можно ли начать с одного направления, например сайта и рекламы?', 'Да. Обычно начинаем с участка, где больше всего несогласованности, и расширяем координацию, если это полезно.' )
		. $sc( '[maxbit_next_steps items="sol:cifrovaya-sreda-kompanii,sol:porucheniya-i-otchety,page:kak-rabotaem,services"]' );

	$env = $h( 'Связываем программы и работу сотрудников', 1 )
		. $p( 'Когда в компании 1С, CRM, ЭДО, почта, таблицы и хранилища используются отдельно, одни и те же данные вводят несколько раз, а правильную версию документа приходится искать. Разбираемся, как устроена работа сейчас, и составляем план: какая программа за что отвечает, кто отвечает за данные и что менять в первую очередь.', 'mb-lead' )
		. $h( 'Узнаёте?' ) . $sc( '[maxbit_solution_problems]' )
		. $h( 'Что делаем' )
		. $ol( array(
			$b( 'Разбираем один процесс от начала до конца.', 'Например, путь заказа: где появляются данные, кто их переносит и где они теряются.' ),
			$b( 'Составляем карту программ и связей.', 'Какие системы используются, какие данные в них ведутся и как они попадают из одной в другую.' ),
			$b( 'Распределяем функции и ответственность.', 'Какая программа — источник для каких данных, кто отвечает за их актуальность, где хранится действующая версия документа.' ),
			$b( 'Готовим план изменений по приоритетам.', 'С чего начать, что можно сделать настройкой, где нужен обмен данными, а что лучше не менять.' ),
		) )
		. $h( 'Что получает компания' )
		. $ul( array( esc_html( 'Карту текущих программ и связей между ними.' ), esc_html( 'Распределение функций: какая система за что отвечает и кто ответственный.' ), esc_html( 'План изменений по приоритетам с понятным первым шагом.' ) ) )
		. $h( 'Чем это отличается от интеграции' )
		. $p( esc_html( 'Разбор среды — отдельная работа: сначала выясняем, что и зачем связывать. Настройка обмена Битрикс24 и 1С и построение системы на Битрикс24 — отдельные решения; разработка и настройка обмена входят в работу только по согласованному объёму.' ) )
		. $h( 'Ответы на вопросы' )
		. $qa( 'Обязательно ли переходить на Битрикс24?', 'Нет. Сначала смотрим, что уже есть в компании. Битрикс24 предлагаем, только если текущие программы задачу не решают, и объясняем почему.' )
		. $qa( 'Вы сразу начнёте что-то настраивать?', 'Нет. Сначала разбор и план. Настройки, обмен данными и доработки — отдельным согласованным этапом.' )
		. $qa( 'С какого процесса лучше начать?', 'С того, где повторный ввод данных или поиск документов отнимают больше всего времени. Обычно это путь заказа или подготовка документов.' )
		. $sc( '[maxbit_next_steps items="sol:integraciya-bitrix24-i-1c,page:tekhnologii/bitrix24-dlya-proizvodstva,sol:koordinaciya-podryadchikov,services"]' );

	return array(
		'ii' => array(
			'slug' => 'ii-praktikum', 'title' => 'Обучение сотрудников работе с ИИ', 'order' => 74, 'stage' => 'internal',
			'excerpt' => 'Сотрудники учатся применять ИИ на своих задачах — с документами, таблицами, перепиской и информацией — по правилам работы с данными и с проверкой ответов.',
			'problems' => "Сотрудники пробуют ИИ, но у каждого свои приёмы.\nНепонятно, на каких задачах ИИ полезен, а где ему нельзя доверять.\nНет правил: какие данные можно использовать и кто проверяет ответы.",
			'seo_t' => 'Обучение сотрудников работе с ИИ на рабочих задачах | MAXBIT',
			'seo_d' => 'Практика применения ИИ на документах, таблицах и переписке компании: выбор задач и инструментов, правила работы с данными, проверка ответов, памятки для команды.',
			'replace' => array(
				'>ИИ в процессы, а не в чат</p>' => '>ИИ-Практикум</p>',
				'>ИИ-Практикум для производственных компаний</h1>' => '>Обучение сотрудников работе с ИИ</h1>',
				'<strong>Помогаем вашей команде применять ИИ в реальных рабочих задачах.</strong> Сотрудники выбирают подходящие операции' => '<strong>Помогаем вашей команде применять ИИ в реальных рабочих задачах</strong> — с документами, таблицами, перепиской и информацией. Сотрудники выбирают подходящие операции',
			),
		),
		'new' => array(
			array( 'slug' => 'koordinaciya-podryadchikov', 'title' => 'Координация ИТ- и диджитал-подрядчиков', 'order' => 72, 'stage' => 'internal', 'content' => $coord,
				'excerpt' => 'Сайт, реклама, CRM и интеграции у разных исполнителей? Собираем общую картину задач, ответственных и зависимостей, договариваемся о требованиях и проверке, готовим сводку для руководителя.',
				'problems' => "Сайт, реклама, CRM и интеграции ведут разные исполнители.\nСогласовывать их работу и сроки приходится руководителю.\nРезультат одного подрядчика не стыкуется с работой другого.",
				'seo_t' => 'Координация ИТ- и диджитал-подрядчиков | MAXBIT',
				'seo_d' => 'Координируем работу подрядчиков по сайту, рекламе, CRM и интеграциям: общая картина задач, ответственные, зависимости, требования к результату и сводка для руководителя.' ),
			array( 'slug' => 'cifrovaya-sreda-kompanii', 'title' => 'Связываем программы и работу сотрудников', 'order' => 75, 'stage' => 'systems', 'content' => $env,
				'excerpt' => 'Разбираем, как устроены программы и данные компании, составляем карту систем и связей, распределяем функции и ответственных, готовим план изменений по приоритетам.',
				'problems' => "Одни и те же данные вводят в нескольких программах.\nНепонятно, где правильные данные и действующая версия документа.\nСервисы появлялись под разные задачи, общей схемы нет.",
				'seo_t' => 'Цифровая среда компании: связываем программы и работу сотрудников | MAXBIT',
				'seo_d' => 'Разбор процесса, карта программ и связей, распределение функций и ответственных, план изменений по приоритетам — до выбора интеграций и новых систем.' ),
		),
		'home_tile_old'  => 'Программы есть, а общей работы нет',
		'home_tile_new'  => 'Программы работают отдельно',
		'home_tile_href' => '/resheniya/cifrovaya-sreda-kompanii/',
		'home_tiles'     => array(
			array( 'Подрядчиков много — согласовывать приходится вам', 'Сайт, реклама, CRM и интеграции ведут разные исполнители. Сроки, зависимости и проверку результата руководитель держит в голове.', '/resheniya/koordinaciya-podryadchikov/' ),
			array( 'Хотим применять ИИ в работе команды', 'Сотрудники пробуют нейросети, но неясно, на каких задачах это полезно, какие данные можно использовать и кто проверяет ответы.', '/resheniya/ii-praktikum/' ),
		),
		'home_faq'       => array(
			array( 'Можно начать без интеграций и новых программ?', 'Да. Можно начать с разбора одного процесса и карты программ компании: какая система за что отвечает, где данные вводят повторно и что менять в первую очередь.' ),
			array( 'Вы координируете работу других подрядчиков?', 'Да, на согласованном участке: собираем задачи, сроки и зависимости, договариваемся о требованиях к результату и готовим сводку для руководителя. Подрядчики отвечают за свои работы, бюджеты и приёмка остаются за вами.' ),
			array( 'Помогаете ли применять ИИ в работе сотрудников?', 'Да. Выбираем рабочие задачи, где ИИ полезен, практикуемся на документах, таблицах и переписке компании, договариваемся о правилах работы с данными и проверке ответов. Программу и формат согласуем отдельно.' ),
		),
	);
}

/** Главная: ситуации и FAQ через разбор блоков (не зависит от способа записи атрибутов). */
function maxbit_dir_home( string $c, array $d, array &$log ): string {
	$blocks  = parse_blocks( $c );
	$changed = false;
	$walk    = function ( array &$list ) use ( &$walk, $d, &$log, &$changed ) {
		foreach ( $list as &$bl ) {
			$cls = (string) ( $bl['attrs']['className'] ?? '' );
			if ( 'core/columns' === $bl['blockName'] && false !== strpos( $cls, 'mb-situations' ) ) {
				$html = serialize_blocks( $bl['innerBlocks'] );
				if ( false === strpos( $html, $d['home_tiles'][0][0] ) ) {
					$last = null;
					foreach ( $bl['innerBlocks'] as $i => &$col ) {
						$s = serialize_block( $col );
						if ( false !== strpos( $s, '>' . $d['home_tile_old'] . '</h3>' ) ) {
							$s   = str_replace( '>' . $d['home_tile_old'] . '</h3>', '>' . $d['home_tile_new'] . '</h3>', $s );
							$s   = preg_replace( '~href="[^"]*"~', 'href="' . $d['home_tile_href'] . '"', $s, 1 );
							$col = parse_blocks( $s )[0];
							$log[] = 'главная: ситуация «' . $d['home_tile_old'] . '» → «' . $d['home_tile_new'] . '», ссылка на цифровую среду';
						}
						$last = $col;
					}
					unset( $col );
					if ( $last ) {
						$tpl = serialize_block( $last );
						preg_match( '~<h3[^>]*>(.*?)</h3>~su', $tpl, $mh );
						preg_match( '~<!-- wp:paragraph -->\s*<p[^>]*>(.*?)</p>~su', $tpl, $mp );
						foreach ( $d['home_tiles'] as $t ) {
							$s = str_replace( array( '>' . $mh[1] . '</h3>', '>' . $mp[1] . '</p>' ), array( '>' . esc_html( $t[0] ) . '</h3>', '>' . esc_html( $t[1] ) . '</p>' ), $tpl );
							$s = preg_replace( '~href="[^"]*"~', 'href="' . $t[2] . '"', $s, 1 );
							$nb = parse_blocks( $s )[0];
							$bl['innerBlocks'][]  = $nb;
							$bl['innerContent'][] = null;
							$log[] = 'главная: новая ситуация «' . $t[0] . '»';
						}
						// innerContent: обёртка columns — [открытие, null…, закрытие]; пересобираем по числу колонок.
						$open  = $bl['innerContent'][0];
						$close = '';
						foreach ( array_reverse( $bl['innerContent'] ) as $ic ) {
							if ( is_string( $ic ) ) { $close = $ic; break; }
						}
						$bl['innerContent'] = array_merge( array( $open ), array_fill( 0, count( $bl['innerBlocks'] ), null ), array( $close ) );
						$changed = true;
					}
				}
			} elseif ( 'core/group' === $bl['blockName'] && false !== strpos( $cls, 'mb-faq' ) ) {
				$html = serialize_blocks( $bl['innerBlocks'] );
				$add  = array_filter( $d['home_faq'], fn( $q ) => false === strpos( $html, $q[0] ) );
				if ( $add ) {
					$tpl = null;
					foreach ( $bl['innerBlocks'] as $ib ) {
						if ( 'core/details' === $ib['blockName'] ) { $tpl = $ib; }
					}
					if ( $tpl ) {
						$t = serialize_block( $tpl );
						preg_match( '~<summary>(.*?)</summary>~su', $t, $mq );
						preg_match( '~<p[^>]*>(.*?)</p>~su', $t, $ma );
						$pos = 0;
						foreach ( $bl['innerBlocks'] as $i => $ib ) {
							if ( 'core/details' === $ib['blockName'] ) { $pos = $i + 1; }
						}
						foreach ( array_values( $add ) as $k => $q ) {
							$s  = str_replace( array( '<summary>' . $mq[1] . '</summary>', '>' . $ma[1] . '</p>' ), array( '<summary>' . esc_html( $q[0] ) . '</summary>', '>' . esc_html( $q[1] ) . '</p>' ), $t );
							array_splice( $bl['innerBlocks'], $pos + $k, 0, array( parse_blocks( $s )[0] ) );
							$log[] = 'главная: вопрос в FAQ «' . $q[0] . '»';
						}
						$open  = $bl['innerContent'][0];
						$close = end( $bl['innerContent'] );
						$bl['innerContent'] = array_merge( array( $open ), array_fill( 0, count( $bl['innerBlocks'] ), null ), array( is_string( $close ) ? $close : '' ) );
						$changed = true;
					}
				}
			}
			if ( ! empty( $bl['innerBlocks'] ) ) {
				$walk( $bl['innerBlocks'] );
			}
		}
		unset( $bl );
	};
	$walk( $blocks );
	return $changed ? serialize_blocks( $blocks ) : $c;
}

/** Решение по адресу в любом статусе, кроме корзины (прямой запрос: фильтры доступа скрывают черновики). */
function maxbit_dir_find( string $slug ): ?WP_Post {
	global $wpdb;
	$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'mb_solution' AND post_name = %s AND post_status <> 'trash' ORDER BY ID LIMIT 1", $slug ) );
	return $id ? get_post( $id ) : null;
}

function maxbit_directions_migrate( bool $dry = false ): array {
	$d      = maxbit_dir_data();
	$log    = array();
	$backup = array( 'created' => array(), 'posts' => array(), 'meta' => array() );
	$keys   = array( '_mb_stage', '_mb_problems', '_mb_solution_cases', '_mb_catalog_title', '_mb_public_ok', '_mb_seo_title', '_mb_seo_description', '_mb_editorial_status' );
	$save   = function ( WP_Post $p ) use ( &$backup, $keys ) {
		$backup['posts'][ $p->ID ] = array( 'post_content' => $p->post_content, 'post_status' => $p->post_status, 'post_title' => $p->post_title, 'post_excerpt' => $p->post_excerpt, 'menu_order' => $p->menu_order );
		$backup['meta'][ $p->ID ]  = array_combine( $keys, array_map( fn( $k ) => metadata_exists( 'post', $p->ID, $k ) ? get_post_meta( $p->ID, $k, true ) : null, $keys ) );
	};
	$writes = array();

	// 1. ИИ-практикум: существующая запись, без дубля.
	$ii = maxbit_dir_find( $d['ii']['slug'] );
	if ( $ii && 'publish' === $ii->post_status && $ii->post_title === $d['ii']['title'] ) {
		$log[] = 'ИИ-практикум #' . $ii->ID . ': уже опубликован — не трогаем';
	} elseif ( $ii ) {
		$p = $ii;
		$c = strtr( $p->post_content, $d['ii']['replace'] );
		$log[] = sprintf( 'ИИ-практикум #%d (%s): опубликовать как «%s», раздел «Внутренние процессы»%s', $p->ID, $p->post_status, $d['ii']['title'], $c !== $p->post_content ? ', заголовок и вводный абзац обновлены' : ', текст без изменений' );
		$writes[] = array( 'update', $p, array( 'post_status' => 'publish', 'post_title' => $d['ii']['title'], 'post_excerpt' => $d['ii']['excerpt'], 'menu_order' => $d['ii']['order'], 'post_content' => $c ),
			array( '_mb_stage' => $d['ii']['stage'], '_mb_problems' => $d['ii']['problems'], '_mb_public_ok' => '1', '_mb_seo_title' => $d['ii']['seo_t'], '_mb_seo_description' => $d['ii']['seo_d'], '_mb_editorial_status' => 'Опубликовано 03.11 (задание владельца)' ) );
	} else {
		$log[] = 'ИИ-практикум: запись /resheniya/ii-praktikum/ не найдена — пропущено (новую не создаём, чтобы не было дубля)';
	}
	// 2. Новые решения.
	foreach ( $d['new'] as $n ) {
		if ( maxbit_dir_find( $n['slug'] ) ) {
			$log[] = 'есть: /resheniya/' . $n['slug'] . '/ — не трогаем';
			continue;
		}
		$log[]    = 'создать: /resheniya/' . $n['slug'] . '/ — ' . $n['title'];
		$writes[] = array( 'create', null, array( 'post_type' => 'mb_solution', 'post_status' => 'publish', 'post_title' => $n['title'], 'post_name' => $n['slug'], 'post_excerpt' => $n['excerpt'], 'menu_order' => $n['order'], 'post_content' => $n['content'] ),
			array( '_mb_stage' => $n['stage'], '_mb_problems' => $n['problems'], '_mb_public_ok' => '1', '_mb_seo_title' => $n['seo_t'], '_mb_seo_description' => $n['seo_d'], '_mb_catalog' => '2026-11-03' ) );
	}
	// 2а. Правка 03.11: блок «Связанные задачи» у уже созданных страниц.
	foreach ( $d['new'] as $n ) {
		$ex = maxbit_dir_find( $n['slug'] );
		if ( $ex ) {
			$nc = maxbit_dir_next_fix( $n['slug'], $ex->post_content );
			if ( $nc !== $ex->post_content ) {
				$log[]    = '/resheniya/' . $n['slug'] . '/: «Связанные задачи» → карточки «Что ещё посмотреть»';
				$writes[] = array( 'update', $ex, array( 'post_content' => $nc ), array() );
			}
		}
	}
	// 3. Главная.
	$front = (int) get_option( 'page_on_front' );
	if ( $front && ( $fp = get_post( $front ) ) ) {
		$nc = maxbit_dir_home( $fp->post_content, $d, $log );
		$nc = maxbit_dir_home_situations( $nc, $log ); // 03.11: новый блок ситуаций
		if ( $nc !== $fp->post_content ) {
			$writes[] = array( 'update', $fp, array( 'post_content' => $nc ), array() );
		} else {
			$log[] = 'главная: изменений нет (уже применено или блоки не найдены)';
		}
	}
	if ( $dry || ! $writes ) {
		return array( 'ok' => true, 'dry_run' => $dry, 'log' => $log );
	}
	foreach ( $writes as $w ) {
		if ( 'update' === $w[0] ) {
			$save( $w[1] );
		}
	}
	$name = 'maxbit_directions_backup_' . gmdate( 'Ymd_His' );
	add_option( $name, $backup, '', 'no' );
	if ( ! is_array( get_option( $name ) ) ) {
		return array( 'ok' => false, 'error' => 'Не удалось сохранить резервную копию — изменения не внесены.' );
	}
	kses_remove_filters();
	foreach ( $writes as $w ) {
		[ $kind, $p, $fields, $meta ] = $w;
		if ( 'create' === $kind ) {
			$id = wp_insert_post( wp_slash( $fields ), true );
			if ( is_wp_error( $id ) ) {
				$log[] = 'ошибка: ' . $fields['post_name'] . ' — ' . $id->get_error_message();
				continue;
			}
			$backup['created'][] = $id;
			update_option( $name, $backup, false );
		} else {
			$id = $p->ID;
			wp_update_post( wp_slash( array( 'ID' => $id ) + $fields ) );
		}
		foreach ( $meta as $k => $v ) {
			update_post_meta( $id, $k, wp_slash( $v ) );
		}
	}
	update_option( $name, $backup, false );
	$log[] = 'Резервная копия: ' . $name;
	return array( 'ok' => true, 'backup' => $name, 'log' => $log );
}

function maxbit_directions_restore( string $name ): array {
	$b = get_option( $name );
	if ( ! is_array( $b ) || ! str_starts_with( $name, 'maxbit_directions_backup_' ) ) {
		return array( 'ok' => false, 'error' => 'Резервная копия не найдена.' );
	}
	kses_remove_filters();
	foreach ( $b['created'] as $id ) {
		wp_trash_post( (int) $id );
	}
	foreach ( $b['posts'] as $id => $f ) {
		wp_update_post( wp_slash( array( 'ID' => (int) $id ) + $f ) );
	}
	foreach ( $b['meta'] as $id => $m ) {
		foreach ( $m as $k => $v ) {
			null === $v ? delete_post_meta( (int) $id, $k ) : update_post_meta( (int) $id, $k, wp_slash( $v ) );
		}
	}
	return array( 'ok' => true, 'restored' => $name );
}

add_action( 'rest_api_init', function () {
	$perm = fn() => current_user_can( 'manage_options' );
	register_rest_route( 'maxbit/v1', '/update/directions', array(
		'methods' => 'POST', 'permission_callback' => $perm,
		'callback' => fn( WP_REST_Request $r ) => rest_ensure_response( maxbit_directions_migrate( (bool) $r->get_param( 'dry_run' ) ) ),
	) );
	register_rest_route( 'maxbit/v1', '/update/directions-restore', array(
		'methods' => 'POST', 'permission_callback' => $perm,
		'callback' => function ( WP_REST_Request $r ) {
			if ( 'restore' !== $r->get_param( 'confirm' ) ) {
				return new WP_Error( 'confirm', 'Нужно confirm: "restore"', array( 'status' => 400 ) );
			}
			return rest_ensure_response( maxbit_directions_restore( (string) $r->get_param( 'backup' ) ) );
		},
	) );
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'maxbit directions', function ( $args, $assoc ) {
		$r = maxbit_directions_migrate( isset( $assoc['dry-run'] ) );
		foreach ( $r['log'] ?? array() as $l ) {
			WP_CLI::log( $l );
		}
		$r['ok'] ? WP_CLI::success( $r['backup'] ?? 'проверка' ) : WP_CLI::error( $r['error'] );
	} );
}

/* ---------------------------------------------------------------- блок «Узнаёте свою ситуацию?» (03.11) */

/** Три крупные ситуации и ряд коротких (утверждено владельцем 03.11). Ссылка: 'sol:<slug>' | 'page:<path>' | '/путь/'. */
function maxbit_situations_data(): array {
	return (array) apply_filters( 'maxbit_situations', array(
		'big'   => array(
			array( 'Непонятно, на каком этапе заказ и кто за него отвечает', 'Статус, ответственный и срок видны в карточке заказа.', 'sol:zakaz-pod-kontrolem', 'order' ),
			array( 'Одно и то же вбиваем в 1С, CRM и таблицу', 'Данные вводятся один раз и сами попадают туда, где нужны.', 'sol:cifrovaya-sreda-kompanii', 'systems' ),
			array( 'Отчёты для руководителя собираем вручную', 'Продажи, заказы и оплаты видны в одном отчёте, без Excel.', 'sol:porucheniya-i-otchety', 'reports' ),
		),
		'small' => array(
			array( 'Договорённости теряются между отделами', 'sol:zakaz-pod-kontrolem' ),
			array( 'Много ручной работы', 'sol:integraciya-bitrix24-i-1c' ),
			array( 'Всё замыкается на руководителе', 'page:tekhnologii/bitrix24-dlya-proizvodstva' ),
			array( 'Подрядчики не стыкуются', 'sol:koordinaciya-podryadchikov' ),
			array( 'Хотим ИИ, но с чего начать?', 'sol:ii-praktikum' ),
		),
	) );
}

function maxbit_situation_url( string $ref ): string {
	[ $t, $v ] = array_pad( explode( ':', $ref, 2 ), 2, '' );
	if ( 'sol' === $t ) {
		$p = maxbit_dir_find( $v );
		return ( $p && 'publish' === $p->post_status && maxbit_is_publicly_visible( $p->ID ) ) ? (string) get_permalink( $p ) : '';
	}
	if ( 'page' === $t ) {
		$p = get_page_by_path( $v );
		return ( $p && 'publish' === $p->post_status ) ? (string) get_permalink( $p ) : '';
	}
	return str_starts_with( $ref, '/' ) ? home_url( $ref ) : '';
}

/** [maxbit_situations] — блок ситуаций на главной. Ситуации без опубликованной страницы не выводятся. */
add_shortcode( 'maxbit_situations', function () {
	$d     = maxbit_situations_data();
	$icons = array(
		'order'   => '<path d="M4 6h16M4 12h10M4 18h6"/><circle cx="18" cy="16" r="3"/><path d="M20.2 18.2 22 20"/>',
		'systems' => '<rect x="3" y="4" width="7" height="7"/><rect x="14" y="13" width="7" height="7"/><path d="M10 7.5h4.5v5.5"/>',
		'reports' => '<path d="M4 20V11M10 20V5M16 20v-6M2 20h20"/>',
	);
	$big = '';
	foreach ( $d['big'] as $b ) {
		$u = maxbit_situation_url( $b[2] );
		if ( ! $u ) {
			continue;
		}
		$big .= '<a class="mb-sit2__card" href="' . esc_url( $u ) . '"><svg class="mb-sit2__icon" viewBox="0 0 24 24" aria-hidden="true">' . ( $icons[ $b[3] ] ?? '' ) . '</svg><h3>' . esc_html( $b[0] ) . '</h3><p>' . esc_html( $b[1] ) . '</p><span class="mb-sit2__more">Как решаем</span></a>';
	}
	$small = '';
	foreach ( $d['small'] as $s ) {
		$u = maxbit_situation_url( $s[1] );
		if ( $u ) {
			$small .= '<a href="' . esc_url( $u ) . '">' . esc_html( $s[0] ) . '</a>';
		}
	}
	$cat    = get_post_type_archive_link( 'mb_solution' );
	$small .= $cat ? '<a class="mb-sit2__all" href="' . esc_url( $cat ) . '">Все решения</a>' : '';
	return '<div class="mb-sit2"><div class="mb-sit2__big">' . $big . '</div>' . ( $small ? '<p class="mb-sit2__label">Другие ситуации:</p><nav class="mb-sit2__row" aria-label="Другие ситуации">' . $small . '</nav>' : '' ) . '</div>';
} );

/** Миграция главной: вместо восьми карточек (columns mb-situations), подводки и строки ссылок — [maxbit_situations]. */
function maxbit_dir_home_situations( string $c, array &$log ): string {
	if ( false !== strpos( $c, '[maxbit_situations]' ) ) {
		return $c;
	}
	$blocks = parse_blocks( $c );
	$done   = false;
	foreach ( $blocks as &$sec ) {
		if ( $done || empty( $sec['innerBlocks'] ) ) {
			continue;
		}
		$idx = null;
		foreach ( $sec['innerBlocks'] as $i => $ib ) {
			if ( 'core/columns' === $ib['blockName'] && false !== strpos( (string) ( $ib['attrs']['className'] ?? '' ), 'mb-situations' ) ) {
				$idx = $i;
			}
		}
		if ( null === $idx ) {
			continue;
		}
		$new = array();
		foreach ( $sec['innerBlocks'] as $i => $ib ) {
			$cls = (string) ( $ib['attrs']['className'] ?? '' );
			if ( $i === $idx ) {
				$new[] = parse_blocks( "<!-- wp:shortcode -->\n[maxbit_situations]\n<!-- /wp:shortcode -->" )[0];
				continue;
			}
			if ( 'core/paragraph' === $ib['blockName'] && false !== strpos( $cls, 'mb-section__after' ) ) {
				$log[] = 'главная: строка «Все решения · Сервисы и интеграции» под ситуациями убрана';
				continue;
			}
			if ( 'core/group' === $ib['blockName'] && false !== strpos( $cls, 'mb-section__head' ) ) {
				$ib['innerBlocks']  = array_values( array_filter( $ib['innerBlocks'], fn( $x ) => 'core/paragraph' !== $x['blockName'] ) );
				$open               = $ib['innerContent'][0];
				$close              = end( $ib['innerContent'] );
				$ib['innerContent'] = array_merge( array( $open ), array_fill( 0, count( $ib['innerBlocks'] ), null ), array( is_string( $close ) ? $close : '' ) );
				$log[] = 'главная: подводка справа от «Узнаёте свою ситуацию?» убрана';
			}
			$new[] = $ib;
		}
		$sec['innerBlocks']  = $new;
		$open                = $sec['innerContent'][0];
		$close               = end( $sec['innerContent'] );
		$sec['innerContent'] = array_merge( array( $open ), array_fill( 0, count( $new ), null ), array( is_string( $close ) ? $close : '' ) );
		$log[]               = 'главная: восемь карточек ситуаций → блок [maxbit_situations] (3 крупные + 5 кнопок + «Все решения»)';
		$done                = true;
	}
	unset( $sec );
	return $done ? serialize_blocks( $blocks ) : $c;
}
