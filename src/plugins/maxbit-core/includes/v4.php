<?php
defined( 'ABSPATH' ) || exit;

/**
 * Применение комплекта содержания v4 (0.5.0) — ТЗ владельца v4.0 от 26.10.2026.
 *
 * Комплект собирается скриптом scripts/v4/build_bundle.py и лежит в публичном репозитории только в виде
 * шифротекста (AES-256-GCM, заголовок «MBX4»): в нём закрытые КП/презентация, кейсы до согласования
 * и сканы писем клиентов. Ключ передаётся в запросе из админ-сессии и нигде не сохраняется.
 *
 * Запрос: POST maxbit/v1/update/content {url, sha256, key, dry_run=1|0}
 *   dry_run=1 (по умолчанию) — только отчёт: что будет изменено, что не найдено. Ничего не пишет.
 *   dry_run=0 — резервная копия всего, что меняется (опция maxbit_v4_backup_<время>), затем применение.
 * Откат: POST maxbit/v1/update/content-restore {backup, confirm:"restore"}.
 * Резервная копия целиком: GET maxbit/v1/update/content-backup?backup=<имя>.
 * Локально: wp maxbit v4 --bundle=<файл> --key=<ключ> [--apply]
 */

/** Расшифровка и распаковка: возвращает [data(array), dir(string)] или WP_Error. */
function maxbit_v4_open_bundle( string $bin_path, string $key_b64 ) {
	$raw = (string) file_get_contents( $bin_path );
	$key = base64_decode( $key_b64, true );
	if ( strlen( $raw ) < 40 || 'MBX4' !== substr( $raw, 0, 4 ) || false === $key || 32 !== strlen( $key ) ) {
		return new WP_Error( 'bad_bundle', 'Комплект или ключ не подходят по формату.', array( 'status' => 400 ) );
	}
	$nonce = substr( $raw, 4, 12 );
	$ct    = substr( $raw, 16, -16 );
	$tag   = substr( $raw, -16 );
	$plain = false;
	if ( function_exists( 'openssl_decrypt' ) ) {
		$plain = openssl_decrypt( $ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, 'maxbit-content-v4' );
	}
	if ( false === $plain && function_exists( 'sodium_crypto_aead_aes256gcm_is_available' ) && sodium_crypto_aead_aes256gcm_is_available() ) {
		$plain = sodium_crypto_aead_aes256gcm_decrypt( $ct . $tag, 'maxbit-content-v4', $nonce, $key );
	}
	if ( false === $plain || '' === $plain ) {
		return new WP_Error( 'decrypt_failed', 'Не удалось расшифровать комплект: неверный ключ или повреждённый файл.', array( 'status' => 400 ) );
	}
	$dir = maxbit_update_dir() . '/content-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 6, false, false );
	wp_mkdir_p( $dir );
	file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" );
	$zip_path = $dir . '/bundle.zip';
	file_put_contents( $zip_path, $plain );
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		return new WP_Error( 'bad_zip', 'Внутри комплекта не ZIP.', array( 'status' => 400 ) );
	}
	for ( $i = 0; $i < $zip->numFiles; $i++ ) {
		$name = $zip->getNameIndex( $i );
		if ( str_contains( $name, '..' ) || ! preg_match( '#^(content\.json|reviews/[a-z0-9\-]+\.webp)$#', $name ) ) {
			$zip->close();
			return new WP_Error( 'bad_zip', 'Недопустимый файл в комплекте: ' . $name, array( 'status' => 400 ) );
		}
	}
	$zip->extractTo( $dir );
	$zip->close();
	@unlink( $zip_path );
	$data = json_decode( (string) file_get_contents( $dir . '/content.json' ), true );
	@unlink( $dir . '/content.json' ); // расшифрованный текст на диске не оставляем
	if ( ! is_array( $data ) || empty( $data['version'] ) ) {
		return new WP_Error( 'bad_json', 'content.json не читается.', array( 'status' => 400 ) );
	}
	return array( $data, $dir );
}

function maxbit_v4_rrmdir( string $dir ): void {
	foreach ( glob( $dir . '/{,.}[!.,!..]*', GLOB_BRACE ) ?: array() as $f ) {
		is_dir( $f ) ? maxbit_v4_rrmdir( $f ) : @unlink( $f );
	}
	@rmdir( $dir );
}

