<?php
/**
 * Решения (переработка 27.10, решение владельца): навигатор «ситуация → решение».
 * Три одинаковых блока: проблема клиента, «Узнаёте?» (те же ситуации, что на главной), «Что меняется»,
 * «С чего начинаем», пример проекта, кнопки. Затем — связка решений и сервисы, регулярная работа, форма.
 * Ситуации на главной ведут сюда по якорям (#zakazy, #b24-1c, #edinaya-sistema).
 * Тексты — черновик на согласование (reports/v4/PROPOSAL_RESHENIYA.md). Кейсы берутся из базы (не из кода).
 */
get_header();
$sol = function ( string $slug ) {
	$p = get_posts( array( 'post_type' => 'mb_solution', 'name' => $slug, 'post_status' => 'publish', 'numberposts' => 1 ) );
	return ( $p && ( ! function_exists( 'maxbit_is_publicly_visible' ) || maxbit_is_publicly_visible( $p[0]->ID ) ) ) ? $p[0] : null;
};
$page = function ( string $path ) {
	$p = get_page_by_path( $path );
	return ( $p && 'publish' === $p->post_status ) ? $p : null;
};
$blocks = array(
	array(
		'id'     => 'zakazy',
		'short'  => 'Заказы между отделами',
		'title'  => 'Заказы теряются между отделами',
		'name'   => 'Управление заказами и исполнением',
		'post'   => $sol( 'zakaz-pod-kontrolem' ),
		'topic'  => 'orders',
		'case'   => 'gk-vpk',
		'sit'    => array(
			array( 'Не видно, что происходит с заказами', 'Чтобы узнать статус, приходится звонить сотрудникам и собирать сведения из переписки.' ),
			array( 'Подразделения работают несогласованно', 'Продажи, производство, снабжение и бухгалтерия по-разному понимают договорённости.' ),
		),
		'change' => array( 'У заказа один путь от заявки до отгрузки и оплаты, с понятными этапами.', 'На каждом этапе известно, кто отвечает и какие данные нужны.', 'Статус и история изменений видны без расспросов.' ),
		'start'  => 'Разбираем путь одного типичного заказа вместе с участниками.',
	),
	array(
		'id'     => 'b24-1c',
		'short'  => 'Битрикс24 и 1С',
		'title'  => 'Данные приходится переносить между Битрикс24 и 1С',
		'name'   => 'Интеграция Битрикс24 и 1С',
		'post'   => $sol( 'integraciya-bitrix24-i-1c' ),
		'topic'  => 'integration',
		'case'   => 'lentochka',
		'sit'    => array(
			array( 'Программы есть, а общей работы нет', '1С, CRM, таблицы и почта используются отдельно. Одни и те же данные вводят несколько раз.' ),
			array( 'Сотрудники заняты повторяющимися действиями', 'Переносят данные, ищут документы и вручную готовят отчёты.' ),
		),
		'change' => array( 'Для каждого вида данных определено, где он создаётся и куда передаётся.', 'Обмен между Битрикс24 и 1С настроен и проверен на рабочих примерах.', 'Сотрудники перестают переносить данные вручную.' ),
		'start'  => 'Составляем карту данных: что, где и кем создаётся.',
	),
	array(
		'id'     => 'edinaya-sistema',
		'short'  => 'Единая система',
		'title'  => 'Нужна единая система для работы отделов',
		'name'   => 'Битрикс24 для производственных компаний',
		'post'   => $page( 'tekhnologii/bitrix24-dlya-proizvodstva' ),
		'topic'  => 'b24',
		'case'   => 'ngsk',
		'sit'    => array(
			array( 'Руководитель собирает картину вручную', 'Данные о продажах, исполнении и оплатах находятся в разных местах.' ),
			array( 'Компания выросла, процессы — ещё нет', 'Правила, которые работали при небольшой нагрузке, перестают справляться.' ),
		),
		'change' => array( 'Отделы работают в одном Битрикс24, настроенном под ваши процессы.', 'Задачи, согласования и передача работы между подразделениями проходят в системе.', 'Руководитель видит ход работы без ручных сводок.' ),
		'start'  => 'Выбираем один процесс, с которого запускаем систему, и подключаем остальные постепенно.',
	),
);
$blocks = array_values( array_filter( $blocks, fn( $b ) => null !== $b['post'] ) );
$how    = $page( 'kak-rabotaem' );
$svc    = function_exists( 'maxbit_tech_hub_active' ) && maxbit_tech_hub_active();
?>
<div class="mb-container mb-page mb-page--wide mb-sol">
	<?php maxbit_the_breadcrumbs(); ?>
	<div class="mb-page__intro mb-page__intro--split">
		<h1 class="mb-page__title">Решения для производственных компаний</h1>
		<div>
			<p class="mb-lead">Три направления, с которых чаще всего начинают. Найдите похожую ситуацию — покажем, что меняем и с чего начать.</p>
			<?php if ( count( $blocks ) > 1 ) : ?>
			<nav class="mb-sol__jump" aria-label="Перейти к решению">
				<?php foreach ( $blocks as $b ) : ?><a href="#<?php echo esc_attr( $b['id'] ); ?>"><?php echo esc_html( $b['short'] ); ?></a><?php endforeach; ?>
			</nav>
			<?php endif; ?>
		</div>
	</div>

	<?php foreach ( $blocks as $i => $b ) :
		$case = function_exists( 'maxbit_projects_by_slugs' ) ? maxbit_projects_by_slugs( array( $b['case'] ) ) : array();
		?>
	<section class="mb-sol__block" id="<?php echo esc_attr( $b['id'] ); ?>" aria-labelledby="sol-<?php echo esc_attr( $b['id'] ); ?>">
		<div class="mb-sol__head">
			<span class="mb-sol__num" aria-hidden="true"><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span>
			<div>
				<h2 id="sol-<?php echo esc_attr( $b['id'] ); ?>"><?php echo esc_html( $b['title'] ); ?></h2>
				<p class="mb-sol__name">Решение: <a href="<?php echo esc_url( get_permalink( $b['post'] ) ); ?>"><?php echo esc_html( $b['name'] ); ?></a></p>
				<?php if ( has_excerpt( $b['post'] ) ) : // краткое описание решения редактируется в админке ?><p class="mb-sol__about"><?php echo esc_html( get_the_excerpt( $b['post'] ) ); ?></p><?php endif; ?>
			</div>
		</div>
		<div class="mb-sol__body">
			<div class="mb-sol__col">
				<h3 class="mb-sol__h3">Узнаёте?</h3>
				<ul class="mb-sol__sit">
					<?php foreach ( $b['sit'] as $s ) : ?><li><strong><?php echo esc_html( $s[0] ); ?></strong><span><?php echo esc_html( $s[1] ); ?></span></li><?php endforeach; ?>
				</ul>
			</div>
			<div class="mb-sol__col">
				<h3 class="mb-sol__h3">Что меняется</h3>
				<ul class="mb-sol__change">
					<?php foreach ( $b['change'] as $c ) : ?><li><?php echo esc_html( $c ); ?></li><?php endforeach; ?>
				</ul>
				<p class="mb-sol__start"><strong>С чего начинаем:</strong> <?php echo esc_html( $b['start'] ); ?></p>
			</div>
		</div>
		<div class="mb-sol__foot">
			<?php if ( $case ) :
				$c      = $case[0];
				$client = (string) get_post_meta( $c->ID, '_mb_pf_client', true );
				$ctitle = (string) get_post_meta( $c->ID, '_mb_card_title', true ) ?: get_the_title( $c );
				?>
			<a class="mb-sol__case" href="<?php echo esc_url( get_permalink( $c ) ); ?>"><span class="mb-sol__case-label">Пример · <?php echo esc_html( $client ); ?></span><span class="mb-sol__case-title"><?php echo esc_html( $ctitle ); ?></span></a>
			<?php endif; ?>
			<div class="mb-sol__actions">
				<a class="mb-btn mb-btn--secondary" href="<?php echo esc_url( get_permalink( $b['post'] ) ); ?>">Подробнее о решении</a>
				<a class="mb-btn mb-btn--primary" href="#contact" data-mb-purpose="discuss">Обсудить такую задачу</a>
			</div>
		</div>
	</section>
	<?php endforeach; ?>

	<section class="mb-sol__extra" aria-label="Как решения связаны">
		<div class="mb-sol__extra-item">
			<h2>Часто нужно несколько решений сразу</h2>
			<p>Начинаем с одного направления, затем связываем остальное. Например, сначала наводим порядок в заказах, потом настраиваем обмен с 1С и документооборот.</p>
			<?php if ( $svc ) : ?><p><a class="mb-btn mb-btn--link" href="<?php echo esc_url( home_url( '/tekhnologii/' ) ); ?>">Как связываем сервисы</a></p><?php endif; ?>
		</div>
		<div class="mb-sol__extra-item">
			<h2>После запуска</h2>
			<p>Не уходим после внедрения: в ежемесячном формате выбираем следующие приоритеты, внедряем и проверяем, как изменения работают.</p>
			<?php if ( $how ) : ?><p><a class="mb-btn mb-btn--link" href="<?php echo esc_url( get_permalink( $how ) . '#regular-development' ); ?>">Как устроена регулярная работа</a></p><?php endif; ?>
		</div>
	</section>

	<?php echo maxbit_theme_form( array( 'topic' => 'general', 'purposes' => '1', 'heading' => 'Не нашли свою ситуацию?', 'text' => 'Опишите задачу своими словами. На бесплатной встрече примерно на 30 минут разберём её и предложим первый шаг.' ) ); // phpcs:ignore ?>
</div>
<?php get_footer(); ?>
