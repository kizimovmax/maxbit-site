/* Основа 1.0 (0.6.0) — преобразование содержимого главной: добавляет секцию самодиагностики [maxbit_quiz]
   перед разделом «Разберитесь сами — или вместе с нами». Чистая функция: вход — post_content, выход — {content, log}.
   Идемпотентна: повторный запуск ничего не меняет. Используется локально (node) и в панели браузера на dev. */
function maxbitGraphiteHome(src) {
  var log = [];
  var out = src;
  if (out.indexOf('[maxbit_quiz') !== -1) {
    log.push('секция самодиагностики уже есть — не дублирую');
    return { content: out, log: log };
  }
  var block = '<!-- wp:group {"className":"mb-section mb-section\\u002d\\u002dquiz","layout":{"type":"constrained"}} -->\n<div class="wp-block-group mb-section mb-section--quiz"><!-- wp:shortcode -->\n[maxbit_quiz]\n<!-- /wp:shortcode --></div>\n<!-- /wp:group -->\n\n';
  var anchor = /<!-- wp:group \{"className":"mb-section","layout":\{"type":"constrained"\}\} -->\s*<div class="wp-block-group mb-section"><!-- wp:group \{"className":"mb-section__head","layout":\{"type":"constrained"\}\} -->\s*<div class="wp-block-group mb-section__head"><!-- wp:heading -->\s*<h2 class="wp-block-heading">Разберитесь сами/u;
  var m = out.match(anchor);
  if (!m) { log.push('НЕ НАЙДЕНО: раздел «Разберитесь сами — или вместе с нами»'); return { content: out, log: log }; }
  out = out.slice(0, m.index) + block + out.slice(m.index);
  log.push('добавлена секция самодиагностики перед разделом «Разберитесь сами…» (+' + block.length + ' симв.)');
  return { content: out, log: log };
}
if (typeof module !== 'undefined') { module.exports = maxbitGraphiteHome; }
