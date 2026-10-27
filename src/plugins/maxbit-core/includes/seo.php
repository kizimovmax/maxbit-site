<?php
defined( 'ABSPATH' ) || exit;

/**
 * Единственное место SEO-метаданных сайта. Второй SEO-плагин не ставить.
 * Canonical: локально/на стенде — текущий адрес среды; в production — адрес сайта.
 * robots: local/staging → noindex,nofollow для всех; production → по флагу страницы.
 */

function maxbit_site_name(): string {
	return 'MAXBIT';
}

function maxbit_document_title(): string {
	if ( is_singular() ) {
		$id     = get_queried_object_id();
		$custom = get_post_meta( $id, '_mb_seo_title', true );
		if ( $custom ) {
			return $custom;
		}
		if ( is_front_page() ) {
			return 'MAXBIT — внешняя команда цифрового развития производственных компаний';
		}
		if ( function_exists( 'maxbit_tech_hub_active' ) && maxbit_tech_hub_active() && is_page( 'tekhnologii' ) ) {
			return 'Сервисы и интеграции: Битрикс24, 1С, ЭДО, телефония | MAXBIT';
		}
		return get_the_title( $id ) . ' — MAXBIT';
	}
	$pg = max( 1, (int) get_query_var( 'paged' ) );
	$suffix = $pg > 1 ? ' — страница ' . $pg : '';
	if ( is_home() ) {
		$pp = (int) get_option( 'page_for_posts' );
		$t  = $pp ? (string) get_post_meta( $pp, '_mb_seo_title', true ) : '';
		return ( $t ?: 'Практика: статьи, чек-листы и разборы для руководителя производства — MAXBIT' ) . $suffix;
	}
	if ( is_post_type_archive( 'mb_solution' ) ) {
		return ( maxbit_seo_archive( '/resheniya/', 'title' ) ?: 'Решения для производственных компаний | MAXBIT' ) . $suffix;
	}
	if ( is_post_type_archive( 'mb_project' ) ) {
		return ( maxbit_seo_archive( '/proekty/', 'title' ) ?: 'Проекты MAXBIT' ) . $suffix;
	}
	if ( is_404() ) {
		return 'Страница не найдена — MAXBIT';
	}
	if ( is_search() ) {
		return 'Поиск — MAXBIT';
	}
	return ''; // пусто = стандартный title ядра (без рекурсии)
}
add_filter( 'pre_get_document_title', 'maxbit_document_title', 20 );

/** Заголовок/описание архивов из реестра SEO v4 (опция maxbit_seo_archives, ставится комплектом содержания). */
function maxbit_seo_archive( string $url, string $field ): string {
	$m = get_option( 'maxbit_seo_archives', array() );
	return is_array( $m ) && ! empty( $m[ $url ][ $field ] ) ? (string) $m[ $url ][ $field ] : '';
}

function maxbit_meta_description(): string {
	if ( is_singular() ) {
		$id   = get_queried_object_id();
		$desc = get_post_meta( $id, '_mb_seo_description', true );
		if ( $desc ) {
			return $desc;
		}
		$ex = get_the_excerpt( $id );
		return $ex ? wp_strip_all_tags( $ex ) : '';
	}
	if ( is_home() ) {
		$pp = (int) get_option( 'page_for_posts' );
		$d  = $pp ? (string) get_post_meta( $pp, '_mb_seo_description', true ) : '';
		return $d ?: 'Разборы типовых проблем производственных компаний, чек-листы и инструкции по учёту заказов, Битрикс24 и 1С.';
	}
	if ( is_post_type_archive( 'mb_solution' ) ) {
		return maxbit_seo_archive( '/resheniya/', 'description' ) ?: 'Управление заказами, Битрикс24 для производства и интеграция с 1С. Рабочие процессы, границы решений и регулярное цифровое развитие.';
	}
	if ( is_post_type_archive( 'mb_project' ) ) {
		return maxbit_seo_archive( '/proekty/', 'description' ) ?: 'Реальные задачи компаний: продажи, производство, документы и взаимодействие отделов. Кейсы внедрения и развития Битрикс24 и связанных систем.';
	}
	return '';
}

