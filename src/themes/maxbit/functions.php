<?php
/**
 * MAXBIT theme 0.7.3 «Графит» + содержание v4. Тема — только оформление. Типы, поля, форма, согласия, SEO — в модуле maxbit-core.
 */
defined( 'ABSPATH' ) || exit;

define( 'MAXBIT_THEME_VERSION', '0.9.0' );

/**
 * Тема работает и без модуля maxbit-core (без формы, типов и допуска). Обёртки с префиксом maxbit_theme_
 * намеренно не совпадают с именами функций модуля: иначе активация модуля при уже загруженной теме
 * давала бы «Cannot redeclare».
 */
function maxbit_theme_form( array $args = array() ): string {
	if ( function_exists( 'maxbit_render_form' ) ) {
		return maxbit_render_form( $args );
	}
	return current_user_can( 'manage_options' ) ? '<p class="mb-note">Форма недоступна: активируйте модуль MAXBIT Core.</p>' : '';
}
function maxbit_theme_project_fields(): array {
	return function_exists( 'maxbit_project_fields' ) ? maxbit_project_fields() : array();
}
function maxbit_theme_can_preview(): bool {
	return function_exists( 'maxbit_can_preview' ) ? maxbit_can_preview() : ( is_user_logged_in() && current_user_can( 'edit_posts' ) );
}
function maxbit_theme_env(): string {
	return function_exists( 'maxbit_env' ) ? maxbit_env() : ( defined( 'MAXBIT_ENV' ) ? MAXBIT_ENV : 'staging' );
}
function maxbit_theme_settings(): array {
	return function_exists( 'maxbit_get_settings' ) ? maxbit_get_settings() : array();
}
add_action( 'admin_notices', function () {
	if ( ! function_exists( 'maxbit_lead_topics' ) && current_user_can( 'activate_plugins' ) ) {
		echo '<div class="notice notice-error"><p><strong>MAXBIT:</strong> модуль «MAXBIT Core» не активен. Без него не работают форма, решения, проекты и защита закрытых материалов.</p></div>';
	}
} );

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets' ) );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/editor.css' );
	add_theme_support( 'wp-block-styles' );
	remove_theme_support( 'core-block-patterns' );   // только свои паттерны — меньше случайных макетов
	register_nav_menus( array(
		'primary' => 'Главное меню',
		'footer'  => 'Меню в подвале',
	) );
	add_image_size( 'mb-card', 800, 500, true );
} );

add_action( 'wp_enqueue_scripts', function () {
	// Версия файла = версия темы + время изменения: браузеры не держат старые стили после обновления.
	$ver = function ( string $f ) { $p = get_theme_file_path( $f ); return MAXBIT_THEME_VERSION . '.' . ( file_exists( $p ) ? filemtime( $p ) : 0 ); };
	wp_enqueue_style( 'maxbit', get_theme_file_uri( 'assets/style.css' ), array(), $ver( 'assets/style.css' ) );
	wp_enqueue_script( 'maxbit-theme', get_theme_file_uri( 'assets/theme.js' ), array(), $ver( 'assets/theme.js' ), array( 'strategy' => 'defer', 'in_footer' => true ) );
	wp_dequeue_style( 'wp-block-library-theme' );
	wp_dequeue_style( 'classic-theme-styles' );
	wp_dequeue_style( 'global-styles' ); // цвета/шрифты уже в assets/style.css через те же токены
}, 20 );

/** Отключаем лишнее в head: emoji и т.п. делает модуль; здесь — скрипты комментариев. */
add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_script( 'comment-reply' );
} );

/** Паттерны редактора. */
add_action( 'init', function () {
	register_block_pattern_category( 'maxbit', array( 'label' => 'MAXBIT' ) );
	foreach ( glob( get_theme_file_path( 'patterns/*.php' ) ) as $file ) {
		$data = get_file_data( $file, array( 'title' => 'Title', 'slug' => 'Slug', 'description' => 'Description' ) );
		ob_start();
		include $file;
		$content = ob_get_clean();
		register_block_pattern( 'maxbit/' . $data['slug'], array(
			'title'       => $data['title'],
			'description' => $data['description'],
			'categories'  => array( 'maxbit' ),
			'content'     => $content,
		) );
	}
} );

