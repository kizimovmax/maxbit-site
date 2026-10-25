<?php
defined( 'ABSPATH' ) || exit;

/**
 * Все служебные поля начинаются с «_mb_» — WordPress считает их защищёнными:
 * они не попадают в REST/публичный вывод, пока мы сами их не выведем.
 */

function maxbit_seo_post_types(): array {
	return array( 'page', 'post', 'mb_solution', 'mb_project' );
}

/** Поля кейса: структура «контекст → … → следующий шаг». */
function maxbit_project_fields(): array {
	return array(
		'client'        => array( 'Компания или обезличенное описание', 'text' ),
		'context'       => array( 'Контекст', 'textarea' ),
		'task'          => array( 'Задача', 'textarea' ),
		'constraints'   => array( 'Ограничения', 'textarea' ),
		'solution'      => array( 'Решение', 'textarea' ),
		'implemented'   => array( 'Что внедрили', 'textarea' ),
		'usage'         => array( 'Что используется', 'textarea' ),
		'effect'        => array( 'Подтверждённый эффект и метод измерения', 'textarea' ),
		'boundaries'    => array( 'Границы', 'textarea' ),
		'next_step'     => array( 'Следующий шаг', 'textarea' ),
		'technologies'  => array( 'Реально использованные технологии', 'text' ),
	);
}

/** Внутренние поля кейса — никогда не выводятся публично. */
function maxbit_project_internal_fields(): array {
	return array(
		'sources'      => 'Источники (внутренние)',
		'permission'   => 'Разрешение клиента (статус, дата, кто подтвердил)',
		'reviewer'     => 'Автор / проверяющий',
		'dates'        => 'Даты проекта',
		'notes'        => 'Внутренние заметки',
	);
}

add_action( 'add_meta_boxes', function () {
	foreach ( maxbit_seo_post_types() as $pt ) {
		add_meta_box( 'maxbit_seo', 'SEO и индексация', 'maxbit_seo_box', $pt, 'normal', 'default' );
		add_meta_box( 'maxbit_related', 'Связанные материалы', 'maxbit_related_box', $pt, 'side', 'default' );
	}
	foreach ( array( 'mb_solution', 'mb_project' ) as $pt ) {
		add_meta_box( 'maxbit_publish', 'Допуск к публичному показу', 'maxbit_publish_box', $pt, 'side', 'high' );
	}
	add_meta_box( 'maxbit_project_fields', 'Структура кейса', 'maxbit_project_box', 'mb_project', 'normal', 'high' );
	add_meta_box( 'maxbit_project_internal', 'Внутренние данные (не публикуются)', 'maxbit_project_internal_box', 'mb_project', 'normal', 'low' );
} );

function maxbit_seo_box( WP_Post $post ): void {
	wp_nonce_field( 'maxbit_meta_' . $post->ID, 'maxbit_meta_nonce' );
	$title = get_post_meta( $post->ID, '_mb_seo_title', true );
	$desc  = get_post_meta( $post->ID, '_mb_seo_description', true );
	$noi   = get_post_meta( $post->ID, '_mb_noindex', true );
	?>
	<p><label for="mb_seo_title"><strong>Title</strong> (пусто — берётся заголовок + «MAXBIT»)</label><br>
	<input type="text" id="mb_seo_title" name="mb_seo_title" class="widefat" maxlength="120" value="<?php echo esc_attr( $title ); ?>"></p>
	<p><label for="mb_seo_description"><strong>Description</strong> (до 200 символов)</label><br>
	<textarea id="mb_seo_description" name="mb_seo_description" class="widefat" rows="3" maxlength="200"><?php echo esc_textarea( $desc ); ?></textarea></p>
	<p><label><input type="checkbox" name="mb_noindex" value="1" <?php checked( $noi, '1' ); ?>> Не индексировать эту страницу (noindex, исключить из sitemap)</label></p>
	<?php $upd = get_post_meta( $post->ID, '_mb_content_updated', true ); ?>
	<p><label><input type="checkbox" name="mb_content_updated" value="1"> <strong>Существенное обновление</strong> — поставить сегодняшнюю дату в «Обновлено» и dateModified</label><br>
	<span class="description">Сейчас: <?php echo $upd ? esc_html( wp_date( 'd.m.Y', strtotime( $upd ) ) ) : 'дата публикации'; ?>. Мелкие правки (опечатки) дату не сдвигают — иначе сигнал свежести обесценится.</span></p>
	<p class="description">Canonical формируется автоматически из адреса страницы и среды. Локальный адрес не выдаётся за maxbitpro.ru.</p>
	<?php
}

