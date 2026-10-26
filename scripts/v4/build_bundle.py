#!/usr/bin/env python3
"""
Сборка зашифрованного комплекта содержания v4 для dev.maxbitpro.ru.

Зачем шифрование: репозиторий публичный, а в комплекте есть закрытые материалы (КП с тарифами, презентация),
кейсы, не согласованные для production, и сканы писем клиентов. В Git попадает только шифротекст
(AES-256-GCM); ключ передаётся на сервер в запросе из админ-сессии владельца и нигде не сохраняется.

Источник — комплект владельца MAXBIT_FOR_CLAUDE_2026-10-26_v4 (путь — первым аргументом). Тексты страниц
сайта (главная, «Как работаем», «О компании», Битрикс24) публичные — они здесь же; тексты КП, презентации,
тарифы и кейсы читаются из комплекта во время сборки и в код не попадают.

Использование: python3 scripts/v4/build_bundle.py <папка комплекта> <выходная папка>
Результат: <out>/content-v4.zip (открытый, НЕ коммитить), <out>/content-v4.bin (шифротекст), <out>/content-v4.key
"""
import json, os, re, sys, html, zipfile, hashlib, base64, io
from cryptography.hazmat.primitives.ciphers.aead import AESGCM

SRC, OUT = sys.argv[1], sys.argv[2]
os.makedirs(OUT, exist_ok=True)
E = lambda s: html.escape(s, quote=False)
A = lambda s: html.escape(s, quote=True)
TZ = open(f'{SRC}/TZ_MAXBIT_UNIFIED_FOR_CLAUDE.md', encoding='utf-8').read()
CASES = json.load(open(f'{SRC}/cases/CASE_INDEX.json'))['cases']
TAR = json.load(open(f'{SRC}/data/tariffs-2026.json'))
SEO = json.load(open(f'{SRC}/data/seo-map.json'))
REV = json.load(open(f'{SRC}/reviews/reviews.json'))
CONTACTS = json.load(open(f'{SRC}/data/contacts.json'))
REF = json.load(open(f'{SRC}/data/cta-map.json'))['bitrix24_referrals']

# ------------------------------------------------------------------ блочная разметка (как в seed.php)
def p(t, cls=''):
    a = f' {{"className":"{cls}"}}' if cls else ''
    c = f' class="{cls}"' if cls else ''
    return f'<!-- wp:paragraph{a} -->\n<p{c}>{t}</p>\n<!-- /wp:paragraph -->\n'

def h(level, t, cls='', anchor=''):
    attrs = {}
    if level != 2:
        attrs['level'] = level
    if cls:
        attrs['className'] = cls
    if anchor:
        attrs['anchor'] = anchor
    j = (' ' + json.dumps(attrs, ensure_ascii=False)) if attrs else ''
    idattr = f' id="{anchor}"' if anchor else ''
    c = f' {cls}' if cls else ''
    return f'<!-- wp:heading{j} -->\n<h{level}{idattr} class="wp-block-heading{c}">{t}</h{level}>\n<!-- /wp:heading -->\n'

def ul(items, cls=''):
    a = f' {{"className":"{cls}"}}' if cls else ''
    c = f' {cls}' if cls else ''
    out = f'<!-- wp:list{a} -->\n<ul class="wp-block-list{c}">'
    for i in items:
        out += f'<!-- wp:list-item -->\n<li>{i}</li>\n<!-- /wp:list-item -->\n'
    return out + '</ul>\n<!-- /wp:list -->\n'

def group(inner, cls, anchor=''):
    attrs = {'className': cls, 'layout': {'type': 'constrained'}}
    if anchor:
        attrs = {'anchor': anchor, **attrs}
    idattr = f' id="{anchor}"' if anchor else ''
    return f'<!-- wp:group {json.dumps(attrs, ensure_ascii=False)} -->\n<div{idattr} class="wp-block-group {cls}">{inner}</div>\n<!-- /wp:group -->\n'

def section(inner, cls='', anchor=''):
    return group(inner, ('mb-section ' + cls).strip(), anchor)

def head(h2, intro=''):
    return group(h(2, h2) + (p(intro) if intro else ''), 'mb-section__head')

def columns(cols, cls):
    out = f'<!-- wp:columns {{"className":"{cls}"}} -->\n<div class="wp-block-columns {cls}">'
    for col in cols:
        out += f'<!-- wp:column -->\n<div class="wp-block-column">{col}</div>\n<!-- /wp:column -->\n'
    return out + '</div>\n<!-- /wp:columns -->\n'

def buttons(btns, cls=''):
    c = ('wp-block-buttons ' + cls).strip()
    j = f' {{"className":"{cls}"}}' if cls else ''
    out = f'<!-- wp:buttons{j} -->\n<div class="{c}">'
    for label, url, outline in btns:
        st = ' {"className":"is-style-outline"}' if outline else ''
        sc = ' is-style-outline' if outline else ''
        out += f'<!-- wp:button{st} -->\n<div class="wp-block-button{sc}"><a class="wp-block-button__link wp-element-button" href="{url}">{label}</a></div>\n<!-- /wp:button -->\n'
    return out + '</div>\n<!-- /wp:buttons -->\n'

def sc(code):
    return f'<!-- wp:shortcode -->\n{code}\n<!-- /wp:shortcode -->\n'

def raw(htmlx):
    return f'<!-- wp:html -->\n{htmlx}\n<!-- /wp:html -->\n'

def details(q, a):
    return f'<!-- wp:details -->\n<details class="wp-block-details"><summary>{q}</summary>{p(a)}</details>\n<!-- /wp:details -->\n'

def faq(qa, title='Ответы на вопросы', anchor=''):
    inner = h(2, title)
    for q, a in qa:
        inner += details(q, a)
    return group(inner, 'mb-faq', anchor)

def form(**kw):
    attrs = ' '.join(f'{k}="{A(v)}"' for k, v in kw.items())
    return sc(f'[maxbit_form {attrs}]')

# ------------------------------------------------------------------ извлечение текстов из ТЗ (закрытые разделы)
def tz_section(start, end):
    i = TZ.index(start)
    j = TZ.index(end, i + len(start))
    return TZ[i:j]

def ru_money(n):
    return f'{n:,}'.replace(',', ' ')

