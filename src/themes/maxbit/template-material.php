<?php
/**
 * Закрытый материал (КП / презентация) — вызывается модулем только после проверки токена на сервере.
 * Без хлебных крошек, связанных материалов и ссылок на другие закрытые материалы. Форма — с логической меткой источника.
 */
get_header();
$post_m  = get_post();
$purpose = (string) get_post_meta( $post_m->ID, '_mb_material_purpose', true ) ?: 'discuss';
$src     = (string) get_post_meta( $post_m->ID, '_mb_material_source', true );
?>
<div class="mb-material-bar" role="note">Материал по персональной ссылке · страница не индексируется<?php echo 'presentation' === $src ? ' · цен в презентации нет' : ''; ?></div>
<div class="mb-home mb-landing mb-material"><?php echo apply_filters( 'the_content', $post_m->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput ?>
	<div class="wp-block-group mb-section mb-section--contact">
	<?php echo maxbit_theme_form( array( 'topic' => 'material', 'purpose' => $purpose, 'src' => $src, 'id' => 'contact', 'heading' => 'kp' === $src ? 'Обсудить подходящий формат' : 'Обсудить задачу', 'text' => 'kp' === $src ? 'Напишите, какая задача сейчас главная, — предложим подходящий формат: ежемесячный пакет или почасовую работу.' : 'На бесплатной встрече примерно на 30 минут познакомимся с ситуацией и предложим возможный следующий шаг.' ) ); // phpcs:ignore ?>
	</div>
</div>
<?php get_footer(); ?>
