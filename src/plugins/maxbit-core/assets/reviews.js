/* MAXBIT — отзывы: раскрытие остальных карточек и просмотр документа в диалоге.
   Без JS: все карточки видны, «Смотреть отзыв» открывает изображение по обычной ссылке. */
(function () {
  'use strict';
  document.querySelectorAll('.mb-reviews').forEach(function (box) {
    var toggle = box.querySelector('.mb-reviews__toggle');
    box.classList.add('is-collapsed');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var open = box.classList.toggle('is-collapsed') === false;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        toggle.textContent = open ? toggle.getAttribute('data-label-less') : toggle.getAttribute('data-label-more');
        if (open) {
          var first = box.querySelector('.mb-review[data-more] a');
          if (first) { first.focus(); }
        }
      });
    }
    var dlg = box.querySelector('.mb-viewer');
    if (!dlg || typeof dlg.showModal !== 'function') { return; } // старые браузеры — обычные ссылки
    var img = dlg.querySelector('.mb-viewer__doc img');
    var open = dlg.querySelector('.mb-viewer__open');
    var count = dlg.querySelector('.mb-viewer__count');
    var prev = dlg.querySelector('.mb-viewer__prev');
    var next = dlg.querySelector('.mb-viewer__next');
    var docs = [], idx = 0, opener = null;
    function show(i) {
      idx = (i + docs.length) % docs.length;
      var d = docs[idx];
      img.src = d.u; img.width = d.w; img.height = d.h;
      img.alt = d.t + ' — ' + dlg.querySelector('.mb-viewer__client').textContent;
      open.href = d.u;
      count.textContent = docs.length > 1 ? d.t + ' · документ ' + (idx + 1) + ' из ' + docs.length : d.t;
      prev.hidden = next.hidden = docs.length < 2;
    }
    box.addEventListener('click', function (e) {
      var a = e.target.closest('[data-review]');
      if (!a || !box.contains(a)) { return; }
      e.preventDefault();
      try { docs = JSON.parse(a.getAttribute('data-review')) || []; } catch (err) { docs = []; }
      if (!docs.length) { window.location.href = a.href; return; }
      opener = a;
      dlg.querySelector('.mb-viewer__client').textContent = a.getAttribute('data-client') || '';
      dlg.querySelector('.mb-viewer__topic').textContent = a.getAttribute('data-topic') || '';
      show(0);
      dlg.showModal();
      dlg.querySelector('.mb-viewer__close').focus();
      if (window.maxbitTrack) { window.maxbitTrack('review_open', { direction: 'general' }); }
    });
    dlg.querySelector('.mb-viewer__close').addEventListener('click', function () { dlg.close(); });
    prev.addEventListener('click', function () { show(idx - 1); });
    next.addEventListener('click', function () { show(idx + 1); });
    dlg.addEventListener('click', function (e) { if (e.target === dlg) { dlg.close(); } }); // клик по подложке
    dlg.addEventListener('close', function () { img.src = 'data:,'; if (opener) { opener.focus(); } });
  });
})();
