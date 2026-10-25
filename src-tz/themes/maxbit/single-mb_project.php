<?php get_header(); the_post(); $id = get_the_ID(); ?>
<div class="mb-container mb-page">
	<?php maxbit_the_breadcrumbs(); ?>
	<?php maxbit_preview_banner( $id ); ?>
	<article class="mb-article mb-article--project">
		<p class="mb-eyebrow"><?php echo '1' === get_post_meta( $id, '_mb_is_sample', true ) ? 'Учебный образец структуры — не история клиента' : 'Проект'; ?></p>
		<h1 class="mb-page__title"><?php the_title(); ?></h1>
		<?php $client = get_post_meta( $id, '_mb_pf_client', true ); if ( $client ) : ?><p class="mb-lead"><?php echo esc_html( $client ); ?></p><?php endif; ?>
		<?php if ( get_the_content() ) : ?><div class="mb-content"><?php the_content(); ?></div><?php endif; ?>
		<dl class="mb-case">
			<?php foreach ( maxbit_theme_project_fields() as $k => $cfg ) :
				if ( 'client' === $k ) { continue; }
				$v = get_post_meta( $id, '_mb_pf_' . $k, true );
				if ( '' === trim( (string) $v ) ) { continue; } ?>
				<div class="mb-case__row"><dt><?php echo esc_html( $cfg[0] ); ?></dt><dd><?php echo nl2br( esc_html( $v ) ); ?></dd></div>
			<?php endforeach; ?>
		</dl>
	</article>
	<?php maxbit_related_block( $id ); ?>
	<?php echo maxbit_theme_form( array( 'topic' => 'general', 'heading' => 'Похожая ситуация?', 'text' => 'Расскажите о своей задаче. Обсудим текущий процесс и определим, с чего разумно начать.' ) ); ?>
</div>
<?php get_footer(); ?>
