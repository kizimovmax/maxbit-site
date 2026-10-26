<?php
defined( 'ABSPATH' ) || exit;

/**
 * Настройки MAXBIT: контакты, реквизиты оператора ПДн, приём заявок (почта, Битрикс24, Telegram),
 * Яндекс (Метрика, Вебмастер). Секреты (вебхук Б24, токен бота) вводятся владельцем в админке,
 * в интерфейсе показываются маской и при сохранении пустого/маскированного поля не затираются.
 */

const MAXBIT_SECRET_MASK = '••••••••';

function maxbit_settings_defaults(): array {
	return array(
		// Публичные контакты
		'phone'         => '',
		'email'         => '',
		'messenger'     => '',
		'messenger_url' => '',
		'founder'       => 'Максим Кизимов',
		'city'          => '',
		'telegram'      => '',   // устар. (0.4.0): ссылка на канал — переносится в tg_channel_url
		'tg_bot_url'    => '',   // бот/аккаунт для связи в Telegram
		'max_bot_url'   => '',   // бот/аккаунт для связи в MAX
		'tg_channel_url' => '',  // канал с материалами в Telegram
		'max_channel_url' => '', // канал с материалами в MAX
		'founder_facts' => '',   // проверяемые факты об основателе, по строке (блок «Кто ведёт проект»)
		'profiles'      => '',   // по одной ссылке в строке (sameAs)
		// Оператор ПДн / реквизиты
		'legal_name'    => '',   // ИП Кизимов М. … / ООО «…»
		'inn'           => '',
		'ogrn'          => '',
		'address'       => '',   // юридический/почтовый адрес
		'pdn_email'     => '',   // почта для обращений по ПДн (если пусто — email)
		'show_requisites' => '1',
		// Приём заявок
		'notify_email'  => '',   // куда слать уведомления (если пусто — email)
		'b24_webhook'   => '',   // секрет
		'b24_entity'    => 'lead', // lead | deal
		'b24_assigned'  => '',   // ID ответственного
		'b24_category'  => '',   // воронка сделок (для deal)
		'tg_token'      => '',   // секрет
		'tg_chat'       => '',
		// Яндекс
		'metrika_id'    => '',
		'yandex_verification' => '',
		'cookie_notice' => '1',
		// Согласия и закрытые материалы (0.4.1)
		'transfer_consent' => '0', // отдельное согласие на передачу данных третьим лицам (CRM/подрядчики)
		'transfer_to'   => '',     // кому передаются: «облачный Битрикс24 (ООО «1С-Битрикс»)» — подставляется в текст согласия
		'email_verified' => '0',   // 1 — ящик создан и доставка проверена; до этого почта на сайте не выводится (ТЗ v4 §17.4)
		'material_ttl_days' => '0', // срок действия ссылок на КП/презентацию, дней; 0 — без срока (отзыв вручную)
		'materials'     => "Чек-лист: 12 пунктов перед передачей заказа в производство | module:chek-list-peredacha-zakaza-v-proizvodstvo.pdf", // по строке: Название | файл
	);
}

function maxbit_get_settings(): array {
	$saved = get_option( 'maxbit_settings', array() );
	$s = array_merge( maxbit_settings_defaults(), is_array( $saved ) ? $saved : array() );
	if ( empty( $s['tg_channel_url'] ) && ! empty( $s['telegram'] ) ) {
		$s['tg_channel_url'] = $s['telegram']; // совместимость с 0.4.0
	}
	return $s;
}

/** Материалы для выдачи после самодиагностики: [ [title, file], … ]; file — «module:имя» (комплект модуля) или имя в uploads/maxbit-private/. */
function maxbit_materials(): array {
	$out = array();
	foreach ( preg_split( '/\R+/', (string) maxbit_get_settings()['materials'] ) ?: array() as $line ) {
		if ( false === strpos( $line, '|' ) ) {
			continue;
		}
		[ $title, $file ] = array_map( 'trim', explode( '|', $line, 2 ) );
		$file = preg_replace( '/[^a-zA-Z0-9_.:\-]/', '', $file );
		if ( $title && $file && ! str_contains( $file, '..' ) ) {
			$out[] = array( mb_substr( $title, 0, 120 ), $file );
		}
	}
	return $out;
}

