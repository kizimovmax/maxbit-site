<?php get_header(); the_post(); ?>
<div class="mb-container mb-page mb-page--aside">
	<?php maxbit_the_breadcrumbs(); ?>
	<?php maxbit_preview_banner( get_the_ID() ); ?>
	<article class="mb-article mb-article--solution">
		<div class="mb-content"><?php the_content(); ?></div>
		<?php maxbit_theme_updated(); ?>
	</article>
	<?php maxbit_theme_aside(); ?>
	<?php maxbit_theme_lead_block( 'Обсудить ' . mb_strtolower( get_the_title() ) . ' на вашем производстве' ); ?>
	<?php maxbit_related_block( get_the_ID() ); ?>
	<?php if ( ! has_shortcode( (string) get_post()->post_content, 'maxbit_form' ) ) : ?>
	<?php echo maxbit_theme_form( array( 'topic' => function_exists( 'maxbit_topic_for_post' ) ? maxbit_topic_for_post( get_the_ID() ) : 'general', 'heading' => 'Обсудить задачу', 'text' => 'На первой встрече познакомимся с вашей ситуацией, определим проблемный участок и обсудим возможные следующие шаги. Без презентаций о нас.' ) ); ?>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
