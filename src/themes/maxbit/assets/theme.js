/* MAXBIT theme 0.6.0 — появление секций при прокрутке и мелкие улучшения. Без зависимостей.
   Без JS: класс html.js не ставится, стили появления не применяются, всё видно сразу.
   prefers-reduced-motion: движение отключено в CSS; здесь просто помечаем всё видимым. */
(function () {
  'use strict';
  var root = document.documentElement;
  root.classList.add('js');
  var items = document.querySelectorAll('.mb-reveal');
  if (!items.length) { return; }
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (reduce || !('IntersectionObserver' in window)) {
    items.forEach(function (el) { el.classList.add('is-in'); });
    return;
  }
  var io = new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
    });
  }, { rootMargin: '0px 0px -10% 0px', threshold: 0 }); // порог 0: секции на мобильном выше экрана, доля площади не подходит
  items.forEach(function (el) {
    var r = el.getBoundingClientRect();
    if (r.top < window.innerHeight * 0.9) { el.classList.add('is-in'); } else { io.observe(el); }
  });
  // Страховка: печать, переход по якорю, очень медленная прокрутка — через 4 с всё видно в любом случае.
  function all() { items.forEach(function (el) { el.classList.add('is-in'); }); }
  window.addEventListener('beforeprint', all);
  window.addEventListener('hashchange', all);
  setTimeout(all, 4000);
})();