/** Найти запись по адресу сайта из реестра (/, /kak-rabotaem/, /resheniya/x/, /proekty/x/, /praktika/x/). */
function maxbit_v4_resolve( string $url ): ?WP_Post {
	$path = trim( $url, '/' );
	if ( '' === $path ) {
		$id = (int) get_option( 'page_on_front' );
		return $id ? get_post( $id ) : null;
	}
	if ( in_array( $path, array( 'praktika', 'baza-znaniy' ), true ) ) {
		$id = (int) get_option( 'page_for_posts' );
		return $id ? get_post( $id ) : null;
	}
	$parts = explode( '/', $path );
	$map = array( 'resheniya' => 'mb_solution', 'proekty' => 'mb_project', 'praktika' => 'post', 'baza-znaniy' => 'post' );
	if ( 2 === count( $parts ) && isset( $map[ $parts[0] ] ) ) {
		$p = get_posts( array( 'post_type' => $map[ $parts[0] ], 'name' => $parts[1], 'post_status' => array( 'publish', 'draft', 'private', 'pending' ), 'numberposts' => 1, 'suppress_filters' => true ) );
		return $p ? $p[0] : null;
	}
	$p = get_page_by_path( $path );
	return ( $p && 'trash' !== $p->post_status ) ? $p : null;
}

/** Точечная операция над текстом записи. Возвращает [новый текст, статус]. */
function maxbit_v4_op( string $c, array $op ): array {
	switch ( $op['op'] ) {
		case 'para':
			if ( str_contains( $c, '>' . $op['new'] . '</p>' ) ) {
				return array( $c, 'уже применено' );
			}
			$re = '/(<p(?:\s[^>]*)?>)' . preg_quote( $op['start'], '/' ) . '.*?(<\/p>)/su';
			if ( ! preg_match( $re, $c ) ) {
				return array( $c, 'НЕ НАЙДЕНО' );
			}
			return array( preg_replace( $re, '${1}' . str_replace( array( '\\', '$' ), array( '\\\\', '\$' ), $op['new'] ) . '${2}', $c, 1 ), 'заменено' );
		case 'replace':
			if ( str_contains( $c, $op['find'] ) ) {
				return array( str_replace( $op['find'], $op['new'], $c ), 'заменено' );
			}
			return array( $c, str_contains( $c, $op['new'] ) ? 'уже применено' : 'НЕ НАЙДЕНО' );
		case 'form_text':
			if ( ! preg_match( '/\[maxbit_form[^\]]*\]/u', $c, $m ) ) {
				return array( $c, 'НЕ НАЙДЕНО (нет формы)' );
			}
			$new_sc = preg_match( '/\btext="[^"]*"/u', $m[0] ) ? preg_replace( '/\btext="[^"]*"/u', 'text="' . esc_attr( $op['new'] ) . '"', $m[0] ) : str_replace( ']', ' text="' . esc_attr( $op['new'] ) . '"]', $m[0] );
			if ( ! empty( $op['heading'] ) ) {
				$new_sc = preg_replace( '/\bheading="[^"]*"/u', 'heading="' . esc_attr( $op['heading'] ) . '"', $new_sc );
			}
			if ( $new_sc === $m[0] ) {
				return array( $c, 'уже применено' );
			}
			return array( str_replace( $m[0], $new_sc, $c ), 'заменено' );
		case 'insert_before_form':
			if ( ! empty( $op['guard'] ) && str_contains( $c, $op['guard'] ) ) {
				return array( $c, 'уже применено' );
			}
			$pos = strpos( $c, '<!-- wp:shortcode -->' . "\n" . '[maxbit_form' );
			if ( false === $pos ) {
				return array( $c . $op['html'], 'добавлено в конец' );
			}
			return array( substr( $c, 0, $pos ) . $op['html'] . substr( $c, $pos ), 'добавлено перед формой' );
	}
	return array( $c, 'неизвестная операция' );
}