# ------------------------------------------------------------------ главная (§3)
SIT = [
    ('Не видно, что происходит с заказами', 'Чтобы узнать статус, приходится звонить сотрудникам и собирать сведения из переписки. Причина задержки и следующий шаг остаются неясными.'),
    ('Руководитель собирает картину вручную', 'Данные о продажах, исполнении и оплатах находятся в разных местах. На подготовку сводки уходит время, а информация быстро устаревает.'),
    ('Подразделения работают несогласованно', 'Продажи, производство, снабжение и бухгалтерия по-разному понимают договорённости. Изменения не всегда доходят до тех, кто должен по ним работать.'),
    ('Сотрудники заняты повторяющимися действиями', 'Переносят данные, напоминают о задачах, ищут документы и вручную готовят отчёты. На работу, требующую внимания, остаётся меньше времени.'),
    ('Программы есть, а общей работы нет', '1С, CRM, таблицы, почта и другие сервисы используются отдельно. Одни и те же данные вводят несколько раз, а расхождения приходится разбирать вручную.'),
    ('Компания выросла, процессы — ещё нет', 'Правила, которые работали при небольшой нагрузке, перестают справляться. Всё больше вопросов замыкается на руководителе и отдельных сотрудниках.'),
]
for t, d in SIT:
    assert t in TZ and d in TZ, t
DIRS = [
    ('Управление заказами и исполнением', 'Проектируем путь заказа от заявки до исполнения, отгрузки и оплаты. Определяем этапы, обязательные данные, ответственных и правила изменения договорённостей.', 'Управление производственными заказами', '/resheniya/zakaz-pod-kontrolem/'),
    ('Битрикс24 для производственных компаний', 'Настраиваем и развиваем Битрикс24 с учётом рабочих процессов компании. Связываем задачи сотрудников, согласования и взаимодействие подразделений.', 'Битрикс24 для производства', '/tekhnologii/bitrix24-dlya-proizvodstva/'),
    ('Интеграция Битрикс24 и 1С', 'Определяем, где создаются и обновляются данные, кому они нужны и как передаются между системами. Настраиваем согласованные обмены и проверяем рабочие сценарии.', 'Интеграция Битрикс24 и 1С', '/resheniya/integraciya-bitrix24-i-1c/'),
]
STEPS = [
    ('Обсуждаем одну конкретную проблему', 'На бесплатной встрече примерно на 30 минут знакомимся с вашей ситуацией, действующими системами и ограничениями. Подводим краткий итог и предлагаем первый шаг.'),
    ('Согласуем формат и приоритеты', 'Определяем задачи, состав участников, ответственность и критерии результата. Основной формат — ежемесячное сотрудничество на согласованную сумму. Если он не подходит, обсуждаем почасовую работу или отдельный проект.'),
    ('Разбираем процесс и внедряем улучшения', 'Вместе с участниками уточняем, как устроена работа. Проектируем изменения, настраиваем необходимые инструменты и проверяем рабочие сценарии.'),
    ('Проверяем результат и выбираем следующий шаг', 'Смотрим, используются ли изменения в ежедневной работе. Разбираем замечания, сравниваем согласованные показатели и определяем следующие приоритеты.'),
]
RESULTS = [
    ('Понятен порядок работы', 'У процесса есть согласованные этапы, ответственные и правила передачи информации. Сотрудники знают, где посмотреть актуальные данные и что делать дальше.'),
    ('Изменения используются', 'Проверяем рабочие ситуации вместе с участниками, обучаем сотрудников и разбираем причины обхода системы. Передаём правила работы и необходимые материалы.'),
    ('Есть основания для следующих решений', 'Сравниваем согласованные показатели с исходной ситуацией и обсуждаем замечания. Измеримые эффекты показываем там, где есть данные для сравнения.'),
]
FAQ = [
    ('С какой задачи можно начать?', 'С одной конкретной проблемы: ручного переноса данных, задержек согласования, контроля исполнения или несогласованности подразделений. На первой встрече обсудим ситуацию и предложим первый шаг.'),
    ('Первый разговор платный?', 'Нет. Первая встреча длится примерно 30 минут и бесплатна. Это знакомство с задачей и обсуждение возможного подхода. Подробный анализ и реализацию изменений согласуем отдельно в выбранном формате работы.'),
    ('Как устроена ежемесячная работа?', 'Согласуем пакет, приоритеты, участников и критерии результата. Внутри сотрудничества разбираем процессы, проектируем и внедряем изменения, проверяем их использование и планируем следующие задачи.'),
    ('Можно работать без ежемесячного пакета?', 'Да. Есть почасовой формат с оплатой фактически затраченного времени. Отдельный проект тоже возможен, если у задачи понятные границы. Подходящий формат обсудим после знакомства с вашей ситуацией.'),
    ('Нужно ли менять программы, которыми мы пользуемся?', 'Сначала проверяем возможности существующих инструментов. Затем предлагаем необходимые настройки, интеграции или доработки. Новую программу рассматриваем, если текущие средства не решают задачу.'),
    ('Сколько стоят работы?', 'Стоимость зависит от формата и объёма задач. Для регулярной работы есть ежемесячные пакеты, для почасового формата согласуем работы и оценку времени. Коммерческое предложение с тарифами отправляем по ссылке после заявки.'),
    ('Есть ли ограничения по задачам и встречам?', 'Да. Общий объём ограничен часами выбранного пакета. Приоритеты и состав работ согласуем, а если объёма недостаточно, отдельно обсуждаем дальнейшие действия.'),
    ('Что происходит после запуска изменений?', 'Проверяем, как сотрудники используют решение, разбираем замечания и определяем следующие улучшения. В ежемесячном формате продолжаем работу по согласованным приоритетам. Для отдельного проекта условия дальнейшей работы обсуждаем отдельно.'),
]
for q, a in FAQ:
    assert q in TZ and a in TZ, q
for lst in (DIRS, STEPS, RESULTS):
    for x in lst:
        assert x[0] in TZ and x[1] in TZ, x[0]

CONTACT_TEXT = 'На бесплатной первой встрече познакомимся с вашей ситуацией, обсудим действующие инструменты и ограничения. По итогам предложим возможный следующий шаг.'