/** Только нужные блоки — редактор без случайных макетов. */
add_filter( 'allowed_block_types_all', function ( $allowed, $ctx ) {
	return array(
		'core/paragraph', 'core/heading', 'core/list', 'core/list-item', 'core/quote', 'core/image', 'core/group',
		'core/columns', 'core/column', 'core/buttons', 'core/button', 'core/separator', 'core/spacer',
		'core/table', 'core/shortcode', 'core/details', 'core/html',
	);
}, 10, 2 );

/** Дата по-русски без зависимости от языкового пакета ядра. */
function maxbit_theme_date_ru( int $post_id ): string {
	$m = array( 1 => 'января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря' );
	$ts = get_post_time( 'U', false, $post_id );
	return (int) gmdate( 'j', $ts ) . ' ' . $m[ (int) gmdate( 'n', $ts ) ] . ' ' . gmdate( 'Y', $ts );
}

/**
 * Инлайн SVG из комплекта бренда (assets/brand; логотип v2 в assets/brand/v2, файлы байт-в-байт из
 * MAXBIT_logo_FINAL_v2). При выводе убираем XML-пролог, <title>/<desc>/aria-label: подпись задаётся рядом текстом,
 * сам знак декоративен. Контуры не меняются.
 */
function maxbit_brand_svg( string $file ): string {
	$path = get_theme_file_path( 'assets/brand/' . $file );
	if ( ! file_exists( $path ) ) {
		return '';
	}
	$svg = (string) file_get_contents( $path );
	$svg = preg_replace( '#<\?xml[^>]*\?>#u', '', $svg );
	$svg = preg_replace( '#<title>.*?</title>#su', '', $svg );
	$svg = preg_replace( '#<desc>.*?</desc>#su', '', $svg );
	$svg = preg_replace( '#\s(role|aria-label)="[^"]*"#u', '', $svg, 2 );
	return preg_replace( '#<svg\b#u', '<svg aria-hidden="true" focusable="false"', $svg, 1 );
}

/**
 * X из логотипа FINAL_v2 (геометрия взята из assets/brand/v2/MAXBIT_compact_*.svg без изменений):
 * синий ‹ снизу, второй шеврон › поверх. На тёмном фоне второй шеврон белый (как в инверсном логотипе),
 * на светлом — графит. Декоративный, без текста.
 */
function maxbit_theme_x_svg( string $class = '', bool $on_dark = true ): string {
	$top = $on_dark ? '#FFFFFF' : '#0F172A';
	return '<svg class="' . esc_attr( $class ) . '" viewBox="0 0 139 110" width="139" height="110" aria-hidden="true" focusable="false">'
		. '<path d="M100 0H139L89 55L139 110H100L50 55Z" fill="#2563EB"/>'
		. '<path d="M0 0H39L89 55L39 110H0L50 55Z" fill="' . $top . '"/>'
		. '</svg>';
}

/** «Обновлено: дата» под текстом решений и страниц (данные — из модуля). */
function maxbit_theme_updated(): void {
	if ( function_exists( 'maxbit_updated_line' ) ) {
		echo maxbit_updated_line(); // phpcs:ignore WordPress.Security.EscapeOutput -- собрано из экранированных частей в модуле
	}
}

function maxbit_the_breadcrumbs(): void {
	if ( ! function_exists( 'maxbit_breadcrumbs' ) ) {
		return;
	}
	$items = maxbit_breadcrumbs();
	if ( count( $items ) < 2 ) {
		return;
	}
	echo '<nav class="mb-crumbs" aria-label="Хлебные крошки"><ol>';
	foreach ( $items as $c ) {
		echo '<li>' . ( $c[1] ? '<a href="' . esc_url( $c[1] ) . '">' . esc_html( $c[0] ) . '</a>' : '<span aria-current="page">' . esc_html( $c[0] ) . '</span>' ) . '</li>';
	}
	echo '</ol></nav>';
}