/** Публичные профили (sameAs): по одному адресу в строке, только http(s). */
/** Почта для показа на сайте и в разметке: только после проверки ящика (ТЗ v4 §17.4). */
function maxbit_public_email(): string {
	$s = maxbit_get_settings();
	return ( ! empty( $s['email'] ) && '1' === (string) $s['email_verified'] ) ? (string) $s['email'] : '';
}

function maxbit_profiles(): array {
	$s   = maxbit_get_settings();
	$out = array();
	foreach ( preg_split( '/\R+/', (string) $s['profiles'] ) ?: array() as $line ) {
		$u = esc_url_raw( trim( $line ) );
		if ( $u && preg_match( '#^https?://#', $u ) ) {
			$out[] = $u;
		}
	}
	foreach ( array( 'tg_channel_url', 'max_channel_url', 'telegram' ) as $k ) {
		if ( ! empty( $s[ $k ] ) && ! in_array( $s[ $k ], $out, true ) ) {
			array_unshift( $out, $s[ $k ] );
		}
	}
	return array_values( array_unique( $out ) );
}

function maxbit_secret_mask( string $v ): string {
	if ( '' === $v ) {
		return '';
	}
	return MAXBIT_SECRET_MASK . substr( $v, -4 );
}

add_action( 'admin_menu', function () {
	add_menu_page( 'MAXBIT', 'MAXBIT', 'manage_options', 'maxbit', 'maxbit_settings_page', 'dashicons-admin-site-alt3', 3 );
} );