def home():
    hero = section(columns([
        h(1, 'Цифровое развитие производственных компаний')
        + p('Помогаем производственным предприятиям улучшать бизнес-процессы, сокращать ручную работу и связывать разрозненные системы.', 'mb-hero__lead')
        + p('Разбираемся в задачах бизнеса, выбираем подходящие решения, внедряем изменения и сопровождаем их в ежедневной работе.', 'mb-hero__text')
        + buttons([('Обсудить задачу за 30 минут', '#contact', False), ('Посмотреть реализованные проекты', '/proekty/', True)], 'mb-hero__actions')
        + sc('[maxbit_messengers kind="bots" style="inline" label="Первый разговор — бесплатно"]')
    ], 'mb-hero__cols'), 'mb-hero')
    sit = section(head('С чем к нам приходят', 'Проблема может быть в одном процессе или на стыке нескольких подразделений. Начинаем с конкретной ситуации и смотрим, что мешает работе.')
                  + columns([h(3, t) + p(d) for t, d in SIT], 'mb-tiles mb-situations'), 'mb-section--surface')
    cards = []
    for i, (t, d, link, url) in enumerate(DIRS):
        cards.append(raw(f'<div class="mb-solution-card"><h3>{t}</h3><p>{d}</p><p><a class="mb-btn mb-btn--link" href="{url}">{link}</a></p></div>'))
    dirs = section(head('С какими задачами помогаем', 'Начать можно с одного процесса. Ниже — направления, для которых уже подготовлено описание подхода и границ работы.')
                   + columns(cards, 'mb-cards-wide mb-directions')
                   + p('<a class="mb-btn mb-btn--link" href="/resheniya/">Все решения</a>', 'mb-section__after'))
    steps = section(head('От первого разговора к изменениям в работе')
                    + group(columns([h(3, t) + p(d) for t, d in STEPS], 'mb-how__steps'), 'mb-how')
                    + buttons([('Обсудить задачу за 30 минут', '#contact', False)], 'mb-how__actions')
                    + p('<a class="mb-btn mb-btn--link" href="/kak-rabotaem/">Подробнее о формате работы</a>', 'mb-section__after'))
    regular = section(head('Продолжаем развивать процессы после запуска')
                      + columns([p('Внедрение — один из этапов работы. После запуска важно разбирать новые вопросы, поддерживать актуальность настроек и выбирать следующие улучшения.'),
                                 p('В ежемесячном формате согласуем приоритеты с руководителем, планируем изменения, внедряем их и проверяем использование. Объём работ, участие специалистов и ритм встреч определяем в рамках выбранного пакета и договорённостей с компанией.')], 'mb-regular__text')
                      + raw('<ol class="mb-cycle" aria-label="Порядок регулярной работы"><li>Обсуждаем проблемы</li><li>Выбираем приоритет</li><li>Внедряем</li><li>Проверяем</li><li>Планируем следующий шаг</li></ol>')
                      + p('Задачи и встречи выполняются в пределах часов выбранного пакета. Почасовой формат доступен отдельно, без ежемесячного пакета.', 'mb-small')
                      + p('<a class="mb-btn mb-btn--link" href="/kak-rabotaem/#regular-development">Как устроено сотрудничество</a>', 'mb-section__after'))
    results = section(head('Что проверяем вместе с вами') + columns([h(3, t) + p(d) for t, d in RESULTS], 'mb-results'), 'mb-section--surface')
    projects = section(head('Как это работает на практике', 'Задачи компаний, внесённые изменения и работа решений в реальных процессах.')
                       + sc('[maxbit_projects_cards ids="gk-vpk,lentochka,paritet-alyans"]')
                       + p('<a class="mb-btn mb-btn--link" href="/proekty/">Все проекты</a>', 'mb-section__after'), 'mb-section--surface', 'projects')
    reviews = section(head('Отзывы клиентов') + sc('[maxbit_reviews initial="3"]'), '', 'reviews')
    practice = section(head('Материалы для самостоятельного разбора', 'Статьи и чек-листы о процессах, данных и цифровых инструментах. Их можно использовать в работе самостоятельно.')
                       + sc('[maxbit_practice_cards diagnostic="1"]')
                       + sc('[maxbit_quiz collapsed="1"]')
                       + p('<a class="mb-btn mb-btn--link" href="/praktika/">Все материалы</a>', 'mb-section__after'), 'mb-section--magnets', 'materials')
    faqs = section(faq(FAQ, anchor='faq'))
    contact = section(form(id='contact', purposes='1', heading='Обсудим вашу задачу за 30 минут', text=CONTACT_TEXT), 'mb-section--contact')
    # Блок «Что проверяем вместе с вами» (results) убран с главной по решению владельца 26.10.
    return hero + sit + dirs + steps + regular + projects + reviews + practice + faqs + contact

# ------------------------------------------------------------------ «Как работаем» (§4)
def how():
    s = tz_section('## 4. «Как работаем»: готовый текст страницы', '## 5. Компания, основатель и каталог решений')
    for frag in ['Первая встреча длится примерно 30 минут и бесплатна.', 'Основной формат — ежемесячная работа на согласованную сумму.', 'Время ответа в чате по тарифу действует в рабочее время по МСК.']:
        assert frag in s
    c = p('MAXBIT — внешняя команда цифрового развития производственных компаний. Помогаем разобраться в рабочей ситуации, выбрать изменения и довести их до использования сотрудниками.', 'mb-lead')
    c += h(2, 'Сначала знакомимся с задачей')
    c += p('Первая встреча длится примерно 30 минут и бесплатна. Вы описываете одну конкретную проблему, действующие программы и ограничения. Вместе определяем, с какого участка имеет смысл начать. Подводим краткий итог и предлагаем первый шаг.')
    c += p('Для начала достаточно примера из вашей работы: заказа, документа, передачи между подразделениями или повторяющегося ручного действия. Подробный аудит до договора не требуется.')
    c += h(2, 'Выбираем формат сотрудничества')
    c += p('Основной формат — ежемесячная работа на согласованную сумму. Определяем пакет, приоритеты, участников и критерии результата. Анализ, проектирование и первые улучшения выполняются внутри сотрудничества. Отдельная платная диагностика не является обязательным этапом.')
    c += p('Если ежемесячный пакет не подходит, можно работать по часам с оплатой фактически затраченного времени. Для задачи с определёнными границами также возможен отдельный проект. Выбор зависит от ситуации компании и состава работ.')
    c += h(2, 'Согласуем задачи и ответственность')
    c += p('Фиксируем, что требует внимания в первую очередь, кто принимает решения со стороны компании и какие материалы нужны для работы. Для изменений определяем критерии готовности и способ проверки.')
    c += p('Число задач и встреч связано с общим объёмом часов пакета. Если появляются новые приоритеты или требуется больше времени, обсуждаем изменение плана и условий до выполнения дополнительных работ.')
    c += h(2, 'Разбираем процесс и внедряем изменения')
    c += p('Изучаем реальные рабочие ситуации вместе с участниками. Уточняем этапы, данные, правила согласования и передачи информации. Затем выбираем необходимые настройки, интеграции или доработки.')
    c += p('Битрикс24, 1С, готовые сервисы и собственные приложения — инструменты реализации. Сначала проверяем возможности того, что уже используется в компании. Состав изменений определяется задачей.')
    c += h(2, 'Проверяем работу вместе с сотрудниками')
    c += p('Проверяем согласованные сценарии на рабочих примерах, обучаем участников и передаём правила работы. Смотрим, используется ли решение в ежедневных действиях. Замечания и причины возврата к ручным операциям разбираем вместе.')
    c += p('Если оцениваем измеримые эффекты, заранее определяем исходные данные, показатели и период сравнения. Не обещаем изменение показателей, зависящих от действий компании и внешних обстоятельств.')
    c += h(2, 'Продолжаем развивать процессы после запуска', anchor='regular-development')
    c += p('В регулярном формате обсуждаем с руководителем новые проблемы, выбираем приоритеты и планируем следующий объём работ. Ритм встреч и участие специалистов согласуются с учётом пакета и текущих задач.')
    c += p('Время ответа в чате по тарифу действует в рабочее время по МСК. Это время ответа, а не обещание решить любую задачу за тот же срок.')
    c += h(2, 'Что нужно от компании')
    c += ul(['Ответственный, который может принимать решения по процессу.', 'Примеры реальной работы и необходимые доступы через согласованный безопасный способ.', 'Участие сотрудников, которые будут пользоваться изменениями.', 'Согласованные приоритеты и обратная связь по результатам проверки.'])
    c += sc('[maxbit_projects_cards ids="gk-vpk,paritet-alyans,cupol365,ngsk" heading="Примеры проектов" columns="2"]')
    c += h(2, 'Как начать')
    c += p('Расскажите об одной ситуации, которую хотите изменить. На бесплатной встрече примерно на 30 минут обсудим её и предложим следующий шаг.')
    c += form(id='contact', purposes='1', heading='Обсудить задачу за 30 минут', text='Опишите одну ситуацию — обсудим её на бесплатной встрече примерно на 30 минут.')
    return c

