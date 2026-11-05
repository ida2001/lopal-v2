<?php
/**
 * LOPAL KAZAKHSTAN (lopal.kz) — MCP Server (JSON-RPC 2.0) & ChatGPT OpenAPI Gateway
 * Поддерживает:
 *  1) Протокол MCP (Model Context Protocol JSON-RPC 2.0: initialize, tools/list, tools/call)
 *  2) Схему OpenAPI 3.1.0 для ChatGPT Actions (GET /api/mcp.php?openapi=1)
 *  3) Прямые REST-вызовы инструментов (POST /api/mcp.php?action=<tool_name>)
 */

require_once dirname(__DIR__) . '/cms/builder.php';

// CORS заголовки для работы с ChatGPT, Claude и браузерными клиентами
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, Mcp-Session-Id');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * 1. Публичная выдача OpenAPI 3.1.0 схемы для импорта в ChatGPT (Custom GPT Actions)
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['openapi'])) {
    $schema = [
        'openapi' => '3.1.0',
        'info' => [
            'title' => 'LOPAL Kazakhstan Headless CMS & MCP API',
            'description' => 'Официальный API и MCP-шлюз управления контентом сайта https://lopal.kz (Новости, Каталог масел, Точки продаж, Настройки и Откат версий).',
            'version' => '1.0.0'
        ],
        'servers' => [
            ['url' => 'https://lopal.kz/api']
        ],
        'components' => [
            'securitySchemes' => [
                'BearerAuth' => [
                    'type' => 'http',
                    'scheme' => 'bearer'
                ]
            ]
        ],
        'security' => [
            ['BearerAuth' => []]
        ],
        'paths' => [
            '/mcp.php' => [
                'post' => [
                    'operationId' => 'executeCmsTool',
                    'summary' => 'Вызов любого инструмента управления сайтом lopal.kz (новости, товары, дилеры, контакты, откат)',
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'required' => ['tool'],
                                    'properties' => [
                                        'tool' => [
                                            'type' => 'string',
                                            'enum' => [
                                                'get_site_overview',
                                                'list_news',
                                                'upsert_news',
                                                'delete_news',
                                                'list_products',
                                                'upsert_product',
                                                'delete_product',
                                                'list_dealers',
                                                'upsert_dealer',
                                                'delete_dealer',
                                                'update_site_settings',
                                                'list_backups',
                                                'rollback_site'
                                            ],
                                            'description' => 'Имя вызываемого инструмента CMS'
                                        ],
                                        'arguments' => [
                                            'type' => 'object',
                                            'description' => 'Параметры инструмента (например title, summary, content для новости или name, address, phone для дилера)'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Результат выполнения операции и статус пересборки HTML'
                        ]
                    ]
                ]
            ]
        ]
    ];
    echo json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

/**
 * 2. Проверка авторизации по API-ключу (Bearer token, X-API-Key или ?api_key=...)
 */
function lopal_check_mcp_auth(): bool {
    $provided = '';
    if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(.+)$/i', $_SERVER['HTTP_AUTHORIZATION'], $m)) {
            $provided = trim($m[1]);
        }
    }
    if (!$provided && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        if (preg_match('/Bearer\s+(.+)$/i', $_SERVER['REDIRECT_HTTP_AUTHORIZATION'], $m)) {
            $provided = trim($m[1]);
        }
    }
    if (!$provided && !empty($_SERVER['HTTP_X_API_KEY'])) {
        $provided = trim($_SERVER['HTTP_X_API_KEY']);
    }
    if (!$provided && !empty($_GET['api_key'])) {
        $provided = trim($_GET['api_key']);
    }
    return hash_equals(LOPAL_MCP_API_KEY, $provided);
}

/**
 * Описание всех инструментов для протокола MCP (tools/list)
 */