add_action( 'admin_init', function () {
	register_setting( 'maxbit_env', 'maxbit_env', array(
		'type' => 'string',
		'sanitize_callback' => fn( $v ) => in_array( $v, array( 'local', 'staging' ), true ) ? $v : 'staging',
	) );
	register_setting( 'maxbit_env', 'maxbit_disable_mail', array(
		'type' => 'string',
		'sanitize_callback' => fn( $v ) => '1' === (string) $v ? '1' : '0',
	) );
	register_setting( 'maxbit', 'maxbit_settings', array(
		'type'              => 'array',
		'sanitize_callback' => function ( $in ) {
			$in   = is_array( $in ) ? $in : array();
			$prev = maxbit_get_settings();
			$txt  = fn( $k, $n ) => mb_substr( sanitize_text_field( $in[ $k ] ?? '' ), 0, $n );
			$secret = function ( $k ) use ( $in, $prev ) {
				$v = trim( (string) ( $in[ $k ] ?? '' ) );
				if ( '' === $v || str_starts_with( $v, MAXBIT_SECRET_MASK ) ) {
					return (string) $prev[ $k ]; // не трогаем сохранённое
				}
				if ( '-' === $v ) {
					return ''; // явная очистка
				}
				return mb_substr( $v, 0, 300 );
			};
			$wh = $secret( 'b24_webhook' );
			if ( $wh && ! preg_match( '#^https://[^/]+/rest/\d+/[A-Za-z0-9]+/?$#', $wh ) ) {
				add_settings_error( 'maxbit_settings', 'b24_webhook', 'Вебхук Битрикс24 должен иметь вид https://портал.bitrix24.ru/rest/<id>/<код>/ — значение не сохранено.' );
				$wh = (string) $prev['b24_webhook'];
			}
			$tg = $secret( 'tg_token' );
			if ( $tg && ! preg_match( '/^\d+:[A-Za-z0-9_\-]{30,}$/', $tg ) ) {
				add_settings_error( 'maxbit_settings', 'tg_token', 'Токен бота Telegram имеет вид 123456:ABC… — значение не сохранено.' );
				$tg = (string) $prev['tg_token'];
			}
			$url = fn( $k ) => ( preg_match( '#^https://#', (string) ( $in[ $k ] ?? '' ) ) ? esc_url_raw( $in[ $k ] ) : '' );
			$out = array(
				'tg_bot_url'    => $url( 'tg_bot_url' ),
				'max_bot_url'   => $url( 'max_bot_url' ),
				'tg_channel_url' => $url( 'tg_channel_url' ),
				'max_channel_url' => $url( 'max_channel_url' ),
				'email_verified' => empty( $in['email_verified'] ) ? '0' : '1',
				'founder_facts' => mb_substr( sanitize_textarea_field( $in['founder_facts'] ?? '' ), 0, 1000 ),
				'transfer_consent' => empty( $in['transfer_consent'] ) ? '0' : '1',
				'transfer_to'   => $txt( 'transfer_to', 200 ),
				'materials'     => mb_substr( sanitize_textarea_field( $in['materials'] ?? '' ), 0, 2000 ),
				'material_ttl_days' => (string) max( 0, min( 365, (int) ( $in['material_ttl_days'] ?? 0 ) ) ),
				'phone'         => '', // телефон на сайте не размещается (решение владельца 26.10.2026)
				'email'         => sanitize_email( $in['email'] ?? '' ),
				'messenger'     => $txt( 'messenger', 60 ),
				'messenger_url' => esc_url_raw( $in['messenger_url'] ?? '' ),
				'founder'       => $txt( 'founder', 80 ),
				'city'          => $txt( 'city', 80 ),
				'telegram'      => esc_url_raw( $in['telegram'] ?? '' ),
				'profiles'      => mb_substr( sanitize_textarea_field( $in['profiles'] ?? '' ), 0, 2000 ),
				'legal_name'    => $txt( 'legal_name', 200 ),
				'inn'           => preg_replace( '/\D/', '', (string) ( $in['inn'] ?? '' ) ),
				'ogrn'          => preg_replace( '/\D/', '', (string) ( $in['ogrn'] ?? '' ) ),
				'address'       => $txt( 'address', 300 ),
				'pdn_email'     => sanitize_email( $in['pdn_email'] ?? '' ),
				'show_requisites' => empty( $in['show_requisites'] ) ? '0' : '1',
				'notify_email'  => implode( ', ', array_filter( array_map( 'sanitize_email', array_map( 'trim', explode( ',', (string) ( $in['notify_email'] ?? '' ) ) ) ) ) ),
				'b24_webhook'   => $wh,
				'b24_entity'    => in_array( $in['b24_entity'] ?? 'lead', array( 'lead', 'deal' ), true ) ? $in['b24_entity'] : 'lead',
				'b24_assigned'  => preg_replace( '/\D/', '', (string) ( $in['b24_assigned'] ?? '' ) ),
				'b24_category'  => preg_replace( '/\D/', '', (string) ( $in['b24_category'] ?? '' ) ),
				'tg_token'      => $tg,
				'tg_chat'       => preg_replace( '/[^\d\-]/', '', (string) ( $in['tg_chat'] ?? '' ) ),
				'metrika_id'    => preg_replace( '/\D/', '', (string) ( $in['metrika_id'] ?? '' ) ),
				'yandex_verification' => preg_replace( '/[^a-f0-9]/i', '', (string) ( $in['yandex_verification'] ?? '' ) ),
				'cookie_notice' => empty( $in['cookie_notice'] ) ? '0' : '1',
			);
			// Ключи, которых нет в форме (служебные), не теряются при сохранении.
			$saved = get_option( 'maxbit_settings', array() );
			return array_merge( is_array( $saved ) ? $saved : array(), $out );
		},
	) );
} );