function maxbit_canonical_url(): string {
	global $wp;
	$paged = max( 1, (int) get_query_var( 'paged' ) );
	if ( is_singular() ) {
		$url = get_permalink( get_queried_object_id() );
		$page = max( 1, (int) get_query_var( 'page' ) );
		if ( $page > 1 ) {
			$url = trailingslashit( $url ) . $page . '/';
		}
		return $url;
	}
	if ( is_post_type_archive() ) {
		$url = get_post_type_archive_link( get_query_var( 'post_type' ) );
	} elseif ( is_home() ) {
		$url = get_permalink( (int) get_option( 'page_for_posts' ) );
	} else {
		$url = trailingslashit( home_url( $wp->request ) );
	}
	$url = $url ?: home_url( '/' );
	if ( $paged > 1 ) {
		$url = trailingslashit( $url ) . 'page/' . $paged . '/';   // каждая страница пагинации — свой canonical
	}
	return $url;
}
// Отключаем canonical ядра и выводим свой (один на страницу).
remove_action( 'wp_head', 'rel_canonical' );
add_action( 'wp_head', function () {
	if ( function_exists( 'maxbit_is_private_material' ) && maxbit_is_private_material() ) {
		return; // закрытый материал: без canonical, OG, description и разметки
	}
	if ( is_404() || is_search() ) {
		return;
	}
	$desc = maxbit_meta_description();
	if ( $desc ) {
		echo '<meta name="description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<link rel="canonical" href="' . esc_url( maxbit_canonical_url() ) . '">' . "\n";
	echo '<meta property="og:site_name" content="MAXBIT">' . "\n";
	echo '<meta property="og:title" content="' . esc_attr( maxbit_document_title() ) . '">' . "\n";
	if ( $desc ) {
		echo '<meta property="og:description" content="' . esc_attr( $desc ) . '">' . "\n";
	}
	echo '<meta property="og:url" content="' . esc_url( maxbit_canonical_url() ) . '">' . "\n";
	echo '<meta property="og:locale" content="ru_RU">' . "\n";
	echo '<meta property="og:type" content="' . ( is_singular( 'post' ) ? 'article' : 'website' ) . '">' . "\n";
	$og = maxbit_og_image();
	if ( $og ) {
		echo '<meta property="og:image" content="' . esc_url( $og['url'] ) . '">' . "\n";
		echo '<meta property="og:image:width" content="' . (int) $og['w'] . '"><meta property="og:image:height" content="' . (int) $og['h'] . '">' . "\n";
		echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	}
	if ( is_singular() && maxbit_show_updated_date() ) {
		echo '<meta property="article:modified_time" content="' . esc_attr( maxbit_content_modified( get_queried_object_id(), 'c' ) ) . '">' . "\n";
	}
	$s = maxbit_get_settings();
	if ( ! empty( $s['yandex_verification'] ) && is_front_page() ) {
		echo '<meta name="yandex-verification" content="' . esc_attr( $s['yandex_verification'] ) . '">' . "\n";
	}
}, 1 );

/** Картинка для соцсетей: миниатюра записи, иначе брендовая из темы (1200×630). */
function maxbit_og_image(): array {
	static $cache = null;
	if ( null !== $cache ) {
		return $cache;
	}
	if ( is_singular() && has_post_thumbnail() ) {
		$img = wp_get_attachment_image_src( get_post_thumbnail_id(), 'large' );
		if ( $img ) {
			return $cache = array( 'url' => $img[0], 'w' => $img[1], 'h' => $img[2], 'custom' => true );
		}
	}
	$og   = array();
	$file = get_theme_file_path( 'assets/brand/og-default.png' );
	if ( is_file( $file ) ) {
		$og = array( 'url' => get_theme_file_uri( 'assets/brand/og-default.png' ), 'w' => 1200, 'h' => 630 );
	}
	$og = apply_filters( 'maxbit_og_image', $og ); // 0.4.1: генерация в стиле «Графит» (includes/og.php)
	return $cache = is_array( $og ) ? $og : array();
}

/**
 * Дата существенного обновления: мета _mb_content_updated (ставится галочкой «Существенное обновление»
 * в редакторе), иначе дата публикации. Мелкие правки дату не сдвигают.
 */
function maxbit_content_modified( int $post_id, string $format = 'c' ): string {
	$ts = (string) get_post_meta( $post_id, '_mb_content_updated', true );
	$ts = $ts ? strtotime( $ts ) : strtotime( get_post( $post_id )->post_date_gmt . ' UTC' );
	return 'c' === $format ? gmdate( 'c', $ts ) : wp_date( $format, $ts );
}

/** Где показывать «Обновлено»: решения, статьи, содержательные страницы (не главная, не юридические). */
function maxbit_show_updated_date(): bool {
	if ( ! is_singular( array( 'post', 'mb_solution', 'page' ) ) || is_front_page() ) {
		return false;
	}
	$slug = get_post_field( 'post_name', get_queried_object_id() );
	return ! in_array( $slug, array( 'politika-obrabotki-personalnyh-dannyh', 'soglasie-na-obrabotku-personalnyh-dannyh', 'proekty', 'praktika', 'resheniya' ), true );
}

/** Строка «Обновлено: 16.10.2026» для темы. */
function maxbit_updated_line(): string {
	if ( ! maxbit_show_updated_date() ) {
		return '';
	}
	$id = get_queried_object_id();
	return '<p class="mb-updated">Обновлено: <time datetime="' . esc_attr( maxbit_content_modified( $id, 'c' ) ) . '">' . esc_html( maxbit_content_modified( $id, 'd.m.Y' ) ) . '</time></p>';
}

add_filter( 'wp_robots', function ( array $robots ) {
	// Индексирование только при совокупности: production, blog_public=1, страница публично видима и без noindex.
	// Чужой noindex (ядро, другие правила) никогда не снимаем.
	$blocked = 'production' !== MAXBIT_ENV || '1' !== (string) get_option( 'blog_public' ) || ! empty( $robots['noindex'] );
	$blocked = $blocked || is_404() || is_search() || ( is_post_type_archive( 'mb_project' ) && ! maxbit_has_public_projects() );
	if ( is_singular() ) {
		$id = get_queried_object_id();
		$blocked = $blocked || '1' === get_post_meta( $id, '_mb_noindex', true ) || ! maxbit_is_publicly_visible( $id ) || '1' === get_post_meta( $id, '_mb_is_sample', true );
	}
	if ( $blocked ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['index'], $robots['follow'] );
		return $robots;
	}
	$robots['max-image-preview'] = 'large';
	return $robots;
} );

