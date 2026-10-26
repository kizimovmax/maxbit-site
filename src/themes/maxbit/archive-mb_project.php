<?php
/** Проекты (ТЗ v4 §7): реальные задачи компаний; только допущенные кейсы, по порядку из реестра. */
get_header();
$projects = function_exists( 'maxbit_public_projects' ) ? maxbit_public_projects() : array();
?>
<div class="mb-container mb-page mb-page--wide">
	<?php maxbit_the_breadcrumbs(); ?>
	<div class="mb-page__intro">
		<h1 class="mb-page__title">Проекты MAXBIT</h1>
		<p class="mb-lead">Реальные задачи компаний: продажи, производство, документы и взаимодействие отделов.</p>
	</div>
	<?php if ( $projects ) : ?>
	<div class="mb-projects mb-grid mb-grid--3">
		<?php foreach ( $projects as $i => $p ) { echo maxbit_project_card_html( $p, 0 === $i ); } // phpcs:ignore ?>
		<div class="mb-pcard mb-pcard--cta"><h3 class="mb-pcard__title">Похожая задача в вашей компании?</h3><p class="mb-pcard__text">Обсудим одну ситуацию на бесплатной встрече примерно на 30 минут.</p><p><a class="mb-btn mb-btn--primary" href="#contact">Обсудить задачу</a></p></div>
	</div>
	<?php else : ?>
		<div class="mb-empty"><p>Описания проектов готовятся к публикации.</p><p><a class="mb-btn mb-btn--secondary" href="<?php echo esc_url( get_post_type_archive_link( 'mb_solution' ) ); ?>">Посмотреть решения</a></p></div>
	<?php endif; ?>
	<?php echo maxbit_theme_form( array( 'topic' => 'project', 'purposes' => '1', 'heading' => 'Обсудим вашу задачу за 30 минут', 'text' => 'На бесплатной первой встрече познакомимся с вашей ситуацией, обсудим действующие инструменты и ограничения. По итогам предложим возможный следующий шаг.' ) ); // phpcs:ignore ?>
</div>
<?php get_footer(); ?>