function maxbit_settings_page(): void {
	$s = maxbit_get_settings();
	$missing = array();
	foreach ( array( 'email' => 'почта', 'legal_name' => 'наименование оператора ПДн', 'inn' => 'ИНН', 'address' => 'адрес' ) as $k => $label ) {
		if ( empty( $s[ $k ] ) ) {
			$missing[] = $label;
		}
	}
	$field = function ( string $k, string $label, string $type = 'text', string $hint = '', string $ph = '' ) use ( $s ) {
		$val = $s[ $k ];
		if ( in_array( $k, array( 'b24_webhook', 'tg_token' ), true ) ) {
			$val = maxbit_secret_mask( (string) $val );
			$hint .= ' Чтобы очистить — введите «-».';
		}
		echo '<tr><th><label for="mb_' . esc_attr( $k ) . '">' . esc_html( $label ) . '</label></th><td>';
		if ( 'textarea' === $type ) {
			echo '<textarea id="mb_' . esc_attr( $k ) . '" name="maxbit_settings[' . esc_attr( $k ) . ']" rows="3" class="large-text" placeholder="' . esc_attr( $ph ) . '">' . esc_textarea( (string) $val ) . '</textarea>';
		} else {
			echo '<input id="mb_' . esc_attr( $k ) . '" name="maxbit_settings[' . esc_attr( $k ) . ']" type="' . esc_attr( $type ) . '" class="regular-text" value="' . esc_attr( (string) $val ) . '" placeholder="' . esc_attr( $ph ) . '" autocomplete="off">';
		}
		if ( $hint ) {
			echo '<p class="description">' . wp_kses_post( $hint ) . '</p>';
		}
		echo '</td></tr>';
	};
	$notify = $s['notify_email'] ?: $s['email'];
	?>
	<div class="wrap">
		<h1>MAXBIT — состояние сайта</h1>
		<?php settings_errors( 'maxbit_settings' ); ?>
		<table class="widefat striped" style="max-width:860px;margin:1em 0">
			<tbody>
			<tr><th>Среда</th><td><code><?php echo esc_html( MAXBIT_ENV ); ?></code> — <?php echo 'production' === MAXBIT_ENV ? 'рабочая' : 'не индексируется, черновики закрыты, внешние отправки выключены'; ?></td></tr>
			<tr><th>Режим формы</th><td><code><?php echo esc_html( MAXBIT_FORM_MODE ); ?></code> — <?php echo 'LOCAL_TEST' === MAXBIT_FORM_MODE ? 'обращения сохраняются локально как тестовые и не отправляются MAXBIT' : 'рабочий: уведомления и доставка включены'; ?></td></tr>
			<tr><th>Уведомление на почту</th><td><?php echo $notify ? esc_html( $notify ) . ( 'production' === MAXBIT_ENV ? '' : ' (на стенде письма не отправляются)' ) : '<strong>не задано</strong>'; ?></td></tr>
			<tr><th>Доставка в Битрикс24</th><td><?php echo maxbit_b24_webhook() ? 'вебхук задан (' . esc_html( 'lead' === $s['b24_entity'] ? 'лид' : 'сделка' ) . ')' . ( maxbit_delivery_enabled() ? '' : ' — <strong>не активна</strong>: режим формы ' . esc_html( MAXBIT_FORM_MODE ) ) : '<strong>выключена</strong> — вебхук не задан'; ?></td></tr>
			<tr><th>Telegram</th><td><?php echo ( $s['tg_token'] && $s['tg_chat'] ) ? 'задан' : 'не задан'; ?></td></tr>
			<tr><th>Метрика</th><td><?php echo $s['metrika_id'] ? 'счётчик ' . esc_html( $s['metrika_id'] ) . ( 'production' === MAXBIT_ENV ? '' : ' (подключается только в production)' ) : '<strong>не задан</strong>'; ?></td></tr>
			<tr><th>Почта WordPress</th><td><?php echo ( 'production' !== MAXBIT_ENV && '1' === (string) get_option( 'maxbit_disable_mail', '1' ) ) ? '<strong>выключена</strong> (стенд): письма, включая сброс пароля, не отправляются' : 'включена'; ?></td></tr>
			<tr><th>Индексирование</th><td><?php echo ( 'production' === MAXBIT_ENV && '1' === (string) get_option( 'blog_public' ) ) ? 'разрешено' : '<strong>запрещено</strong> (noindex для всех страниц, robots: Disallow /, sitemap выключен)'; ?>; «Настройки → Чтение → Попросить поисковые системы не индексировать»: <?php echo '1' === (string) get_option( 'blog_public' ) ? 'снята' : 'установлена'; ?></td></tr>
			<tr><th>Пермалинки</th><td><code><?php echo esc_html( get_option( 'permalink_structure' ) ?: '(простые — адреса /praktika/… работать не будут)' ); ?></code></td></tr>
			<tr><th>Тема</th><td><?php echo esc_html( wp_get_theme()->get( 'Name' ) . ' ' . wp_get_theme()->get( 'Version' ) ); ?>; модуль <?php echo esc_html( MAXBIT_CORE_VERSION ); ?></td></tr>
			<tr><th>Содержание</th><td><?php $cnt = wp_count_posts( 'mb_solution' ); echo (int) ( $cnt->publish + $cnt->draft ) . ' решений, ' . (int) wp_count_posts( 'page' )->publish . ' страниц, ' . (int) wp_count_posts( 'mb_lead' )->private . ' обращений'; echo get_option( 'maxbit_seed_done' ) ? '; наполнение выполнено ' . esc_html( get_option( 'maxbit_seed_done' ) ) : '; <strong>наполнение не выполнялось</strong> (MAXBIT → Наполнение)'; ?></td></tr>
			<tr><th>Обязательно до выпуска</th><td>
				<?php if ( $missing ) : ?><span style="color:#b91c1c">Не заполнено: <?php echo esc_html( implode( ', ', $missing ) ); ?>.</span><?php else : ?>Контакты и реквизиты заполнены.<?php endif; ?>
				Незаданные контакты на сайте скрыты; вымышленные не подставляются. Тексты политики и согласия — проверить юристу (страницы «Политика обработки персональных данных», «Согласие»).
			</td></tr>
			</tbody>
		</table>
		<?php if ( ! ( defined( 'MAXBIT_ENV_FROM_CONFIG' ) ) ) : ?>
		<form method="post" action="options.php" style="margin-bottom:2em">
			<?php settings_fields( 'maxbit_env' ); ?>
			<h2>Среда стенда</h2>
			<p class="description">Константа <code>MAXBIT_ENV</code> в wp-config.php имеет приоритет над этой настройкой. Значение <code>production</code> задаётся только константой — после приёмки, отдельным решением.</p>
			<table class="form-table">
				<tr><th>Среда</th><td>
					<label><input type="radio" name="maxbit_env" value="staging" <?php checked( get_option( 'maxbit_env', 'staging' ), 'staging' ); ?>> staging — тестовый стенд (dev.maxbitpro.ru)</label><br>
					<label><input type="radio" name="maxbit_env" value="local" <?php checked( get_option( 'maxbit_env', 'staging' ), 'local' ); ?>> local — локальная разработка</label>
				</td></tr>
				<tr><th>Почта</th><td><label><input type="checkbox" name="maxbit_disable_mail" value="1" <?php checked( get_option( 'maxbit_disable_mail', '1' ), '1' ); ?>> Не отправлять письма вне production (сброс пароля, уведомления)</label></td></tr>
			</table>
			<?php submit_button( 'Сохранить среду' ); ?>
		</form>
		<?php endif; ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'maxbit' ); ?>
			<h2>Контакты (публичные, показываются только если заполнены)</h2>
			<table class="form-table">
				<?php
				$field( 'tg_bot_url', 'Бот Telegram (написать)', 'url', 'Кнопка «Написать в Telegram». Только https-ссылка.', 'https://t.me/...' );
				$field( 'max_bot_url', 'Бот MAX (написать)', 'url', 'Кнопка «Написать в MAX».', 'https://max.ru/..._bot' );
				$field( 'tg_channel_url', 'Канал в Telegram', 'url', 'Подвал, «Наши каналы».' );
				$field( 'max_channel_url', 'Канал в MAX', 'url', 'Подвал, «Наши каналы». Не путать с ботом.' );
				$field( 'email', 'Почта', 'email', 'Выводится на сайте только после отметки ниже.' );
				?>
				<tr><th>Почта проверена</th><td><label><input type="checkbox" name="maxbit_settings[email_verified]" value="1" <?php checked( $s['email_verified'], '1' ); ?>> Ящик создан, письмо снаружи доходит и ответ уходит — показывать почту на сайте</label></td></tr>
				<tr><th>Телефон</th><td><p class="description">Не размещается (решение владельца). Поле убрано.</p></td></tr>
				<?php
				$field( 'city', 'Город', 'text', 'Показывается в контактах и в разметке Organization.' );
				$field( 'telegram', 'Telegram (ссылка)', 'url', '', 'https://t.me/...' );
				$field( 'messenger', 'Другой мессенджер (подпись)', 'text', '', 'например: MAX' );
				$field( 'messenger_url', 'Ссылка мессенджера', 'url' );
				$field( 'profiles', 'Профили компании', 'textarea', 'По одной ссылке в строке: vc.ru, Хабр, Дзен, VK, партнёрские каталоги. Попадают в sameAs разметки.', "https://vc.ru/...\nhttps://dzen.ru/..." );
				$field( 'founder', 'Основатель', 'text' );
				$field( 'founder_facts', 'Основатель: факты', 'textarea', 'Только проверяемые факты, по строке. Пока не выводятся.' );
				?>
			</table>
			<h2>Согласия и закрытые материалы</h2>
			<table class="form-table">
				<tr><th>Передача данных</th><td><label><input type="checkbox" name="maxbit_settings[transfer_consent]" value="1" <?php checked( $s['transfer_consent'], '1' ); ?>> Отдельная галочка согласия на передачу данных (включить, если заявки уходят в облачный Битрикс24 или подрядчику)</label></td></tr>
				<?php
				$field( 'transfer_to', 'Кому передаются данные', 'text', 'Подставляется в текст галочки.' );
				$field( 'material_ttl_days', 'Срок ссылок на КП и презентацию, дней', 'number', '0 — без срока; ссылку можно отозвать в карточке обращения.' );
				$field( 'materials', 'Материалы самодиагностики', 'textarea', 'По строке: «Название | файл». Файл — module:имя или имя в uploads/maxbit-private/.' );
				?>
			</table>
			<h2>Оператор персональных данных и реквизиты</h2>
			<p class="description">Подставляются в политику обработки ПДн, согласие, подвал и разметку Organization. До заполнения в текстах стоят пометки «[реквизиты оператора]».</p>
			<table class="form-table">
				<?php
				$field( 'legal_name', 'Наименование оператора', 'text', 'Полностью, как в ЕГРЮЛ/ЕГРИП.', 'ИП Кизимов Максим … / ООО «…»' );
				$field( 'inn', 'ИНН', 'text' );
				$field( 'ogrn', 'ОГРН / ОГРНИП', 'text' );
				$field( 'address', 'Адрес', 'text', 'Юридический (почтовый) адрес для политики ПДн.' );
				$field( 'pdn_email', 'Почта для обращений по ПДн', 'email', 'Если пусто — используется основная почта.' );
				?>
				<tr><th>Подвал</th><td><label><input type="checkbox" name="maxbit_settings[show_requisites]" value="1" <?php checked( $s['show_requisites'], '1' ); ?>> Показывать наименование, ИНН и ОГРН в подвале сайта</label></td></tr>
			</table>
			<h2>Приём заявок</h2>
			<p class="description">Работает только в рабочем режиме формы (<code>MAXBIT_FORM_MODE</code> ≠ LOCAL_TEST). Заявка всегда сохраняется в панели «Обращения»; дальше — письмо, Битрикс24 и Telegram, если заданы. Секреты в чат с ИИ не отправлять: вводите их здесь.</p>
			<table class="form-table">
				<?php
				$field( 'notify_email', 'Уведомления на почту', 'text', 'Один или несколько адресов через запятую. Если пусто — основная почта.' );
				$field( 'b24_webhook', 'Входящий вебхук Битрикс24', 'text', 'Битрикс24 → Разработчикам → Другое → Входящий вебхук, права <code>crm</code>. Вид: https://портал.bitrix24.ru/rest/1/xxxx/' );
				?>
				<tr><th>Создавать в Битрикс24</th><td>
					<label><input type="radio" name="maxbit_settings[b24_entity]" value="lead" <?php checked( $s['b24_entity'], 'lead' ); ?>> лид</label>&nbsp;&nbsp;
					<label><input type="radio" name="maxbit_settings[b24_entity]" value="deal" <?php checked( $s['b24_entity'], 'deal' ); ?>> сделку (+ контакт)</label>
				</td></tr>
				<?php
				$field( 'b24_assigned', 'Ответственный (ID пользователя Б24)', 'text', 'Пусто — по правилам портала.' );
				$field( 'b24_category', 'Воронка сделок (ID направления)', 'text', 'Только для сделок; пусто — общая.' );
				$field( 'tg_token', 'Токен бота Telegram', 'text', 'Создаётся в @BotFather. Бот должен быть добавлен в чат.' );
				$field( 'tg_chat', 'ID чата Telegram', 'text', 'Числовой ID чата или группы (для групп — с минусом).' );
				?>
			</table>
			<h2>Яндекс</h2>
			<table class="form-table">
				<?php
				$field( 'metrika_id', 'Номер счётчика Метрики', 'text', 'Подключается только в production; цель — событие <code>lead_submit_accepted</code>.' );
				$field( 'yandex_verification', 'Код подтверждения Вебмастера', 'text', 'Содержимое <code>content</code> мета-тега <code>yandex-verification</code>.' );
				?>
				<tr><th>Cookie-уведомление</th><td><label><input type="checkbox" name="maxbit_settings[cookie_notice]" value="1" <?php checked( $s['cookie_notice'], '1' ); ?>> Показывать уведомление об использовании cookie (в production при включённой Метрике)</label></td></tr>
			</table>
			<?php submit_button( 'Сохранить' ); ?>
		</form>
	</div>
	<?php
}