/** Карточка материала в списках. */
function maxbit_card( WP_Post $post, string $label = '' ): void {
	$closed = function_exists( 'maxbit_is_publicly_visible' ) && ! maxbit_is_publicly_visible( $post->ID );
	?>
	<article class="mb-card<?php echo $closed ? ' mb-card--closed' : ''; ?>">
		<?php if ( $label ) : ?><p class="mb-card__label"><?php echo esc_html( $label ); ?></p><?php endif; ?>
		<h3 class="mb-card__title"><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( get_the_title( $post ) ); ?></a></h3>
		<?php if ( has_excerpt( $post ) ) : ?><p class="mb-card__text"><?php echo esc_html( get_the_excerpt( $post ) ); ?></p><?php endif; ?>
		<p class="mb-card__more">Читать</p>
		<?php if ( $closed ) : ?><p class="mb-badge mb-badge--draft">Закрытый просмотр: <?php echo 'publish' === $post->post_status ? 'без допуска' : esc_html( get_post_status_object( $post->post_status )->label ); ?></p><?php endif; ?>
	</article>
	<?php
}

/** Плашка черновика/закрытого просмотра для авторизованных. */
function maxbit_preview_banner( int $post_id ): void {
	if ( ! function_exists( 'maxbit_can_preview' ) || ! maxbit_can_preview() ) {
		return;
	}
	$post = get_post( $post_id );
	$notes = array();
	if ( 'publish' !== $post->post_status ) {
		$notes[] = 'статус «' . get_post_status_object( $post->post_status )->label . '»';
	}
	if ( function_exists( 'maxbit_is_public_ok' ) && in_array( $post->post_type, array( 'mb_solution', 'mb_project' ), true ) && ! maxbit_is_public_ok( $post_id ) ) {
		$notes[] = 'допуск к публичному показу выключен';
	}
	if ( '1' === get_post_meta( $post_id, '_mb_is_sample', true ) ) {
		$notes[] = 'учебный образец структуры, не кейс';
	}
	if ( $notes ) {
		echo '<div class="mb-preview-banner" role="note"><strong>Закрытый просмотр.</strong> Анонимным посетителям страница не видна: ' . esc_html( implode( '; ', $notes ) ) . '.</div>';
	}
}

/** Связанные материалы: явные связи из редактора, иначе — общие рубрики (роль / процесс / система), только допущенные. */
function maxbit_related_block( int $post_id ): void {
	$items = array();
	if ( function_exists( 'maxbit_get_related' ) ) {
		foreach ( array( 'solution' => 'Решение', 'article' => 'Статья', 'project' => 'Кейс' ) as $kind => $label ) {
			$p = maxbit_get_related( $post_id, $kind );
			if ( $p ) {
				$items[] = array( $p, $label );
			}
		}
	}
	if ( count( $items ) < 3 && function_exists( 'maxbit_related_by_terms' ) ) {
		$have = array_map( fn( $i ) => $i[0]->ID, $items );
		foreach ( maxbit_related_by_terms( $post_id, 3 - count( $items ), array_merge( $have, array( $post_id ) ) ) as $p ) {
			$items[] = array( $p, function_exists( 'maxbit_type_label' ) ? maxbit_type_label( $p ) : '' );
		}
	}
	if ( ! $items ) {
		return;
	}
	echo '<section class="mb-related" aria-labelledby="mb-related-h"><h2 id="mb-related-h">Связанные материалы</h2><div class="mb-grid mb-grid--3">';
	foreach ( $items as $i ) {
		maxbit_card( $i[0], $i[1] );
	}
	echo '</div></section>';
}