# ------------------------------------------------------------------ «О компании» (§5.1)
def about():
    c = p('MAXBIT — внешняя команда цифрового развития производственных компаний. Помогаем совершенствовать бизнес-процессы, сокращать ручную работу и связывать цифровые инструменты с ежедневной работой сотрудников.', 'mb-lead')
    c += p('Сначала разбираемся в задаче бизнеса: как устроен процесс, кто участвует и где возникают потери времени, информации или ответственности. Затем выбираем подходящие решения, внедряем изменения и проверяем их использование.')
    c += p('Битрикс24, 1С, интеграции и собственные приложения рассматриваем как инструменты. Состав решения определяется задачей и возможностями существующих систем.')
    c += h(2, 'С чем работаем')
    c += p('Заказы и исполнение, взаимодействие подразделений, согласование документов, повторяющиеся ручные операции и обмен данными между программами. Начинаем с конкретного участка, а последующие изменения определяем по результатам работы.')
    c += p('Подробные описания подготовлены для <a href="/resheniya/zakaz-pod-kontrolem/">управления производственными заказами</a>, <a href="/tekhnologii/bitrix24-dlya-proizvodstva/">Битрикс24 для производства</a> и <a href="/resheniya/integraciya-bitrix24-i-1c/">интеграции Битрикс24 с 1С</a>.')
    c += h(2, 'Формат сотрудничества')
    c += p('Основной формат — ежемесячная работа по согласованным приоритетам. Анализируем процессы, проектируем и внедряем улучшения, помогаем сотрудникам использовать изменения. Для клиентов, которым не подходит ежемесячный пакет, есть почасовая работа; отдельные проекты согласуются по задаче.')
    c += h(2, 'Команда и руководство')
    c += p('MAXBIT развивается под руководством основателя Максима Кизимова. Он участвует в определении ключевых задач, выборе подхода и контроле результата. Для реализации привлекаются специалисты с компетенциями, необходимыми для согласованного объёма работ.')
    c += p('<a class="mb-btn mb-btn--link" href="/osnovatel/">Профиль основателя</a>')
    c += h(2, 'Как проверяем результат')
    c += p('Перед началом согласуем задачу, ответственность и критерии готовности. Проверяем рабочие сценарии, использование решения сотрудниками и необходимые материалы. Измеримые изменения показываем на основе подтверждённых данных.')
    c += h(2, 'Контакты')
    c += p('Обсудить задачу или получить коммерческое предложение можно через форму, в Telegram или MAX. Работаем с клиентами по всей России.')
    c += sc('[maxbit_messengers kind="bots"]')
    c += form(id='contact', purposes='1', heading='Обсудим вашу задачу за 30 минут', text=CONTACT_TEXT)
    for frag in ['Подробные описания подготовлены для', 'Для реализации привлекаются специалисты']:
        assert frag in TZ
    return c