/** Вебхук Битрикс24: настройка имеет приоритет над константой. */
function maxbit_b24_webhook(): string {
	$s = maxbit_get_settings();
	if ( ! empty( $s['b24_webhook'] ) ) {
		return (string) $s['b24_webhook'];
	}
	return (string) MAXBIT_B24_WEBHOOK_URL;
}

/** Контакты на странице «О компании»: только заполненные; телефон — только если задан (по умолчанию не размещается). */
add_shortcode( 'maxbit_contacts', function () {
	$s = maxbit_get_settings();
	$rows = array();
	if ( ! empty( $s['tg_bot_url'] ) ) {
		$rows[] = '<li><span>Telegram</span><a href="' . esc_url( $s['tg_bot_url'] ) . '" data-contact="telegram" target="_blank" rel="noopener">Написать в Telegram ››</a></li>';
	}
	if ( ! empty( $s['max_bot_url'] ) ) {
		$rows[] = '<li><span>MAX</span><a href="' . esc_url( $s['max_bot_url'] ) . '" data-contact="max" target="_blank" rel="noopener">Написать в MAX ››</a></li>';
	}
	if ( maxbit_public_email() ) {
		$rows[] = '<li><span>Почта</span><a href="mailto:' . esc_attr( $s['email'] ) . '" data-contact="email">' . esc_html( $s['email'] ) . '</a></li>';
	}
	if ( ! empty( $s['tg_channel_url'] ) ) {
		$rows[] = '<li><span>Канал</span><a href="' . esc_url( $s['tg_channel_url'] ) . '" data-contact="telegram_channel" target="_blank" rel="noopener">' . esc_html( preg_replace( '#^https?://#', '', $s['tg_channel_url'] ) ) . '</a></li>';
	}
	if ( ! empty( $s['max_channel_url'] ) ) {
		$rows[] = '<li><span>Канал в MAX</span><a href="' . esc_url( $s['max_channel_url'] ) . '" data-contact="max_channel" target="_blank" rel="noopener">' . esc_html( preg_replace( '#^https?://#', '', $s['max_channel_url'] ) ) . '</a></li>';
	}
	if ( ! empty( $s['city'] ) ) {
		$rows[] = '<li><span>Город</span><span>' . esc_html( $s['city'] ) . '</span></li>';
	}
	if ( ! $rows ) {
		$note = maxbit_can_preview() ? ' <span class="mb-badge mb-badge--draft">Для редактора: заполните контакты в разделе MAXBIT — до выпуска это обязательно.</span>' : '';
		return '<p>Самый простой способ связаться — форма ниже. Ответим по указанному вами контакту.' . $note . '</p>';
	}
	return '<ul class="mb-contacts">' . implode( '', $rows ) . '</ul>';
} );

