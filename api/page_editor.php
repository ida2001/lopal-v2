<?php
/**
 * LOPAL KAZAKHSTAN (lopal.kz) — Visual Page Editor & Menu/Media API
 * Обеспечивает работу визуального In-Place редактора страниц,
 * загрузку баннеров и изображений, управление меню и создание новых вкладок.
 */

session_start();
require_once dirname(__DIR__) . '/cms/builder.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Проверка авторизации администратора
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
        'error' => 'Требуется авторизация администратора. Пожалуйста, войдите в админ-панель.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Загрузка файла через multipart/form-data
if (!empty($_FILES['file'])) {
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'error' => 'Ошибка загрузки файла (код ' . $file['error'] . ')'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];
    if (!in_array($ext, $allowed)) {
        echo json_encode(['ok' => false, 'error' => 'Разрешены только изображения: JPG, PNG, WebP, GIF, SVG'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $uploadDir = LOPAL_ROOT_DIR . '/assets/uploads';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $cleanBase = preg_replace('/[^a-zA-Z0-9_-]/', '-', pathinfo($file['name'], PATHINFO_FILENAME));
    $cleanBase = trim(preg_replace('/-+/', '-', $cleanBase), '-');
    if ($cleanBase === '') $cleanBase = 'image';

    $targetName = date('Ymd_His') . '_' . substr($cleanBase, 0, 40) . '.' . $ext;
    $targetPath = $uploadDir . '/' . $targetName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        echo json_encode(['ok' => false, 'error' => 'Не удалось сохранить файл на сервере'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $relUrl = 'assets/uploads/' . $targetName;
    echo json_encode([
        'ok' => true,
        'url' => $relUrl,
        'name' => $targetName
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode($rawBody, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$action = $payload['action'] ?? ($_GET['action'] ?? '');

/**
 * 1. Список медиафайлов (загруженные + брендинг + продукты)
 */
if ($action === 'list_media') {
    $folders = [
        'Загруженные (uploads)' => 'assets/uploads',
        'Баннеры и брендинг' => 'assets/branding',
        'Продукция 2026' => 'assets/products_2026',
        'Каталог масел' => 'assets/products'
    ];
    $media = [];
    foreach ($folders as $groupLabel => $relFolder) {
        $absFolder = LOPAL_ROOT_DIR . '/' . $relFolder;
        if (!is_dir($absFolder)) continue;
        $files = glob($absFolder . '/*.{jpg,jpeg,png,webp,gif,svg}', GLOB_BRACE) ?: [];
        // Сортируем по дате изменения (новые сверху)
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach ($files as $f) {
            $bname = basename($f);
            $media[] = [
                'group' => $groupLabel,
                'name' => $bname,
                'url' => $relFolder . '/' . $bname,
                'size_kb' => round(filesize($f) / 1024, 1)
            ];
        }
    }
    echo json_encode(['ok' => true, 'media' => $media], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * 2. Сохранение отредактированной страницы из визуального редактора
 */
if ($action === 'save_page') {
    $page = basename(trim($payload['page'] ?? ''));
    if ($page === '' || substr($page, -5) !== '.html') {
        echo json_encode(['ok' => false, 'error' => 'Некорректное имя файла страницы'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $filepath = LOPAL_ROOT_DIR . '/' . $page;
    if (!file_exists($filepath)) {
        echo json_encode(['ok' => false, 'error' => 'Файл не найден: ' . $page], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mainHtml = $payload['main_html'] ?? null;
    $pageTitle = trim($payload['page_title'] ?? '');

    // Создаем резервную копию страницы перед записью
    $backupFile = lopal_backup_page_file($page, 'visual_save');

    $currentContent = file_get_contents($filepath);

    if ($mainHtml !== null) {
        // Заменяем содержимое <main ...>...</main>
        $patternMain = '/(<main\b[^>]*>)([\s\S]*?)(<\/main>)/i';
        if (preg_match($patternMain, $currentContent)) {
            $updatedContent = preg_replace_callback($patternMain, function($m) use ($mainHtml) {
                return $m[1] . "\n" . $mainHtml . "\n  " . $m[3];
            }, $currentContent, 1);
        } else {
            echo json_encode(['ok' => false, 'error' => 'На странице не найден контейнер <main>'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    } else {
        echo json_encode(['ok' => false, 'error' => 'Не передано содержимое для сохранения'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($pageTitle !== '') {
        $titleSafe = htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8');
        $updatedContent = preg_replace('/<title>[\s\S]*?<\/title>/i', '<title>' . $titleSafe . '</title>', $updatedContent, 1);
    }

    if (file_put_contents($filepath, $updatedContent, LOCK_EX) === false) {
        echo json_encode(['ok' => false, 'error' => 'Ошибка записи файла на диск'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode([
        'ok' => true,
        'page' => $page,
        'backup' => $backupFile,
        'message' => 'Страница ' . $page . ' успешно сохранена!'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 3. Сохранение структуры главного меню и сквозное применение ко всем страницам
 */
if ($action === 'save_menu') {
    $menuItems = $payload['menu'] ?? [];
    if (!is_array($menuItems) || empty($menuItems)) {
        echo json_encode(['ok' => false, 'error' => 'Список меню не может быть пустым'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $cleanMenu = [];
    foreach ($menuItems as $idx => $item) {
        $title = trim($item['title'] ?? '');
        $url = trim($item['url'] ?? '');
        if ($title === '' || $url === '') continue;
        $cleanMenu[] = [
            'id' => !empty($item['id']) ? trim($item['id']) : ('nav-item-' . ($idx + 1)),
            'title' => $title,
            'url' => $url,
            'visible' => isset($item['visible']) ? (bool)$item['visible'] : true
        ];
    }

    lopal_write_json('menu', $cleanMenu, 'save_menu');
    $updatedPages = lopal_apply_global_menu();

    echo json_encode([
        'ok' => true,
        'menu' => $cleanMenu,
        'updated_pages' => $updatedPages,
        'message' => "Меню сохранено и обновлено на всех страницах сайта ({$updatedPages} стр.)!"
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 4. Создание новой страницы (и добавление в меню)
 */
if ($action === 'create_page') {
    $slug = trim($payload['slug'] ?? '');
    $title = trim($payload['title'] ?? '');
    $subtitle = trim($payload['subtitle'] ?? '');
    $kicker = trim($payload['kicker'] ?? 'Официальное представительство LOPAL в РК');
    $addToMenu = !empty($payload['add_to_menu']);
    $menuTitle = trim($payload['menu_title'] ?? $title);

    $res = lopal_create_new_page($slug, $title, $subtitle, $kicker, $addToMenu, $menuTitle);
    echo json_encode($res, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 5. Удаление созданной пользовательской страницы
 */
if ($action === 'delete_page') {
    $page = basename(trim($payload['page'] ?? ''));
    $protectedPages = [
        'index.html', 'products.html', 'selector.html', 'technologies.html',
        'certificates.html', 'service-points.html', 'news.html', 'partners.html',
        'contacts.html', 'about.html', 'media.html'
    ];
    if (in_array($page, $protectedPages)) {
        echo json_encode([
            'ok' => false,
            'error' => 'Базовые системные страницы сайта защищены от удаления файла. Вы можете скрыть вкладку в настройках меню или изменить её содержимое.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $filepath = LOPAL_ROOT_DIR . '/' . $page;
    if (!file_exists($filepath)) {
        echo json_encode(['ok' => false, 'error' => 'Файл не найден'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    lopal_backup_page_file($page, 'before_delete');
    @unlink($filepath);

    // Убираем из меню, если страница там была
    $menu = lopal_get_menu();
    $newMenu = array_values(array_filter($menu, fn($m) => basename($m['url'] ?? '') !== $page));
    if (count($newMenu) !== count($menu)) {
        lopal_write_json('menu', $newMenu, 'del_page_' . $page);
        lopal_apply_global_menu();
    }
    lopal_build_sitemap();

    echo json_encode(['ok' => true, 'deleted' => $page], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 6. Список бэкапов страниц и восстановление конкретной страницы
 */
if ($action === 'list_page_backups') {
    $pagesBackupDir = LOPAL_BACKUPS_DIR . '/pages';
    $files = is_dir($pagesBackupDir) ? (glob($pagesBackupDir . '/*') ?: []) : [];
    rsort($files);
    $list = [];
    foreach (array_slice($files, 0, 25) as $f) {
        $list[] = [
            'name' => basename($f),
            'date' => date('d.m.Y H:i:s', filemtime($f)),
            'size_kb' => round(filesize($f) / 1024, 1)
        ];
    }
    echo json_encode(['ok' => true, 'backups' => $list], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'restore_page_backup') {
    $backupName = basename(trim($payload['backup'] ?? ''));
    $src = LOPAL_BACKUPS_DIR . '/pages/' . $backupName;
    if (!file_exists($src)) {
        echo json_encode(['ok' => false, 'error' => 'Файл резервной копии не найден'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    // Имя формата YYYYMMDD_HHMMSS_filename.html
    if (preg_match('/^\d{8}_\d{6}_(.+\.html)$/i', $backupName, $m)) {
        $targetPage = basename($m[1]);
        @copy($src, LOPAL_ROOT_DIR . '/' . $targetPage);
        echo json_encode(['ok' => true, 'page' => $targetPage, 'message' => 'Страница ' . $targetPage . ' восстановлена из копии!'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    echo json_encode(['ok' => false, 'error' => 'Не удалось определить целевую страницу из имени бэкапа'], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['ok' => false, 'error' => 'Неизвестное действие API'], JSON_UNESCAPED_UNICODE);
