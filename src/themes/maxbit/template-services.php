<?php
/**
 * Template Name: Сервисы и интеграции
 * Каталог сервисов, с которыми работает MAXBIT, и схема их связей (решение владельца 27.10 — фундамент раздела).
 * Данные — maxbit_theme_services() (фильтр maxbit_services). Логотипы не выводятся; статус партнёра — только
 * если заполнен ('partner' => текст, 'certificate' => ссылка). Проекты по сервису подбираются по полю «Системы» кейса.
 */
get_header(); the_post();
$groups = maxbit_theme_services();
$cases  = function ( array $keys ): array {
	if ( ! $keys || ! function_exists( 'maxbit_public_projects' ) ) {
		return array();
	}
	return array_values( array_filter( maxbit_public_projects(), function ( $p ) use ( $keys ) {
		$sys = (string) get_post_meta( $p->ID, '_mb_case_systems', true );
		foreach ( $keys as $k ) {
			if ( '' !== $sys && false !== mb_stripos( $sys, $k ) ) {
				return true;
			}
		}
		return false;
	} ) );
};
?>
<div class="mb-container mb-page mb-page--wide mb-services">
	<?php maxbit_the_breadcrumbs(); ?>
	<div class="mb-page__intro mb-page__intro--split">
		<h1 class="mb-page__title">Сервисы для задач компании</h1>
		<p class="mb-lead">Подбираем и связываем сервисы под конкретную работу: приём обращений, продажи и учёт, документы и подписание. Начинаем с задачи, а не с программы — и в первую очередь используем то, что у компании уже есть.</p>
	</div>

	<nav class="mb-svc-tasks" aria-label="Задачи">
		<?php foreach ( maxbit_theme_service_combos() as $i => $c ) : ?>
		<a href="#<?php echo esc_attr( $c['id'] ); ?>"><span><?php echo esc_html( sprintf( '%02d', $i + 1 ) ); ?></span><?php echo esc_html( $c['task'] ); ?></a>
		<?php endforeach; ?>
		<?php $env = get_posts( array( 'post_type' => 'mb_solution', 'name' => 'cifrovaya-sreda-kompanii', 'post_status' => 'publish', 'numberposts' => 1 ) ); if ( $env ) : ?>
		<a class="mb-svc-tasks__other" href="<?php echo esc_url( get_permalink( $env[0] ) ); ?>"><span>?</span>Программ много, и непонятно, с чего начать</a>
		<?php endif; ?>
	</nav>

	<section class="mb-svc-combos" aria-labelledby="mb-svc-combos-h">
		<div class="mb-svc-combos__head">
			<h2 id="mb-svc-combos-h" class="mb-svc__h2">Примеры сочетаний сервисов</h2>
		</div>
		<?php foreach ( maxbit_theme_service_combos() as $c ) : ?>
		<article class="mb-svc-combo" id="<?php echo esc_attr( $c['id'] ); ?>">
			<div class="mb-svc-combo__main">
				<p class="mb-ccard__label">Задача</p>
				<h3><?php echo esc_html( $c['title'] ); ?></h3>
				<p><?php echo esc_html( $c['problem'] ); ?></p>
				<p class="mb-svc-combo__parts"><?php foreach ( $c['parts'] as $pt ) : ?><a href="#<?php echo esc_attr( $pt[0] ); ?>"><?php echo esc_html( $pt[1] ); ?></a><?php endforeach; ?></p>
				<ol class="mb-svc-combo__flow" aria-label="Движение информации"><?php foreach ( $c['flow'] as $f ) : ?><li><?php echo esc_html( $f ); ?></li><?php endforeach; ?></ol>
			</div>
			<div class="mb-svc-combo__side">
				<p class="mb-ccard__label">Что делает MAXBIT</p>
				<p><?php echo esc_html( $c['do'] ); ?></p>
				<p class="mb-ccard__label">Ограничения</p>
				<p class="mb-svc-combo__limits"><?php echo esc_html( $c['limits'] ); ?></p>
				<p class="mb-svc-combo__links">
					<?php foreach ( $c['links'] as $l ) : $u = maxbit_theme_combo_url( $l[0] ); if ( $u ) : ?><a class="mb-btn mb-btn--link" href="<?php echo esc_url( $u ); ?>"><?php echo esc_html( maxbit_theme_combo_label( $l ) ); ?></a> <?php endif; endforeach; ?>
				</p>
			</div>
		</article>
		<?php endforeach; ?>
	</section>

	<section class="mb-svc-map" aria-labelledby="mb-svc-map-h">
		<h2 id="mb-svc-map-h" class="mb-svc__h2">Карта связей</h2>
		<div class="mb-svc-map__grid">
			<?php
			$node = function ( string $area, string $title, array $items, bool $core = false ) {
				echo '<div class="mb-svc-node' . ( $core ? ' mb-svc-node--core' : '' ) . '" style="grid-area:' . esc_attr( $area ) . '"><p class="mb-svc-node__title">' . esc_html( $title ) . '</p><p class="mb-svc-node__items">' . esc_html( implode( ' · ', $items ) ) . '</p></div>';
			};
			$link = function ( string $area, string $text, string $dir ) {
				echo '<div class="mb-svc-link mb-svc-link--' . esc_attr( $dir ) . '" style="grid-area:' . esc_attr( $area ) . '" aria-hidden="true"><span>' . esc_html( $text ) . '</span></div>';
			};
			foreach ( $groups as $g ) {
				if ( 'core' === $g['map'] ) {
					$node( 'core', $g['title'], wp_list_pluck( $g['items'], 'name' ), true );
				} else {
					$node( $g['map'], $g['title'], wp_list_pluck( $g['items'], 'name' ) );
					$link( 'l-' . $g['map'], $g['flow'], 'left' === $g['map'] || 'right' === $g['map'] ? 'h' : 'v' );
				}
			}
			?>
		</div>
		<p class="mb-small mb-svc-map__note">Схема показывает типичные связи. В центре — система, где сотрудники ведут работу с клиентом; у многих наших клиентов это Битрикс24, но это не обязательное условие. Какие связи нужны вашей компании, определяем на первой встрече.</p>
	</section>

	<h2 class="mb-svc__h2 mb-svc-catalog-h" id="katalog">Каталог сервисов</h2>
	<?php foreach ( $groups as $gi => $g ) : ?>
	<section class="mb-catalog__group mb-svc-group" aria-labelledby="svc-g<?php echo (int) $gi; ?>">
		<h3 class="mb-svc-group__h" id="svc-g<?php echo (int) $gi; ?>"><span><?php echo esc_html( sprintf( '%02d', $gi + 1 ) ); ?></span><?php echo esc_html( $g['title'] ); ?></h3>
		<div class="mb-grid mb-grid--2">
			<?php foreach ( $g['items'] as $s ) : $pr = $cases( $s['case_keys'] ?? array() ); ?>
			<article class="mb-ccard mb-svc-card" id="<?php echo esc_attr( $s['id'] ); ?>">
				<p class="mb-ccard__label"><?php echo esc_html( $s['kind'] ); ?></p>
				<h4><?php echo esc_html( $s['name'] ); ?></h4>
				<p><?php echo esc_html( $s['about'] ); ?></p>
				<p class="mb-svc-card__do"><strong>Что делаем:</strong> <?php echo esc_html( $s['do'] ); ?></p>
				<?php if ( ! empty( $s['partner'] ) ) : ?><p class="mb-svc-card__partner"><?php echo esc_html( $s['partner'] ); ?><?php if ( ! empty( $s['certificate'] ) ) : ?> · <a href="<?php echo esc_url( $s['certificate'] ); ?>">Документы</a><?php endif; ?></p><?php endif; ?>
				<?php if ( $pr ) : ?><p class="mb-svc-card__cases">В проектах: <?php echo implode( ', ', array_map( fn( $p ) => '<a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_post_meta( $p->ID, '_mb_pf_client', true ) ?: get_the_title( $p ) ) . '</a>', $pr ) ); // phpcs:ignore ?></p><?php endif; ?>
				<?php if ( ! empty( $s['url'] ) ) : ?><a class="mb-ccard__more" href="<?php echo esc_url( home_url( $s['url'] ) ); ?>"><?php echo esc_html( $s['url_label'] ?? 'Подробнее' ); ?></a><?php endif; ?>
			</article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endforeach; ?>

	<?php echo maxbit_theme_form( array( 'topic' => 'general', 'purposes' => '1', 'heading' => 'Подобрать и связать сервисы', 'text' => 'Расскажите, какие системы уже используются в компании и что приходится переносить вручную. На бесплатной встрече примерно на 45 минут обсудим, с чего начать.' ) ); // phpcs:ignore ?>
</div>
<?php get_footer(); ?>
