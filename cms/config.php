<?php
/**
 * LOPAL KAZAKHSTAN (lopal.kz) — Headless CMS Core Configuration
 */

define('LOPAL_ROOT_DIR', dirname(__DIR__));
define('LOPAL_CMS_DIR', __DIR__);
define('LOPAL_DATA_DIR', __DIR__ . '/data');
define('LOPAL_BACKUPS_DIR', __DIR__ . '/backups');

// Доступ в визуальную панель управления https://lopal.kz/admin/
define('LOPAL_ADMIN_USER', 'admin');
define('LOPAL_ADMIN_PASS', 'Lopal2026!');

// Ключ авторизации для подключения ChatGPT по MCP и REST API
define('LOPAL_MCP_API_KEY', 'lopal_mcp_sk_2026_topmaqoil_kz');

/**
 * Чтение JSON-файла из хранилища
 */
function lopal_read_json(string $name): array {
    $file = LOPAL_DATA_DIR . '/' . $name . '.json';
    if (!file_exists($file)) {
        return [];
    }
    $raw = file_get_contents($file);
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Запись JSON-файла в хранилище с автоматическим созданием точки отката
 */
function lopal_write_json(string $name, array $data, string $reason = 'update'): bool {
    lopal_create_data_backup($reason);
    $file = LOPAL_DATA_DIR . '/' . $name . '.json';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    return file_put_contents($file, $json, LOCK_EX) !== false;
}

/**
 * Создание снимка JSON-данных перед изменением (хранит последние 30 версий)
 */
function lopal_create_data_backup(string $reason = 'auto'): string {
    $stamp = date('Ymd_His') . '_' . preg_replace('/[^a-zA-Z0-9_-]/', '', $reason);
    $snapDir = LOPAL_BACKUPS_DIR . '/snap_' . $stamp;
    if (!is_dir($snapDir)) {
        @mkdir($snapDir, 0755, true);
    }
    foreach (['settings', 'news', 'products', 'dealers'] as $file) {
        $src = LOPAL_DATA_DIR . '/' . $file . '.json';
        if (file_exists($src)) {
            @copy($src, $snapDir . '/' . $file . '.json');
        }
    }
    // Очистка старых авто-бэкапов (оставляем 30 последних + initial_snapshot)
    $dirs = glob(LOPAL_BACKUPS_DIR . '/snap_*', GLOB_ONLYDIR);
    if (is_array($dirs) && count($dirs) > 30) {
        sort($dirs);
        $toDelete = array_slice($dirs, 0, count($dirs) - 30);
        foreach ($toDelete as $d) {
            foreach (glob($d . '/*') as $f) @unlink($f);
            @rmdir($d);
        }
    }
    return 'snap_' . $stamp;
}
