<?php get_header(); the_post(); ?>
<div class="mb-container mb-page mb-page--aside">
	<?php maxbit_the_breadcrumbs(); ?>
	<?php maxbit_preview_banner( get_the_ID() ); ?>
	<article class="mb-article">
		<p class="mb-eyebrow">Практика</p>
		<h1 class="mb-page__title"><?php the_title(); ?></h1>
		<p class="mb-article__meta">MAXBIT · <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( maxbit_theme_date_ru( get_the_ID() ) ); ?></time>
			<?php if ( get_post_meta( get_the_ID(), '_mb_editorial_status', true ) && maxbit_theme_can_preview() ) : ?> · <span class="mb-badge mb-badge--draft"><?php echo esc_html( get_post_meta( get_the_ID(), '_mb_editorial_status', true ) ); ?></span><?php endif; ?>
		</p>
		<div class="mb-content"><?php the_content(); ?></div>
	</article>
	<?php maxbit_theme_aside(); ?>
	<?php maxbit_related_block( get_the_ID() ); ?>
	<?php if ( ! has_shortcode( (string) get_post()->post_content, 'maxbit_form' ) ) : ?>
	<?php echo maxbit_theme_form( array( 'topic' => 'article', 'heading' => 'Обсудить задачу', 'text' => 'Узнали свою ситуацию? На первой встрече познакомимся с ней, определим проблемный участок и обсудим возможные следующие шаги.' ) ); ?>
	<?php endif; ?>
</div>
<?php get_footer(); ?>
