<?php
/**
 * Решения — каталог типичных проблем (решение владельца 27.10): путь заказа, внутренние процессы, данные и системы.
 * Данные — maxbit_solution_catalog() из модуля: направления (решения) с кратким описанием, проблемами и проектами;
 * редактируются в админке у каждого решения (блок «Каталог решений»). Якоря карточек — slug решения.
 */
get_header();
$catalog = function_exists( 'maxbit_solution_catalog' ) ? maxbit_solution_catalog() : array();
$how     = get_page_by_path( 'kak-rabotaem' );
$pilot   = get_page_by_path( 'pilotnyj-proekt' );
$svc     = function_exists( 'maxbit_tech_hub_active' ) && maxbit_tech_hub_active();
$n       = 0;
?>
<div class="mb-container mb-page mb-page--wide mb-cat2">
	<?php maxbit_the_breadcrumbs(); ?>
	<div class="mb-page__intro mb-page__intro--split">
		<h1 class="mb-page__title">Решения для производственных компаний</h1>
		<p class="mb-lead">Типичные задачи — от первой заявки до закрывающих документов, а также внутренние процессы компании. Найдите похожую ситуацию и откройте направление.</p>
	</div>

	<?php if ( isset( $catalog['order'] ) ) : ?>
	<nav class="mb-cat2__path" aria-label="Путь заказа">
		<?php foreach ( $catalog['order']['items'] as $i => $it ) : ?>
		<a href="#<?php echo esc_attr( $it['post']->post_name ); ?>"><span><?php echo esc_html( $i + 1 ); ?></span><?php echo esc_html( $it['title'] ); ?></a>
		<?php endforeach; ?>
	</nav>
	<?php endif; ?>

	<?php foreach ( $catalog as $key => $g ) : ?>
	<section class="mb-cat2__group" id="stage-<?php echo esc_attr( $key ); ?>" aria-labelledby="stage-<?php echo esc_attr( $key ); ?>-h">
		<div class="mb-cat2__group-head">
			<h2 id="stage-<?php echo esc_attr( $key ); ?>-h"><?php echo esc_html( $g['title'] ); ?></h2>
			<p><?php echo esc_html( $g['lead'] ); ?></p>
		</div>
		<div class="mb-cat2__grid">
			<?php foreach ( $g['items'] as $it ) : $n++; $url = get_permalink( $it['post'] ); ?>
			<article class="mb-cat2__card" id="<?php echo esc_attr( $it['post']->post_name ); ?>">
				<p class="mb-cat2__num"><?php echo esc_html( sprintf( '%02d', $n ) ); ?></p>
				<h3><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $it['title'] ); ?></a></h3>
				<?php if ( $it['excerpt'] ) : ?><p class="mb-cat2__text"><?php echo esc_html( $it['excerpt'] ); ?></p><?php endif; ?>
				<?php if ( $it['problems'] ) : ?>
				<p class="mb-cat2__label">Узнаёте?</p>
				<ul class="mb-cat2__problems"><?php foreach ( $it['problems'] as $pr ) : ?><li><?php echo esc_html( $pr ); ?></li><?php endforeach; ?></ul>
				<?php endif; ?>
				<?php if ( $it['cases'] ) : ?>
				<p class="mb-cat2__cases"><span>Проекты:</span> <?php echo implode( ', ', array_map( fn( $c ) => '<a href="' . esc_url( get_permalink( $c ) ) . '">' . esc_html( get_post_meta( $c->ID, '_mb_pf_client', true ) ?: get_the_title( $c ) ) . '</a>', $it['cases'] ) ); // phpcs:ignore ?></p>
				<?php endif; ?>
				<a class="mb-ccard__more mb-cat2__more" href="<?php echo esc_url( $url ); ?>">Подробнее</a>
			</article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endforeach; ?>

	<section class="mb-sol__extra" aria-label="Как решения связаны">
		<div class="mb-sol__extra-item">
			<h2>Часто нужно несколько решений сразу</h2>
			<p>Начинаем с одного направления, затем связываем остальное. Например, сначала наводим порядок в заказах, потом настраиваем обмен с 1С и документооборот.</p>
			<?php if ( $svc ) : ?><p><a class="mb-btn mb-btn--link" href="<?php echo esc_url( home_url( '/tekhnologii/' ) ); ?>">Как связываем сервисы</a></p><?php endif; ?>
		</div>
		<div class="mb-sol__extra-item">
			<h2>После запуска</h2>
			<p>Не уходим после внедрения: в ежемесячном формате выбираем следующие приоритеты, внедряем и проверяем, как изменения работают.</p>
			<?php if ( $how ) : ?><p><a class="mb-btn mb-btn--link" href="<?php echo esc_url( get_permalink( $how ) . '#regular-development' ); ?>">Как устроена регулярная работа</a></p><?php endif; ?>
		</div>
	</section>

	<?php if ( $pilot && 'publish' === $pilot->post_status ) : ?>
	<section class="mb-pilot-cta" aria-labelledby="mb-pilot-cta-h">
		<div>
			<p class="mb-pilot-cta__label">Пилотный проект</p>
			<h2 id="mb-pilot-cta-h">Одну задачу — за наш счёт</h2>
			<p>Чтобы вы оценили работу на деле, небольшую задачу можем выполнить в рамках пилота. Решение об участии принимаем после разбора задачи.</p>
		</div>
		<a class="mb-btn mb-btn--primary" href="<?php echo esc_url( get_permalink( $pilot ) ); ?>">Условия и анкета</a>
	</section>
	<?php endif; ?>

	<?php echo maxbit_theme_form( array( 'topic' => 'general', 'purposes' => '1', 'heading' => 'Не нашли свою ситуацию?', 'text' => 'Опишите задачу своими словами. На бесплатной встрече примерно на 30 минут разберём её и предложим первый шаг.' ) ); // phpcs:ignore ?>
</div>
<?php get_footer(); ?>
