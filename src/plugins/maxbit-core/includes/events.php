<?php
defined( 'ABSPATH' ) || exit;

/**
 * События: solution_view, case_view, article_view, contact_click, form_start,
 * lead_submit_accepted, lead_submit_error. Все — в window.dataLayer; реальный счётчик
 * подключается отдельным заданием (MAXBIT_ANALYTICS_ENABLED). Никаких PII, свободного
 * текста и произвольных query-параметров: только тип страницы, направление и slug.
 */
add_action( 'wp_footer', function () {
	$event = '';
	$dir   = 'general';
	if ( is_singular( 'mb_solution' ) ) {
		$event = 'solution_view';
		$slug  = get_post_field( 'post_name', get_queried_object_id() );
		$dir   = 'ii-praktikum' === $slug ? 'ai_practicum' : ( 'integraciya-bitrix24-i-1c' === $slug ? 'integration' : 'order_control' );
	} elseif ( is_singular( 'mb_project' ) ) {
		$event = 'case_view';
	} elseif ( is_singular( 'post' ) ) {
		$event = 'article_view';
		$dir   = 'article';
	} elseif ( is_page( 'bitrix24-dlya-proizvodstva' ) ) {
		$dir = 'bitrix24';
	}
	if ( ! in_array( $dir, maxbit_event_directions(), true ) ) {
		$dir = 'general';
	}
	$payload = array(
		'page_type' => $event ? $event : ( is_front_page() ? 'home' : 'page' ),
		'direction' => $dir,
		'slug'      => is_singular() ? substr( get_post_field( 'post_name', get_queried_object_id() ), 0, 60 ) : '',
	);
	echo '<script>window.dataLayer=window.dataLayer||[];window.MAXBIT_PAGE=' . wp_json_encode( $payload ) . ';';
	if ( $event ) {
		echo 'window.dataLayer.push({event:' . wp_json_encode( $event ) . ',direction:' . wp_json_encode( $dir ) . ',slug:' . wp_json_encode( $payload['slug'] ) . '});';
	}
	echo '</script>' . "\n";
}, 5 );