/** Реквизиты оператора: строка для подвала и политики. Пустое не выводится. */
function maxbit_requisites_line(): string {
	$s = maxbit_get_settings();
	$parts = array();
	if ( $s['legal_name'] ) {
		$parts[] = $s['legal_name'];
	}
	if ( $s['inn'] ) {
		$parts[] = 'ИНН ' . $s['inn'];
	}
	if ( $s['ogrn'] ) {
		$parts[] = ( strlen( $s['ogrn'] ) === 15 ? 'ОГРНИП ' : 'ОГРН ' ) . $s['ogrn'];
	}
	return implode( ' · ', $parts );
}

/** Шорткод реквизитов для текстов: [maxbit_requisite field="legal_name|inn|ogrn|address|pdn_email|email|phone|city"]. */
add_shortcode( 'maxbit_requisite', function ( $atts ) {
	$a = shortcode_atts( array( 'field' => 'legal_name', 'placeholder' => '' ), $atts );
	$s = maxbit_get_settings();
	$k = preg_replace( '/[^a-z_]/', '', $a['field'] );
	$v = $s[ $k ] ?? '';
	if ( 'email' === $k || 'phone' === $k ) {
		$v = 'email' === $k ? maxbit_public_email() : '';
	}
	if ( 'pdn_email' === $k && ! $v ) {
		$v = maxbit_public_email();
	}
	if ( '' === (string) $v ) {
		$labels = array( 'legal_name' => 'наименование оператора', 'inn' => 'ИНН', 'ogrn' => 'ОГРН', 'address' => 'адрес', 'pdn_email' => 'почта', 'email' => 'почта', 'phone' => 'телефон', 'city' => 'город' );
		$ph = $a['placeholder'] ?: ( $labels[ $k ] ?? $k );
		return '<mark class="mb-placeholder" title="Заполняется в MAXBIT → Настройки">[' . esc_html( $ph ) . ']</mark>';
	}
	if ( in_array( $k, array( 'pdn_email', 'email' ), true ) ) {
		return '<a href="mailto:' . esc_attr( $v ) . '">' . esc_html( $v ) . '</a>';
	}
	return esc_html( $v );
} );
