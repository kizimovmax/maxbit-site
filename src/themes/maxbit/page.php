<?php get_header(); the_post(); ?>
<?php if ( is_front_page() ) : ?>
	<?php maxbit_preview_banner( get_the_ID() ); ?>
	<div class="mb-home"><?php the_content(); ?></div>
<?php else : $founder = maxbit_theme_is_founder_page(); ?>
	<?php if ( $founder ) { maxbit_theme_founder_hero(); } ?>
	<div class="mb-container mb-page mb-page--aside<?php echo $founder ? ' mb-page--founder' : ''; ?>">
		<?php if ( ! $founder ) { maxbit_the_breadcrumbs(); } ?>
		<?php maxbit_preview_banner( get_the_ID() ); ?>
		<article class="mb-article">
			<?php if ( ! $founder ) : ?><h1 class="mb-page__title"><?php the_title(); ?></h1><?php endif; ?>
			<div class="mb-content"><?php the_content(); ?></div>
			<?php if ( str_starts_with( (string) get_page_uri( get_the_ID() ), 'tekhnologii/' ) ) { maxbit_theme_services_link(); } ?>
			<?php maxbit_theme_updated(); ?>
		</article>
		<?php maxbit_theme_aside(); ?>
		<?php maxbit_related_block( get_the_ID() ); ?>
	</div>
<?php endif; ?>
<?php get_footer(); ?>
