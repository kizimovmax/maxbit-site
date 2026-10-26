<?php
/**
 * Template Name: Широкая страница (без боковой колонки)
 * Обзорные страницы из секций (например, «Битрикс24»): крошки и содержимое во всю ширину, H1 — в содержимом.
 */
get_header(); the_post(); ?>
<div class="mb-container mb-landing__crumbs"><?php maxbit_the_breadcrumbs(); ?><?php maxbit_preview_banner( get_the_ID() ); ?></div>
<div class="mb-home mb-landing"><?php the_content(); ?></div>
<?php get_footer(); ?>
