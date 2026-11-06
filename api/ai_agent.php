<?php
/**
 * LOPAL KAZAKHSTAN (lopal.kz) — DeepSeek AI Content Management Agent
 * Обрабатывает текстовые и голосовые команды из админ-панели,
 * автоматически вызывает функции CMS (новости, каталог, дилеры, контакты, правка HTML-страниц, бэкапы)
 * и пересобирает статические страницы сайта.
 */

session_start();
require_once dirname(__DIR__) . '/cms/builder.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Проверка авторизации администратора (сессия админки или токен)
$isAuthed = !empty($_SESSION['lopal_admin_logged']);
if (!$isAuthed) {
    $hdrKey = $_SERVER['HTTP_X_API_KEY'] ?? ($_GET['api_key'] ?? '');
    if ($hdrKey === LOPAL_MCP_API_KEY) {
        $isAuthed = true;
    }
}

if (!$isAuthed) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'error' => 'Сессия истекла. Пожалуйста, перезагрузите страницу админ-панели и войдите снова.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$action = $payload['action'] ?? 'chat';

/**
 * 1. Сохранение API-ключа DeepSeek
 */
if ($action === 'save_key') {
    $apiKey = trim($payload['api_key'] ?? '');
    $settings = lopal_read_json('settings');
    $settings['deepseek_api_key'] = $apiKey;
    lopal_write_json('settings', $settings, 'save_deepseek_key');
    echo json_encode([
        'ok' => true,
        'hasKey' => ($apiKey !== ''),
        'maskedKey' => $apiKey !== '' ? (substr($apiKey, 0, 5) . '...' . substr($apiKey, -4)) : '',
        'model' => $settings['deepseek_model'] ?? 'deepseek-chat'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 2. Сохранение выбранной модели DeepSeek
 */
if ($action === 'save_model') {
    $model = trim($payload['model'] ?? 'deepseek-flash');
    $allowedModels = ['deepseek-flash', 'deepseek-chat', 'deepseek-reasoner'];
    if (!in_array($model, $allowedModels)) {
        $model = 'deepseek-flash';
    }
    $settings = lopal_read_json('settings');
    $settings['deepseek_model'] = $model;
    lopal_write_json('settings', $settings, 'save_deepseek_model');
    echo json_encode([
        'ok' => true,
        'model' => $model,
        'message' => 'Модель успешно переключена на ' . $model
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 3. Проверка статуса ключа и модели
 */
if ($action === 'status') {
    $settings = lopal_read_json('settings');
    $apiKey = trim($settings['deepseek_api_key'] ?? '');
    echo json_encode([
        'ok' => true,
        'hasKey' => ($apiKey !== ''),
        'maskedKey' => $apiKey !== '' ? (substr($apiKey, 0, 5) . '...' . substr($apiKey, -4)) : '',
        'model' => $settings['deepseek_model'] ?? 'deepseek-flash'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Автоматическое геокодирование адреса через Nominatim (OpenStreetMap)
 */
function lopal_geocode_address(string $address): array {
    $query = trim($address);
    if (stripos($query, 'Казахстан') === false) {
        $query = 'Казахстан, ' . $query;
    }
    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . rawurlencode($query);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: LopalKzCMS/1.0 (info@lopal.kz)\r\nAccept-Language: ru\r\n",
            'timeout' => 5
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp) {
        $data = json_decode($resp, true);
        if (!empty($data[0]['lat']) && !empty($data[0]['lon'])) {
            return [(float)$data[0]['lat'], (float)$data[0]['lon']];
        }
    }
    // Фолбэк по городу в адресе
    $cityCoords = [
        'Астана' => [51.1435, 71.4450],
        'Шымкент' => [42.3417, 69.5901],
        'Актау' => [43.6532, 51.1600],
        'Талдыкорган' => [45.0156, 78.3739],
        'Алтай' => [49.7300, 84.2679],
        'Арысь' => [42.4280, 68.8020],
        'Караганда' => [49.8047, 73.1094],
        'Актобе' => [50.2839, 57.1670],
        'Атырау' => [47.0945, 51.9238],
        'Павлодар' => [52.2873, 76.9674],
        'Усть-Каменогорск' => [49.9483, 82.6285],
        'Семей' => [50.4111, 80.2275],
        'Костанай' => [53.2144, 63.6246],
        'Уральск' => [51.2278, 51.3865],
        'Тараз' => [42.9000, 71.3667],
        'Кызылорда' => [44.8488, 65.4823],
        'Кокшетау' => [53.2833, 69.3833],
        'Петропавловск' => [54.8667, 69.1500]
    ];
    foreach ($cityCoords as $city => $coords) {
        if (mb_stripos($address, $city) !== false) {
            return $coords;
        }
    }
    return [43.2550, 76.9120];
}

/**
 * Описание всех инструментов для DeepSeek Function Calling
 */
function lopal_get_ai_tools_schema(): array {
    return [
        [
            'type' => 'function',
            'function' => [
                'name' => 'get_site_overview',
                'description' => 'Получить полную сводку о состоянии сайта lopal.kz: настройки, контакты, график работы, количество новостей, товаров в каталоге, дилеров и список всех HTML-страниц.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new stdClass()
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_news',
                'description' => 'Получить список всех новостей на сайте (с id, заголовками, датами и кратким содержанием).',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new stdClass()
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'upsert_news',
                'description' => 'Опубликовать новую новость или изменить существующую на странице news.html.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['title', 'summary', 'content'],
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'ID новости (оставьте пустым для новой, или укажите существующий id для редактирования)'],
                        'title' => ['type' => 'string', 'description' => 'Заголовок новости'],
                        'date' => ['type' => 'string', 'description' => 'Дата публикации (например: 06 ноября 2026)'],
                        'category' => ['type' => 'string', 'enum' => ['products', 'nev', 'dealers', 'corporate'], 'description' => 'Категория новости'],
                        'badge' => ['type' => 'string', 'description' => 'Короткий бейдж (например: НОВИНКА, ДИЛЕРСКАЯ СЕТЬ, API SQ)'],
                        'image' => ['type' => 'string', 'description' => 'Путь к фото (по умолчанию assets/branding/topmaq-hero-dark.jpg)'],
                        'summary' => ['type' => 'string', 'description' => 'Краткий анонс для карточки (1-2 предложения)'],
                        'content' => ['type' => 'string', 'description' => 'Полный текст статьи (абзацы разделяются переносом строки)']
                    ]
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'delete_news',
                'description' => 'Удалить новость с сайта по её id.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['id'],
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'ID удаляемой новости']
                    ]
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_products',
                'description' => 'Получить список всех товаров (моторных масел, трансмиссионных жидкостей, антифризов) из каталога products.html.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new stdClass()
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'upsert_product',
                'description' => 'Добавить новый продукт в каталог масел или обновить существующий продукт.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['name', 'category', 'description'],
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'ID товара (латиницей, например lopal-1-new, или существующий id)'],
                        'name' => ['type' => 'string', 'description' => 'Полное название продукта (например: LOPAL 1 Ultra ESF FE 0W-20)'],
                        'category' => ['type' => 'string', 'enum' => ['passengers', 'nev', 'commercial', 'transmission', 'coolant'], 'description' => 'Категория каталога'],
                        'badge_text' => ['type' => 'string', 'description' => 'Текст главного бейджа (например: API SQ, HYBRID / EV, API CK-4)'],
                        'specs' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Массив спецификаций и допусков (например: ["SAE 0W-20", "API SQ", "ILSAC GF-7A", "Канистра 4 л"])'
                        ],
                        'description' => ['type' => 'string', 'description' => 'Техническое описание продукта'],
                        'image' => ['type' => 'string', 'description' => 'Путь к изображению (например: assets/products_2026/lopal1-ultra-esf-0w20.png)'],
                        'detail_url' => ['type' => 'string', 'description' => 'Ссылка на детальную страницу (по умолчанию products.html)']
                    ]
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'delete_product',
                'description' => 'Удалить продукт из каталога по его id.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['id'],
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'ID удаляемого товара']
                    ]
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_dealers',
                'description' => 'Получить список всех дилеров, точек продаж и ПЗМ на карте Казахстана.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new stdClass()
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'upsert_dealer',
                'description' => 'Добавить новую точку продаж / СТО / дилера или изменить существующую. Координаты определяются автоматически по адресу, если не переданы.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['name', 'address', 'phone'],
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'ID дилера (оставьте пустым для создания нового, или укажите существующий id для редактирования)'],
                        'city' => ['type' => 'string', 'description' => 'Город с префиксом (например: "г. Алматы", "г. Астана", "г. Шымкент", "г. Караганда")'],
                        'name' => ['type' => 'string', 'description' => 'Название магазина или автосервиса'],
                        'badge' => ['type' => 'string', 'description' => 'Специализация / примечание (например: "Магазин и автосервис", "Автосервис, Магазин, оптовая и розничная торговля")'],
                        'address' => ['type' => 'string', 'description' => 'Полный адрес с указанием города и улицы'],
                        'phone' => ['type' => 'string', 'description' => 'Контактный телефон (или несколько телефонов через запятую)'],
                        'lat' => ['type' => 'number', 'description' => 'Широта (необязательно, определится автоматически по адресу)'],
                        'lng' => ['type' => 'number', 'description' => 'Долгота (необязательно, определится автоматически по адресу)']
                    ]
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'delete_dealer',
                'description' => 'Удалить точку продаж или дилера по его id.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['id'],
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'ID точки продаж']
                    ]
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'update_site_settings',
                'description' => 'Изменить глобальные контакты, телефоны, WhatsApp, email, адрес или график работы сразу на всех страницах сайта.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'company_name' => ['type' => 'string', 'description' => 'Название дистрибьютора (ТОО «Topmaq oil» (TOPMAQ OIL))'],
                        'address' => ['type' => 'string', 'description' => 'Центральный адрес'],
                        'phone_main' => ['type' => 'string', 'description' => 'Основной телефон'],
                        'phone_service' => ['type' => 'string', 'description' => 'Дополнительный телефон (передайте пустую строку "", чтобы удалить второй телефон)'],
                        'email' => ['type' => 'string', 'description' => 'Контактный Email'],
                        'whatsapp' => ['type' => 'string', 'description' => 'Номер WhatsApp (только цифры, например 77075236525)'],
                        'instagram' => ['type' => 'string', 'description' => 'Ник Instagram (например @lopal.kz)'],
                        'schedule_weekdays' => ['type' => 'string', 'description' => 'График Пн–Пт (например: 09:00 — 18:00)'],
                        'schedule_sat' => ['type' => 'string', 'description' => 'График в Субботу (например: Выходной)'],
                        'schedule_sun' => ['type' => 'string', 'description' => 'График в Воскресенье (например: Выходной)']
                    ]
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_html_pages',
                'description' => 'Получить список всех HTML-страниц сайта (index.html, partners.html, about.html, contacts.html и др.) с их заголовками.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new stdClass()
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'read_page_text',
                'description' => 'Прочитать текстовое содержимое или HTML-фрагмент любой страницы сайта перед внесением точечных правок.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['page'],
                    'properties' => [
                        'page' => ['type' => 'string', 'description' => 'Имя файла страницы (например: partners.html, about.html, index.html, contacts.html)'],
                        'search_query' => ['type' => 'string', 'description' => 'Необязательная строка поиска, чтобы вернуть фрагмент кода вокруг нужного текста']
                    ]
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'replace_page_text',
                'description' => 'Заменить фрагмент текста или HTML-кода на выбранной странице (или сразу на всех страницах, если page="ALL"). Автоматически создает резервную копию.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['page', 'search', 'replace'],
                    'properties' => [
                        'page' => ['type' => 'string', 'description' => 'Имя файла (например: partners.html) или "ALL" для замены на всех страницах сайта'],
                        'search' => ['type' => 'string', 'description' => 'Точная строка или фраза, которую нужно найти'],
                        'replace' => ['type' => 'string', 'description' => 'Новый текст или HTML, на который нужно заменить']
                    ]
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'create_page',
                'description' => 'Создать новую полноценную страницу на сайте по единому дизайну LOPAL (с мобильным меню, шапкой, футером, Hero-баннером и контентными блоками) и автоматически добавить её в меню навигации.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['slug', 'title'],
                    'properties' => [
                        'slug' => ['type' => 'string', 'description' => 'Имя файла на латинице (например: "promo", "warranty", "agro", "faq") без .html'],
                        'title' => ['type' => 'string', 'description' => 'Название/заголовок страницы (например: "Условия гарантии и сервиса")'],
                        'subtitle' => ['type' => 'string', 'description' => 'Подзаголовок и краткое описание страницы'],
                        'kicker' => ['type' => 'string', 'description' => 'Верхний бейдж над заголовком (например: "Официальный сервис LOPAL")'],
                        'add_to_menu' => ['type' => 'boolean', 'description' => 'Добавить ли ссылку в главное меню навигации (по умолчанию true)'],
                        'menu_title' => ['type' => 'string', 'description' => 'Короткое название пункта в меню (например: "Гарантия"). Если не указано, используется title.']
                    ]
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'manage_menu',
                'description' => 'Управление пунктами главного меню сайта (список, добавление, скрытие/показ, удаление). Изменения автоматически применяются ко всем страницам сайта.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['action'],
                    'properties' => [
                        'action' => ['type' => 'string', 'enum' => ['list', 'add', 'toggle_visibility', 'delete'], 'description' => 'Действие с меню'],
                        'title' => ['type' => 'string', 'description' => 'Название пункта в меню (для action="add")'],
                        'url' => ['type' => 'string', 'description' => 'URL страницы (например: "promo.html" или "https://...")'],
                        'id' => ['type' => 'string', 'description' => 'ID пункта меню (например: "nav-promo" для toggle_visibility или delete)'],
                        'visible' => ['type' => 'boolean', 'description' => 'Отображать пункт в меню или скрыть (true/false)']
                    ]
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'list_backups',
                'description' => 'Показать список доступных автоматических бэкапов и статус эталонного снимка initial_snapshot.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => new stdClass()
                ]
            ]
        ],
        [
            'type' => 'function',
            'function' => [
                'name' => 'rollback_site',
                'description' => 'Выполнить откат сайта к предыдущему снимку или к исходному состоянию initial_snapshot.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['target'],
                    'properties' => [
                        'target' => ['type' => 'string', 'description' => '"initial" для полного отката или имя снимка из list_backups']
                    ]
                ]
            ]
        ]
    ];
}