function lopal_get_mcp_tools_definitions(): array {
    return [
        [
            'name' => 'get_site_overview',
            'description' => 'Получить общую сводку по сайту lopal.kz: глобальные настройки, контакты, количество товаров, новостей, точек продаж и бэкапов.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => (object)[]
            ]
        ],
        [
            'name' => 'list_news',
            'description' => 'Получить список всех новостей на странице https://lopal.kz/news.html.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => (object)[]
            ]
        ],
        [
            'name' => 'upsert_news',
            'description' => 'Добавить новую новость или обновить существующую на странице https://lopal.kz/news.html. Автоматически пересобирает HTML.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['title', 'summary', 'content'],
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'ID новости для обновления (оставьте пустым для создания новой)'],
                    'title' => ['type' => 'string', 'description' => 'Заголовок новости'],
                    'date' => ['type' => 'string', 'description' => 'Дата публикации (например: 05 ноября 2026)'],
                    'category' => ['type' => 'string', 'enum' => ['products', 'nev', 'dealers', 'corporate'], 'description' => 'Категория фильтра'],
                    'category_label' => ['type' => 'string', 'description' => 'Подпись рубрики (например: Продукция и стандарты)'],
                    'badge' => ['type' => 'string', 'description' => 'Текст бейджа на фото (например: НОВИНКА или API SQ)'],
                    'image' => ['type' => 'string', 'description' => 'Путь к изображению (по умолчанию assets/branding/topmaq-hero-dark.jpg)'],
                    'summary' => ['type' => 'string', 'description' => 'Краткий анонс на карточке (1-2 предложения)'],
                    'content' => ['type' => 'string', 'description' => 'Полный текст новости для чтения в модальном окне']
                ]
            ]
        ],
        [
            'name' => 'delete_news',
            'description' => 'Удалить новость по её ID и пересобрать news.html.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['id'],
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'ID новости (например news-1)']
                ]
            ]
        ],
        [
            'name' => 'list_products',
            'description' => 'Получить список всех смазочных материалов и техжидкостей из каталога https://lopal.kz/products.html.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => (object)[]
            ]
        ],
        [
            'name' => 'upsert_product',
            'description' => 'Добавить новый продукт в каталог или отредактировать существующий. Автоматически обновляет products.html.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['name', 'category', 'description'],
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'ID товара (например prod-1). Если не указан — создается новый'],
                    'name' => ['type' => 'string', 'description' => 'Полное название масла и вязкость SAE'],
                    'category' => ['type' => 'string', 'enum' => ['passengers', 'nev', 'commercial', 'fluids'], 'description' => 'Категория каталога'],
                    'category_title' => ['type' => 'string', 'description' => 'Название категории на карточке'],
                    'image' => ['type' => 'string', 'description' => 'Путь к фото канистры'],
                    'specs' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Массив допусков (например ["API SQ", "ILSAC GF-7A"])'],
                    'description' => ['type' => 'string', 'description' => 'Описание продукта'],
                    'detail_url' => ['type' => 'string', 'description' => 'Ссылка на детальную страницу']
                ]
            ]
        ],
        [
            'name' => 'delete_product',
            'description' => 'Удалить продукт из каталога по его ID.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['id'],
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'ID продукта']
                ]
            ]
        ],
        [
            'name' => 'list_dealers',
            'description' => 'Получить список всех точек продаж и ПЗМ на карте дилеров.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => (object)[]
            ]
        ],
        [
            'name' => 'upsert_dealer',
            'description' => 'Добавить новую точку продаж/СТО или обновить существующую на карте и странице service-points.html.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['name', 'address', 'phone'],
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'Уникальный ID точки (например almaty-sto-2)'],
                    'badge' => ['type' => 'string', 'description' => 'Тип точки (например Автосервис и ПЗМ или Точка продаж)'],
                    'name' => ['type' => 'string', 'description' => 'Название партнера / сервиса'],
                    'address' => ['type' => 'string', 'description' => 'Точный адрес'],
                    'phone' => ['type' => 'string', 'description' => 'Отображаемый телефон'],
                    'lat' => ['type' => 'number', 'description' => 'Широта для карты (по умолчанию 43.255)'],
                    'lng' => ['type' => 'number', 'description' => 'Долгота для карты (по умолчанию 76.912)'],
                    'dgis' => ['type' => 'string', 'description' => 'Ссылка на 2ГИС']
                ]
            ]
        ],
        [
            'name' => 'delete_dealer',
            'description' => 'Удалить точку продаж с карты и страницы service-points.html по её ID.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['id'],
                'properties' => [
                    'id' => ['type' => 'string', 'description' => 'ID дилера']
                ]
            ]
        ],
        [
            'name' => 'update_site_settings',
            'description' => 'Обновить глобальные контакты сайта (телефоны, email, адрес склада, WhatsApp) сразу на всех страницах.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'phone_main' => ['type' => 'string', 'description' => 'Основной телефон (например +7 (707) 523-65-25)'],
                    'phone_service' => ['type' => 'string', 'description' => 'Телефон сервиса (например +7 (707) 138-30-26)'],
                    'email' => ['type' => 'string', 'description' => 'Электронная почта (например info@lopal.kz)'],
                    'address' => ['type' => 'string', 'description' => 'Физический адрес в Алматы'],
                    'whatsapp' => ['type' => 'string', 'description' => 'Номер WhatsApp только цифрами (например 77075236525)']
                ]
            ]
        ],
        [
            'name' => 'rollback_site',
            'description' => 'Откатить сайт к исходному состоянию до внедрения CMS (initial_snapshot) или к выбранному снимку данных.',
            'inputSchema' => [
                'type' => 'object',
                'properties' => [
                    'target' => ['type' => 'string', 'description' => 'initial — для полного отката к исходной статике, либо имя снимка snap_YYYYMMDD_HHMMSS']
                ]
            ]
        ]
    ];
}