function maxbit_v4_post_snapshot( WP_Post $p ): array {
	$meta = array();
	foreach ( array( '_mb_seo_title', '_mb_seo_description', '_wp_page_template', '_mb_public_ok', '_mb_seed_key' ) as $k ) {
		$meta[ $k ] = metadata_exists( 'post', $p->ID, $k ) ? get_post_meta( $p->ID, $k, true ) : null;
	}
	return array( 'ID' => $p->ID, 'post_type' => $p->post_type, 'post_status' => $p->post_status, 'post_title' => $p->post_title, 'post_name' => $p->post_name, 'post_content' => $p->post_content, 'post_excerpt' => $p->post_excerpt, 'menu_order' => $p->menu_order, 'meta' => $meta );
}

/** Главное: применить (или показать, что будет применено). */
function maxbit_v4_apply( array $d, string $dir, bool $dry ): array {
	$r = array( 'dry_run' => $dry, 'version' => $d['version'], 'log' => array(), 'warnings' => array() );
	$log = function ( string $s ) use ( &$r ) { $r['log'][] = $s; };
	$warn = function ( string $s ) use ( &$r ) { $r['warnings'][] = $s; };
	$backup = array( 'created' => gmdate( 'c' ), 'version' => $d['version'], 'posts' => array(), 'created_posts' => array(), 'options' => array(), 'menu' => null );
	$touch = function ( WP_Post $p ) use ( &$backup ) {
		if ( ! isset( $backup['posts'][ $p->ID ] ) ) {
			$backup['posts'][ $p->ID ] = maxbit_v4_post_snapshot( $p );
		}
	};
	foreach ( array( 'maxbit_settings', 'maxbit_reviews', 'maxbit_seo_archives' ) as $o ) {
		$backup['options'][ $o ] = get_option( $o, null );
	}

	/* 1. Контакты */
	$s = get_option( 'maxbit_settings', array() );
	$s = is_array( $s ) ? $s : array();
	$changed = array();
	foreach ( $d['settings'] as $k => $v ) {
		if ( (string) ( $s[ $k ] ?? '' ) !== (string) $v ) {
			$changed[] = $k;
			$s[ $k ] = $v;
		}
	}
	$log( 'Контакты: ' . ( $changed ? 'обновить ' . implode( ', ', $changed ) : 'без изменений' ) );
	if ( ! $dry && $changed ) {
		update_option( 'maxbit_settings', $s );
	}

	/* 2. Страницы целиком */
	foreach ( $d['pages'] as $key => $pg ) {
		$post = ! empty( $pg['front'] ) ? maxbit_v4_resolve( '/' ) : maxbit_v4_resolve( '/' . $pg['path'] . '/' );
		if ( ! $post && empty( $pg['create'] ) ) {
			$warn( "Страница $key не найдена — пропущено" );
			continue;
		}
		if ( ! $post ) {
			$log( "Создать страницу /{$pg['path']}/ «{$pg['title']}»" );
			if ( ! $dry ) {
				$id = wp_insert_post( wp_slash( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => $pg['title'], 'post_name' => $pg['path'], 'post_content' => $pg['content'], 'post_excerpt' => $pg['excerpt'] ?? '', 'meta_input' => array( '_mb_v4' => $d['version'], '_wp_page_template' => $pg['template'] ?? 'default' ) ) ), true );
				if ( is_wp_error( $id ) ) {
					$warn( "Ошибка создания $key: " . $id->get_error_message() );
				} else {
					$backup['created_posts'][] = $id;
				}
			}
			continue;
		}
		$same = $post->post_content === $pg['content'] && ( empty( $pg['title'] ) || $post->post_title === $pg['title'] );
		$log( "Страница #{$post->ID} /{$post->post_name}/: " . ( $same ? 'уже актуальна' : 'заменить содержание (' . mb_strlen( $post->post_content ) . ' → ' . mb_strlen( $pg['content'] ) . ' симв.)' . ( ! empty( $pg['title'] ) && $post->post_title !== $pg['title'] ? '; заголовок «' . $pg['title'] . '»' : '' ) ) );
		if ( ! $dry && ! $same ) {
			$touch( $post );
			$upd = array( 'ID' => $post->ID, 'post_content' => $pg['content'] );
			if ( isset( $pg['excerpt'] ) ) {
				$upd['post_excerpt'] = $pg['excerpt'];
			}
			if ( ! empty( $pg['title'] ) ) {
				$upd['post_title'] = $pg['title'];
			}
			wp_update_post( wp_slash( $upd ) );
			update_post_meta( $post->ID, '_mb_v4', $d['version'] );
			if ( ! empty( $pg['template'] ) ) {
				update_post_meta( $post->ID, '_wp_page_template', $pg['template'] );
			}
		}
	}

	/* 3. Точечные правки */
	foreach ( $d['targeted'] as $t ) {
		$post = 'mb_solution' === $t['type'] ? maxbit_v4_resolve( '/' . $t['path'] . '/' ) : get_page_by_path( $t['path'] );
		if ( ! $post ) {
			$warn( "Точечные правки: {$t['path']} не найдена" );
			continue;
		}
		$c = $post->post_content;
		$st = array();
		foreach ( $t['ops'] as $op ) {
			[ $c, $status ] = maxbit_v4_op( $c, $op );
			$st[] = $op['op'] . ': ' . $status;
			if ( str_starts_with( $status, 'НЕ НАЙДЕНО' ) ) {
				$warn( "{$t['path']}: {$op['op']} «" . mb_substr( $op['start'] ?? $op['find'] ?? '', 0, 50 ) . '…» не найдено — текст на сайте отличается, правка не применена' );
			}
		}
		$ex_changed = ! empty( $t['excerpt'] ) && $post->post_excerpt !== $t['excerpt'];
		if ( $ex_changed ) {
			$st[] = 'краткое описание (карточка каталога): обновить';
		}
		$log( "#{$post->ID} /{$t['path']}/: " . implode( '; ', $st ) );
		if ( ! $dry && ( $c !== $post->post_content || $ex_changed ) ) {
			$touch( $post );
			wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_content' => $c ) + ( $ex_changed ? array( 'post_excerpt' => $t['excerpt'] ) : array() ) ) );
			update_post_meta( $post->ID, '_mb_v4', $d['version'] );
		}
	}

	/* 4. Проекты */
	foreach ( $d['projects'] as $pr ) {
		$ex = get_posts( array( 'post_type' => 'mb_project', 'name' => $pr['slug'], 'post_status' => array( 'publish', 'draft', 'private', 'pending', 'trash' ), 'numberposts' => 1, 'suppress_filters' => true ) );
		$ex = $ex ? $ex[0] : null;
		$log( 'Проект /proekty/' . $pr['slug'] . '/: ' . ( $ex ? "обновить #{$ex->ID}" : 'создать' ) );
		if ( $dry ) {
			continue;
		}
		$args = array( 'post_type' => 'mb_project', 'post_status' => 'publish', 'post_title' => $pr['title'], 'post_name' => $pr['slug'], 'post_content' => $pr['content'], 'post_excerpt' => $pr['excerpt'], 'menu_order' => $pr['menu_order'] );
		if ( $ex ) {
			$touch( $ex );
			$args['ID'] = $ex->ID;
			$id = wp_update_post( wp_slash( $args ), true );
		} else {
			$id = wp_insert_post( wp_slash( $args ), true );
			if ( ! is_wp_error( $id ) ) {
				$backup['created_posts'][] = $id;
			}
		}
		if ( is_wp_error( $id ) ) {
			$warn( 'Проект ' . $pr['slug'] . ': ' . $id->get_error_message() );
			continue;
		}
		foreach ( $pr['meta'] as $k => $v ) {
			update_post_meta( $id, $k, wp_slash( $v ) );
		}
		update_post_meta( $id, '_mb_v4', $d['version'] );
		update_post_meta( $id, '_mb_seed_key', 'project-v4-' . $pr['slug'] );
	}

	/* 5. Закрытые материалы */
	foreach ( $d['materials'] as $key => $m ) {
		$ex = function_exists( 'maxbit_material_post' ) ? maxbit_material_post( $key ) : null;
		$log( 'Материал «' . $m['title'] . '»: ' . ( $ex ? "обновить #{$ex->ID}" : 'создать (закрытый тип, по ссылке)' ) );
		if ( $dry ) {
			continue;
		}
		$args = array( 'post_type' => 'mb_material', 'post_status' => 'publish', 'post_title' => $m['title'], 'post_name' => 'material-' . $key, 'post_content' => $m['content'] );
		if ( $ex ) {
			$touch( $ex );
			$args['ID'] = $ex->ID;
			$id = wp_update_post( wp_slash( $args ), true );
		} else {
			$id = wp_insert_post( wp_slash( $args ), true );
			if ( ! is_wp_error( $id ) ) {
				$backup['created_posts'][] = $id;
			}
		}
		if ( ! is_wp_error( $id ) ) {
			update_post_meta( $id, '_mb_material_key', $key );
			update_post_meta( $id, '_mb_material_purpose', $m['purpose'] );
			update_post_meta( $id, '_mb_material_source', $m['source'] );
		}
	}

	/* 6. SEO всех страниц реестра */
	$arch = array();
	$miss = array();
	foreach ( $d['seo'] as $url => $m ) {
		if ( in_array( $url, array( '/resheniya/', '/proekty/' ), true ) ) {
			$arch[ $url ] = $m;
			continue;
		}
		$post = maxbit_v4_resolve( $url );
		if ( ! $post ) {
			if ( ! $dry && '/bitrix24/' !== $url && ! str_starts_with( $url, '/proekty/' ) ) {
				$miss[] = $url;
			}
			if ( $dry ) {
				$miss[] = $url;
			}
			continue;
		}
		if ( ! $dry ) {
			if ( ! isset( $backup['posts'][ $post->ID ] ) && ! in_array( $post->ID, $backup['created_posts'], true ) ) {
				$touch( $post );
			}
			update_post_meta( $post->ID, '_mb_seo_title', $m['title'] );
			update_post_meta( $post->ID, '_mb_seo_description', $m['description'] );
			// H1 из реестра для страниц и статей, где заголовок записи — это H1 шаблона.
			if ( in_array( $post->post_type, array( 'page', 'post' ), true ) && (int) get_option( 'page_on_front' ) !== $post->ID && (int) get_option( 'page_for_posts' ) !== $post->ID && '/bitrix24/' !== $url && $post->post_title !== $m['h1'] ) {
				wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_title' => $m['h1'] ) ) );
			}
		}
	}
	$log( 'SEO: ' . count( $d['seo'] ) . ' страниц реестра' . ( $miss ? '; пока не существуют (будут созданы этим же запуском): ' . implode( ', ', $miss ) : '' ) );
	if ( ! $dry ) {
		update_option( 'maxbit_seo_archives', $arch, false );
	}

	/* 7. Отзывы: изображения в uploads/maxbit-reviews */
	$up  = wp_get_upload_dir();
	$dst = trailingslashit( $up['basedir'] ) . 'maxbit-reviews';
	$files = glob( $dir . '/reviews/*.webp' ) ?: array();
	$log( 'Отзывы: ' . count( $d['reviews'] ) . ' клиентов, ' . array_sum( array_map( fn( $x ) => count( $x['docs'] ), $d['reviews'] ) ) . ' документов, ' . count( $files ) . ' изображений' );
	if ( ! $dry ) {
		wp_mkdir_p( $dst );
		if ( ! file_exists( $dst . '/index.php' ) ) {
			file_put_contents( $dst . '/index.php', "<?php\n// Silence is golden.\n" );
		}
		foreach ( $files as $f ) {
			copy( $f, $dst . '/' . basename( $f ) );
		}
		update_option( 'maxbit_reviews', $d['reviews'], false );
	}

	/* 8. Главное меню */
	$locs = get_nav_menu_locations();
	$menu_id = (int) ( $locs['primary'] ?? 0 );
	if ( ! $menu_id ) {
		$warn( 'Главное меню не назначено — пункты не изменены' );
	} else {
		$items = wp_get_nav_menu_items( $menu_id ) ?: array();
		$want = array_map( fn( $x ) => $x[0], $d['menu'] );
		$have = array_map( fn( $i ) => $i->title, $items );
		$log( 'Меню: ' . implode( ' · ', $have ) . ' → ' . implode( ' · ', $want ) );
		if ( ! $dry && $have !== $want ) {
			$backup['menu'] = array( 'id' => $menu_id, 'items' => array_map( fn( $i ) => array( 'title' => $i->title, 'type' => $i->type, 'object' => $i->object, 'object_id' => (int) $i->object_id, 'url' => $i->url, 'order' => (int) $i->menu_order ), $items ) );
			foreach ( $items as $i ) {
				wp_delete_post( $i->ID, true );
			}
			foreach ( $d['menu'] as $n => [ $label, $url ] ) {
				$args = array( 'menu-item-title' => $label, 'menu-item-status' => 'publish', 'menu-item-position' => $n + 1 );
				if ( '/resheniya/' === $url || '/proekty/' === $url ) {
					$args += array( 'menu-item-type' => 'post_type_archive', 'menu-item-object' => '/resheniya/' === $url ? 'mb_solution' : 'mb_project' );
				} else {
					$p = maxbit_v4_resolve( $url );
					$args += $p ? array( 'menu-item-type' => 'post_type', 'menu-item-object' => 'page', 'menu-item-object-id' => $p->ID ) : array( 'menu-item-type' => 'custom', 'menu-item-url' => home_url( $url ) );
				}
				wp_update_nav_menu_item( $menu_id, 0, $args );
			}
		}
	}

	if ( ! $dry ) {
		$name = 'maxbit_v4_backup_' . gmdate( 'Ymd_His' );
		add_option( $name, $backup, '', false );
		$list = get_option( 'maxbit_v4_backups', array() );
		$list[] = $name;
		update_option( 'maxbit_v4_backups', $list, false );
		update_option( 'maxbit_v4_applied', array( 'version' => $d['version'], 'at' => gmdate( 'c' ), 'backup' => $name ), false );
		flush_rewrite_rules( false );
		$r['backup'] = $name;
		$r['backup_posts'] = count( $backup['posts'] );
		$r['created_posts'] = count( $backup['created_posts'] );
	}
	return $r;
}

