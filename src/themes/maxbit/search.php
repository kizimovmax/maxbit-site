<?php get_header(); ?>
<div class="mb-container mb-page">
	<h1 class="mb-page__title">Поиск</h1>
	<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="mb-search">
		<label for="mb-s">Что ищете?</label>
		<input id="mb-s" type="search" name="s" value="<?php echo esc_attr( get_search_query() ); ?>">
		<button class="mb-btn mb-btn--secondary" type="submit">Найти</button>
	</form>
	<?php if ( have_posts() ) : ?>
		<div class="mb-grid mb-grid--3"><?php while ( have_posts() ) : the_post(); maxbit_card( get_post() ); endwhile; ?></div>
	<?php else : ?>
		<div class="mb-empty"><p>По запросу ничего не найдено. Попробуйте другие слова или посмотрите <a href="<?php echo esc_url( get_post_type_archive_link( 'mb_solution' ) ); ?>">решения</a>.</p></div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