function lopal_sanitize_settings_for_ai(array $s): array {
    if (isset($s['deepseek_api_key'])) {
        $raw = trim($s['deepseek_api_key']);
        $s['deepseek_api_key'] = ($raw !== '') ? (substr($raw, 0, 5) . '...' . substr($raw, -4)) : '';
    }
    return $s;
}

/**
 * Исполнение одного инструмента CMS
 */
function lopal_ai_exec_tool(string $name, array $args, bool &$siteChanged): array {
    switch ($name) {
        case 'get_site_overview':
            $htmlFiles = array_map('basename', glob(LOPAL_ROOT_DIR . '/*.html') ?: []);
            return [
                'ok' => true,
                'settings' => lopal_sanitize_settings_for_ai(lopal_read_json('settings')),
                'counts' => [
                    'news' => count(lopal_read_json('news')),
                    'products' => count(lopal_read_json('products')),
                    'dealers' => count(lopal_read_json('dealers')),
                    'html_pages' => count($htmlFiles)
                ],
                'pages' => $htmlFiles
            ];

        case 'list_news':
            return ['ok' => true, 'news' => lopal_read_json('news')];

        case 'upsert_news':
            $news = lopal_read_json('news');
            $id = !empty($args['id']) ? trim($args['id']) : ('news-' . time());
            $cat = $args['category'] ?? 'corporate';
            $catLabels = [
                'products' => 'Продукция и стандарты',
                'nev' => 'Инновации NEV',
                'dealers' => 'Дилерская сеть КЗ',
                'corporate' => 'Технологии и OEM'
            ];
            $item = [
                'id' => $id,
                'date' => $args['date'] ?? date('d.m.Y'),
                'category' => $cat,
                'category_label' => $catLabels[$cat] ?? 'Новости LOPAL',
                'badge' => $args['badge'] ?? 'НОВОСТИ LOPAL',
                'title' => trim($args['title'] ?? 'Новость Lopal Kazakhstan'),
                'image' => $args['image'] ?? 'assets/branding/topmaq-hero-dark.jpg',
                'summary' => trim($args['summary'] ?? ''),
                'content' => trim($args['content'] ?? '')
            ];
            $found = false;
            foreach ($news as $k => $n) {
                if (($n['id'] ?? '') === $id) {
                    $news[$k] = array_merge($n, $item);
                    $found = true;
                    break;
                }
            }
            if (!$found) array_unshift($news, $item);
            lopal_write_json('news', $news, 'ai_upsert_news');
            lopal_build_news_page();
            lopal_build_sitemap();
            $siteChanged = true;
            return ['ok' => true, 'message' => 'Новость сохранена и опубликована на news.html', 'item' => $item];

        case 'delete_news':
            $id = trim($args['id'] ?? '');
            $news = lopal_read_json('news');
            $before = count($news);
            $news = array_values(array_filter($news, fn($n) => ($n['id'] ?? '') !== $id));
            lopal_write_json('news', $news, 'ai_delete_news');
            lopal_build_news_page();
            $siteChanged = true;
            return ['ok' => true, 'deleted' => ($before - count($news)), 'id' => $id];

        case 'list_products':
            return ['ok' => true, 'products' => lopal_read_json('products')];

        case 'upsert_product':
            $products = lopal_read_json('products');
            $id = !empty($args['id']) ? trim($args['id']) : ('prod-' . time());
            $cat = $args['category'] ?? 'passengers';
            $badgeText = $args['badge_text'] ?? 'API SQ';
            $badgeClass = 'badge-sq';
            if ($cat === 'nev') $badgeClass = 'badge-nev';
            elseif ($cat === 'commercial') $badgeClass = 'badge-ck4';

            $item = [
                'id' => $id,
                'category' => $cat,
                'name' => trim($args['name'] ?? ''),
                'image' => $args['image'] ?? 'assets/products_2026/lopal1-ultra-esf-0w20.png',
                'badges' => [['text' => $badgeText, 'class' => $badgeClass]],
                'specs' => is_array($args['specs'] ?? null) ? $args['specs'] : ['Оригинал LOPAL'],
                'description' => trim($args['description'] ?? ''),
                'detail_url' => $args['detail_url'] ?? 'products.html',
                'detail_btn_text' => 'Подробнее →'
            ];
            $found = false;
            foreach ($products as $k => $p) {
                if (($p['id'] ?? '') === $id) {
                    $products[$k] = array_merge($p, $item);
                    $found = true;
                    break;
                }
            }
            if (!$found) $products[] = $item;
            lopal_write_json('products', $products, 'ai_upsert_product');
            lopal_build_products_page();
            lopal_build_sitemap();
            $siteChanged = true;
            return ['ok' => true, 'message' => 'Товар сохранен, страница products.html пересобрана', 'product' => $item];

        case 'delete_product':
            $id = trim($args['id'] ?? '');
            $products = lopal_read_json('products');
            $before = count($products);
            $products = array_values(array_filter($products, fn($p) => ($p['id'] ?? '') !== $id));
            lopal_write_json('products', $products, 'ai_delete_product');
            lopal_build_products_page();
            $siteChanged = true;
            return ['ok' => true, 'deleted' => ($before - count($products)), 'id' => $id];

        case 'list_dealers':
            return ['ok' => true, 'dealers' => lopal_read_json('dealers')];

        case 'upsert_dealer':
            $dealers = lopal_read_json('dealers');
            $id = !empty($args['id']) ? trim($args['id']) : ('dealer-' . time());
            $addr = trim($args['address'] ?? '');
            $phone = trim($args['phone'] ?? '+7 (707) 523-65-25');
            $phoneDigits = preg_replace('/\D+/', '', $phone);
            if (strlen($phoneDigits) >= 11) {
                $phoneHref = '+' . substr($phoneDigits, 0, 11);
            } else {
                $phoneHref = '+' . $phoneDigits;
            }

            if (!empty($args['lat']) && !empty($args['lng'])) {
                $coords = [(float)$args['lat'], (float)$args['lng']];
            } else {
                $coords = lopal_geocode_address($addr);
            }

            $tempArr = ['city' => $args['city'] ?? '', 'address' => $addr];
            $city = lopal_extract_dealer_city($tempArr);

            $item = [
                'id' => $id,
                'isHq' => ($id === 'hq'),
                'city' => $city,
                'badge' => trim($args['badge'] ?? 'Магазин и автосервис'),
                'name' => trim($args['name'] ?? ''),
                'address' => $addr,
                'phone' => $phone,
                'phoneHref' => $phoneHref,
                'coords' => $coords,
                'dgis' => $args['dgis'] ?? ('https://2gis.kz/search/' . rawurlencode($addr))
            ];
            $found = false;
            foreach ($dealers as $k => $d) {
                if (($d['id'] ?? '') === $id) {
                    $dealers[$k] = array_merge($d, $item);
                    $found = true;
                    break;
                }
            }
            if (!$found) $dealers[] = $item;
            lopal_write_json('dealers', $dealers, 'ai_upsert_dealer');
            lopal_build_dealers();
            $siteChanged = true;
            return ['ok' => true, 'message' => 'Точка продаж добавлена/обновлена на карте и в списке по городам', 'dealer' => $item];

        case 'delete_dealer':
            $id = trim($args['id'] ?? '');
            $dealers = lopal_read_json('dealers');
            $before = count($dealers);
            $dealers = array_values(array_filter($dealers, fn($d) => ($d['id'] ?? '') !== $id));
            lopal_write_json('dealers', $dealers, 'ai_delete_dealer');
            lopal_build_dealers();
            $siteChanged = true;
            return ['ok' => true, 'deleted' => ($before - count($dealers)), 'id' => $id];

        case 'update_site_settings':
            $old = lopal_read_json('settings');
            // Защита: AI никогда не может перезаписать или раскрыть API-ключ через этот инструмент
            unset($args['deepseek_api_key']);
            $new = array_merge($old, $args);
            if (!empty($args['phone_main'])) {
                $new['phone_main_href'] = '+' . preg_replace('/\D+/', '', $args['phone_main']);
            }
            if (array_key_exists('phone_service', $args)) {
                $ps = trim((string)$args['phone_service']);
                $new['phone_service'] = $ps;
                $new['phone_service_href'] = $ps !== '' ? ('+' . preg_replace('/\D+/', '', $ps)) : '';
            }
            lopal_write_json('settings', $new, 'ai_update_settings');
            $updatedFiles = lopal_apply_global_settings($old, $new);
            $siteChanged = true;
            return ['ok' => true, 'updated_html_files' => $updatedFiles, 'settings' => lopal_sanitize_settings_for_ai($new)];

        case 'list_html_pages':
            $files = glob(LOPAL_ROOT_DIR . '/*.html') ?: [];
            $pages = [];
            foreach ($files as $f) {
                $c = @file_get_contents($f);
                $title = '';
                if ($c && preg_match('/<title>(.*?)<\/title>/is', $c, $m)) {
                    $title = trim($m[1]);
                }
                $pages[] = ['file' => basename($f), 'title' => $title, 'size_bytes' => filesize($f)];
            }
            return ['ok' => true, 'pages' => $pages];

        case 'read_page_text':
            $page = basename($args['page'] ?? 'index.html');
            $filePath = LOPAL_ROOT_DIR . '/' . $page;
            if (!file_exists($filePath)) {
                return ['ok' => false, 'error' => 'Страница не найдена: ' . $page];
            }
            $html = file_get_contents($filePath);
            $query = trim($args['search_query'] ?? '');
            if ($query !== '') {
                $pos = mb_stripos($html, $query);
                if ($pos === false) {
                    return ['ok' => false, 'message' => "Фраза '{$query}' не найдена в {$page}"];
                }
                $start = max(0, $pos - 600);
                $snippet = mb_substr($html, $start, 1500);
                return ['ok' => true, 'page' => $page, 'match_snippet' => $snippet];
            }
            // Извлекаем чистый текст из main или body
            if (preg_match('/<main[^>]*>([\s\S]*?)<\/main>/i', $html, $m)) {
                $body = $m[1];
            } else {
                $body = $html;
            }
            $body = preg_replace('/<script[\s\S]*?<\/script>/i', '', $body);
            $body = preg_replace('/<style[\s\S]*?<\/style>/i', '', $body);
            $text = trim(preg_replace('/\s+/', ' ', strip_tags($body)));
            return ['ok' => true, 'page' => $page, 'text_preview' => mb_substr($text, 0, 4000)];

        case 'replace_page_text':
            $page = trim($args['page'] ?? '');
            $search = (string)($args['search'] ?? '');
            $replace = (string)($args['replace'] ?? '');
            if ($search === '') {
                return ['ok' => false, 'error' => 'Параметр search не может быть пустым'];
            }
            lopal_create_data_backup('ai_page_edit');
            $targetFiles = ($page === 'ALL')
                ? (glob(LOPAL_ROOT_DIR . '/*.html') ?: [])
                : [LOPAL_ROOT_DIR . '/' . basename($page)];

            $modifiedPages = [];
            foreach ($targetFiles as $tf) {
                if (!file_exists($tf)) continue;
                $content = file_get_contents($tf);
                if (strpos($content, $search) !== false) {
                    $newContent = str_replace($search, $replace, $content);
                    file_put_contents($tf, $newContent, LOCK_EX);
                    $modifiedPages[] = basename($tf);
                }
            }
            if (empty($modifiedPages)) {
                return ['ok' => false, 'error' => 'Искомая строка не найдена в указанных файлах. Используйте read_page_text с search_query, чтобы проверить точный текст.'];
            }
            $siteChanged = true;
            return ['ok' => true, 'modified_pages' => $modifiedPages, 'count' => count($modifiedPages)];

        case 'create_page':
            $slug = trim($args['slug'] ?? '');
            $title = trim($args['title'] ?? '');
            $subtitle = trim($args['subtitle'] ?? '');
            $kicker = trim($args['kicker'] ?? 'Официальное представительство LOPAL в РК');
            $addToMenu = !isset($args['add_to_menu']) || !empty($args['add_to_menu']);
            $menuTitle = trim($args['menu_title'] ?? $title);

            $res = lopal_create_new_page($slug, $title, $subtitle, $kicker, $addToMenu, $menuTitle);
            if (!empty($res['ok'])) {
                $filename = $res['filename'] ?? ($slug . '.html');
                $siteChanged = true;
                return [
                    'ok' => true,
                    'filename' => $filename,
                    'url' => 'https://lopal.kz/' . $filename,
                    'editor_url' => 'visual_editor.php?page=' . urlencode($filename),
                    'added_to_menu' => $addToMenu,
                    'message' => "Страница {$filename} успешно создана и опубликована! Она добавлена в меню сайта и доступна для визуального редактирования."
                ];
            }
            return $res;

        case 'manage_menu':
            $act = $args['action'] ?? 'list';
            $menu = lopal_get_menu();
            if ($act === 'list') {
                return ['ok' => true, 'menu' => $menu];
            }
            if ($act === 'add') {
                $title = trim($args['title'] ?? '');
                $url = trim($args['url'] ?? '');
                if ($title === '' || $url === '') {
                    return ['ok' => false, 'error' => 'Для добавления пункта меню укажите title и url'];
                }
                $slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($url, PATHINFO_FILENAME));
                $menu[] = [
                    'id' => 'nav-' . ($slug ?: time()),
                    'title' => $title,
                    'url' => $url,
                    'visible' => true
                ];
                lopal_write_json('menu', $menu, 'ai_add_menu');
                lopal_rebuild_all_menus();
                $siteChanged = true;
                return ['ok' => true, 'message' => "Пункт меню '{$title}' добавлен!", 'menu' => $menu];
            }
            if ($act === 'toggle_visibility') {
                $id = trim($args['id'] ?? '');
                $vis = !empty($args['visible']);
                $found = false;
                foreach ($menu as &$item) {
                    if (($item['id'] ?? '') === $id || ($item['url'] ?? '') === $id) {
                        $item['visible'] = $vis;
                        $found = true;
                    }
                }
                unset($item);
                if (!$found) return ['ok' => false, 'error' => "Пункт меню с ID/URL '{$id}' не найден"];
                lopal_write_json('menu', $menu, 'ai_toggle_menu');
                lopal_rebuild_all_menus();
                $siteChanged = true;
                return ['ok' => true, 'message' => "Видимость пункта '{$id}' изменена на " . ($vis ? 'видимый' : 'скрытый'), 'menu' => $menu];
            }
            if ($act === 'delete') {
                $id = trim($args['id'] ?? '');
                $before = count($menu);
                $menu = array_values(array_filter($menu, fn($m) => ($m['id'] ?? '') !== $id && ($m['url'] ?? '') !== $id));
                if (count($menu) === $before) {
                    return ['ok' => false, 'error' => "Пункт меню '{$id}' не найден для удаления"];
                }
                lopal_write_json('menu', $menu, 'ai_delete_menu');
                lopal_rebuild_all_menus();
                $siteChanged = true;
                return ['ok' => true, 'message' => "Пункт меню '{$id}' удален!", 'menu' => $menu];
            }
            return ['ok' => false, 'error' => 'Неизвестное действие с меню: ' . $act];

        case 'list_backups':
            $snaps = array_map('basename', glob(LOPAL_BACKUPS_DIR . '/snap_*', GLOB_ONLYDIR) ?: []);
            rsort($snaps);
            return [
                'ok' => true,
                'initial_snapshot_available' => is_dir(LOPAL_BACKUPS_DIR . '/initial_snapshot'),
                'snapshots' => $snaps
            ];

        case 'rollback_site':
            $target = $args['target'] ?? 'initial';
            $res = ($target === 'initial')
                ? lopal_rollback_to_initial()
                : lopal_restore_data_snapshot($target);
            $siteChanged = true;
            return $res;

        default:
            return ['ok' => false, 'error' => 'Неизвестный инструмент: ' . $name];
    }
}