/** robots.txt: политика по средам. Не защита данных — только сигнал роботам. */
add_filter( 'robots_txt', function ( string $output, bool $public ) {
	if ( 'production' !== MAXBIT_ENV || ! $public ) {
		return "User-agent: *\nDisallow: /\n";
	}
	$common = "Disallow: /wp-admin/\nAllow: /wp-admin/admin-ajax.php\nDisallow: /wp-json/\nDisallow: /?s=\nDisallow: /search/\nDisallow: /*?utm_\nDisallow: /*?yclid=\nDisallow: /*?gclid=\nDisallow: /*?mbr=\n";
	$out  = "User-agent: *\n" . $common . "\n";
	$out .= "User-agent: Yandex\n" . $common . "Clean-param: utm_source&utm_medium&utm_campaign&utm_content&utm_term&yclid&gclid&mbr\n\n";
	$out .= 'Sitemap: ' . home_url( '/wp-sitemap.xml' ) . "\n";
	// Обучающие и поисковые ИИ-боты (GPTBot, OAI-SearchBot, PerplexityBot, ClaudeBot, YandexAdditional) не закрываются:
	// видимость в ИИ-ответах важнее защиты от обучения (решение 11.10.2026).
	return $out;
}, 10, 2 );

function maxbit_has_public_projects(): bool {
	$q = new WP_Query( array(
		'post_type'      => 'mb_project',
		'post_status'    => 'publish',
		'posts_per_page' => 1,
		'fields'         => 'ids',
		'no_found_rows'  => true,
		'meta_query'     => array(
			array( 'key' => '_mb_public_ok', 'value' => '1' ),
			array( 'relation' => 'OR', array( 'key' => '_mb_is_sample', 'compare' => 'NOT EXISTS' ), array( 'key' => '_mb_is_sample', 'value' => '1', 'compare' => '!=' ) ),
		),
	) );
	return $q->have_posts();
}

