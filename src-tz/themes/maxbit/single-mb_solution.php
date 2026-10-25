<?php get_header(); the_post(); ?>
<div class="mb-container mb-page mb-page--aside">
	<?php maxbit_the_breadcrumbs(); ?>
	<?php maxbit_preview_banner( get_the_ID() ); ?>
	<article class="mb-article mb-article--solution">
		<div class="mb-content"><?php the_content(); ?></div>
	</article>
	<?php maxbit_theme_aside(); ?>
	<?php maxbit_related_block( get_the_ID() ); ?>
</div>
<?php get_footer(); ?>
