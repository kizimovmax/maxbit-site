<?php
defined( 'ABSPATH' ) || exit;

/**
 * Картинки для соцсетей и мессенджеров (og:image) в стиле «Графит» — 1200×630, генерируются GD один раз
 * на запись и кешируются в uploads/maxbit-og/ (имя по хешу заголовка, подписи, даты изменения и версии).
 * Логотип — растр официального SVG (белая версия), не перерисован. Без GD/FreeType — брендовая картинка темы.
 */

const MAXBIT_OG_VERSION = '5';

function maxbit_og_can(): bool {
	return function_exists( 'imagecreatetruecolor' ) && function_exists( 'imagettftext' ) && function_exists( 'imagepng' ) && is_file( MAXBIT_CORE_DIR . 'assets/fonts/GolosText-ExtraBold.ttf' );
}

/** Подпись-«надзаголовок» для картинки по типу страницы. */
function maxbit_og_label( int $post_id ): string {
	if ( ! $post_id ) {
		return 'Ваша команда цифрового развития';
	}
	$p = get_post( $post_id );
	if ( ! $p ) {
		return '';
	}
	if ( 'mb_solution' === $p->post_type ) {
		return 'Решение';
	}
	if ( 'mb_project' === $p->post_type ) {
		return 'Кейс';
	}
	if ( 'post' === $p->post_type ) {
		return 'chek-list-peredacha-zakaza-v-proizvodstvo' === $p->post_name ? 'Чек-лист' : 'Практика';
	}
	return 'MAXBIT';
}

/** Перенос строк под ширину (в пикселях) для imagettftext. */
function maxbit_og_wrap( string $text, string $font, float $size, int $max_w, int $max_lines ): array {
	$words = preg_split( '/\s+/u', trim( $text ) ) ?: array();
	$lines = array();
	$cur   = '';
	foreach ( $words as $w ) {
		$try = $cur ? $cur . ' ' . $w : $w;
		$box = imagettfbbox( $size, 0, $font, $try );
		if ( $box && ( $box[2] - $box[0] ) > $max_w && $cur ) {
			$lines[] = $cur;
			$cur = $w;
		} else {
			$cur = $try;
		}
	}
	if ( $cur ) {
		$lines[] = $cur;
	}
	if ( count( $lines ) > $max_lines ) {
		$lines = array_slice( $lines, 0, $max_lines );
		$lines[ $max_lines - 1 ] = rtrim( $lines[ $max_lines - 1 ], ' ,.:;—-' ) . '…';
	}
	return $lines;
}

/** Картинка записи (или главной при 0). Возвращает [url, w, h] или пустой массив. */
function maxbit_og_generate( int $post_id ): array {
	$title = $post_id ? get_the_title( $post_id ) : 'Наводим порядок в заказах и процессах производственных компаний';
	$mod   = $post_id ? (string) get_post_field( 'post_modified_gmt', $post_id ) : '';
	return maxbit_og_render( (string) ( $post_id ?: 'home' ), (string) $title, maxbit_og_label( $post_id ), $mod, (bool) $post_id );
}

/** Картинка для произвольного заголовка (архивы). */
function maxbit_og_generate_text( string $title, string $label ): array {
	return maxbit_og_render( 'a-' . substr( md5( $title . '|' . $label ), 0, 10 ), $title, $label, '', true );
}