function maxbit_publish_box( WP_Post $post ): void {
	$ok = maxbit_is_public_ok( $post->ID );
	if ( ! current_user_can( 'manage_options' ) ) {
		echo '<p><strong>' . ( $ok ? 'Допущено к публичному показу.' : 'Не допущено к публичному показу.' ) . '</strong></p><p class="description">Флаг допуска меняет только администратор (решение владельца). Редактор может готовить и сохранять материал.</p>';
		return;
	}
	?>
	<p><label><input type="checkbox" name="mb_public_ok" value="1" <?php checked( $ok ); ?>> <strong>Допущено к публичному показу</strong></label></p>
	<p class="description">Отдельное решение владельца. Без флага материал виден только авторизованным проверяющим — даже в статусе «Опубликовано». Флаг не ставится автоматически при наполнении.</p>
	<?php if ( ! $ok ) : ?>
		<p style="color:#b45309"><strong>Сейчас: закрытый просмотр.</strong> Не показывается анонимно, в меню, списках, поиске, RSS, REST и sitemap.</p>
	<?php endif;
}

function maxbit_related_box( WP_Post $post ): void {
	$fields = array(
		'_mb_related_solution' => array( 'Связанное решение', 'mb_solution' ),
		'_mb_related_article'  => array( 'Связанная статья', 'post' ),
		'_mb_related_project'  => array( 'Связанный проект (показывается только после допуска)', 'mb_project' ),
	);
	foreach ( $fields as $key => $cfg ) {
		$current = (int) get_post_meta( $post->ID, $key, true );
		$items   = get_posts( array( 'post_type' => $cfg[1], 'post_status' => array( 'publish', 'draft', 'private' ), 'numberposts' => 50, 'orderby' => 'title', 'order' => 'ASC' ) );
		echo '<p><label><strong>' . esc_html( $cfg[0] ) . '</strong></label><br><select name="' . esc_attr( ltrim( $key, '_' ) ) . '" class="widefat"><option value="0">— нет —</option>';
		foreach ( $items as $it ) {
			if ( $it->ID === $post->ID ) {
				continue;
			}
			echo '<option value="' . (int) $it->ID . '" ' . selected( $current, $it->ID, false ) . '>' . esc_html( $it->post_title ) . ' (' . esc_html( $it->post_status ) . ')</option>';
		}
		echo '</select></p>';
	}
}

function maxbit_project_box( WP_Post $post ): void {
	echo '<p class="description">Кейс публикуется только с флагом допуска и после разрешения клиента. Пустые поля не показываются. Показатели и отзывы необязательны, когда их нет — не выдумывать.</p>';
	foreach ( maxbit_project_fields() as $k => $cfg ) {
		$v = get_post_meta( $post->ID, '_mb_pf_' . $k, true );
		echo '<p><label for="mb_pf_' . esc_attr( $k ) . '"><strong>' . esc_html( $cfg[0] ) . '</strong></label><br>';
		if ( 'textarea' === $cfg[1] ) {
			echo '<textarea id="mb_pf_' . esc_attr( $k ) . '" name="mb_pf_' . esc_attr( $k ) . '" class="widefat" rows="3">' . esc_textarea( $v ) . '</textarea>';
		} else {
			echo '<input type="text" id="mb_pf_' . esc_attr( $k ) . '" name="mb_pf_' . esc_attr( $k ) . '" class="widefat" value="' . esc_attr( $v ) . '">';
		}
		echo '</p>';
	}
	$sample = get_post_meta( $post->ID, '_mb_is_sample', true );
	echo '<p><label><input type="checkbox" name="mb_is_sample" value="1" ' . checked( $sample, '1', false ) . '> Учебный образец структуры (без имени клиента; никогда не выводится на главную и в sitemap)</label></p>';
}