/** Ссылка «Обсудить задачу»: на форму текущей страницы (с её темой), если она там есть; иначе на форму главной. */
function maxbit_cta_url(): string {
	if ( is_singular() ) {
		$post = get_queried_object();
		if ( $post && ( in_array( $post->post_type, array( 'post', 'mb_project', 'mb_solution' ), true ) || has_shortcode( (string) $post->post_content, 'maxbit_form' ) ) ) {
			return '#contact';
		}
	}
	return home_url( '/#contact' );
}

/**
 * Кнопки «Написать в Telegram / MAX» (боты) и «Канал в Telegram / MAX» (каналы) — точные ссылки из настроек модуля.
 * Почта — только после проверки ящика. Телефона на сайте нет (решение владельца 26.10.2026).
 */
function maxbit_theme_links( string $kind ): array {
	return function_exists( 'maxbit_messenger_links' ) ? maxbit_messenger_links( $kind ) : array();
}
function maxbit_theme_msg( string $kind = 'bots', bool $dark = false, string $label = '' ): string {
	$links = maxbit_theme_links( 'channels' === $kind ? 'channels' : 'bots' );
	if ( ! $links ) {
		return '';
	}
	$out = '<div class="mb-msg' . ( $dark ? ' mb-msg--dark' : '' ) . '">';
	if ( $label ) {
		$out .= '<p class="mb-msg__label">' . esc_html( $label ) . '</p>';
	}
	foreach ( $links as $l ) {
		$ext = str_starts_with( $l['url'], 'mailto:' ) ? '' : ' target="_blank" rel="noopener"';
		$out .= '<a class="mb-msg__btn mb-msg__btn--' . esc_attr( $l['icon'] ) . '" href="' . esc_url( $l['url'] ) . '" data-event="' . esc_attr( $l['event'] ) . '"' . $ext . '>' . esc_html( $l['label'] ) . '</a>';
	}
	return $out . '</div>';
}
/** Модуль просит разметку мессенджеров — отдаём фирменную. */
add_filter( 'maxbit_messengers_html', function ( $html, array $links, string $kind, string $style, string $label, bool $dark ) {
	if ( ! $links ) {
		return '';
	}
	if ( 'inline' === $style ) {
		$a = array();
		foreach ( $links as $l ) {
			$a[] = '<a href="' . esc_url( $l['url'] ) . '" data-event="' . esc_attr( $l['event'] ) . '" target="_blank" rel="noopener">' . esc_html( $l['short'] ) . '</a>';
		}
		return '<p class="mb-msg-inline">' . ( $label ? '<span class="mb-msg-inline__label">' . esc_html( $label ) . '</span>' : '' ) . '<span class="mb-msg-inline__links">Написать: ' . implode( ' · ', $a ) . '</span></p>';
	}
	return maxbit_theme_msg( $kind, $dark, $label );
}, 10, 6 );

/** Карточка проекта (кейса): клиент, название, краткое описание, системы. */
add_filter( 'maxbit_project_card_html', function ( $html, WP_Post $p, bool $featured ) {
	$client = get_post_meta( $p->ID, '_mb_pf_client', true );
	$title  = get_post_meta( $p->ID, '_mb_card_title', true ) ?: get_the_title( $p );
	$text   = get_post_meta( $p->ID, '_mb_card_text', true ) ?: get_the_excerpt( $p );
	$tags   = array_filter( array_map( 'trim', explode( ',', (string) get_post_meta( $p->ID, '_mb_case_tags', true ) ) ) );
	$out  = '<article class="mb-pcard">'; // все карточки проектов одинаковые (решение владельца 27.10)
	$out .= '<p class="mb-pcard__client">' . esc_html( $client ) . '</p>';
	$out .= '<h3 class="mb-pcard__title"><a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( $title ) . '</a></h3>';
	$out .= '<p class="mb-pcard__text">' . esc_html( $text ) . '</p>';
	if ( $tags ) {
		$out .= '<p class="mb-pcard__tags">' . implode( '', array_map( fn( $t ) => '<span>' . esc_html( $t ) . '</span>', $tags ) ) . '</p>';
	}
	$out .= '<p class="mb-pcard__more" aria-hidden="true">Посмотреть проект</p>';
	return $out . '</article>';
}, 10, 3 );