/**
 * Выполнение конкретного инструмента CMS
 */
function lopal_execute_mcp_tool(string $toolName, array $args): array {
    switch ($toolName) {
        case 'get_site_overview':
            return [
                'status' => 'ok',
                'site' => 'https://lopal.kz',
                'settings' => lopal_read_json('settings'),
                'counts' => [
                    'news' => count(lopal_read_json('news')),
                    'products' => count(lopal_read_json('products')),
                    'dealers' => count(lopal_read_json('dealers'))
                ]
            ];

        case 'list_news':
            return ['status' => 'ok', 'news' => lopal_read_json('news')];

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
            $newItem = [
                'id' => $id,
                'date' => !empty($args['date']) ? $args['date'] : date('d.m.Y'),
                'category' => $cat,
                'category_label' => !empty($args['category_label']) ? $args['category_label'] : ($catLabels[$cat] ?? 'Новости LOPAL'),
                'badge' => !empty($args['badge']) ? $args['badge'] : 'НОВОСТИ',
                'title' => trim($args['title'] ?? 'Без заголовка'),
                'image' => !empty($args['image']) ? $args['image'] : 'assets/branding/topmaq-hero-dark.jpg',
                'summary' => trim($args['summary'] ?? ''),
                'content' => trim($args['content'] ?? ($args['summary'] ?? '')),
                'published' => true
            ];
            $found = false;
            foreach ($news as $k => $item) {
                if (($item['id'] ?? '') === $id) {
                    $news[$k] = array_merge($item, $newItem);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                array_unshift($news, $newItem);
            }
            lopal_write_json('news', $news, 'mcp_upsert_news');
            lopal_build_news_page();
            lopal_build_sitemap();
            return ['status' => 'ok', 'action' => $found ? 'updated' : 'created', 'item' => $newItem, 'page_url' => 'https://lopal.kz/news.html'];

        case 'delete_news':
            $id = trim($args['id'] ?? '');
            $news = lopal_read_json('news');
            $filtered = array_values(array_filter($news, fn($x) => ($x['id'] ?? '') !== $id));
            lopal_write_json('news', $filtered, 'mcp_delete_news');
            lopal_build_news_page();
            return ['status' => 'ok', 'deleted_id' => $id, 'remaining' => count($filtered)];

        case 'list_products':
            return ['status' => 'ok', 'products' => lopal_read_json('products')];

        case 'upsert_product':
            $products = lopal_read_json('products');
            $id = !empty($args['id']) ? trim($args['id']) : ('prod-' . (count($products) + 1));
            $cat = $args['category'] ?? 'passengers';
            $catTitles = [
                'passengers' => 'Легковые автомобили',
                'nev' => 'Электрокары & Гибриды (NEV)',
                'commercial' => 'Коммерческий транспорт',
                'fluids' => 'Трансмиссия & Техжидкости'
            ];
            $item = [
                'id' => $id,
                'slug' => $id,
                'category' => $cat,
                'category_title' => $args['category_title'] ?? ($catTitles[$cat] ?? 'Продукция LOPAL'),
                'name' => trim($args['name'] ?? ''),
                'image' => $args['image'] ?? 'assets/products/lopal-1-advance-esf-fe-plus-0w20.jpg',
                'badges' => $args['badges'] ?? [['class' => 'badge-sq', 'text' => 'LOPAL TECH']],
                'specs' => $args['specs'] ?? ['Оригинальная продукция LOPAL'],
                'description' => trim($args['description'] ?? ''),
                'detail_url' => $args['detail_url'] ?? 'products.html',
                'detail_btn_text' => 'Подробное описание →'
            ];
            $found = false;
            foreach ($products as $k => $p) {
                if (($p['id'] ?? '') === $id) {
                    $products[$k] = array_merge($p, $item);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $products[] = $item;
            }
            lopal_write_json('products', $products, 'mcp_upsert_product');
            lopal_build_products_page();
            return ['status' => 'ok', 'action' => $found ? 'updated' : 'created', 'product' => $item];

        case 'delete_product':
            $id = trim($args['id'] ?? '');
            $products = lopal_read_json('products');
            $filtered = array_values(array_filter($products, fn($x) => ($x['id'] ?? '') !== $id));
            lopal_write_json('products', $filtered, 'mcp_delete_product');
            lopal_build_products_page();
            return ['status' => 'ok', 'deleted_id' => $id, 'remaining' => count($filtered)];

        case 'list_dealers':
            return ['status' => 'ok', 'dealers' => lopal_read_json('dealers')];

        case 'upsert_dealer':
            $dealers = lopal_read_json('dealers');
            $id = !empty($args['id']) ? trim($args['id']) : ('dealer-' . time());
            $phone = trim($args['phone'] ?? '+7 (707) 523-65-25');
            $phoneHref = '+' . preg_replace('/\D+/', '', $phone);
            $newDealer = [
                'id' => $id,
                'isHq' => !empty($args['isHq']),
                'badge' => trim($args['badge'] ?? 'Точка продаж и ПЗМ'),
                'name' => trim($args['name'] ?? ''),
                'address' => trim($args['address'] ?? ''),
                'phone' => $phone,
                'phoneHref' => $phoneHref,
                'coords' => [
                    isset($args['lat']) ? (float)$args['lat'] : 43.2550,
                    isset($args['lng']) ? (float)$args['lng'] : 76.9120
                ],
                'dgis' => !empty($args['dgis']) ? $args['dgis'] : ('https://2gis.kz/almaty/search/' . rawurlencode($args['address'] ?? $args['name'] ?? 'Алматы'))
            ];
            $found = false;
            foreach ($dealers as $k => $d) {
                if (($d['id'] ?? '') === $id) {
                    $dealers[$k] = array_merge($d, $newDealer);
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $dealers[] = $newDealer;
            }
            lopal_write_json('dealers', $dealers, 'mcp_upsert_dealer');
            lopal_build_dealers();
            return ['status' => 'ok', 'action' => $found ? 'updated' : 'created', 'dealer' => $newDealer];

        case 'delete_dealer':
            $id = trim($args['id'] ?? '');
            $dealers = lopal_read_json('dealers');
            $filtered = array_values(array_filter($dealers, fn($x) => ($x['id'] ?? '') !== $id));
            lopal_write_json('dealers', $filtered, 'mcp_delete_dealer');
            lopal_build_dealers();
            return ['status' => 'ok', 'deleted_id' => $id, 'remaining' => count($filtered)];

        case 'update_site_settings':
            $old = lopal_read_json('settings');
            $new = $old;
            foreach (['company_name', 'address', 'phone_main', 'phone_service', 'email', 'whatsapp', 'instagram', 'instagram_url'] as $field) {
                if (!empty($args[$field])) {
                    $new[$field] = trim($args[$field]);
                }
            }
            if (!empty($args['phone_main'])) {
                $new['phone_main_href'] = '+' . preg_replace('/\D+/', '', $args['phone_main']);
            }
            if (!empty($args['phone_service'])) {
                $new['phone_service_href'] = '+' . preg_replace('/\D+/', '', $args['phone_service']);
            }
            $new['updated_at'] = date('c');
            lopal_write_json('settings', $new, 'mcp_settings');
            $updatedFiles = lopal_apply_global_settings($old, $new);
            lopal_build_news_page();
            return ['status' => 'ok', 'updated_html_files' => $updatedFiles, 'settings' => $new];

        case 'list_backups':
            $snaps = array_map('basename', glob(LOPAL_BACKUPS_DIR . '/snap_*', GLOB_ONLYDIR) ?: []);
            rsort($snaps);
            return ['status' => 'ok', 'initial_snapshot_ready' => is_dir(LOPAL_BACKUPS_DIR . '/initial_snapshot'), 'snapshots' => $snaps];

        case 'rollback_site':
            $target = $args['target'] ?? 'initial';
            if ($target === 'initial' || $target === 'initial_snapshot') {
                return lopal_rollback_to_initial();
            }
            return lopal_restore_data_snapshot($target);

        default:
            return ['status' => 'error', 'message' => 'Неизвестный инструмент: ' . $toolName];
    }
}

// Если GET запрос без параметров — показываем статус MCP-сервера
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo json_encode([
        'service' => 'LOPAL Kazakhstan Headless CMS & MCP Server',
        'status' => 'online',
        'protocol' => 'MCP JSON-RPC 2.0 & OpenAPI 3.1.0',
        'openapi_schema_url' => 'https://lopal.kz/api/mcp.php?openapi=1',
        'admin_panel_url' => 'https://lopal.kz/admin/'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// Для POST-запросов проверяем авторизацию
if (!lopal_check_mcp_auth()) {
    http_response_code(401);
    echo json_encode([
        'error' => 'Unauthorized. Provide valid Bearer token or X-API-Key header.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    $payload = [];
}

/**
 * 3. Обработка стандартного протокола MCP (JSON-RPC 2.0)
 */
if (isset($payload['jsonrpc']) && $payload['jsonrpc'] === '2.0') {
    $method = $payload['method'] ?? '';
    $id = $payload['id'] ?? null;
    $params = $payload['params'] ?? [];

    if ($method === 'initialize') {
        echo json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'protocolVersion' => $params['protocolVersion'] ?? '2024-11-05',
                'capabilities' => [
                    'tools' => ['listChanged' => false]
                ],
                'serverInfo' => [
                    'name' => 'lopal-kz-headless-mcp',
                    'version' => '1.0.0'
                ]
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'notifications/initialized') {
        http_response_code(200);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($method === 'ping') {
        echo json_encode(['jsonrpc' => '2.0', 'id' => $id, 'result' => (object)[]]);
        exit;
    }

    if ($method === 'tools/list') {
        echo json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'tools' => lopal_get_mcp_tools_definitions()
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    if ($method === 'tools/call') {
        $toolName = $params['name'] ?? '';
        $toolArgs = $params['arguments'] ?? [];
        $res = lopal_execute_mcp_tool($toolName, is_array($toolArgs) ? $toolArgs : []);
        echo json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => json_encode($res, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)
                    ]
                ],
                'isError' => (($res['status'] ?? '') === 'error')
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    echo json_encode([
        'jsonrpc' => '2.0',
        'id' => $id,
        'error' => ['code' => -32601, 'message' => 'Method not found: ' . $method]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 4. Обработка вызовов из ChatGPT Custom GPT Actions (REST JSON `{ "tool": "...", "arguments": {...} }`)
 */
$toolName = $payload['tool'] ?? ($_GET['action'] ?? '');
$toolArgs = $payload['arguments'] ?? $payload;
if (!$toolName) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing tool name'], JSON_UNESCAPED_UNICODE);
    exit;
}

$result = lopal_execute_mcp_tool($toolName, is_array($toolArgs) ? $toolArgs : []);
echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
