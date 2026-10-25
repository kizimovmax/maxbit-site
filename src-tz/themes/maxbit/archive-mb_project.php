<?php get_header(); ?>
<div class="mb-container mb-page">
	<?php maxbit_the_breadcrumbs(); ?>
	<h1 class="mb-page__title">Проекты</h1>
	<?php if ( have_posts() ) : ?>
		<p class="mb-lead">Опубликованные проекты — только с разрешения клиента и с подтверждённым содержанием.</p>
		<div class="mb-grid mb-grid--3">
			<?php while ( have_posts() ) : the_post(); maxbit_card( get_post() ); endwhile; ?>
		</div>
		<?php the_posts_pagination( array( 'prev_text' => '← Назад', 'next_text' => 'Вперёд →', 'screen_reader_text' => 'Страницы списка' ) ); ?>
	<?php else : ?>
		<div class="mb-empty">
			<p>Описания проектов появятся здесь после согласования с клиентами. Пока мы не публикуем названия компаний, логотипы и показатели без подтверждения.</p>
			<p><a class="mb-btn mb-btn--secondary" href="<?php echo esc_url( get_post_type_archive_link( 'mb_solution' ) ); ?>">Посмотреть решения</a></p>
		</div>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