function maxbit_project_internal_box( WP_Post $post ): void {
	echo '<p class="description" style="color:#b91c1c">Эти поля видны только в редакторе. Они не попадают в HTML, REST, RSS, экспорт для посетителя.</p>';
	foreach ( maxbit_project_internal_fields() as $k => $label ) {
		$v = get_post_meta( $post->ID, '_mb_int_' . $k, true );
		echo '<p><label for="mb_int_' . esc_attr( $k ) . '"><strong>' . esc_html( $label ) . '</strong></label><br><textarea id="mb_int_' . esc_attr( $k ) . '" name="mb_int_' . esc_attr( $k ) . '" class="widefat" rows="2">' . esc_textarea( $v ) . '</textarea></p>';
	}
}

add_action( 'save_post', function ( int $post_id, WP_Post $post ) {
	if ( ! isset( $_POST['maxbit_meta_nonce'] ) || ! wp_verify_nonce( $_POST['maxbit_meta_nonce'], 'maxbit_meta_' . $post_id ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$text = static fn( $k, $max ) => isset( $_POST[ $k ] ) ? mb_substr( sanitize_text_field( wp_unslash( $_POST[ $k ] ) ), 0, $max ) : '';
	$area = static fn( $k, $max ) => isset( $_POST[ $k ] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST[ $k ] ) ), 0, $max ) : '';

	update_post_meta( $post_id, '_mb_seo_title', $text( 'mb_seo_title', 120 ) );
	update_post_meta( $post_id, '_mb_seo_description', $area( 'mb_seo_description', 200 ) );
	update_post_meta( $post_id, '_mb_noindex', empty( $_POST['mb_noindex'] ) ? '0' : '1' );
	if ( ! empty( $_POST['mb_content_updated'] ) ) {
		update_post_meta( $post_id, '_mb_content_updated', gmdate( 'c' ) );
	}

	foreach ( array( 'mb_related_solution', 'mb_related_article', 'mb_related_project' ) as $k ) {
		update_post_meta( $post_id, '_' . $k, isset( $_POST[ $k ] ) ? absint( $_POST[ $k ] ) : 0 );
	}

	if ( in_array( $post->post_type, array( 'mb_solution', 'mb_project' ), true ) ) {
		// Допуск может выставить только тот, кто вправе публиковать; редактору-контентщику это не автоматически.
		if ( current_user_can( 'manage_options' ) ) {
			update_post_meta( $post_id, '_mb_public_ok', empty( $_POST['mb_public_ok'] ) ? '0' : '1' );
		}
	}
	if ( 'mb_project' === $post->post_type ) {
		foreach ( maxbit_project_fields() as $k => $cfg ) {
			update_post_meta( $post_id, '_mb_pf_' . $k, 'textarea' === $cfg[1] ? $area( 'mb_pf_' . $k, 3000 ) : $text( 'mb_pf_' . $k, 300 ) );
		}
		foreach ( array_keys( maxbit_project_internal_fields() ) as $k ) {
			update_post_meta( $post_id, '_mb_int_' . $k, $area( 'mb_int_' . $k, 5000 ) );
		}
		update_post_meta( $post_id, '_mb_is_sample', empty( $_POST['mb_is_sample'] ) ? '0' : '1' );
	}
}, 10, 2 );

/** Публичный вспомогательный доступ к связям для темы. */
function maxbit_get_related( int $post_id, string $kind ): ?WP_Post {
	$id = (int) get_post_meta( $post_id, '_mb_related_' . $kind, true );
	if ( ! $id ) {
		return null;
	}
	if ( maxbit_can_preview() ) {
		$p = get_post( $id );
		return ( $p && in_array( $p->post_status, array( 'publish', 'draft', 'private', 'pending' ), true ) ) ? $p : null;
	}
	return maxbit_is_publicly_visible( $id ) ? get_post( $id ) : null;
}
