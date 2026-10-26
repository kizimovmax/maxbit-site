<?php
/** Решения (ТЗ v4 §5.3): три группы — бизнес-процессы, цифровые системы (обзор Битрикс24 + прикладные), регулярное развитие. */
get_header();
$sol = function ( string $slug ) {
	$p = get_posts( array( 'post_type' => 'mb_solution', 'name' => $slug, 'post_status' => 'publish', 'numberposts' => 1 ) );
	return ( $p && ( ! function_exists( 'maxbit_is_publicly_visible' ) || maxbit_is_publicly_visible( $p[0]->ID ) ) ) ? $p[0] : null;
};
$page = function ( string $path ) {
	$p = get_page_by_path( $path );
	return ( $p && 'publish' === $p->post_status ) ? $p : null;
};
$zakaz = $sol( 'zakaz-pod-kontrolem' );
$integ = $sol( 'integraciya-bitrix24-i-1c' );
$b24p  = $page( 'tekhnologii/bitrix24-dlya-proizvodstva' );
$b24   = $page( 'bitrix24' );
$how   = $page( 'kak-rabotaem' );
/** Текст карточки — краткое описание записи (редактируется в админке); без него — текст ТЗ v4. */
$ex = function ( $p, string $fallback ) {
	return esc_html( $p && has_excerpt( $p ) ? get_the_excerpt( $p ) : $fallback );
};
?>
<div class="mb-container mb-page mb-page--wide mb-catalog">
	<?php maxbit_the_breadcrumbs(); ?>
	<div class="mb-page__intro mb-page__intro--split">
		<h1 class="mb-page__title">Решения для производственных компаний</h1>
		<p class="mb-lead">Помогаем улучшать рабочие процессы и связывать цифровые инструменты компании. Состав изменений определяем по задаче; начать можно с одного участка.</p>
	</div>
	<?php if ( $zakaz ) : ?>
	<section class="mb-catalog__group" aria-labelledby="cg1"><h2 id="cg1"><span>01</span>Бизнес-процессы</h2>
		<a class="mb-ccard mb-ccard--row" href="<?php echo esc_url( get_permalink( $zakaz ) ); ?>"><h3>Управление заказами и исполнением</h3><p><?php echo $ex( $zakaz, 'Проектируем путь заказа от заявки до исполнения, отгрузки и оплаты. Определяем этапы, обязательные данные, ответственных и правила изменения договорённостей.' ); // phpcs:ignore ?></p><span class="mb-ccard__more">Подробнее</span></a>
	</section>
	<?php endif; ?>
	<?php if ( $b24 || $b24p || $integ ) : ?>
	<section class="mb-catalog__group" aria-labelledby="cg2"><h2 id="cg2"><span>02</span>Цифровые системы</h2>
		<?php if ( $b24 ) : ?><a class="mb-ccard mb-ccard--row mb-ccard--overview" href="<?php echo esc_url( get_permalink( $b24 ) ); ?>"><div><p class="mb-ccard__label">Обзор продукта</p><h3>Битрикс24: возможности и внедрение</h3></div><p><?php echo $ex( $b24, 'CRM, задачи, общение, документы, автоматизация и ИИ. Как MAXBIT помогает внедрить систему, настроить процессы и интеграции.' ); // phpcs:ignore ?></p><span class="mb-ccard__more">Подробнее</span></a><?php endif; ?>
		<div class="mb-grid mb-grid--2">
			<?php if ( $b24p ) : ?><a class="mb-ccard" href="<?php echo esc_url( get_permalink( $b24p ) ); ?>"><h3>Битрикс24 для производственных компаний</h3><p><?php echo $ex( $b24p, 'Настраиваем и развиваем Битрикс24 с учётом рабочих процессов компании. Связываем задачи сотрудников, согласования и взаимодействие подразделений.' ); // phpcs:ignore ?></p><span class="mb-ccard__more">Подробнее</span></a><?php endif; ?>
			<?php if ( $integ ) : ?><a class="mb-ccard" href="<?php echo esc_url( get_permalink( $integ ) ); ?>"><h3>Интеграция Битрикс24 и 1С</h3><p><?php echo $ex( $integ, 'Определяем, где создаются и обновляются данные, кому они нужны и как передаются между системами. Настраиваем согласованные обмены и проверяем рабочие сценарии.' ); // phpcs:ignore ?></p><span class="mb-ccard__more">Подробнее</span></a><?php endif; ?>
		</div>
	</section>
	<?php endif; ?>
	<section class="mb-catalog__group" aria-labelledby="cg3"><h2 id="cg3"><span>03</span>Регулярное развитие</h2>
		<div class="mb-ccard mb-ccard--row mb-ccard--surface"><p class="mb-ccard__big">Согласуем приоритеты, внедряем улучшения и проверяем их использование в ежемесячном формате.</p><?php if ( $how ) : ?><a class="mb-btn mb-btn--link" href="<?php echo esc_url( get_permalink( $how ) . '#regular-development' ); ?>">Как устроена работа</a><?php endif; ?></div>
	</section>
	<?php echo maxbit_theme_form( array( 'topic' => 'general', 'purposes' => '1', 'heading' => 'Обсудим вашу задачу за 30 минут', 'text' => 'На бесплатной первой встрече познакомимся с вашей ситуацией, обсудим действующие инструменты и ограничения. По итогам предложим возможный следующий шаг.' ) ); // phpcs:ignore ?>
</div>
<?php get_footer(); ?>