/** Хлебные крошки: массив [label, url]. Домашняя всегда первая. */
function maxbit_breadcrumbs(): array {
	$items = array( array( 'Главная', home_url( '/' ) ) );
	if ( is_front_page() ) {
		return $items;
	}
	if ( is_singular( 'mb_solution' ) ) {
		$items[] = array( 'Решения', get_post_type_archive_link( 'mb_solution' ) );
		$items[] = array( get_the_title(), '' );
	} elseif ( is_singular( 'mb_project' ) ) {
		$items[] = array( 'Проекты', get_post_type_archive_link( 'mb_project' ) );
		$items[] = array( get_post_meta( get_queried_object_id(), '_mb_pf_client', true ) ?: get_the_title(), '' );
	} elseif ( is_singular( 'post' ) ) {
		$items[] = array( 'Практика', get_permalink( (int) get_option( 'page_for_posts' ) ) );
		$items[] = array( get_the_title(), '' );
	} elseif ( is_page() ) {
		$post = get_queried_object();
		if ( $post->post_parent && ( 'tekhnologii' !== get_post( $post->post_parent )->post_name || ( function_exists( 'maxbit_tech_hub_active' ) && maxbit_tech_hub_active() ) ) ) {
			$items[] = array( get_the_title( $post->post_parent ), get_permalink( $post->post_parent ) );
		}
		$items[] = array( 'bitrix24' === $post->post_name && ! $post->post_parent ? 'Битрикс24' : get_the_title(), '' );
	} elseif ( is_home() ) {
		$items[] = array( 'Практика', '' );
	} elseif ( is_post_type_archive( 'mb_solution' ) ) {
		$items[] = array( 'Решения', '' );
	} elseif ( is_post_type_archive( 'mb_project' ) ) {
		$items[] = array( 'Проекты', '' );
	} elseif ( is_404() ) {
		$items[] = array( 'Страница не найдена', '' );
	}
	return $items;
}

/** JSON-LD: только достоверное и видимое. Без рейтингов, Offer, SoftwareApplication. Пустые поля не выводятся. */
function maxbit_org_schema(): array {
	$s   = maxbit_get_settings();
	$org = array(
		'@type'       => 'Organization',
		'@id'         => home_url( '/#organization' ),
		'name'        => 'MAXBIT',
		'alternateName' => 'МАКСБИТ',
		'url'         => home_url( '/' ),
		'slogan'      => 'Ваша команда цифрового развития',
		'description' => 'Внешняя команда цифрового развития производственных компаний: разбираемся в задачах, проектируем процессы, внедряем изменения в Битрикс24, 1С и других системах, проверяем результат.',
		'founder'     => array( '@id' => home_url( '/osnovatel/#person' ) ),
		'areaServed'  => array( '@type' => 'Country', 'name' => 'Россия' ),
		'knowsAbout'  => array( 'управление производственными заказами', 'цифровизация производства', 'автоматизация бизнес-процессов', 'Битрикс24', '1С', 'интеграция систем' ),
	);
	$logo = get_theme_file_path( 'assets/brand/logo-512.png' );
	if ( is_file( $logo ) ) {
		$org['logo'] = array( '@type' => 'ImageObject', 'url' => get_theme_file_uri( 'assets/brand/logo-512.png' ), 'width' => 512, 'height' => 512 );
	}
	$cp = array( '@type' => 'ContactPoint', 'contactType' => 'sales', 'areaServed' => 'RU', 'availableLanguage' => 'ru' );
	// Телефон не публикуется (решение владельца); почта — только после проверки ящика.
	$mail = maxbit_public_email();
	if ( $mail ) {
		$cp['email'] = $mail;
		$org['email'] = $mail;
	}
	if ( ! empty( $s['tg_bot_url'] ) ) {
		$cp['url'] = $s['tg_bot_url'];
	} elseif ( ! empty( $s['max_bot_url'] ) ) {
		$cp['url'] = $s['max_bot_url'];
	}
	if ( isset( $cp['email'] ) || isset( $cp['url'] ) ) {
		$org['contactPoint'] = $cp;
	}
	if ( ! empty( $s['city'] ) ) {
		$org['address'] = array( '@type' => 'PostalAddress', 'addressLocality' => $s['city'], 'addressCountry' => 'RU' );
	}
	if ( ! empty( $s['legal_name'] ) ) {
		$org['legalName'] = $s['legal_name'];
	}
	if ( ! empty( $s['inn'] ) ) {
		$org['taxID'] = $s['inn'];
	}
	$same = maxbit_profiles();
	if ( $same ) {
		$org['sameAs'] = $same;
	}
	return $org;
}