/** Форма модуля: после вступления — кнопки мессенджеров «или напишите сразу». */
add_action( 'maxbit_form_intro_after', function () {
	echo maxbit_theme_msg( 'bots', false, 'Или напишите нам:' ); // phpcs:ignore WordPress.Security.EscapeOutput
} );

/** Тема подменяет нейтральную карточку модуля своей (с пометкой закрытого просмотра). */
add_filter( 'maxbit_render_card', function ( $html, WP_Post $p, string $label ) {
	ob_start();
	maxbit_card( $p, $label );
	return ob_get_clean();
}, 10, 3 );

/** Публичные проекты есть? (для навигации и главной) */
function maxbit_theme_has_projects(): bool {
	return function_exists( 'maxbit_has_public_projects' ) && maxbit_has_public_projects();
}

/** Выдержка без «[…]». */
add_filter( 'excerpt_more', fn() => '…' );
add_filter( 'excerpt_length', fn() => 28 );

/** Стиль блока «Группа» — панель на фоне. */
add_action( 'init', function () {
	register_block_style( 'core/group', array( 'name' => 'panel', 'label' => 'Панель' ) );
} );

/** Пункт меню «Проекты» (архив) скрываем, пока нет допущенного кейса. */
add_filter( 'wp_nav_menu_objects', function ( array $items ) {
	if ( function_exists( 'maxbit_can_preview' ) && maxbit_theme_can_preview() ) {
		return $items;
	}
	return array_values( array_filter( $items, function ( $item ) {
		return ! ( 'post_type_archive' === $item->type && 'mb_project' === $item->object && ! maxbit_theme_has_projects() );
	} ) );
}, 20 );

/** Боковая колонка длинных страниц: компактный призыв; на узких экранах не показывается. Только оформление, без обещаний. */
function maxbit_theme_aside(): void {
	?>
	<aside class="mb-aside" aria-label="Обсудить задачу"><div class="mb-aside__sticky">
		<?php $bots = maxbit_theme_msg( 'bots', false ); if ( $bots ) : ?>
		<div class="mb-aside__card">
			<p class="mb-aside__title">Написать нам</p>
			<p>Первый разговор — бесплатно, примерно 30 минут. Форма заявки — внизу страницы.</p>
			<?php echo $bots; // phpcs:ignore WordPress.Security.EscapeOutput ?>
		</div>
		<?php endif; ?>
		<?php
		$cl = get_page_by_path( 'chek-list-peredacha-zakaza-v-proizvodstvo', OBJECT, 'post' );
		if ( $cl && 'publish' === $cl->post_status && get_the_ID() !== $cl->ID ) : ?>
		<a class="mb-aside__link" href="<?php echo esc_url( get_permalink( $cl ) ); ?>"><span class="mb-ico" style="--i: var(--i-pdf)"></span><span>Чек-лист: 12 пунктов перед передачей заказа в производство</span></a>
		<?php endif; ?>
	</div></aside>
	<?php
}

/** Карточка чек-листа для раздела «Практика» (та же, что в блоке практики модуля). */
function maxbit_theme_magnet(): void {
	$cl = get_page_by_path( 'chek-list-peredacha-zakaza-v-proizvodstvo', OBJECT, 'post' );
	if ( ! $cl || 'publish' !== $cl->post_status ) {
		return;
	}
	echo '<aside class="mb-archive__aside"><div class="mb-practice__magnet"><p class="mb-eyebrow">Чек-лист</p><h3>12 пунктов перед передачей заказа в производство</h3><p>Одна страница. Распечатайте и проверьте свой следующий заказ — без регистрации и без формы.</p><p><a class="mb-btn mb-btn--primary" href="' . esc_url( get_permalink( $cl ) ) . '">Открыть чек-лист</a></p></div></aside>';
}

