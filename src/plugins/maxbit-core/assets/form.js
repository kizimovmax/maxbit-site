/* MAXBIT — форма обращения. Прогрессивное улучшение: без JS форма работает через admin-post.php.
   Успех показывается только по ответу сервера {ok:true}. Никаких PII в событиях. */
(function () {
  'use strict';
  var cfg = window.MAXBIT_CFG || {};
  window.dataLayer = window.dataLayer || [];

  function track(name, params) {
    var p = Object.assign({ event: name, direction: 'general' }, params || {});
    if (cfg.directions && cfg.directions.indexOf(p.direction) === -1) { p.direction = 'general'; }
    // Свободный текст/контакты сюда не попадают по построению: только фиксированные ключи.
    window.dataLayer.push(p);
    try { window.dispatchEvent(new CustomEvent('maxbit:track', { detail: { event: name, params: p } })); } catch (e) { /* старые браузеры */ }
    if (!cfg.analytics && window.console) { console.debug('[maxbit event]', p); }
  }
  window.maxbitTrack = track;

  function uuid() {
    if (window.crypto && crypto.randomUUID) { return crypto.randomUUID(); }
    return 'k-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
  }

  function setError(form, field, text) {
    var input = form.querySelector('[name="' + field + '"]');
    if (!input) { return; }
    var err = form.querySelector('#' + input.id + '-err');
    if (err) { err.textContent = text || ''; }
    input.setAttribute('aria-invalid', text ? 'true' : 'false');
    input.classList.toggle('is-invalid', !!text);
  }

  function notice(form, kind, html) {
    var box = form.querySelector('.mb-form__status');
    box.innerHTML = '';
    var div = document.createElement('div');
    div.className = 'mb-notice mb-notice--' + kind;
    div.textContent = html;
    box.appendChild(div);
    div.setAttribute('tabindex', '-1');
    div.focus({ preventScroll: false });
  }

  function validate(form) {
    var ok = true;
    var name = form.querySelector('[name="name"]');
    var contact = form.querySelector('[name="contact"]');
    var consent = form.querySelector('[name="consent"]');
    var transfer = form.querySelector('[name="consent_transfer"]');
    setError(form, 'name', ''); setError(form, 'contact', ''); setError(form, 'consent', ''); if (transfer) { setError(form, 'consent_transfer', ''); }
    var first = null;
    if (!name.value || name.value.trim().length < 2) { setError(form, 'name', 'Укажите, как к вам обращаться.'); ok = false; first = first || name; }
    if (!contact.value || contact.value.trim().length < 3) { setError(form, 'contact', 'Укажите email или имя пользователя в Telegram/MAX.'); ok = false; first = first || contact; }
    if (consent && !consent.checked) { setError(form, 'consent', 'Нужно согласие на обработку персональных данных.'); ok = false; first = first || consent; }
    if (transfer && !transfer.checked) { setError(form, 'consent_transfer', 'Нужно согласие на передачу данных.'); ok = false; first = first || transfer; }
    if (!ok && first) { first.focus(); }
    return ok;
  }

  document.querySelectorAll('.mb-form__form').forEach(function (form) {
    var direction = form.closest('.mb-form').getAttribute('data-topic') || 'general';
    var started = false, busy = false;
    var keyInput = form.querySelector('[name="client_key"]');
    keyInput.value = uuid();

    form.addEventListener('input', function () {
      if (!started) { started = true; track('form_start', { direction: direction }); }
    }, { once: false });

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (busy) { return; }                      // защита от двойного клика
      if (!validate(form)) { return; }
      busy = true;
      var btn = form.querySelector('button[type="submit"]');
      var label = btn.textContent;
      btn.disabled = true; btn.setAttribute('aria-busy', 'true'); btn.textContent = 'Отправляем…';

      var data = {};
      new FormData(form).forEach(function (v, k) { data[k] = v; });
      delete data.action; delete data.redirect;
      data.page = location.pathname.replace(/^(\/materialy\/[a-z]+\/)[^\/]*\/?/, '$1').slice(0, 300); // без токена

      var ctrl = (typeof AbortController !== 'undefined') ? new AbortController() : null;
      var timer = ctrl ? setTimeout(function () { ctrl.abort(); }, cfg.timeoutMs || 15000) : null;
      var tokenField = form.querySelector('[name="token"]');
      var send = function () {
        return fetch(form.getAttribute('data-endpoint'), {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
          body: JSON.stringify(data),
          credentials: 'same-origin',
          signal: ctrl ? ctrl.signal : undefined
        });
      };
      // Токен мог не обновиться после прошлой отправки (сбой сети): запрашиваем перед отправкой.
      var ready = data.token ? Promise.resolve() : fetch(cfg.tokenUrl, { credentials: 'same-origin', signal: ctrl ? ctrl.signal : undefined })
        .then(function (r) { return r.json(); }).then(function (t) { tokenField.value = t.token; data.token = t.token; });
      ready.then(send).then(function (r) {
        return r.json().then(function (j) { return { status: r.status, body: j }; });
      }).then(function (res) {
        var j = res.body || {};
        if (j.ok) {
          // Текст — только из ответа сервера: успех показывается после подтверждённого сохранения.
          notice(form, 'ok', j.message || 'Заявка получена.');
          if (j.code === 'accepted') {
            track('lead_submit_accepted', { direction: direction });
            track('form_success', { direction: direction, purpose: j.purpose || 'discuss' });
            if (j.purpose === 'kp') { track('request_kp', { direction: direction }); }
            if (j.purpose === 'presentation') { track('request_presentation', { direction: direction }); }
          }
          try { document.dispatchEvent(new CustomEvent('maxbit:lead', { detail: { form: form, response: j } })); } catch (e2) { /* старые браузеры */ }
          form.querySelectorAll('input[type=text],textarea').forEach(function (i) { i.value = ''; });
          form.querySelectorAll('input[type=checkbox]').forEach(function (i) { i.checked = false; });
          var first = form.querySelector('input[name=purpose][type=radio]'); if (first) { first.checked = true; }
          keyInput.value = uuid();               // новый ключ для следующего, действительно нового обращения
          tokenField.value = '';   // одноразовость: новый токен запросим перед следующей отправкой
          fetch(cfg.tokenUrl, { credentials: 'same-origin' }).then(function (r) { return r.json(); })
            .then(function (t) { tokenField.value = t.token; }).catch(function () { /* повторный запрос — при следующей отправке */ });
          started = false;
          return;
        }
        track('lead_submit_error', { direction: direction, reason: j.code || ('http_' + res.status) });
        if (j.code === 'validation' && j.fields) {
          Object.keys(j.fields).forEach(function (f) { setError(form, f, j.fields[f]); });
          notice(form, 'error', j.message || 'Проверьте заполнение полей.');
          return;
        }
        if (j.code === 'token_expired' && j.new_token) {
          form.querySelector('[name="token"]').value = j.new_token;   // тема и значения полей сохранены — можно повторить
          notice(form, 'error', j.message);
          return;
        }
        notice(form, 'error', j.message || 'Не удалось отправить заявку. Попробуйте ещё раз позже.');
      }).catch(function (err) {
        var aborted = err && err.name === 'AbortError';
        track('lead_submit_error', { direction: direction, reason: aborted ? 'timeout' : 'network' });
        notice(form, 'error', aborted
          ? 'Сервер не ответил за отведённое время. Попробуйте ещё раз — введённые данные сохранены в форме; повторная запись не создастся.'
          : 'Сервер недоступен. Проверьте соединение и попробуйте ещё раз — введённые данные сохранены в форме.');
      }).finally(function () {
        if (timer) { clearTimeout(timer); }
        busy = false; btn.disabled = false; btn.removeAttribute('aria-busy'); btn.textContent = label;
      });
    });
  });

  // Клик по контакту (телефон/почта/мессенджер) — отдельное событие, не заявка.
  document.querySelectorAll('a[data-contact]').forEach(function (a) {
    a.addEventListener('click', function () { track('contact_click', { direction: 'general', kind: a.getAttribute('data-contact') }); });
  });

  // Клики по ботам, каналам и реферальным ссылкам — события без персональных данных (не равны обращению/регистрации).
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('a[data-event]') : null;
    if (a) { track(a.getAttribute('data-event'), { direction: 'general' }); }
    // Кнопки «Обсудить почасовую работу» и т. п.: подставить цель в форму, к которой ведёт ссылка.
    var pb = e.target.closest ? e.target.closest('[data-mb-purpose]') : null;
    if (pb) {
      var target = document.querySelector((pb.getAttribute('href') || '#contact').replace(/^[^#]*/, '') || '#contact');
      var f = target ? (target.matches('form') ? target : target.querySelector('form')) : null;
      if (f) {
        var v = pb.getAttribute('data-mb-purpose');
        var radio = f.querySelector('input[name=purpose][value="' + v + '"]');
        if (radio) { radio.checked = true; } else { var h = f.querySelector('input[name=purpose]'); if (h) { h.value = v; } }
      }
    }
  });

  // Мобильное меню.
  var toggle = document.querySelector('.mb-nav__toggle');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var open = document.body.classList.toggle('nav-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      // Панель меню начинается под шапкой: учитываем служебную полосу среды над ней (dev/local).
      var hdr = document.querySelector('.mb-header');
      if (open && hdr) { document.documentElement.style.setProperty('--mb-nav-top', Math.max(0, Math.round(hdr.getBoundingClientRect().bottom)) + 'px'); }
      if (open) { var first = document.querySelector('.mb-nav__menu a'); if (first) { first.focus(); } }
    });
    document.querySelectorAll('.mb-nav a').forEach(function (a) {
      a.addEventListener('click', function () { document.body.classList.remove('nav-open'); toggle.setAttribute('aria-expanded', 'false'); });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && document.body.classList.contains('nav-open')) {
        document.body.classList.remove('nav-open'); toggle.setAttribute('aria-expanded', 'false'); toggle.focus();
      }
    });
  }
})();
