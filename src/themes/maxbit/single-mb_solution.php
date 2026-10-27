<?php get_header(); the_post(); ?>
<div class="mb-container mb-page mb-page--aside">
	<?php maxbit_the_breadcrumbs(); ?>
	<?php maxbit_preview_banner( get_the_ID() ); ?>
	<article class="mb-article mb-article--solution">
		<div class="mb-content"><?php the_content(); ?></div>
		<?php maxbit_theme_services_link(); ?>
		<?php maxbit_theme_updated(); ?>
	</article>
	<?php maxbit_theme_aside(); ?>
	<?php if ( ! has_shortcode( (string) get_post()->post_content, 'maxbit_form' ) ) : ?>
	<?php echo maxbit_theme_form( array( 'topic' => function_exists( 'maxbit_topic_for_post' ) ? maxbit_topic_for_post( get_the_ID() ) : 'general', 'heading' => 'С чего начать', 'text' => 'Расскажите о ситуации — обсудим её на бесплатной встрече примерно на 30 минут.' ) ); // phpcs:ignore ?>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
