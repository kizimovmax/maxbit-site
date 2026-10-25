<?php
defined( 'ABSPATH' ) || exit;

/**
 * Типы материалов живут в модуле, а не в теме: при смене темы содержание остаётся.
 * - mb_solution  — «Решения» (/resheniya/…), редактируются в Gutenberg, флаг допуска.
 * - mb_project   — «Проекты/кейсы» (/proekty/…), структурированные поля + флаг допуска.
 * - mb_lead      — обращения из формы: непубличный тип, без REST, без UI редактора.
 * Обычные страницы и статьи — штатные page/post.
 */
function maxbit_register_post_types(): void {
	register_post_type( 'mb_solution', array(
		'labels'              => array(
			'name'          => 'Решения',
			'singular_name' => 'Решение',
			'add_new_item'  => 'Добавить решение',
			'edit_item'     => 'Редактировать решение',
			'menu_name'     => 'Решения',
		),
		'public'              => true,
		'show_in_rest'        => true,
		'menu_icon'           => 'dashicons-networking',
		'menu_position'       => 21,
		'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
		'has_archive'         => 'resheniya',
		'rewrite'             => array( 'slug' => 'resheniya', 'with_front' => false ),
		'template'            => array(
			array( 'core/paragraph', array( 'placeholder' => 'Вступление: с какой ситуацией помогаем' ) ),
		),
	) );

	register_post_type( 'mb_project', array(
		'labels'              => array(
			'name'          => 'Проекты',
			'singular_name' => 'Проект',
			'add_new_item'  => 'Добавить проект',
			'edit_item'     => 'Редактировать проект',
			'menu_name'     => 'Проекты',
		),
		'public'              => true,
		'show_in_rest'        => true,
		'menu_icon'           => 'dashicons-portfolio',
		'menu_position'       => 22,
		'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'custom-fields' ),
		'has_archive'         => 'proekty',
		'rewrite'             => array( 'slug' => 'proekty', 'with_front' => false ),
	) );

	register_post_type( 'mb_lead', array(
		'labels'              => array(
			'name'          => 'Обращения',
			'singular_name' => 'Обращение',
			'menu_name'     => 'Обращения',
		),
		'public'              => false,
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => false,
		'show_in_nav_menus'   => false,
		'menu_icon'           => 'dashicons-email-alt',
		'menu_position'       => 23,
		'supports'            => array( 'title' ),
		'capability_type'     => 'mb_lead',
		'map_meta_cap'        => true,
		'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
		'rewrite'             => false,
		'query_var'           => false,
	) );
}
add_action( 'init', 'maxbit_register_post_types' );

/** Права: заявки видит только администратор (и явно наделённая роль), редактор — нет. */
function maxbit_add_roles_caps(): void {
	$admin = get_role( 'administrator' );
	if ( ! $admin ) {
		return;
	}
	foreach ( array( 'edit_mb_leads', 'edit_others_mb_leads', 'read_private_mb_leads', 'delete_mb_leads', 'delete_others_mb_leads', 'edit_private_mb_leads', 'delete_private_mb_leads', 'publish_mb_leads', 'read_mb_lead', 'edit_mb_lead', 'delete_mb_lead', 'maxbit_manage_leads' ) as $cap ) {
		$admin->add_cap( $cap );
	}
}
add_action( 'init', 'maxbit_add_roles_caps', 20 );

/* Статьи практики получают адрес /praktika/{slug}/ через permalink_structure (см. scripts/seed.php). */