function maxbit_person_schema(): array {
	$s = maxbit_get_settings();
	$p = array(
		'@type'    => 'Person',
		'@id'      => home_url( '/osnovatel/#person' ),
		'name'     => $s['founder'] ?: 'Максим Кизимов',
		'jobTitle' => 'Основатель MAXBIT',
		'worksFor' => array( '@id' => home_url( '/#organization' ) ),
		'url'      => home_url( '/osnovatel/' ),
		'knowsAbout' => array( 'управление производственными заказами', 'Битрикс24', '1С', 'интеграция систем', 'цифровизация производства' ),
	);
	$page = get_page_by_path( 'osnovatel' );
	if ( $page && has_post_thumbnail( $page ) ) {
		$img = wp_get_attachment_image_src( get_post_thumbnail_id( $page ), 'large' );
		if ( $img ) {
			$p['image'] = $img[0];
		}
	}
	$same = maxbit_profiles();
	if ( $same ) {
		$p['sameAs'] = $same;
	}
	return $p;
}

/** FAQ из видимых <details><summary> в содержимом записи: разметка совпадает с текстом на странице. */
function maxbit_faq_from_content( int $post_id ): array {
	$html = (string) get_post_field( 'post_content', $post_id );
	if ( false === stripos( $html, '<details' ) ) {
		return array();
	}
	$html = do_shortcode( do_blocks( $html ) );
	$out  = array();
	if ( preg_match_all( '#<details[^>]*>\s*<summary[^>]*>(.*?)</summary>(.*?)</details>#is', $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $row ) {
			$q = trim( wp_strip_all_tags( $row[1] ) );
			$a = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $row[2] ) ) );
			if ( mb_strlen( $q ) > 5 && mb_strlen( $a ) > 10 ) {
				$out[] = array( '@type' => 'Question', 'name' => $q, 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $a ) );
			}
		}
	}
	return $out;
}

