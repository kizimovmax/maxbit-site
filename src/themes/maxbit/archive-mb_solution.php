<?php get_header(); ?>
<div class="mb-container mb-page">
	<?php maxbit_the_breadcrumbs(); ?>
	<h1 class="mb-page__title">Решения</h1>
	<p class="mb-lead">Направления, с которых производственные компании чаще всего начинают работу с MAXBIT. Каждое — конкретный процесс с составом, границами и порядком работы; начать можно с одного участка. Раздел развивается: новые решения появляются после подготовки.</p>
	<div class="mb-grid mb-grid--3">
		<?php while ( have_posts() ) : the_post(); maxbit_card( get_post() ); endwhile; ?>
		<?php
		$b24 = get_page_by_path( 'tekhnologii/bitrix24-dlya-proizvodstva' );
		if ( $b24 && 'publish' === $b24->post_status ) {
			maxbit_card( $b24, 'Технология' );
		}
		?>
	</div>
	<?php the_posts_pagination( array( 'prev_text' => '← Назад', 'next_text' => 'Вперёд →', 'screen_reader_text' => 'Страницы списка' ) ); ?>
	<?php if ( ! have_posts() ) : get_template_part( 'template-parts/empty' ); endif; ?>
</div>
<?php get_footer(); ?>