/**
 * Вызов DeepSeek Chat Completions API
 */
function lopal_call_deepseek(string $apiKey, array $messages, array $tools, string $model = 'deepseek-flash'): array {
    $url = 'https://api.deepseek.com/chat/completions';
    $allowedModels = ['deepseek-flash', 'deepseek-chat', 'deepseek-reasoner'];
    $apiModel = in_array($model, $allowedModels) ? $model : 'deepseek-flash';

    $reqData = [
        'model' => $apiModel,
        'messages' => $messages,
        'temperature' => ($apiModel === 'deepseek-reasoner') ? 1.0 : 0.2
    ];

    // deepseek-reasoner (R1) в DeepSeek API не поддерживает параметр tools
    if ($apiModel !== 'deepseek-reasoner' && !empty($tools)) {
        $reqData['tools'] = $tools;
        $reqData['tool_choice'] = 'auto';
    }

    $body = json_encode($reqData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ],
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        if ($resp === false) {
            return ['ok' => false, 'error' => 'Ошибка соединения с DeepSeek API: ' . $curlErr];
        }
        $decoded = json_decode($resp, true);
        if ($httpCode >= 400 || empty($decoded['choices'])) {
            $errMsg = $decoded['error']['message'] ?? ('HTTP ' . $httpCode . ': ' . substr((string)$resp, 0, 300));
            return ['ok' => false, 'error' => 'Ошибка DeepSeek API: ' . $errMsg];
        }
        return ['ok' => true, 'data' => $decoded];
    }

    // Fallback через stream_context
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\nAuthorization: Bearer {$apiKey}\r\n",
            'content' => $body,
            'timeout' => 60,
            'ignore_errors' => true
        ],
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false
        ]
    ]);
    $resp = @file_get_contents($url, false, $ctx);
    if ($resp === false) {
        return ['ok' => false, 'error' => 'Не удалось подключиться к api.deepseek.com'];
    }
    $decoded = json_decode($resp, true);
    if (empty($decoded['choices'])) {
        $errMsg = $decoded['error']['message'] ?? substr((string)$resp, 0, 300);
        return ['ok' => false, 'error' => 'Ошибка DeepSeek API: ' . $errMsg];
    }
    return ['ok' => true, 'data' => $decoded];
}