# ------------------------------------------------------------------ обзор Битрикс24 (§19)
def b24():
    pt = open(f'{SRC}/pages/bitrix24/PUBLIC_TEXT.txt', encoding='utf-8').read()
    feats = [
        ('mb-b24-crm', 'CRM и продажи'), ('mb-b24-tasks', 'Задачи и проекты'), ('mb-b24-team', 'Мессенджер и видеозвонки'),
        ('mb-b24-office', 'Онлайн-офис и знания'), ('mb-b24-automation', 'Автоматизация процессов'), ('mb-b24-integrations', 'Интеграции и обмен данными'),
    ]
    blocks = [b.strip() for b in pt.split('\n\n') if b.strip()]
    def feature(title):
        i = blocks.index(title)
        lead = blocks[i + 1]
        items = [x[2:].strip() for x in blocks[i + 2].split('\n') if x.startswith('- ')]
        prac = blocks[i + 3]
        assert prac.startswith('На практике:'), prac
        return lead, items, prac[len('На практике:'):].strip()
    cards = ''
    for aid, t in feats:
        lead, items, prac = feature(t)
        li = ''.join(f'<li>{E(x)}</li>' for x in items)
        cards += f'<article class="mb-b24-card" id="{aid}"><h3>{E(t)}</h3><p>{E(lead)}</p><ul>{li}</ul><p class="mb-b24-card__practice"><strong>На практике:</strong> {E(prac)}</p></article>'
    feat_url, create_url = REF['website']['href'], REF['create_portal']['href']
    assert 'p=2468129' in feat_url and 'p=2468129' in create_url
    ai = []
    for t in ['ИИ и BitrixGPT', 'Битрикс24 Вайбкод — AI Beta']:
        i = blocks.index(t)
        lead = blocks[i + 1]
        items = [x[2:].strip() for x in blocks[i + 2].split('\n') if x.startswith('- ')]
        note = blocks[i + 3]
        title = t.replace(' — AI Beta', '') + (' <span class="mb-tag">AI Beta</span>' if 'Beta' in t else '')
        ai.append(f'<article class="mb-b24-ai__card"><h3>{title}</h3><p>{E(lead)}</p><ul>{"".join(f"<li>{E(x)}</li>" for x in items)}</ul><p class="mb-small">{E(note)}</p></article>')
    more = ['Онлайн-запись', 'Складской учёт', 'Аналитика', 'Кадровый ЭДО', 'Электронная подпись', 'Мобильное приложение']
    more_html = ''.join(f'<div class="mb-b24-more__item"><h3>{t}</h3><p>{E(blocks[blocks.index(t) + 1])}</p></div>' for t in more)
    cloud = blocks[blocks.index('Облачная версия') + 1]
    box = blocks[blocks.index('Коробочная версия') + 1]
    assert 'Встроенный искусственный интеллект помогает в работе'.upper() in pt
    c = section(
        p('Обзор продукта', 'mb-eyebrow') + h(1, 'Битрикс24 для работы компании')
        + p(E(blocks[0]), 'mb-hero__lead')
        + buttons([('Обсудить внедрение', '#mb-b24-contact', False), ('Посмотреть возможности', '#mb-b24-features', True)], 'mb-hero__actions'),
        'mb-b24-hero')
    c += section(head('Инструменты для ежедневной работы', 'Можно начать с одного отдела или процесса и подключать остальные инструменты по мере необходимости.')
                 + raw(f'<div class="mb-b24-grid">{cards}</div>')
                 + raw(f'<div class="mb-b24-after"><p class="mb-small">Состав инструментов и ограничения зависят от версии, тарифа, подписок и подключённых приложений. Часть интеграций требует отдельной настройки и оплаты.</p><a class="mb-btn mb-btn--secondary" href="{A(feat_url)}" target="_blank" rel="noopener sponsored" data-event="bitrix24_features_click">Все возможности Битрикс24</a></div>'),
                 'mb-section--surface', 'mb-b24-features')
    c += section(head('Встроенный искусственный интеллект помогает в работе', 'Помогает написать текст, составить задачу, расшифровать звонок и перенести данные разговора в CRM.')
                 + raw(f'<div class="mb-b24-ai">{"".join(ai)}</div>'), 'mb-section--dark', 'mb-b24-ai')
    c += section(head('Для отдельных задач компании') + raw(f'<div class="mb-b24-more">{more_html}</div>'), '', 'mb-b24-more')
    c += section(head('Облако или коробка')
                 + raw(f'<div class="mb-b24-versions"><article class="mb-b24-version"><h3>Облачная версия</h3><p>{E(cloud)}</p><div class="mb-b24-version__action"><a class="mb-btn mb-btn--primary" href="{A(create_url)}" target="_blank" rel="noopener sponsored" data-event="bitrix24_create_click">Создать портал Битрикс24</a><p class="mb-small">Ссылка ведёт на сайт Битрикс24 и создаёт новый облачный портал. Это не заявка MAXBIT.</p></div></article><article class="mb-b24-version"><h3>Коробочная версия</h3><p>{E(box)}</p></article></div>')
                 + p('Подходящий вариант выбираем по процессам, требованиям к данным, интеграциям и сопровождению.')
                 + p('Лицензия Битрикс24, дополнительные подписки и услуги MAXBIT рассчитываются отдельно.', 'mb-small')
                 + sc('[maxbit_b24_prices]'), 'mb-section--surface')
    c += section(head('Чем помогает MAXBIT', 'Разбираем рабочий процесс, подбираем инструменты и настраиваем Битрикс24 под задачи компании. Если система уже используется — помогаем доработать её и связать с другими сервисами.')
                 + columns([h(3, 'Разобрать задачу') + p('Процесс, участники и текущие системы.'), h(3, 'Настроить решение') + p('CRM, задачи, автоматизация и интеграции.'), h(3, 'Запустить в работу') + p('Обучение, проверка и дальнейшее сопровождение.')], 'mb-results'))
    c += section(head('Примеры наших проектов') + sc('[maxbit_projects_cards ids="gk-vpk,lentochka,helper-med"]')
                 + p('<a class="mb-btn mb-btn--link" href="/proekty/">Все проекты</a>', 'mb-section__after')
                 + p('Прикладные решения: <a href="/tekhnologii/bitrix24-dlya-proizvodstva/">Битрикс24 для производства</a> · <a href="/resheniya/integraciya-bitrix24-i-1c/">Интеграция Битрикс24 и 1С</a> · <a href="/resheniya/zakaz-pod-kontrolem/">Управление производственными заказами</a>', 'mb-small'),
                 'mb-section--surface', 'mb-b24-projects')
    c += section(form(id='mb-b24-contact', purpose='b24', heading='Обсудим вашу задачу', text='Расскажите, что хотите организовать или улучшить. Оставьте заявку или напишите в Telegram или MAX.'), 'mb-section--contact')
    return c

# ------------------------------------------------------------------ кейсы (§16)
ACR = [('crm', 'CRM'), ('битрикс24', 'Битрикс24'), ('1с', '1С'), ('сбис', 'СБИС'), ('эдо', 'ЭДО'), ('упд', 'УПД'), ('б24', 'Б24'), ('вэд', 'ВЭД'), ('пто', 'ПТО'), ('vin', 'VIN'), ('maxbit', 'MAXBIT'), ('стт', 'СТТ'), ('нгск', 'НГСК'), ('впк', 'ВПК'), ('cupol365', 'Cupol365'), ('китая', 'Китая'), ('хелпер-мед', 'ХЕЛПЕР-МЕД')]
def sentence_case(s):
    s = s[0] + s[1:].lower()
    for a, b in ACR:
        s = re.sub(r'(?<![\w-])' + re.escape(a) + r'(?![\w-])', b, s)
    return s

CASE_FACTS = {
    'gk-vpk': ('Продажи · производство · сервис', 'Битрикс24 · 1С'),
    'lentochka': ('Заказы · счета · документы', 'Битрикс24 · 1С Бухгалтерия · СБИС ЭДО'),
    'paritet-alyans': ('Продажи · закупка · логистика', 'Битрикс24'),
    'avtospeccentr': ('ПТО · производство · логистика', 'Битрикс24'),
    'cupol365': ('Каталог · документы · производство', 'Битрикс24'),
    'helper-med': ('Заказы · спецификации · задачи', 'Битрикс24'),
    'ngsk': ('Техприсоединение · службы · платежи', 'Битрикс24'),
}
CASE_TAGS = {'gk-vpk': ['Битрикс24', '1С'], 'lentochka': ['Битрикс24', '1С', 'СБИС'], 'paritet-alyans': ['Битрикс24'], 'avtospeccentr': ['Битрикс24'], 'cupol365': ['Битрикс24'], 'helper-med': ['Битрикс24'], 'ngsk': ['Битрикс24']}