/** Блок «Больше разборов — в канале» после статей: только если заданы ссылки на каналы. */
function maxbit_theme_channel(): void {
	$msg = maxbit_theme_msg( 'channels', false );
	if ( ! $msg ) {
		return;
	}
	echo '<aside class="mb-channel" aria-label="Каналы с материалами"><p><strong>Больше разборов — в канале.</strong> Короткие заметки о порядке в заказах, настройках Битрикс24 и 1С. Без рекламы.</p>' . $msg . '</aside>'; // phpcs:ignore WordPress.Security.EscapeOutput
}

/** Подсветка пункта меню: «Практика» — на статьях; на решениях и страницах WordPress по умолчанию помечает страницу записей как current_page_parent — снимаем. */
add_filter( 'nav_menu_css_class', function ( array $classes, $item ) {
	$posts_page = (int) get_option( 'page_for_posts' );
	if ( $posts_page && 'page' === $item->object && (int) $item->object_id === $posts_page ) {
		$classes = array_diff( $classes, array( 'current_page_parent' ) );
		if ( is_singular( 'post' ) ) {
			$classes[] = 'current-menu-item';
		}
	}
	return $classes;
}, 10, 2 );

/**
 * Первый экран главной: графитовая диагональная плита с X из логотипа и приглашением к самодиагностике
 * (лид-магнит, замечание владельца 26.10). Подставляется фильтром вывода — содержание страницы в базе не меняется.
 * Ссылка ведёт к блоку самодиагностики и раскрывает вопросы (quiz.js, data-mb-open). Без самодиагностики — только X.
 */
function maxbit_theme_hero_art_html(): string {
	$teaser = '';
	if ( shortcode_exists( 'maxbit_quiz' ) ) {
		$teaser = '<div class="mb-hero__teaser"><p class="mb-hero__teaser-label">Самодиагностика · 3 минуты</p><p class="mb-hero__teaser-title">Где теряются информация и задачи в вашей компании?</p><a class="mb-btn mb-btn--primary" href="#diagnostic" data-mb-open="diagnostic" data-event="quiz_open_hero">Пройти самодиагностику</a></div>';
	}
	return '<div class="mb-hero__art">' . $teaser . maxbit_theme_x_svg( 'mb-hero__x', true ) . '</div>';
}
add_filter( 'the_content', function ( string $html ): string {
	if ( ! is_front_page() || ! in_the_loop() || ! is_main_query() || false === strpos( $html, 'mb-hero__cols' ) || false !== strpos( $html, 'mb-hero__art' ) ) {
		return $html;
	}
	return preg_replace( '/(<div class="wp-block-columns[^"]*mb-hero__cols[^"]*">)/u', '$1' . maxbit_theme_hero_art_html(), $html, 1 );
}, 20 );

/** Секциям главной — класс появления при прокрутке (без JS ничего не скрывается). */
add_filter( 'the_content', function ( string $html ): string {
	if ( ! is_front_page() || ! in_the_loop() || ! is_main_query() ) {
		return $html;
	}
	return preg_replace( '/class="wp-block-group mb-section (?!mb-hero)/u', 'class="wp-block-group mb-reveal mb-section ', $html );
}, 22 );

/**
 * Сервисы и интеграции (страница /tekhnologii/, шаблон template-services.php). Фундамент раздела, 27.10:
 * список согласован владельцем; описания «Что делаем» — черновик на проверку. Статус партнёра и сертификат
 * не заполнены — выводятся только при наличии. Расширяется фильтром maxbit_services.
 */