/** Откат к резервной копии: содержимое и мета изменённых записей, созданные записи — в корзину, опции и меню. */
function maxbit_v4_restore( string $name ): array {
	$b = get_option( $name );
	if ( ! is_array( $b ) || ! str_starts_with( $name, 'maxbit_v4_backup_' ) ) {
		return array( 'ok' => false, 'error' => 'Резервная копия не найдена.' );
	}
	$log = array();
	foreach ( $b['posts'] as $id => $p ) {
		wp_update_post( wp_slash( array( 'ID' => (int) $id, 'post_title' => $p['post_title'], 'post_content' => $p['post_content'], 'post_excerpt' => $p['post_excerpt'], 'post_status' => $p['post_status'], 'post_name' => $p['post_name'], 'menu_order' => $p['menu_order'] ) ) );
		foreach ( $p['meta'] as $k => $v ) {
			null === $v ? delete_post_meta( (int) $id, $k ) : update_post_meta( (int) $id, $k, wp_slash( $v ) );
		}
		delete_post_meta( (int) $id, '_mb_v4' );
		$log[] = "восстановлено #$id";
	}
	foreach ( $b['created_posts'] as $id ) {
		wp_trash_post( (int) $id );
		$log[] = "в корзину #$id (создано комплектом)";
	}
	foreach ( $b['options'] as $o => $v ) {
		null === $v ? delete_option( $o ) : update_option( $o, $v );
	}
	if ( ! empty( $b['menu'] ) ) {
		$menu_id = (int) $b['menu']['id'];
		foreach ( wp_get_nav_menu_items( $menu_id ) ?: array() as $i ) {
			wp_delete_post( $i->ID, true );
		}
		foreach ( $b['menu']['items'] as $n => $i ) {
			$args = array( 'menu-item-title' => $i['title'], 'menu-item-status' => 'publish', 'menu-item-position' => $n + 1, 'menu-item-type' => $i['type'], 'menu-item-object' => $i['object'] );
			if ( 'custom' === $i['type'] ) {
				$args['menu-item-url'] = $i['url'];
			} elseif ( 'post_type' === $i['type'] ) {
				$args['menu-item-object-id'] = $i['object_id'];
			}
			wp_update_nav_menu_item( $menu_id, 0, $args );
		}
		$log[] = 'меню восстановлено';
	}
	delete_option( 'maxbit_v4_applied' );
	flush_rewrite_rules( false );
	return array( 'ok' => true, 'log' => $log );
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'maxbit/v1', '/update/content', array(
		'methods'             => 'POST',
		'permission_callback' => 'maxbit_update_can',
		'callback'            => function ( WP_REST_Request $req ) {
			$url = trim( (string) $req->get_param( 'url' ) );
			$sha = strtolower( trim( (string) $req->get_param( 'sha256' ) ) );
			$key = trim( (string) $req->get_param( 'key' ) );
			$dry = '0' !== (string) ( $req->get_param( 'dry_run' ) ?? '1' );
			if ( ! preg_match( '/^[0-9a-f]{64}$/', $sha ) || ! $key ) {
				return new WP_Error( 'bad_request', 'Нужны url, sha256 и key.', array( 'status' => 400 ) );
			}
			if ( 'https' !== strtolower( (string) wp_parse_url( $url, PHP_URL_SCHEME ) ) || ! wp_http_validate_url( $url ) ) {
				return new WP_Error( 'bad_url', 'Ссылка должна быть публичной https.', array( 'status' => 400 ) );
			}
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$tmp = download_url( $url, 300 );
			if ( is_wp_error( $tmp ) ) {
				return new WP_Error( 'download_failed', 'Не удалось скачать комплект: ' . $tmp->get_error_message(), array( 'status' => 502 ) );
			}
			if ( ! hash_equals( $sha, hash_file( 'sha256', $tmp ) ) ) {
				@unlink( $tmp );
				return new WP_Error( 'sha_mismatch', 'SHA-256 комплекта не совпадает.', array( 'status' => 400 ) );
			}
			$opened = maxbit_v4_open_bundle( $tmp, $key );
			@unlink( $tmp );
			if ( is_wp_error( $opened ) ) {
				return $opened;
			}
			[ $data, $dir ] = $opened;
			$res = maxbit_v4_apply( $data, $dir, $dry );
			maxbit_v4_rrmdir( $dir );
			$resp = new WP_REST_Response( array( 'ok' => true ) + $res, 200 );
			$resp->header( 'Cache-Control', 'no-store' );
			return $resp;
		},
	) );
	register_rest_route( 'maxbit/v1', '/update/content-restore', array(
		'methods'             => 'POST',
		'permission_callback' => 'maxbit_update_can',
		'callback'            => function ( WP_REST_Request $req ) {
			if ( 'restore' !== (string) $req->get_param( 'confirm' ) ) {
				return new WP_Error( 'confirm', 'Нужно confirm=restore.', array( 'status' => 400 ) );
			}
			return new WP_REST_Response( maxbit_v4_restore( sanitize_key( (string) $req->get_param( 'backup' ) ) ), 200 );
		},
	) );
	register_rest_route( 'maxbit/v1', '/update/content-backup', array(
		'methods'             => 'GET',
		'permission_callback' => 'maxbit_update_can',
		'callback'            => function ( WP_REST_Request $req ) {
			$name = sanitize_key( (string) $req->get_param( 'backup' ) );
			$b = $name ? get_option( $name ) : null;
			if ( ! $name ) {
				return new WP_REST_Response( array( 'backups' => get_option( 'maxbit_v4_backups', array() ), 'applied' => get_option( 'maxbit_v4_applied' ) ), 200 );
			}
			return is_array( $b ) ? new WP_REST_Response( $b, 200 ) : new WP_Error( 'not_found', 'Нет такой копии.', array( 'status' => 404 ) );
		},
	) );
} );

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'maxbit v4', function ( $args, $assoc ) {
		$opened = maxbit_v4_open_bundle( (string) $assoc['bundle'], (string) $assoc['key'] );
		if ( is_wp_error( $opened ) ) {
			WP_CLI::error( $opened->get_error_message() );
		}
		[ $data, $dir ] = $opened;
		$res = maxbit_v4_apply( $data, $dir, empty( $assoc['apply'] ) );
		maxbit_v4_rrmdir( $dir );
		foreach ( $res['log'] as $l ) {
			WP_CLI::log( $l );
		}
		foreach ( $res['warnings'] as $w ) {
			WP_CLI::warning( $w );
		}
		WP_CLI::success( ( $res['dry_run'] ? 'Сухой прогон' : 'Применено, копия ' . $res['backup'] ) );
	} );
	WP_CLI::add_command( 'maxbit v4-restore', function ( $args ) {
		$r = maxbit_v4_restore( (string) $args[0] );
		$r['ok'] ? WP_CLI::success( implode( '; ', $r['log'] ) ) : WP_CLI::error( $r['error'] );
	} );
}