def case_body(cid):
    raw_text = open(f'{SRC}/cases/{cid}/PUBLIC_TEXT.txt', encoding='utf-8').read().strip()
    blocks = [b.strip() for b in raw_text.split('\n\n') if b.strip()]
    out = ''
    lead = None
    for i, b in enumerate(blocks):
        nxt = blocks[i + 1] if i + 1 < len(blocks) else ''
        if re.fullmatch(r'[A-ZА-ЯЁ0-9 ,:;—«»\-\.()/]+', b) and len(b) < 90 and b.upper() == b:
            out += h(2, E(sentence_case(b)))
        elif all(l.startswith('— ') for l in b.split('\n')):
            out += ul([E(l[2:].strip()) for l in b.split('\n')], 'mb-case-list')
        elif len(b) < 70 and not re.search(r'[.!?:;»]$', b) and '\n' not in b and nxt and not nxt.isupper():
            out += h(3, E(b))
        elif re.search(r':\s*«', b) and ('лиент' in b or b.startswith('Сотрудник')):
            out += p(E(b), 'mb-case-quote')
        else:
            if lead is None and not out:
                lead = b
                out += p(E(b), 'mb-lead')
            else:
                out += p(E(b).replace('\n', '<br>'))
    return out

def cases():
    res = []
    for n, c in enumerate(CASES):
        cid = c['id']
        body = case_body(cid)
        assert '<h1' not in body
        res.append({
            'slug': cid, 'title': c['h1'], 'excerpt': c['card_text'], 'content': body, 'menu_order': n + 1,
            'meta': {
                '_mb_pf_client': c['client'], '_mb_card_title': c['card_title'], '_mb_card_text': c['card_text'],
                '_mb_case_processes': CASE_FACTS[cid][0], '_mb_case_systems': CASE_FACTS[cid][1], '_mb_case_tags': ','.join(CASE_TAGS[cid]),
                '_mb_case_links': json.dumps(c['links'], ensure_ascii=False), '_mb_seo_title': c['title'], '_mb_seo_description': c['description'],
                '_mb_public_ok': '1', '_mb_production_approved': '0',
            },
        })
    return res

# ------------------------------------------------------------------ КП (§9) — тексты из ТЗ, цифры из tariffs-2026.json
def kp():
    s = tz_section('## 9. Страница КП: готовое содержание и точные тарифы', '## 10. Страница презентации MAXBIT')
    sub = re.search(r'\*\*Подзаголовок:\*\* (.+)', s).group(1).strip()
    intro = re.search(r'\*\*Вводный текст:\*\* (.+)', s).group(1).strip()
    P = TAR['packages']
    # Цены сверяются по отпечатку (сами значения в публичный репозиторий не попадают); значения — в tariffs-2026.json комплекта.
    assert hashlib.sha256(repr([x['price_rub_per_month'] for x in P]).encode()).hexdigest() == '89fe5575b053b7fb5bf3f97e9799b1f9d8a3ebfff5a6b6579e9847e20ac1d5a0'
    assert [x['total_hours'] for x in P] == [1, 4, 8, 16, 32, 64]
    assert [x['chat_response_minutes'] for x in P] == [120, 120, 60, 60, 60, 30]
    yn = lambda v: 'Да' if v else 'Нет'
    rows = [
        ('Стоимость, ₽/мес.', [ru_money(x['price_rub_per_month']) for x in P]),
        ('Общие часы', [str(x['total_hours']) for x in P]),
        ('Встречи', [str(x['meetings_count_limit']) if x['meetings_count_limit'] is not None else 'В пределах часов' for x in P]),
        ('Задачи', [str(x['tasks_count_limit']) if x['tasks_count_limit'] is not None else 'В пределах часов' for x in P]),
        ('Ответ в чате, минут, в рабочее время МСК', [str(x['chat_response_minutes']) for x in P]),
        ('Обучение сотрудников', [yn(x['employee_training']) for x in P]),
        ('Внешние подрядчики клиента', [yn(x['client_external_contractors']) for x in P]),
        ('Регламент и карта проекта', [yn(x['project_regulations_and_map']) for x in P]),
        ('Контроль правильной работы в системе', [yn(x['correct_system_usage_control']) for x in P]),
    ]
    assert rows[2][1] == ['1', '2', '4', 'В пределах часов', 'В пределах часов', 'В пределах часов']
    assert rows[3][1] == ['0', '4', '8', 'В пределах часов', 'В пределах часов', 'В пределах часов']
    cards = ''
    for i, x in enumerate(P):
        m = re.search(r'\*\*' + x['name'] + r' — [\d\s]+ ₽/мес\.\*\*\n\n(.+?)\n\n(Команда: .+?)\n', s, re.S)
        desc, team = m.group(1).strip(), m.group(2).strip()
        det = ''.join(f'<div><dt>{E(lab)}</dt><dd>{E(vals[i])}</dd></div>' for lab, vals in rows[1:])
        cards += (f'<article class="mb-pack"><p class="mb-pack__name">{x["name"]}</p><p class="mb-pack__price">{ru_money(x["price_rub_per_month"])} <span>₽/мес.</span></p>'
                  f'<p class="mb-pack__meta">{x["total_hours"]} ч в пакете · ответ в чате {x["chat_response_minutes"]} мин в рабочее время МСК</p>'
                  f'<p>{E(desc)}</p><details class="mb-pack__more"><summary>Подробнее</summary><p class="mb-small">{E(team)}</p><dl>{det}</dl></details></article>')
    th = ''.join(f'<th scope="col">{x["name"]}</th>' for x in P)
    tb = ''.join(f'<tr><th scope="row">{E(lab)}</th>' + ''.join(f'<td>{E(v)}</td>' for v in vals) + '</tr>' for lab, vals in rows)
    note = re.search(r'\*\*Пояснение под пакетами:\*\*\n\n(.+?)\n\n', s, re.S).group(1).strip()
    tm_text = re.search(r'\*\*Заголовок:\*\* Почасовая работа без ежемесячного пакета\n\n\*\*Текст:\*\* (.+?)\n', s).group(1).strip()
    assert '- Объём работ — от 10 часов.' in s and '- Стоимость — 50 000 ₽.' in s
    choose1 = re.search(r'\*\*Заголовок:\*\* Как выбрать формат\n\n(.+?)\n\n(.+?)\n', s, re.S)
    terms = 'Состав работ, условия оплаты и порядок взаимодействия фиксируем перед началом сотрудничества.'
    assert terms[:-1] in s
    c = section(p('Коммерческое предложение', 'mb-eyebrow') + h(1, 'Коммерческое предложение MAXBIT') + p(E(sub), 'mb-hero__lead') + p(E(intro))
                + buttons([('Обсудить подходящий формат', '#contact', False)], 'mb-hero__actions'), 'mb-mat-hero')
    c += section(head('Пакеты ежемесячной работы') + raw(f'<div class="mb-packs">{cards}</div>'), 'mb-section--surface')
    c += section(head('Сравнение пакетов') + raw(f'<div class="mb-packs-table"><table><thead><tr><th scope="col">Параметр</th>{th}</tr></thead><tbody>{tb}</tbody></table></div>') + p(E(note), 'mb-small'))
    c += section(head('Почасовая работа без ежемесячного пакета', E(tm_text))
                 + raw('<div class="mb-tm"><div><p class="mb-tm__label">Объём работ</p><p class="mb-tm__value">от 10 часов</p></div><div><p class="mb-tm__label">Стоимость</p><p class="mb-tm__value">50 000 ₽</p></div></div>')
                 + raw('<p><a class="mb-btn mb-btn--primary" href="#contact" data-mb-purpose="hourly">Обсудить почасовую работу</a></p>'), 'mb-section--dark')
    c += section(head('Как выбрать формат') + p(E(choose1.group(1).strip())) + p(E(choose1.group(2).strip()) + ' ' + terms))
    return {'title': 'Коммерческое предложение MAXBIT', 'content': c, 'purpose': 'tariff', 'source': 'kp'}

