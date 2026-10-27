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
		<h1 class="mb-page__title"><?php the_title(); ?></h1>
		<p class="mb-lead">Сервисы, с которыми мы работаем, и то, как они связываются между собой. Битрикс24 — в центре: в него приходят заявки и переписка, из него уходят счета и документы.</p>
	</div>

	<section class="mb-svc-map" aria-labelledby="mb-svc-map-h">
		<h2 id="mb-svc-map-h" class="mb-svc__h2">Как связываем сервисы</h2>
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
		<p class="mb-small mb-svc-map__note">Схема показывает типичные связи. Какие из них нужны вашей компании, определяем на первой встрече.</p>
	</section>

	<?php foreach ( $groups as $gi => $g ) : ?>
	<section class="mb-catalog__group mb-svc-group" aria-labelledby="svc-g<?php echo (int) $gi; ?>">
		<h2 id="svc-g<?php echo (int) $gi; ?>"><span><?php echo esc_html( sprintf( '%02d', $gi + 1 ) ); ?></span><?php echo esc_html( $g['title'] ); ?></h2>
		<div class="mb-grid mb-grid--2">
			<?php foreach ( $g['items'] as $s ) : $pr = $cases( $s['case_keys'] ?? array() ); ?>
			<article class="mb-ccard mb-svc-card" id="<?php echo esc_attr( $s['id'] ); ?>">
				<p class="mb-ccard__label"><?php echo esc_html( $s['kind'] ); ?></p>
				<h3><?php echo esc_html( $s['name'] ); ?></h3>
				<p><?php echo esc_html( $s['about'] ); ?></p>
				<p class="mb-svc-card__do"><strong>Что делаем:</strong> <?php echo esc_html( $s['do'] ); ?></p>
				<?php if ( ! empty( $s['partner'] ) ) : ?><p class="mb-svc-card__partner"><?php echo esc_html( $s['partner'] ); ?><?php if ( ! empty( $s['certificate'] ) ) : ?> · <a href="<?php echo esc_url( $s['certificate'] ); ?>">Сертификат</a><?php endif; ?></p><?php endif; ?>
				<?php if ( $pr ) : ?><p class="mb-svc-card__cases">В проектах: <?php echo implode( ', ', array_map( fn( $p ) => '<a href="' . esc_url( get_permalink( $p ) ) . '">' . esc_html( get_post_meta( $p->ID, '_mb_pf_client', true ) ?: get_the_title( $p ) ) . '</a>', $pr ) ); // phpcs:ignore ?></p><?php endif; ?>
				<?php if ( ! empty( $s['url'] ) ) : ?><a class="mb-ccard__more" href="<?php echo esc_url( home_url( $s['url'] ) ); ?>"><?php echo esc_html( $s['url_label'] ?? 'Подробнее' ); ?></a><?php endif; ?>
			</article>
			<?php endforeach; ?>
		</div>
	</section>
	<?php endforeach; ?>

	<?php echo maxbit_theme_form( array( 'topic' => 'general', 'purposes' => '1', 'heading' => 'Связать ваши сервисы', 'text' => 'Расскажите, какие системы уже используются в компании и что приходится переносить вручную. На бесплатной встрече обсудим, как их связать.' ) ); // phpcs:ignore ?>
</div>
<?php get_footer(); ?>
