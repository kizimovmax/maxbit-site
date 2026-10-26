<?php
/** Кейс (ТЗ v4 §16): крошки «Главная › Проекты › Клиент», один H1 из реестра, тело кейса, связанные решения, другие проекты, обращение. */
get_header(); the_post(); $id = get_the_ID();
$client    = (string) get_post_meta( $id, '_mb_pf_client', true );
$processes = (string) get_post_meta( $id, '_mb_case_processes', true );
$systems   = (string) get_post_meta( $id, '_mb_case_systems', true );
$links     = json_decode( (string) get_post_meta( $id, '_mb_case_links', true ), true );
$sample    = '1' === get_post_meta( $id, '_mb_is_sample', true );
?>
<div class="mb-container mb-page mb-page--aside mb-page--case">
	<?php maxbit_the_breadcrumbs(); ?>
	<?php maxbit_preview_banner( $id ); ?>
	<article class="mb-article mb-article--case">
		<p class="mb-eyebrow"><?php echo $sample ? 'Учебный образец структуры — не история клиента' : esc_html( 'Проект' . ( $client ? ' · ' . $client : '' ) ); ?></p>
		<h1 class="mb-page__title"><?php the_title(); ?></h1>
		<?php if ( $client || $processes || $systems ) : ?>
		<dl class="mb-case-facts">
			<?php foreach ( array( 'Клиент' => $client, 'Процессы' => $processes, 'Системы' => $systems ) as $k => $v ) : if ( ! $v ) { continue; } ?>
			<div><dt><?php echo esc_html( $k ); ?></dt><dd><?php echo esc_html( $v ); ?></dd></div>
			<?php endforeach; ?>
		</dl>
		<?php endif; ?>
		<div class="mb-content"><?php the_content(); ?></div>
		<?php
		// Старые структурированные поля (если кейс заполнен через редактор, а не текстом).
		$fields = maxbit_theme_project_fields();
		$rows = '';
		foreach ( $fields as $k => $cfg ) {
			if ( 'client' === $k ) { continue; }
			$v = get_post_meta( $id, '_mb_pf_' . $k, true );
			if ( '' !== trim( (string) $v ) ) {
				$rows .= '<div class="mb-case__row"><dt>' . esc_html( $cfg[0] ) . '</dt><dd>' . nl2br( esc_html( $v ) ) . '</dd></div>';
			}
		}
		if ( $rows ) { echo '<dl class="mb-case">' . $rows . '</dl>'; } // phpcs:ignore
		?>
		<?php maxbit_theme_updated(); ?>
	</article>
	<aside class="mb-aside mb-aside--case" aria-label="Связанные решения">
		<?php if ( is_array( $links ) && $links ) : ?>
		<div class="mb-aside__links">
			<p class="mb-aside__title">Связанные решения</p>
			<ul><?php foreach ( $links as $l ) : if ( ! is_array( $l ) || count( $l ) < 2 ) { continue; } ?><li><a href="<?php echo esc_url( home_url( $l[0] ) ); ?>"><?php echo esc_html( $l[1] ); ?></a></li><?php endforeach; ?></ul>
			<p><a class="mb-btn mb-btn--link" href="<?php echo esc_url( get_post_type_archive_link( 'mb_project' ) ); ?>">Все проекты</a></p>
		</div>
		<?php endif; ?>
		<?php $bots = maxbit_theme_msg( 'bots', false ); if ( $bots ) : ?>
		<div class="mb-aside__card"><p class="mb-aside__title">Похожая задача?</p><p>Первый разговор — бесплатно, примерно 30 минут.</p><?php echo $bots; // phpcs:ignore ?></div>
		<?php endif; ?>
	</aside>
	<?php
	$others = function_exists( 'maxbit_public_projects' ) ? maxbit_public_projects( 3, array( $id ) ) : array();
	if ( $others && function_exists( 'maxbit_project_card_html' ) ) :
		?>
	<section class="mb-related mb-related--projects" aria-labelledby="mb-related-h">
		<div class="mb-related__head"><h2 id="mb-related-h">Другие проекты</h2><a class="mb-btn mb-btn--link" href="<?php echo esc_url( get_post_type_archive_link( 'mb_project' ) ); ?>">Все проекты</a></div>
		<div class="mb-projects mb-grid mb-grid--3"><?php foreach ( $others as $p ) { echo maxbit_project_card_html( $p ); } // phpcs:ignore ?></div>
	</section>
	<?php endif; ?>
	<?php echo maxbit_theme_form( array( 'topic' => 'project', 'heading' => 'Обсудить похожую задачу', 'text' => 'Расскажите, что хотите изменить. На бесплатной встрече примерно на 30 минут обсудим ситуацию и возможный следующий шаг.' ) ); // phpcs:ignore ?>
</div>
<?php get_footer(); ?>