# ------------------------------------------------------------------ Презентация (§10)
def pres():
    s = tz_section('## 10. Страница презентации MAXBIT: готовая структура и тексты', '## 11. Формы, тестовый режим')
    skip = ('Не добавлять', 'На dev', 'До получения', 'Кнопка:')
    screens = re.split(r'\n### Экран (\d)\. (.+)\n', s)[1:]
    out = ''
    vpk = next(c for c in CASES if c['id'] == 'gk-vpk')
    for k in range(0, len(screens), 3):
        num, label, body = screens[k], screens[k + 1].strip(), screens[k + 2]
        if num == '6':
            heading = 'Реальный проект: ГК ВПК'
            inner = p(E(vpk['card_title']), 'mb-lead') + p(E(vpk['card_text'])) + p('<a class="mb-btn mb-btn--link" href="/proekty/gk-vpk/">Посмотреть проект</a>')
        else:
            heading = re.search(r'\*\*(.+?)\*\*', body).group(1)
            lines = [l.strip() for l in body.split('\n') if l.strip() and not l.startswith('**') and not l.startswith(skip)]
            inner = ''
            items, ol = [], []
            for l in lines:
                if l.startswith('- '):
                    items.append(E(l[2:]))
                elif re.match(r'\d\. ', l):
                    ol.append(E(l[3:]))
                else:
                    if items:
                        inner += ul(items); items = []
                    inner += p(E(l))
            if items:
                inner += ul(items)
            if ol:
                inner = raw('<ol class="mb-pres__steps">' + ''.join(f'<li>{x}</li>' for x in ol) + '</ol>') + inner
        dark = num in ('1', '5', '8')
        cls = 'mb-pres-screen' + (' mb-section--dark' if dark else (' mb-section--surface' if num in ('3', '7') else ''))
        head_html = p(f'{int(num):02d} · {E(label)}', 'mb-eyebrow') + (h(1, E(heading)) if num == '1' else h(2, E(heading)))
        if num == '8':
            inner += buttons([('Обсудить задачу', '#contact', False)], 'mb-hero__actions') + sc('[maxbit_messengers kind="bots"]')
        out += section(columns([head_html, inner], 'mb-pres__cols'), cls)
    assert out.count('<h1') == 1
    return {'title': 'Презентация MAXBIT', 'content': out, 'purpose': 'discuss', 'source': 'presentation'}

# ------------------------------------------------------------------ точечные правки (§5.2, §6)
START_ZAKAZ = 'Выберите заказ или участок, где теряются данные, договорённости или ответственность. На бесплатной встрече примерно на 30 минут обсудим ситуацию и возможный следующий шаг. Изменения можно выполнять в ежемесячном формате, по часам или отдельным проектом — в зависимости от задачи.'
START_B24 = 'Расскажите, что уже настроено в Битрикс24 и что мешает работе сотрудников. На бесплатной встрече примерно на 30 минут обсудим приоритетный участок. Предложим подходящий формат: регулярное развитие, почасовую работу или отдельный проект внедрения.'
START_INT = 'Покажите, какие сведения сейчас переносятся вручную между Битрикс24 и 1С. На бесплатной встрече примерно на 30 минут обсудим рабочий сценарий и ограничения. Объём интеграции и формат работы определим после уточнения задачи.'
PRICE_FAQ = 'Стоимость зависит от формата и объёма задач. Для регулярной работы есть ежемесячные пакеты, для почасового формата согласуем работы и оценку времени. Коммерческое предложение с тарифами отправляем по ссылке после заявки.'
for t in (START_ZAKAZ, START_B24, START_INT):
    assert t[:-1] + "»" in TZ, t[:40]

def related(ids, heading='Проекты по теме'):
    return {'op': 'insert_before_form', 'html': sc(f'[maxbit_projects_cards ids="{ids}" heading="{heading}"]'), 'guard': 'maxbit_projects_cards'}

