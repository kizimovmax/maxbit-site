<?php get_header(); ?>
<div class="mb-container mb-page">
	<?php maxbit_the_breadcrumbs(); ?>
	<?php if ( have_posts() ) : ?>
		<h1 class="mb-page__title"><?php echo esc_html( wp_strip_all_tags( get_the_archive_title() ) ); ?></h1>
		<div class="mb-grid mb-grid--3">
			<?php while ( have_posts() ) : the_post(); maxbit_card( get_post() ); endwhile; ?>
		</div>
		<?php the_posts_pagination( array( 'prev_text' => '← Назад', 'next_text' => 'Вперёд →', 'screen_reader_text' => 'Страницы списка' ) ); ?>
	<?php else : ?>
		<?php get_template_part( 'template-parts/empty' ); ?>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
