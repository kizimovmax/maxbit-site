<?php
/**
 * 404 в стиле «Графит»: понятный заголовок, выход на главную, поиск, основные разделы и связь.
 * Без автоматического перенаправления; ответ остаётся 404 и не индексируется.
 */
get_header();
$links = array(
	array( get_post_type_archive_link( 'mb_solution' ), 'Решения', 'Заказы, Битрикс24, интеграция с 1С' ),
	array( get_post_type_archive_link( 'mb_project' ), 'Проекты', 'Задачи компаний и что изменили' ),
	array( home_url( '/kak-rabotaem/' ), 'Как работаем', 'Первая встреча, формат и шаги' ),
	array( get_permalink( (int) get_option( 'page_for_posts' ) ), 'База знаний', 'Статьи, чек-лист и самодиагностика' ),
);
?>
<section class="mb-404" aria-labelledby="mb-404-h">
	<div class="mb-container mb-404__inner">
		<div class="mb-404__text">
			<p class="mb-404__code" aria-hidden="true">404</p>
			<h1 id="mb-404-h" class="mb-404__title">Упс, кажется, такой страницы нет</h1>
			<p class="mb-404__lead">Похоже, адрес набран с ошибкой или страница переехала. Начните с главной или найдите нужное через поиск.</p>
			<div class="mb-404__actions">
				<a class="mb-btn mb-btn--primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">На главную</a>
				<a class="mb-btn mb-btn--secondary" href="<?php echo esc_url( home_url( '/#contact' ) ); ?>">Обсудить задачу</a>
			</div>
			<form class="mb-404__search" role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label for="mb-404-s">Поиск по сайту</label>
				<div class="mb-404__search-row">
					<input id="mb-404-s" type="search" name="s" placeholder="Например, интеграция с 1С" autocomplete="off">
					<button class="mb-btn mb-btn--secondary" type="submit">Найти</button>
				</div>
			</form>
		</div>
		<div class="mb-404__art" aria-hidden="true"><?php echo maxbit_theme_x_svg( 'mb-404__x', true ); ?></div>
	</div>
</section>
<section class="mb-container mb-404__more" aria-labelledby="mb-404-more">
	<h2 id="mb-404-more" class="mb-404__more-title">Основные разделы</h2>
	<ul class="mb-404__links">
		<?php foreach ( $links as $l ) : if ( ! $l[0] ) { continue; } ?>
		<li><a href="<?php echo esc_url( $l[0] ); ?>"><span class="mb-404__link-title"><?php echo esc_html( $l[1] ); ?></span><span class="mb-404__link-text"><?php echo esc_html( $l[2] ); ?></span></a></li>
		<?php endforeach; ?>
	</ul>
	<?php $msg = maxbit_theme_msg( 'bots', false, 'Искали что-то конкретное? Напишите нам:' ); if ( $msg ) { echo $msg; } // phpcs:ignore WordPress.Security.EscapeOutput ?>
</section>
<script>window.addEventListener('load',function(){if(window.maxbitTrack){window.maxbitTrack('page_404',{direction:'general'});}});</script>
<?php get_footer(); ?>
