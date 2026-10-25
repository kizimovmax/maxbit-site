/* ТЗ 25.10.2026 — преобразование содержимого главной (Gutenberg-разметка).
   Чистая функция: вход — post_content, выход — {content, log}. Используется и локально (node), и в панели браузера. */
function maxbitTzHome(src) {
  var log = [];
  var out = src;
  function cut(name, re) {
    var m = out.match(re);
    if (!m) { log.push('НЕ НАЙДЕНО: ' + name); return; }
    out = out.replace(re, '');
    log.push('удалено: ' + name + ' (' + m[0].length + ' симв.)');
  }
  // 2.1 надпись над заголовком
  cut('eyebrow «MAXBIT — ваша команда…»', /<!-- wp:paragraph \{"className":"mb-eyebrow"\} -->\s*<p class="mb-eyebrow">MAXBIT — ваша команда цифрового развития<\/p>\s*<!-- \/wp:paragraph -->\s*/u);
  // 2.2 абзац «Работаем с существующими системами…»
  cut('абзац mb-hero__principle', /<!-- wp:paragraph \{"className":"mb-hero__principle"\} -->\s*<p class="mb-hero__principle">Работаем с существующими системами[\s\S]*?<\/p>\s*<!-- \/wp:paragraph -->\s*/u);
  // 2.3 текст под кнопками
  cut('текст mb-hero__note', /<!-- wp:paragraph \{"className":"mb-hero__note"\} -->\s*<p class="mb-hero__note">На первой встрече познакомимся[\s\S]*?<\/p>\s*<!-- \/wp:paragraph -->\s*/u);
  // 2.4 вторая колонка первого экрана целиком (блок «Что делает команда…» + подпись)
  cut('колонка «Что делает команда цифрового развития»', /<!-- wp:column -->\s*<div class="wp-block-column"><!-- wp:html -->\s*<div class="mb-flow mb-flow--model"[\s\S]*?<!-- \/wp:html -->\s*<\/div>\s*<!-- \/wp:column -->\s*/u);
  // 2.5 секция «Кто будет с вами работать»
  cut('секция «Кто будет с вами работать»', /<!-- wp:group \{"className":"mb-section mb-section(?:--|\\u002d\\u002d)surface","layout":\{"type":"constrained"\}\} -->\s*<div class="wp-block-group mb-section mb-section--surface"><!-- wp:group \{"className":"mb-section__head","layout":\{"type":"constrained"\}\} -->\s*<div class="wp-block-group mb-section__head"><!-- wp:heading -->\s*<h2 class="wp-block-heading">Кто будет с вами работать<\/h2>[\s\S]*?<!-- \/wp:group -->\s*<\/div>\s*<!-- \/wp:group -->\s*/u);
  // 5. FAQ перед формой
  var qa = [
    ['С какой задачи можно начать?', 'С одного участка: обработки заявок, согласования заказа, передачи в производство или контроля сроков. Вместе определим, что требует внимания в первую очередь.'],
    ['Нужно ли менять программы, которыми мы уже пользуемся?', 'Сначала проверяем возможности существующих систем. Затем предлагаем необходимые настройки, интеграции или доработки. Новую программу рассматриваем, если текущие инструменты задачу не решают.'],
    ['Что будет на первой встрече?', 'Обсудим вашу задачу, действующие программы и сложности в работе. Полезно разобрать один конкретный пример — заказ, документ или ситуацию. По итогам определим возможный следующий шаг.'],
    ['Сколько стоят работы и сколько времени они занимают?', 'Это зависит от задачи, текущих настроек и объёма изменений. После уточнения предложим состав работ с оценкой стоимости и сроков. Начать можно с отдельного этапа.'],
    ['Как вы проверяете результат?', 'До начала работ согласуем, что должно измениться и как это проверить. Затем вместе с сотрудниками проверяем решение на рабочих ситуациях. Если оцениваем показатели, заранее определяем исходные данные и период сравнения.'],
    ['Что происходит после запуска?', 'Помогаем сотрудникам освоить новый порядок работы и разбираем замечания в рамках согласованного этапа. Дальнейшее сопровождение и развитие системы обсуждаем отдельно.']
  ];
  var faq = '<!-- wp:group {"className":"mb-faq","layout":{"type":"constrained"}} -->\n<div class="wp-block-group mb-faq"><!-- wp:heading -->\n<h2 class="wp-block-heading">Ответы на вопросы</h2>\n<!-- /wp:heading -->\n';
  qa.forEach(function (p) {
    faq += '<!-- wp:details -->\n<details class="wp-block-details"><summary>' + p[0] + '</summary><!-- wp:paragraph -->\n<p>' + p[1] + '</p>\n<!-- /wp:paragraph -->\n</details>\n<!-- /wp:details -->\n';
  });
  faq += '</div>\n<!-- /wp:group -->\n';
  if (out.indexOf('mb-faq') !== -1) {
    log.push('FAQ уже есть — не дублирую');
  } else {
    var anchor = /<!-- wp:shortcode -->\s*\[maxbit_form topic="general"/u;
    if (!anchor.test(out)) { log.push('НЕ НАЙДЕНО: форма главной для вставки FAQ'); }
    else { out = out.replace(anchor, function (m) { return faq + m; }); log.push('FAQ вставлен перед формой (6 вопросов)'); }
  }
  return { content: out, log: log };
}
if (typeof module !== 'undefined') { module.exports = maxbitTzHome; }