function maxbit_theme_services(): array {
	$g = array(
		array( 'map' => 'core', 'title' => 'CRM и управление работой', 'flow' => '', 'items' => array(
			array( 'id' => 'bitrix24', 'name' => 'Битрикс24', 'kind' => 'CRM и совместная работа', 'about' => 'CRM, задачи, проекты, документы и общение сотрудников в одной системе.', 'do' => 'внедряем и настраиваем под процессы компании, связываем с учётом, документооборотом, телефонией и мессенджерами.', 'url' => '/bitrix24/', 'url_label' => 'Возможности Битрикс24', 'case_keys' => array( 'Битрикс24' ) ),
		) ),
		array( 'map' => 'left', 'title' => 'Каналы общения с клиентами', 'flow' => 'заявки, переписка, звонки', 'items' => array(
			array( 'id' => 'wazzup', 'name' => 'Wazzup', 'kind' => 'Мессенджеры в CRM', 'about' => 'Подключает переписку с клиентами в мессенджерах к CRM.', 'do' => 'настраиваем, чтобы сообщения клиентов попадали в Битрикс24 и обрабатывались в карточке клиента вместе со сделкой.', 'case_keys' => array( 'Wazzup' ) ),
			array( 'id' => 'uis', 'name' => 'UIS', 'kind' => 'Телефония', 'about' => 'Облачная телефония: виртуальная АТС, запись разговоров, статистика звонков.', 'do' => 'связываем с Битрикс24: звонки фиксируются в CRM, записи разговоров доступны в карточке клиента.', 'case_keys' => array( 'UIS' ) ),
		) ),
		array( 'map' => 'right', 'title' => 'Учёт и бухгалтерия', 'flow' => 'клиенты, товары, счета, оплаты', 'items' => array(
			array( 'id' => '1c', 'name' => '1С', 'kind' => 'Учётные системы', 'about' => 'Программы для бухгалтерии, торговли и производственного учёта.', 'do' => 'настраиваем обмен с Битрикс24: клиенты, товары, счета, оплаты и отгрузки без двойного ввода.', 'url' => '/resheniya/integraciya-bitrix24-i-1c/', 'url_label' => 'Интеграция Битрикс24 и 1С', 'case_keys' => array( '1С' ) ),
			array( 'id' => 'kontur-elba', 'name' => 'Контур.Эльба', 'kind' => 'Онлайн-бухгалтерия', 'about' => 'Онлайн-бухгалтерия для ИП и небольших компаний.', 'do' => 'помогаем связать работу с клиентами в CRM и бухгалтерский учёт.', 'case_keys' => array( 'Эльба' ) ),
		) ),
		array( 'map' => 'bottom', 'title' => 'Электронный документооборот', 'flow' => 'документы и статусы подписания', 'items' => array(
			array( 'id' => 'saby', 'name' => 'Saby (СБИС)', 'kind' => 'ЭДО и отчётность', 'about' => 'Электронный документооборот с контрагентами и отчётность.', 'do' => 'настраиваем подписание и отправку документов — счетов, УПД — из Битрикс24.', 'case_keys' => array( 'СБИС', 'Saby' ) ),
			array( 'id' => 'kontur-diadok', 'name' => 'Контур.Диадок', 'kind' => 'ЭДО', 'about' => 'Сервис электронного документооборота с контрагентами.', 'do' => 'связываем отправку и статусы документов с карточками сделок в CRM.', 'case_keys' => array( 'Диадок' ) ),
		) ),
	);
	return (array) apply_filters( 'maxbit_services', $g );
}

/** Ссылка «Сервисы и интеграции» в конце страниц решений и Битрикс24 для производства (если страница сервисов включена). */
function maxbit_theme_services_link(): void {
	if ( ! function_exists( 'maxbit_tech_hub_active' ) || ! maxbit_tech_hub_active() ) {
		return;
	}
	echo '<p class="mb-svc-cta"><span>Битрикс24, 1С, ЭДО, телефония и мессенджеры — как связываем сервисы компании.</span> <a class="mb-btn mb-btn--link" href="' . esc_url( home_url( '/tekhnologii/' ) ) . '">Сервисы и интеграции</a></p>';
}