TARGETED = [
    {'path': 'resheniya/zakaz-pod-kontrolem', 'type': 'mb_solution', 'excerpt': DIRS[0][1], 'ops': [
        {'op': 'para', 'start': 'Когда заказ проходит через продажи', 'new': 'Наводим порядок в заказах и исполнении: что нужно сделать, по какой версии договорённостей, к какому сроку и кто отвечает. Проектируем путь заказа от заявки до отгрузки и оплаты и настраиваем необходимые правила в используемых системах.'},
        {'op': 'para', 'start': 'С одного заказа или проблемного участка', 'new': START_ZAKAZ},
        {'op': 'para', 'start': 'Зависит от размера участка и состояния систем', 'new': PRICE_FAQ},
        {'op': 'form_text', 'new': 'Расскажите, где сейчас теряются данные, договорённости или ответственность. Обсудим ситуацию на бесплатной встрече примерно на 30 минут.'},
        related('gk-vpk,avtospeccentr,helper-med,cupol365,lentochka'),
    ]},
    {'path': 'tekhnologii/bitrix24-dlya-proizvodstva', 'type': 'page', 'excerpt': DIRS[1][1], 'ops': [
        {'op': 'replace', 'find': 'Чаще всего это дешевле и быстрее нового внедрения.', 'new': 'Сравниваем варианты доработки и нового внедрения с учётом текущих настроек, ограничений и состава задачи. Сохраняем то, что работает, а необходимые изменения согласуем отдельно.'},
        {'op': 'para', 'start': 'Зависит от редакции, числа процессов и участников', 'new': PRICE_FAQ},
        {'op': 'replace', 'find': 'Состав работ и сроки определяются после разбора: они зависят от редакции, объёма настройки и числа участников.', 'new': 'Состав работ и сроки зависят от редакции, объёма настройки и числа участников; их согласуем в выбранном формате работы.'},
        {'op': 'replace', 'find': 'Точный состав фиксируем в предложении после разбора — он зависит от вашей ситуации.', 'new': 'Точный состав фиксируем перед началом работ — он зависит от вашей ситуации.'},
        {'op': 'form_text', 'new': START_B24, 'heading': 'С чего начать'},
        {'op': 'insert_before_form', 'html': p('Обзор возможностей системы — на странице <a href="/bitrix24/">Битрикс24: возможности и внедрение</a>.'), 'guard': 'href="/bitrix24/"'},
        related('gk-vpk,avtospeccentr,helper-med,cupol365'),
    ]},
    {'path': 'resheniya/integraciya-bitrix24-i-1c', 'type': 'mb_solution', 'excerpt': DIRS[2][1], 'ops': [
        {'op': 'para', 'start': 'Менеджеры работают в Битрикс24, бухгалтерия и склад', 'new': 'Определяем, какие данные нужны участникам процесса, где они создаются и как передаются между системами. Настраиваем согласованные обмены, чтобы сократить повторный ввод и сделать необходимую информацию доступной в работе.'},
        {'op': 'replace', 'find': '>Интеграция Битрикс24 и 1С для производственной компании</h1>', 'new': '>Интеграция Битрикс24 и 1С</h1>'},
        {'op': 'form_text', 'new': START_INT, 'heading': 'С чего начать'},
        related('lentochka,gk-vpk'),
    ]},
    {'path': 'osnovatel', 'type': 'page', 'ops': [
        {'op': 'para', 'start': 'Основатель MAXBIT. Участвует', 'new': 'Основатель MAXBIT. Участвует в определении задач, выборе подхода и контроле результата в работе с производственными компаниями.'},
        {'op': 'replace', 'find': '<strong>Сначала один заказ, потом вся система.</strong> Один проблемный участок доводится до работающего состояния, затем следующий.', 'new': '<strong>Сначала конкретный процесс.</strong> Начинаем с ограниченного участка и проверяем изменения в работе, затем определяем следующий шаг.'},
        {'op': 'replace', 'find': '>Материалы автора<', 'new': '>Материалы MAXBIT<'},
    ]},
]

# ------------------------------------------------------------------ SEO (все 20 страниц)
def seo():
    out = {}
    for pg in SEO['public_pages']:
        out[pg['url']] = {'title': pg['title'], 'description': pg['description'], 'h1': pg['h1']}
    assert len(out) == 20, len(out)
    return out

# ------------------------------------------------------------------ отзывы
def reviews():
    items = []
    for r in REV['reviews']:
        docs = [{'title': 'Отзыв' if 'Благодар' not in r['source'] else 'Благодарственное письмо', 'full': os.path.basename(r['assets']['full']), 'thumb': os.path.basename(r['assets']['thumbnail'])}]
        for d in r.get('additional_documents') or []:
            docs.append({'title': d['title'], 'full': os.path.basename(d['assets']['full']), 'thumb': os.path.basename(d['assets']['thumbnail'])})
        items.append({'id': r['id'], 'client': r['client'], 'topic': r['topic'], 'date': r.get('date'), 'docs': docs})
    assert len(items) == 9 and sum(len(i['docs']) for i in items) == 10
    return items

def build():
    data = {
        'version': 'v4-2026-10-26',
        'settings': {'tg_bot_url': CONTACTS['telegram_bot'], 'max_bot_url': CONTACTS['max_bot'], 'tg_channel_url': CONTACTS['telegram_channel'], 'max_channel_url': CONTACTS['max_channel'], 'email': CONTACTS['email'], 'email_verified': '0', 'phone': ''},
        'pages': {
            'home': {'front': True, 'content': home()},
            'kak-rabotaem': {'path': 'kak-rabotaem', 'title': 'Как работаем: от задачи к регулярным улучшениям', 'content': how()},
            'o-kompanii': {'path': 'o-kompanii', 'title': 'О компании MAXBIT', 'content': about()},
            'bitrix24': {'path': 'bitrix24', 'create': True, 'title': 'Битрикс24 для работы компании', 'template': 'template-landing.php', 'content': b24(), 'excerpt': 'CRM, задачи, общение, документы, автоматизация и ИИ. Как MAXBIT помогает внедрить систему, настроить процессы и интеграции.'},
        },
        'targeted': TARGETED,
        'seo': seo(),
        'projects': cases(),
        'materials': {'kp': kp(), 'prezentaciya': pres()},
        'reviews': reviews(),
        'menu': [('Решения', '/resheniya/'), ('Как работаем', '/kak-rabotaem/'), ('Проекты', '/proekty/'), ('Практика', '/praktika/'), ('О компании', '/o-kompanii/')],
    }
    for t in ('home', 'kak-rabotaem', 'o-kompanii'):
        assert 'depbz' not in data['pages'][t]['content'] and '+7' not in data['pages'][t]['content']
    blob = json.dumps(data, ensure_ascii=False, indent=1)
    for bad in ('info@depbz.ru', '923-888', 'digital for business'):
        assert bad not in blob, bad
    zbuf = io.BytesIO()
    with zipfile.ZipFile(zbuf, 'w', zipfile.ZIP_DEFLATED) as z:
        z.writestr('content.json', blob)
        for it in data['reviews']:
            for d in it['docs']:
                for f in (d['full'], d['thumb']):
                    z.write(f'{SRC}/reviews/site/{f}', f'reviews/{f}')
    plain = zbuf.getvalue()
    open(f'{OUT}/content-v4.zip', 'wb').write(plain)
    key = os.urandom(32)
    nonce = os.urandom(12)
    ct = AESGCM(key).encrypt(nonce, plain, b'maxbit-content-v4')
    enc = b'MBX4' + nonce + ct
    open(f'{OUT}/content-v4.bin', 'wb').write(enc)
    open(f'{OUT}/content-v4.key', 'w').write(base64.b64encode(key).decode())
    print('json', len(blob), 'zip', len(plain), 'bin', len(enc), 'sha256', hashlib.sha256(enc).hexdigest())

build()