add_action( 'wp_head', function () {
	if ( function_exists( 'maxbit_is_private_material' ) && maxbit_is_private_material() ) {
		return; // закрытый материал: без canonical, OG, description и разметки
	}
	if ( is_404() || is_search() ) {
		return;
	}
	$graph   = array();
	$graph[] = maxbit_org_schema();
	$graph[] = array(
		'@type' => 'WebSite',
		'@id'   => home_url( '/#website' ),
		'url'   => home_url( '/' ),
		'name'  => 'MAXBIT',
		'publisher' => array( '@id' => home_url( '/#organization' ) ),
		'inLanguage' => 'ru-RU',
	);
	$crumbs = maxbit_breadcrumbs();
	if ( count( $crumbs ) > 1 ) {
		$list = array();
		foreach ( $crumbs as $i => $c ) {
			$item = array( '@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0] );
			if ( $c[1] ) {
				$item['item'] = $c[1];
			}
			$list[] = $item;
		}
		$graph[] = array( '@type' => 'BreadcrumbList', 'itemListElement' => $list );
	}
	$id = is_singular() ? get_queried_object_id() : 0;
	$is_person_page = $id && 'osnovatel' === get_post_field( 'post_name', $id );
	if ( $is_person_page ) {
		$graph[] = maxbit_person_schema();
	}
	// Автор публикаций и кейсов — MAXBIT, пока персональное авторство не подтверждено (ТЗ v4 §7).
	if ( is_singular( array( 'post', 'mb_project' ) ) ) {
		$desc = maxbit_meta_description();
		$art  = array(
			'@type'    => 'Article',
			'@id'      => get_permalink( $id ) . '#article',
			'headline' => get_the_title( $id ),
			'url'      => get_permalink( $id ),
			'mainEntityOfPage' => get_permalink( $id ),
			'datePublished' => get_the_date( 'c', $id ),
			'dateModified'  => maxbit_content_modified( $id, 'c' ),
			'author'   => array( '@id' => home_url( '/#organization' ) ),
			'publisher' => array( '@id' => home_url( '/#organization' ) ),
			'inLanguage' => 'ru-RU',
		);
		if ( $desc ) {
			$art['description'] = $desc;
		}
		$og = maxbit_og_image();
		if ( $og ) {
			$art['image'] = $og['url'];
		}
		$graph[] = $art;
	}
	if ( is_singular( 'mb_solution' ) || ( is_page() && $id && ( str_starts_with( (string) get_page_uri( $id ), 'tekhnologii/' ) || 'bitrix24' === get_page_uri( $id ) ) ) ) {
		$desc = maxbit_meta_description();
		$svc  = array(
			'@type'       => 'Service',
			'@id'         => get_permalink( $id ) . '#service',
			'name'        => get_the_title( $id ),
			'url'         => get_permalink( $id ),
			'serviceType' => get_the_title( $id ),
			'provider'    => array( '@id' => home_url( '/#organization' ) ),
			'areaServed'  => array( '@type' => 'Country', 'name' => 'Россия' ),
			'audience'    => array( '@type' => 'BusinessAudience', 'audienceType' => 'производственные компании' ),
			'inLanguage'  => 'ru-RU',
		);
		if ( $desc ) {
			$svc['description'] = $desc;
		}
		$graph[] = $svc;
	}
	if ( $is_person_page ) {
		$graph[] = array(
			'@type'      => 'ProfilePage',
			'@id'        => get_permalink( $id ) . '#profile',
			'url'        => get_permalink( $id ),
			'mainEntity' => array( '@id' => home_url( '/osnovatel/#person' ) ),
			'dateModified' => maxbit_content_modified( $id, 'c' ),
			'inLanguage' => 'ru-RU',
		);
	}
	if ( $id && maxbit_is_publicly_visible( $id ) ) {
		$faq = maxbit_faq_from_content( $id );
		if ( $faq ) {
			$graph[] = array( '@type' => 'FAQPage', '@id' => get_permalink( $id ) . '#faq', 'mainEntity' => $faq );
		}
	}
	echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
}, 5 );

/** Заголовки безопасности (сервер их не ставит). CSP не задаём: инлайн-стили/скрипты темы и Метрика. */
add_action( 'send_headers', function () {
	if ( headers_sent() ) {
		return;
	}
	header_remove( 'X-Powered-By' );
	header( 'X-Content-Type-Options: nosniff' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
	if ( ! is_admin() ) {
		header( 'X-Frame-Options: SAMEORIGIN' );
	}
} );

/** /llms.txt — только в production: краткое описание сайта и ссылки на разделы, автоматически. */
add_action( 'init', function () {
	$path = (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	$base = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
	if ( $path !== $base . '/llms.txt' ) {
		return;
	}
	if ( 'production' !== MAXBIT_ENV || '1' !== (string) get_option( 'blog_public' ) ) {
		status_header( 404 );
		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		echo "Not available\n";
		exit;
	}
	$s   = maxbit_get_settings();
	$out = "# MAXBIT — ваша команда цифрового развития\n\n";
	$out .= "> Внешняя команда цифрового развития производственных компаний: разбираемся в задачах, проектируем процессы, внедряем изменения в Битрикс24, 1С и других системах, проверяем результат. Работаем по всей России.\n\n";
	$out .= "Битрикс24, 1С, интеграции и ИИ — инструменты реализации, а не суть услуги. Первый шаг — обсудить задачу.\n\n";
	$out .= "## Решения\n";
	foreach ( get_posts( array( 'post_type' => 'mb_solution', 'post_status' => 'publish', 'numberposts' => 20, 'orderby' => 'menu_order title', 'order' => 'ASC' ) ) as $p ) {
		if ( ! maxbit_is_publicly_visible( $p->ID ) ) {
			continue;
		}
		$d = get_post_meta( $p->ID, '_mb_seo_description', true ) ?: wp_strip_all_tags( get_the_excerpt( $p ) );
		$out .= '- [' . $p->post_title . '](' . get_permalink( $p ) . ')' . ( $d ? ': ' . $d : '' ) . "\n";
	}
	$tech = get_page_by_path( 'tekhnologii/bitrix24-dlya-proizvodstva' );
	if ( $tech && 'publish' === $tech->post_status ) {
		$out .= '- [' . $tech->post_title . '](' . get_permalink( $tech ) . '): ' . ( get_post_meta( $tech->ID, '_mb_seo_description', true ) ?: '' ) . "\n";
	}
	$b24 = get_page_by_path( 'bitrix24' );
	if ( $b24 && 'publish' === $b24->post_status ) {
		$out .= '- [' . $b24->post_title . '](' . get_permalink( $b24 ) . '): ' . ( get_post_meta( $b24->ID, '_mb_seo_description', true ) ?: '' ) . "\n";
	}
	$projects = function_exists( 'maxbit_public_projects' ) ? maxbit_public_projects() : array();
	if ( $projects ) {
		$out .= "\n## Проекты (реальные кейсы)\n";
		foreach ( $projects as $p ) {
			$out .= '- [' . $p->post_title . '](' . get_permalink( $p ) . '): ' . ( get_post_meta( $p->ID, '_mb_seo_description', true ) ?: '' ) . "\n";
		}
	}
	$out .= "\n## О компании\n";
	foreach ( array( 'kak-rabotaem' => 'Как работаем', 'o-kompanii' => 'О компании', 'osnovatel' => 'Основатель' ) as $slug => $label ) {
		$pg = get_page_by_path( $slug );
		if ( $pg && 'publish' === $pg->post_status ) {
			$out .= '- [' . $label . '](' . get_permalink( $pg ) . ")\n";
		}
	}
	$out .= "\n## Практика (статьи и чек-листы)\n";
	foreach ( get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => 30 ) ) as $p ) {
		$out .= '- [' . $p->post_title . '](' . get_permalink( $p ) . ")\n";
	}
	$out .= "\n## Контакты\n";
	$out .= '- Сайт: ' . home_url( '/' ) . "\n";
	if ( maxbit_public_email() ) {
		$out .= '- Почта: ' . maxbit_public_email() . "\n";
	}
	if ( ! empty( $s['tg_bot_url'] ) ) {
		$out .= '- Написать в Telegram: ' . $s['tg_bot_url'] . "\n";
	}
	if ( ! empty( $s['max_bot_url'] ) ) {
		$out .= '- Написать в MAX: ' . $s['max_bot_url'] . "\n";
	}
	if ( ! empty( $s['tg_channel_url'] ) ) {
		$out .= '- Канал с материалами (Telegram): ' . $s['tg_channel_url'] . "\n";
	}
	if ( ! empty( $s['max_channel_url'] ) ) {
		$out .= '- Канал с материалами (MAX): ' . $s['max_channel_url'] . "\n";
	}
	$out .= "\n## Как читать этот сайт\n- Решения описывают один участок процесса: для кого, как устроен путь заказа, что входит в работу, что получает клиент.\n- Статьи «Практики» — разборы и чек-листы, применимые без MAXBIT.\n- «Проекты» — реальные истории клиентов: задача, что сделано, что подтверждено; без придуманных показателей.\n- Первая встреча — бесплатная, примерно 30 минут. Основной формат — ежемесячная работа; есть почасовой формат и отдельные проекты.\n- Цены на сайте не публикуются: коммерческое предложение отправляем по ссылке после заявки.\n";
	status_header( 200 );
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	echo $out;
	exit;
} );

/* Метрика и согласие на cookie — includes/consent.php (0.4.1). */

/** Кеш страницы: любое обновление записи сбрасывает кеш объектов и даёт сигнал внешнему кешу (если появится). */
add_action( 'save_post', function ( int $post_id ) {
	if ( wp_is_post_revision( $post_id ) ) {
		return;
	}
	clean_post_cache( $post_id );
	do_action( 'maxbit_purge_url', get_permalink( $post_id ) );
	do_action( 'maxbit_purge_url', home_url( '/' ) );
} );

/** Язык документа — русский (содержание на русском; языковой пакет ядра не обязателен). */
add_filter( 'language_attributes', function ( string $output ) {
	return preg_replace( '/lang="[^"]*"/', 'lang="ru-RU"', $output ) ?: 'lang="ru-RU"';
} );
