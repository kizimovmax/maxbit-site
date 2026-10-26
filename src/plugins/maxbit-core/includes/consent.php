<?php
defined( 'ABSPATH' ) || exit;

/**
 * Согласие на cookie и аналитика (0.4.1). Единственный необязательный сборщик данных на сайте — Яндекс Метрика.
 * Правила: баннер с равнозначными «Принять» и «Отклонить»; до выбора или при отказе Метрика не загружается
 * (ни одного запроса к mc.yandex.ru, включая noscript-пиксель); сайт при отказе работает полностью.
 * Выбор хранится в localStorage 12 месяцев; «Настройки cookie» в подвале показывают баннер снова.
 * Метрика подключается только в production и только при заданном счётчике — на стенде баннер можно
 * посмотреть, но счётчик не загрузится.
 */

function maxbit_cookie_consent_enabled(): bool {
	return '1' === (string) maxbit_get_settings()['cookie_notice'];
}

function maxbit_metrika_id(): string {
	if ( 'production' !== MAXBIT_ENV ) {
		return '';
	}
	return (string) maxbit_get_settings()['metrika_id'];
}

add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}
	$id      = maxbit_metrika_id();
	$enabled = maxbit_cookie_consent_enabled();
	if ( ! $enabled && ! $id ) {
		return;
	}
	$policy = function_exists( 'maxbit_legal_url' ) ? maxbit_legal_url( 'policy' ) : home_url( '/politika-obrabotki-personalnyh-dannyh/' );
	if ( $enabled ) :
		?>
<div class="mb-cookie" id="mb-cookie" role="region" aria-label="Согласие на использование cookie" hidden>
	<p><strong>Cookie и статистика.</strong> Для работы сайта нужны только технические cookie. Дополнительно мы хотели бы считать обезличенную статистику посещений (Яндекс Метрика). Выбор можно изменить в любой момент — ссылка «Настройки cookie» в подвале. <a href="<?php echo esc_url( $policy ); ?>">Политика обработки данных</a>.</p>
	<div class="mb-cookie__actions">
		<button type="button" class="mb-btn mb-btn--primary mb-btn--small" id="mb-cookie-accept">Принять</button>
		<button type="button" class="mb-btn mb-btn--ghost mb-btn--small" id="mb-cookie-reject">Отклонить</button>
	</div>
</div>
		<?php
	endif;
	?>
<script>(function(){var K='mb_consent',B=document.getElementById('mb-cookie'),MID=<?php echo (int) $id; ?>,loaded=false;
function read(){try{var v=JSON.parse(localStorage.getItem(K)||'null');if(v&&v.at&&(Date.now()-v.at)<31536e6)return v;}catch(e){}return null;}
function save(a){try{localStorage.setItem(K,JSON.stringify({v:1,analytics:!!a,at:Date.now()}));}catch(e){}}
function metrika(){if(loaded||!MID)return;loaded=true;(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return;}}k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})(window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");ym(MID,"init",{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:false});window.addEventListener("maxbit:track",function(e){try{ym(MID,"reachGoal",e.detail.event,e.detail.params||{});}catch(_){}});}
function show(){if(B){B.hidden=false;}}
function hide(){if(B){B.hidden=true;}}
var c=read();
if(c){if(c.analytics)metrika();}else if(B){show();}
if(B){document.getElementById('mb-cookie-accept').addEventListener('click',function(){save(true);hide();metrika();});
document.getElementById('mb-cookie-reject').addEventListener('click',function(){save(false);hide();});}
document.querySelectorAll('[data-mb-cookie-open]').forEach(function(b){b.addEventListener('click',function(){if(B){show();B.scrollIntoView({block:'end'});var a=document.getElementById('mb-cookie-accept');if(a)a.focus();}});});
})();</script>
	<?php
}, 40 );
