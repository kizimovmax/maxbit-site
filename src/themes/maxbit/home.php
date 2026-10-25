<?php get_header(); ?>
<div class="mb-container mb-page">
	<?php maxbit_the_breadcrumbs(); ?>
	<h1 class="mb-page__title">Практика</h1>
	<p class="mb-lead">Статьи, разборы проблем, руководства, чек-листы и схемы для руководителя производства: учёт заказов, Битрикс24, 1С. Материалы можно применить без нас; это не описания клиентских внедрений.</p>
	<?php if ( have_posts() ) : ?>
		<div class="mb-archive">
			<div class="mb-archive__main">
				<div class="mb-grid mb-grid--2">
					<?php while ( have_posts() ) : the_post(); maxbit_card( get_post(), 'Статья' ); endwhile; ?>
				</div>
				<?php the_posts_pagination( array( 'prev_text' => '← Назад', 'next_text' => 'Вперёд →', 'screen_reader_text' => 'Страницы списка' ) ); ?>
			</div>
			<?php maxbit_theme_magnet(); ?>
		</div>
	<?php else : ?>
		<?php get_template_part( 'template-parts/empty' ); ?>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
