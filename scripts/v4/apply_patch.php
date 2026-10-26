<?php
/**
 * Точечная правка содержания страниц по JSON-списку замен (локально, через wp eval-file).
 * Каждая замена должна встречаться в тексте ровно один раз — иначе страница не меняется.
 * Перед сохранением WordPress создаёт ревизию (откат — из ревизий страницы).
 * Использование: php vendor/wp-cli.phar --path=site eval-file scripts/v4/apply_patch.php <patch.json> [dry]
 */
$file = $args[0] ?? '';
$dry  = in_array( 'dry', $args, true );
$spec = json_decode( (string) file_get_contents( $file ), true );
if ( ! is_array( $spec ) ) {
	WP_CLI::error( 'Нет файла правок' );
}
foreach ( $spec as $slug => $pairs ) {
	$id = 'home' === $slug ? (int) get_option( 'page_on_front' ) : (int) ( get_page_by_path( $slug )->ID ?? 0 );
	if ( ! $id ) {
		WP_CLI::warning( "$slug: страница не найдена" );
		continue;
	}
	$c  = get_post_field( 'post_content', $id, 'raw' );
	$ok = true;
	foreach ( $pairs as $i => $p ) {
		$n = substr_count( $c, $p[0] );
		if ( 1 !== $n ) {
			WP_CLI::warning( "$slug: замена $i найдена $n раз — страница не изменена" );
			$ok = false;
			break;
		}
		$c = str_replace( $p[0], $p[1], $c );
	}
	if ( ! $ok ) {
		continue;
	}
	if ( $dry ) {
		WP_CLI::log( "$slug #$id: проверка пройдена (" . count( $pairs ) . ' замен)' );
		continue;
	}
	wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $c ) ) );
	WP_CLI::success( "$slug #$id: применено " . count( $pairs ) . ' замен' );
}
