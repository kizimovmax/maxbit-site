<?php
/** Скрытая страница (по ссылке с ключом): без хлебных крошек, боковой колонки и связанных материалов. */
get_header(); ?>
<div class="mb-container mb-page">
	<div class="mb-preview-banner" role="note"><strong>Страница доступна только по ссылке.</strong> Она не индексируется и не показывается в меню и поиске. Цены действительны на дату, указанную внизу страницы.</div>
	<article class="mb-article">
		<h1 class="mb-page__title"><?php echo esc_html( get_post()->post_title ); ?></h1>
		<div class="mb-content"><?php echo apply_filters( 'the_content', get_post()->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
		<p class="mb-updated">Актуально на: <time datetime="<?php echo esc_attr( get_post_modified_time( 'c' ) ); ?>"><?php echo esc_html( get_post_modified_time( 'd.m.Y' ) ); ?></time></p>
	</article>
	<?php echo maxbit_theme_form( array( 'topic' => 'general', 'heading' => 'Обсудить состав и стоимость', 'text' => 'Напишите, какой участок хотите разобрать первым — ответим с предварительной оценкой по этапам.' ) ); ?>
</div>
<?php get_footer(); ?>
