<?php get_header(); ?>
<div class="mb-container mb-page">
	<?php maxbit_the_breadcrumbs(); ?>
	<h1 class="mb-page__title">База знаний</h1>
	<p class="mb-lead">Статьи и чек-листы о процессах, данных и цифровых инструментах. Их можно использовать в работе самостоятельно.</p>
	<?php if ( have_posts() ) : ?>
		<div class="mb-grid mb-grid--2 mb-grid--practice">
			<?php while ( have_posts() ) : the_post(); maxbit_card( get_post(), 'chek-list-peredacha-zakaza-v-proizvodstvo' === get_post()->post_name ? 'Чек-лист' : 'Статья' ); endwhile; ?>
		</div>
		<?php the_posts_pagination( array( 'prev_text' => '← Назад', 'next_text' => 'Вперёд →', 'screen_reader_text' => 'Страницы списка' ) ); ?>
	<?php else : ?>
		<?php get_template_part( 'template-parts/empty' ); ?>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