/** Общий генератор: ключ файла, заголовок, подпись, дата изменения (в хеш), вид подвала. */
function maxbit_og_render( string $key, string $title, string $label, string $mod, bool $inner ): array {
	if ( ! maxbit_og_can() ) {
		return array();
	}
	$title = html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES, 'UTF-8' );
	$hash  = substr( hash( 'sha256', MAXBIT_OG_VERSION . '|' . $title . '|' . $label . '|' . $mod ), 0, 16 );
	$up    = wp_upload_dir();
	if ( ! empty( $up['error'] ) ) {
		return array();
	}
	$dir  = trailingslashit( $up['basedir'] ) . 'maxbit-og';
	$name = $key . '-' . $hash . '.png';
	$file = $dir . '/' . $name;
	$url  = trailingslashit( $up['baseurl'] ) . 'maxbit-og/' . $name;
	if ( is_file( $file ) ) {
		return array( 'url' => $url, 'w' => 1200, 'h' => 630 );
	}
	if ( ! wp_mkdir_p( $dir ) ) {
		return array();
	}
	if ( ! is_file( $dir . '/index.php' ) ) {
		file_put_contents( $dir . '/index.php', "<?php // silence\n" ); // phpcs:ignore
	}
	// Старые версии этой записи — удаляем (не более нескольких файлов).
	foreach ( glob( $dir . '/' . $key . '-*.png' ) ?: array() as $old ) {
		@unlink( $old ); // phpcs:ignore
	}

	$W = 1200; $H = 630;
	$im = imagecreatetruecolor( $W, $H );
	imagesavealpha( $im, false );
	$graphite  = imagecolorallocate( $im, 15, 23, 42 );
	$graphite2 = imagecolorallocate( $im, 30, 41, 59 );
	$blue      = imagecolorallocate( $im, 37, 99, 235 );
	$white     = imagecolorallocate( $im, 255, 255, 255 );
	$sky       = imagecolorallocate( $im, 147, 197, 253 );
	$textdark  = imagecolorallocate( $im, 199, 211, 230 );
	imagefilledrectangle( $im, 0, 0, $W, $H, $graphite );
	// X-фигура справа: графитовый-2 ›, синий ‹ поверх (пропорции как в теме: плечи 45°, толщина = ½ ширины)
	$x0 = 770; $y0 = 135; $w = 180; $t = 90; $h = 2 * $w;
	imagefilledpolygon( $im, array( $x0 + $t, $y0, $x0 + $t + $w, $y0 + $h / 2, $x0 + $t, $y0 + $h, $x0, $y0 + $h, $x0 + $w, $y0 + $h / 2, $x0, $y0 ), $graphite2 );
	$x1 = $x0 + $w + 45; // вершины смотрят друг на друга, перекрытие как в X-фигуре темы
	imagefilledpolygon( $im, array( $x1 + $w, $y0, $x1, $y0 + $h / 2, $x1 + $w, $y0 + $h, $x1 + $w + $t, $y0 + $h, $x1 + $t, $y0 + $h / 2, $x1 + $w + $t, $y0 ), $blue );
	// Синяя диагональная полоса слева (15°)
	imagefilledpolygon( $im, array( 0, 0, 22, 0, 22 - (int) round( tan( deg2rad( 15 ) ) * $H ), $H, 0, $H ), $blue );
	imagefilledrectangle( $im, 0, 0, 22, $H, $blue );

	$bold = MAXBIT_CORE_DIR . 'assets/fonts/GolosText-ExtraBold.ttf';
	$med  = MAXBIT_CORE_DIR . 'assets/fonts/GolosText-Medium.ttf';
	// Логотип (растр официального SVG, белая версия)
	$logo = MAXBIT_CORE_DIR . 'assets/fonts/logo-white-800.png';
	if ( is_file( $logo ) ) {
		$lg = imagecreatefrompng( $logo );
		if ( $lg ) {
			imagealphablending( $im, true );
			$lw = 230; $lh = (int) round( imagesy( $lg ) * $lw / imagesx( $lg ) );
			imagecopyresampled( $im, $lg, 72, 64, 0, 0, $lw, $lh, imagesx( $lg ), imagesy( $lg ) );
			imagedestroy( $lg );
		}
	}
	// Надзаголовок
	if ( $label ) {
		imagettftext( $im, 20, 0, 72, 170, $sky, $bold, mb_strtoupper( $label ) );
	}
	// Заголовок: подбираем кегль, чтобы влезть в 4 строки шириной 660
	$size = 58;
	do {
		$lines = maxbit_og_wrap( $title, $bold, $size, 660, 4 );
		$fits  = count( $lines ) <= 4 && ( count( $lines ) < 4 || ! str_ends_with( end( $lines ), '…' ) || $size <= 40 );
		if ( ! $fits ) {
			$size -= 4;
		}
	} while ( ! $fits && $size > 36 );
	$y = 240;
	foreach ( $lines as $ln ) {
		imagettftext( $im, $size, 0, 72, $y, $white, $bold, $ln );
		$y += (int) round( $size * 1.18 );
	}
	// Подвал картинки
	imagettftext( $im, 18, 0, 72, 578, $textdark, $med, 'maxbitpro.ru  ›› ' . ( $inner ? 'Ваша команда цифрового развития' : 'Порядок в заказах и процессах производственных компаний' ) );
	imagepng( $im, $file, 6 );
	imagedestroy( $im );
	return is_file( $file ) ? array( 'url' => $url, 'w' => 1200, 'h' => 630 ) : array();
}

/** Подмена брендовой картинки темы сгенерированной: миниатюра записи по-прежнему в приоритете (ставится редактором). */
add_filter( 'maxbit_og_image', function ( array $og ) {
	if ( ! empty( $og['custom'] ) ) {
		return $og;
	}
	$id = ( is_singular() && ! is_front_page() ) ? get_queried_object_id() : 0;
	if ( ! is_singular() && ! is_front_page() ) {
		if ( is_home() ) {
			$id = (int) get_option( 'page_for_posts' );
		} elseif ( is_post_type_archive( 'mb_solution' ) ) {
			$gen = maxbit_og_generate_text( 'Решения для производственных компаний', 'Решения' );
			return $gen ?: $og;
		} else {
			return $og;
		}
	}
	$gen = maxbit_og_generate( (int) $id );
	return $gen ?: $og;
} );