/**
 * 3. Основной обработчик чата с циклом вызова инструментов (Function Calling)
 */
if ($action === 'chat') {
    $settings = lopal_read_json('settings');
    $apiKey = trim($settings['deepseek_api_key'] ?? '');
    if ($apiKey === '') {
        echo json_encode([
            'ok' => false,
            'needKey' => true,
            'error' => 'Для работы AI-ассистента введите ваш DeepSeek API Key (sk-...) в поле сверху и нажмите «Сохранить ключ».'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $userMessages = $payload['messages'] ?? [];
    if (empty($userMessages) || !is_array($userMessages)) {
        echo json_encode(['ok' => false, 'error' => 'Пустой список сообщений'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $systemPrompt = "Ты — встроенный ИИ-администратор официального сайта LOPAL Kazakhstan (https://lopal.kz).\n"
        . "Официальные наименования:\n"
        . "- Дистрибьютор в РК: ТОО «Topmaq oil» (или ТОО Topmaq oil)\n"
        . "- Производитель: Jiangsu Lopal New Material Tech. Co., Ltd.\n"
        . "Текущая дата: " . date('d.m.Y') . ".\n\n"
        . "Твои возможности:\n"
        . "1. Публиковать, редактировать и удалять новости (list_news, upsert_news, delete_news).\n"
        . "2. Добавлять, изменять и удалять товары в каталоге масел (list_products, upsert_product, delete_product).\n"
        . "3. Управлять дилерской сетью и СТО по городам Казахстана (list_dealers, upsert_dealer, delete_dealer). Координаты на карте определяются автоматически по адресу.\n"
        . "4. Изменять глобальные контакты, телефоны, WhatsApp и график работы сразу на всех 25 страницах сайта (update_site_settings).\n"
        . "5. Читать и напрямую редактировать тексты на любой HTML-странице сайта (list_html_pages, read_page_text, replace_page_text).\n"
        . "6. Делать откат к бэкапам (list_backups, rollback_site).\n"
        . "7. Создавать новые страницы на сайте и сразу публиковать их (create_page). Страница создается адаптивной, с единой шапкой, мобильным меню и футером.\n"
        . "8. Управлять пунктами меню навигации (manage_menu) — добавлять ссылки, скрывать/показывать и удалять вкладки.\n\n"
        . "Правила:\n"
        . "- Когда пользователь просит что-то добавить, изменить или удалить на сайте — СРАЗУ вызывай нужный инструмент (function tool), а не просто пиши текст.\n"
        . "- Когда пользователь просит создать новую страницу, вкладку или раздел (например: 'создай страницу акций', 'сделай вкладку гарантия'):\n"
        . "   1) Сгенерируй slug на латинице (например: promo, warranty, agro, faq).\n"
        . "   2) Вызови инструмент create_page с понятным заголовком (title), кратким описанием (subtitle) и бейджем (kicker).\n"
        . "   3) В ответе обязательно дай пользователю две прямые ссылки в формате Markdown:\n"
        . "      - 🌐 Опубликованная страница: [lopal.kz/имя.html](https://lopal.kz/имя.html)\n"
        . "      - 🎨 Открыть в визуальном конструкторе: [Редактировать страницу](visual_editor.php?page=имя.html)\n"
        . "   4) Напомни пользователю, что в визуальном конструкторе он может кликнуть по любому тексту прямо на странице, сменить картинки или добавить специфические блоки (FAQ-аккордеон, таблицу характеристик, видео YouTube, Hero-баннер).\n"
        . "- Если нужно исправить текст на конкретной странице (например, partners.html или about.html), сначала вызови read_page_text, чтобы увидеть точный фрагмент, а затем вызови replace_page_text.\n"
        . "- НИКОГДА и ни при каких условиях не выводи в ответах секретные API-ключи, пароли или токены. Если упоминаются настройки или API, отображай только маску (например sk-b0...0c77).\n"
        . "- Отвечай лаконично, вежливо и по делу на русском языке, перечисляя какие именно действия были выполнены.";

    $messages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];

    // Добавляем последние 16 сообщений из истории диалога
    $recent = array_slice($userMessages, -16);
    foreach ($recent as $m) {
        if (!empty($m['role']) && isset($m['content']) && in_array($m['role'], ['user', 'assistant'])) {
            $messages[] = [
                'role' => $m['role'],
                'content' => (string)$m['content']
            ];
        }
    }

    $chosenModel = trim($payload['model'] ?? ($settings['deepseek_model'] ?? 'deepseek-flash'));
    $allowedModels = ['deepseek-flash', 'deepseek-chat', 'deepseek-reasoner'];
    if (!in_array($chosenModel, $allowedModels)) {
        $chosenModel = 'deepseek-flash';
    }

    $tools = lopal_get_ai_tools_schema();
    $executedActions = [];
    $siteChanged = false;
    $finalReply = '';

    // Цикл вызова инструментов (до 5 шагов за один запрос)
    for ($step = 0; $step < 5; $step++) {
        $res = lopal_call_deepseek($apiKey, $messages, $tools, $chosenModel);
        if (!$res['ok']) {
            echo json_encode([
                'ok' => false,
                'error' => $res['error'],
                'actions' => $executedActions,
                'siteChanged' => $siteChanged
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $choice = $res['data']['choices'][0] ?? null;
        $assistantMsg = $choice['message'] ?? null;
        if (!$assistantMsg) {
            break;
        }

        // Если DeepSeek решил вызвать один или несколько инструментов
        if (!empty($assistantMsg['tool_calls']) && is_array($assistantMsg['tool_calls'])) {
            $messages[] = [
                'role' => 'assistant',
                'content' => $assistantMsg['content'] ?? '',
                'tool_calls' => $assistantMsg['tool_calls']
            ];

            foreach ($assistantMsg['tool_calls'] as $tc) {
                $tcId = $tc['id'] ?? ('call_' . uniqid());
                $fnName = $tc['function']['name'] ?? '';
                $fnArgsRaw = $tc['function']['arguments'] ?? '{}';
                $fnArgs = json_decode($fnArgsRaw, true);
                if (!is_array($fnArgs)) $fnArgs = [];

                $toolResult = lopal_ai_exec_tool($fnName, $fnArgs, $siteChanged);

                $executedActions[] = [
                    'tool' => $fnName,
                    'args' => $fnArgs,
                    'ok' => !empty($toolResult['ok'])
                ];

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $tcId,
                    'content' => json_encode($toolResult, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                ];
            }
            // Переходим к следующей итерации, чтобы DeepSeek увидел результат работы инструментов
            continue;
        }

        // Инструментов больше вызывать не нужно — получен финальный ответ
        $finalReply = trim((string)($assistantMsg['content'] ?? 'Готово!'));
        break;
    }

    if ($finalReply === '' && !empty($executedActions)) {
        $finalReply = 'Команда успешно выполнена! Изменения применены на сайте.';
    }

    echo json_encode([
        'ok' => true,
        'reply' => $finalReply,
        'actions' => $executedActions,
        'siteChanged' => $siteChanged
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Неизвестное действие'], JSON_UNESCAPED_UNICODE);
