<?php get_header(); ?>
<div class="mb-container mb-page">
	<article class="mb-article mb-empty">
		<h1 class="mb-page__title">Страница не найдена</h1>
		<p>Возможно, адрес изменился или материал ещё не опубликован.</p>
		<p><a class="mb-btn mb-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">На главную</a> <a class="mb-btn mb-btn--secondary" href="<?php echo esc_url( get_post_type_archive_link( 'mb_solution' ) ); ?>">Решения</a></p>
	</article>
</div>
<?php get_footer(); ?>
