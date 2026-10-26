/* MAXBIT — самодиагностика участка. Считает результат в браузере, ничего не отправляет до формы материалов.
   В форму материалов уходят только цифры ответов (скрытое поле quiz). */
(function () {
  'use strict';
  var cfg = window.MAXBIT_QUIZ || {};
  // Свёрнутая самодиагностика (главная): раскрывается кнопкой в блоке или карточкой «Пройти самодиагностику».
  var box = document.querySelector('.mb-quiz[data-collapsible]');
  if (box) {
    var openBtn = box.querySelector('.mb-quiz__open');
    box.classList.add('is-collapsed');
    var expand = function (focus) {
      box.classList.remove('is-collapsed');
      if (openBtn) { openBtn.setAttribute('aria-expanded', 'true'); openBtn.parentNode.hidden = true; }
      box.scrollIntoView({ behavior: 'smooth', block: 'start' });
      if (focus) { var r = box.querySelector('input[type=radio]'); if (r) { r.focus({ preventScroll: true }); } }
    };
    if (openBtn) { openBtn.addEventListener('click', function () { expand(true); }); }
    document.querySelectorAll('[data-mb-open="diagnostic"]').forEach(function (a) {
      a.addEventListener('click', function (e) { e.preventDefault(); expand(true); });
    });
    if (location.hash === '#diagnostic') { expand(false); }
  }
  var form = document.querySelector('[data-quiz-form]');
  var result = document.querySelector('[data-quiz-result]');
  if (!form || !result) { return; }
  var hint = form.querySelector('[data-quiz-hint]');
  var groups = form.querySelectorAll('.mb-quiz__item');

  function answers() {
    var out = [];
    groups.forEach(function (g) {
      var c = g.querySelector('input[type=radio]:checked');
      out.push(c ? parseInt(c.value, 10) : null);
    });
    return out;
  }
  function zoneFor(score) {
    var zones = cfg.zones || [];
    for (var i = 0; i < zones.length; i++) { if (score >= zones[i].min) { return zones[i]; } }
    return zones[zones.length - 1];
  }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var a = answers();
    var missing = a.indexOf(null);
    if (missing !== -1) {
      if (hint) { hint.textContent = 'Остался без ответа вопрос ' + (missing + 1) + '.'; }
      var first = groups[missing].querySelector('input[type=radio]');
      if (first) { first.focus(); }
      groups[missing].scrollIntoView({ behavior: 'smooth', block: 'center' });
      return;
    }
    var score = a.reduce(function (s, v) { return s + v; }, 0);
    var z = zoneFor(score);
    result.querySelector('[data-quiz-score]').textContent = score + '/' + (cfg.max || 14);
    result.querySelector('[data-quiz-title]').textContent = z.title;
    result.querySelector('[data-quiz-text]').textContent = z.text;
    var tips = result.querySelector('[data-quiz-tips]');
    tips.innerHTML = '';
    (z.tips || []).forEach(function (t) { var li = document.createElement('li'); li.textContent = t; tips.appendChild(li); });
    var link = result.querySelector('[data-quiz-link]');
    if (z.url) { link.hidden = false; link.querySelector('a').setAttribute('href', z.url); } else { link.hidden = true; }
    var q = result.querySelector('[data-quiz]');
    if (q) { q.value = a.join(','); }
    result.hidden = false;
    if (hint) { hint.textContent = 'Результат ниже. Ответы можно изменить и пересчитать.'; }
    result.scrollIntoView({ behavior: 'smooth', block: 'start' });
    if (window.maxbitTrack) { window.maxbitTrack('quiz_complete', { direction: 'diagnostic', score: score }); }
  });

  // После успешной отправки формы материалов — показать именные ссылки из ответа сервера.
  document.addEventListener('maxbit:lead', function (e) {
    var d = e.detail || {};
    if (!d.form || !result.contains(d.form) || !d.response || !d.response.materials) { return; }
    d.form.querySelectorAll('.mb-form__row, .mb-form__actions, .mb-form__note').forEach(function (el) { el.hidden = true; });
    var box = d.form.querySelector('.mb-quiz__files');
    if (!box) { box = document.createElement('ul'); box.className = 'mb-quiz__files'; d.form.appendChild(box); }
    box.innerHTML = '';
    d.response.materials.forEach(function (m) {
      var li = document.createElement('li'); var a = document.createElement('a');
      a.href = m.url; a.textContent = m.title; a.setAttribute('data-event', 'material_download'); li.appendChild(a); box.appendChild(li);
    });
    box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  });
})();
