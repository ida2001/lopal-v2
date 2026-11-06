<?php
/**
 * LOPAL KAZAKHSTAN (lopal.kz) — Визуальная панель управления Headless CMS
 * Адрес: https://lopal.kz/admin/
 */

session_start();
require_once dirname(__DIR__) . '/cms/builder.php';

// Выход из системы
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Авторизация
$loginError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_action'])) {
    $u = trim($_POST['username'] ?? '');
    $p = trim($_POST['password'] ?? '');
    if ($u === LOPAL_ADMIN_USER && $p === LOPAL_ADMIN_PASS) {
        $_SESSION['lopal_admin_logged'] = true;
        header('Location: index.php');
        exit;
    } else {
        $loginError = 'Неверный логин или пароль';
    }
}

$isLogged = !empty($_SESSION['lopal_admin_logged']);

// Обработка загрузки изображений (для новостей и товаров)
function lopal_handle_image_upload(string $fieldName, string $defaultPath): string {
    if (!empty($_FILES[$fieldName]['name']) && $_FILES[$fieldName]['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($_FILES[$fieldName]['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $safeName = 'cms_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            $destRel = 'assets/products_2026/' . $safeName;
            $destAbs = LOPAL_ROOT_DIR . '/' . $destRel;
            if (@move_uploaded_file($_FILES[$fieldName]['tmp_name'], $destAbs)) {
                return $destRel;
            }
        }
    }
    return $defaultPath;
}

$flashMessage = '';
$flashType = 'success';

if ($isLogged && $_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cms_action'])) {
    $act = $_POST['cms_action'];

    // 1. Сохранение / добавление новости
    if ($act === 'save_news') {
        $news = lopal_read_json('news');
        $id = !empty($_POST['id']) ? trim($_POST['id']) : ('news-' . time());
        $cat = $_POST['category'] ?? 'corporate';
        $catLabels = [
            'products' => 'Продукция и стандарты',
            'nev' => 'Инновации NEV',
            'dealers' => 'Дилерская сеть КЗ',
            'corporate' => 'Технологии и OEM'
        ];
        $imgPath = lopal_handle_image_upload('image_file', trim($_POST['image'] ?? 'assets/branding/topmaq-hero-dark.jpg'));
        $newItem = [
            'id' => $id,
            'date' => trim($_POST['date'] ?: date('d.m.Y')),
            'category' => $cat,
            'category_label' => $catLabels[$cat] ?? 'Новости LOPAL',
            'badge' => trim($_POST['badge'] ?: 'НОВОСТИ'),
            'title' => trim($_POST['title'] ?? ''),
            'image' => $imgPath ?: 'assets/branding/topmaq-hero-dark.jpg',
            'summary' => trim($_POST['summary'] ?? ''),
            'content' => trim($_POST['content'] ?? ''),
            'published' => true
        ];
        $found = false;
        foreach ($news as $k => $item) {
            if (($item['id'] ?? '') === $id) {
                $news[$k] = $newItem;
                $found = true;
                break;
            }
        }
        if (!$found) {
            array_unshift($news, $newItem);
        }
        lopal_write_json('news', $news, 'admin_save_news');
        lopal_build_news_page();
        lopal_build_sitemap();
        $flashMessage = $found ? 'Новость успешно обновлена, страница news.html пересобрана!' : 'Новая публикация добавлена на страницу Новости!';
    }

    // 2. Удаление новости
    if ($act === 'delete_news') {
        $id = trim($_POST['id'] ?? '');
        $news = lopal_read_json('news');
        $news = array_values(array_filter($news, fn($x) => ($x['id'] ?? '') !== $id));
        lopal_write_json('news', $news, 'admin_del_news');
        lopal_build_news_page();
        $flashMessage = 'Публикация удалена, страница news.html обновлена.';
    }

    // 3. Сохранение / добавление товара
    if ($act === 'save_product') {
        $products = lopal_read_json('products');
        $id = !empty($_POST['id']) ? trim($_POST['id']) : ('prod-' . (count($products) + 1));
        $cat = $_POST['category'] ?? 'passengers';
        $catTitles = [
            'passengers' => 'Легковые автомобили',
            'nev' => 'Электрокары & Гибриды (NEV)',
            'commercial' => 'Коммерческий транспорт',
            'fluids' => 'Трансмиссия & Техжидкости'
        ];
        $imgPath = lopal_handle_image_upload('image_file', trim($_POST['image'] ?? 'assets/products/lopal-1-advance-esf-fe-plus-0w20.jpg'));
        $specsArr = array_values(array_filter(array_map('trim', explode(',', $_POST['specs'] ?? ''))));
        if (empty($specsArr)) $specsArr = ['Оригинальная продукция LOPAL'];

        $badgeText = trim($_POST['badge_text'] ?: 'API SQ');
        $item = [
            'id' => $id,
            'slug' => $id,
            'category' => $cat,
            'category_title' => $catTitles[$cat] ?? 'Продукция LOPAL',
            'name' => trim($_POST['name'] ?? ''),
            'image' => $imgPath,
            'badges' => [['class' => ($cat === 'nev' ? 'badge-nev' : 'badge-sq'), 'text' => $badgeText]],
            'specs' => $specsArr,
            'description' => trim($_POST['description'] ?? ''),
            'detail_url' => trim($_POST['detail_url'] ?: 'products.html'),
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
        lopal_write_json('products', $products, 'admin_save_product');
        lopal_build_products_page();
        $flashMessage = $found ? 'Карточка товара обновлена в каталоге!' : 'Новый продукт добавлен в каталог products.html!';
    }

    // 4. Удаление товара
    if ($act === 'delete_product') {
        $id = trim($_POST['id'] ?? '');
        $products = lopal_read_json('products');
        $products = array_values(array_filter($products, fn($x) => ($x['id'] ?? '') !== $id));
        lopal_write_json('products', $products, 'admin_del_product');
        lopal_build_products_page();
        $flashMessage = 'Товар удален из каталога.';
    }

    // 5. Сохранение / добавление дилера
    if ($act === 'save_dealer') {
        $dealers = lopal_read_json('dealers');
        $id = !empty($_POST['id']) ? trim($_POST['id']) : ('dealer-' . time());
        $phone = trim($_POST['phone'] ?? '+7 (707) 523-65-25');
        $phoneHref = '+' . preg_replace('/\D+/', '', $phone);
        $lat = (float)($_POST['lat'] ?: 43.2550);
        $lng = (float)($_POST['lng'] ?: 76.9120);
        $item = [
            'id' => $id,
            'isHq' => ($id === 'hq'),
            'city' => !empty($_POST['city']) ? trim($_POST['city']) : lopal_extract_dealer_city($_POST),
            'badge' => trim($_POST['badge'] ?: 'Точка продаж и ПЗМ'),
            'name' => trim($_POST['name'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'phone' => $phone,
            'phoneHref' => $phoneHref,
            'coords' => [$lat, $lng],
            'dgis' => trim($_POST['dgis'] ?: ('https://2gis.kz/search/' . rawurlencode($_POST['address'] ?? '')))
        ];
        $found = false;
        foreach ($dealers as $k => $d) {
            if (($d['id'] ?? '') === $id) {
                $dealers[$k] = $item;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $dealers[] = $item;
        }
        lopal_write_json('dealers', $dealers, 'admin_save_dealer');
        lopal_build_dealers();
        $flashMessage = 'Точка продаж сохранена! Интерактивная карта и страница «Точки продаж» обновлены.';
    }

    // 6. Удаление дилера
    if ($act === 'delete_dealer') {
        $id = trim($_POST['id'] ?? '');
        $dealers = lopal_read_json('dealers');
        $dealers = array_values(array_filter($dealers, fn($x) => ($x['id'] ?? '') !== $id));
        lopal_write_json('dealers', $dealers, 'admin_del_dealer');
        lopal_build_dealers();
        $flashMessage = 'Точка продаж удалена с карты и со страницы.';
    }

    // 7. Глобальные настройки и контакты
    if ($act === 'save_settings') {
        $old = lopal_read_json('settings');
        $new = $old;
        $new['company_name'] = trim($_POST['company_name'] ?? ($old['company_name'] ?? 'ТОО «Topmaq oil» (TOPMAQ OIL)'));
        $new['address'] = trim($_POST['address'] ?? ($old['address'] ?? 'Казахстан, г. Алматы, ул. Сокпакбаева, 67а'));
        $new['phone_main'] = trim($_POST['phone_main'] ?? ($old['phone_main'] ?? '+7 (707) 523-65-25'));
        $new['phone_main_href'] = '+' . preg_replace('/\D+/', '', $new['phone_main']);
        $new['phone_service'] = trim($_POST['phone_service'] ?? '');
        $cleanService = preg_replace('/\D+/', '', $new['phone_service']);
        $new['phone_service_href'] = !empty($cleanService) ? ('+' . $cleanService) : '';
        $new['email'] = trim($_POST['email'] ?? ($old['email'] ?? 'info@lopal.kz'));
        $new['whatsapp'] = preg_replace('/\D+/', '', $_POST['whatsapp'] ?? ($old['whatsapp'] ?? '77075236525'));
        $new['instagram'] = trim($_POST['instagram'] ?? ($old['instagram'] ?? '@lopal.kz'));
        $new['schedule_weekdays'] = trim($_POST['schedule_weekdays'] ?? ($old['schedule_weekdays'] ?? '09:00 — 18:00'));
        $new['schedule_sat'] = trim($_POST['schedule_sat'] ?? ($old['schedule_sat'] ?? 'Выходной'));
        $new['schedule_sun'] = trim($_POST['schedule_sun'] ?? ($old['schedule_sun'] ?? 'Выходной'));
        $new['updated_at'] = date('c');

        lopal_write_json('settings', $new, 'admin_save_settings');
        $count = lopal_apply_global_settings($old, $new);
        lopal_build_news_page();
        $flashMessage = "Глобальные контакты сохранены и автоматически обновлены во всех файлах сайта (обновлено страниц: {$count})!";
    }

    // 8. Откат к снимку данных
    if ($act === 'restore_snapshot') {
        $snap = $_POST['snapshot'] ?? '';
        $res = lopal_restore_data_snapshot($snap);
        $flashMessage = !empty($res['ok']) ? "Данные успешно восстановлены из точки {$snap} и сайт пересобран!" : ('Ошибка: ' . ($res['error'] ?? ''));
    }

    // 9. Полный аварийный откат к initial_snapshot
    if ($act === 'rollback_initial') {
        $res = lopal_rollback_to_initial();
        $flashMessage = $res['message'] ?? 'Откат выполнен';
    }
}

$newsList = $isLogged ? lopal_read_json('news') : [];
$productsList = $isLogged ? lopal_read_json('products') : [];
$dealersList = $isLogged ? lopal_read_json('dealers') : [];
$settings = $isLogged ? lopal_read_json('settings') : [];
$dsModel = $settings['deepseek_model'] ?? 'deepseek-flash';
$snapshots = $isLogged ? array_map('basename', glob(LOPAL_BACKUPS_DIR . '/snap_*', GLOB_ONLYDIR) ?: []) : [];
rsort($snapshots);
$activeTab = $_GET['tab'] ?? ($_POST['active_tab'] ?? 'news');
if ($activeTab === 'mcp') {
    $activeTab = 'news';
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Панель управления — LOPAL Казахстан (Headless CMS)</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    :root {
      --green: #00843D;
      --green-dark: #006B31;
      --gold: #C5A059;
      --bg: #F4F6F9;
      --card: #FFFFFF;
      --ink: #0F172A;
      --muted: #64748B;
      --border: #E2E8F0;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--ink); line-height: 1.5; }
    .topbar { background: #FFFFFF; border-bottom: 1px solid var(--border); padding: 14px 28px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 50; box-shadow: 0 2px 10px rgba(0,0,0,0.04); }
    .topbar-left { display: flex; align-items: center; gap: 16px; }
    .topbar-left img { height: 38px; }
    .badge-cms { background: rgba(0, 132, 61, 0.12); color: var(--green); font-size: 12px; font-weight: 700; padding: 4px 10px; border-radius: 99px; }
    .wrap { max-width: 1260px; margin: 28px auto; padding: 0 20px; }
    .tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 24px; background: #FFF; padding: 8px; border-radius: 14px; border: 1px solid var(--border); }
    .tab-btn { padding: 10px 18px; border-radius: 10px; border: none; background: transparent; font: inherit; font-size: 14px; font-weight: 600; color: var(--muted); cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .tab-btn:hover { color: var(--ink); background: #F8FAFC; }
    .tab-btn.active { background: var(--green); color: #FFF; }
    .card { background: var(--card); border: 1px solid var(--border); border-radius: 16px; padding: 24px; margin-bottom: 24px; box-shadow: 0 4px 16px rgba(15,23,42,0.03); }
    .card h2 { font-size: 20px; font-weight: 800; margin-bottom: 6px; }
    .card p.sub { font-size: 13.5px; color: var(--muted); margin-bottom: 20px; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 768px) { .grid-2 { grid-template-columns: 1fr; } }
    label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; color: #334155; }
    input[type="text"], input[type="password"], input[type="email"], input[type="number"], select, textarea {
      width: 100%; padding: 10px 14px; border: 1px solid #CBD5E1; border-radius: 10px; font: inherit; font-size: 14px; margin-bottom: 14px; background: #FFF;
    }
    textarea { min-height: 90px; resize: vertical; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 10px 20px; border-radius: 10px; border: none; font: inherit; font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; transition: 0.2s; }
    .btn-green { background: var(--green); color: #FFF; }
    .btn-green:hover { background: var(--green-dark); }
    .btn-outline { background: #F8FAFC; border: 1px solid #CBD5E1; color: var(--ink); }
    .btn-danger { background: #FEE2E2; color: #B91C1C; border: 1px solid #FECACA; }
    .btn-danger:hover { background: #EF4444; color: #FFF; }
    .item-row { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; padding: 16px; border: 1px solid var(--border); border-radius: 12px; margin-bottom: 12px; background: #FAFBFD; }
    .item-row img { width: 72px; height: 72px; object-fit: cover; border-radius: 8px; background: #0F172A; flex-shrink: 0; }
    .flash { padding: 14px 20px; border-radius: 12px; background: #DCFCE7; border: 1px solid #86EFAC; color: #166534; font-weight: 600; margin-bottom: 20px; }
    code.code-box { display: block; background: #0F172A; color: #38BDF8; padding: 12px 16px; border-radius: 10px; font-size: 13px; overflow-x: auto; margin-bottom: 14px; }
  </style>
</head>
<body>

<?php if (!$isLogged): ?>
  <div style="min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="max-width: 420px; width: 100%; text-align: center;">
      <img src="../assets/branding/lopal-logo.png" alt="Lopal" style="height: 46px; margin-bottom: 16px;">
      <h2>Вход в панель управления</h2>
      <p class="sub">LOPAL Казахстан · Headless CMS</p>
      <?php if ($loginError): ?>
        <div style="background:#FEE2E2;color:#B91C1C;padding:10px;border-radius:8px;margin-bottom:14px;font-size:13px;font-weight:600;"><?= htmlspecialchars($loginError) ?></div>
      <?php endif; ?>
      <form method="post" style="text-align: left;">
        <input type="hidden" name="login_action" value="1">
        <label>Логин администратора</label>
        <input type="text" name="username" required autofocus placeholder="admin">
        <label>Пароль</label>
        <input type="password" name="password" required placeholder="••••••••">
        <button type="submit" class="btn btn-green" style="width: 100%; margin-top: 6px;">Войти в систему</button>
      </form>
      <div style="margin-top: 16px;">
        <a href="../index.html" style="font-size: 13px; color: var(--muted); text-decoration: none;">← Вернуться на сайт lopal.kz</a>
      </div>
    </div>
  </div>
<?php else: ?>

  <header class="topbar">
    <div class="topbar-left">
      <img src="../assets/branding/lopal-logo.png" alt="Lopal">
      <span class="badge-cms">Headless CMS</span>
    </div>
    <div style="display: flex; align-items: center; gap: 12px;">
      <a href="../index.html" target="_blank" class="btn btn-outline">Открыть сайт ↗</a>
      <a href="../news.html" target="_blank" class="btn btn-outline">Страница Новостей ↗</a>
      <a href="?logout=1" class="btn btn-danger">Выйти</a>
    </div>
  </header>

  <div class="wrap">
    <?php if ($flashMessage): ?>
      <div class="flash">✅ <?= htmlspecialchars($flashMessage) ?></div>
    <?php endif; ?>

    <nav class="tabs">
      <a href="?tab=ai" class="tab-btn <?= $activeTab === 'ai' ? 'active' : '' ?>">🤖 AI-Ассистент (DeepSeek)</a>
      <a href="?tab=pages" class="tab-btn <?= $activeTab === 'pages' ? 'active' : '' ?>">🎨 Страницы и Меню (Визуальный редактор)</a>
      <a href="?tab=news" class="tab-btn <?= $activeTab === 'news' ? 'active' : '' ?>">📰 Новости (<?= count($newsList) ?>)</a>
      <a href="?tab=products" class="tab-btn <?= $activeTab === 'products' ? 'active' : '' ?>">🛢️ Каталог масел (<?= count($productsList) ?>)</a>
      <a href="?tab=dealers" class="tab-btn <?= $activeTab === 'dealers' ? 'active' : '' ?>">📍 Точки продаж и СТО (<?= count($dealersList) ?>)</a>
      <a href="?tab=settings" class="tab-btn <?= $activeTab === 'settings' ? 'active' : '' ?>">⚙️ Контакты и Настройки</a>
      <a href="?tab=backups" class="tab-btn <?= $activeTab === 'backups' ? 'active' : '' ?>">⏪ Откат и Бэкапы</a>
    </nav>

    <?php if ($activeTab === 'news'): ?>
      <div class="card">
        <h2>➕ Добавить или отредактировать новость</h2>
        <p class="sub">После нажатия «Опубликовать» статическая страница <code>news.html</code> автоматически пересобирается.</p>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="cms_action" value="save_news">
          <input type="hidden" name="active_tab" value="news">
          <input type="hidden" name="id" id="news_id" value="">
          <div class="grid-2">
            <div>
              <label>Заголовок новости *</label>
              <input type="text" name="title" id="news_title" required placeholder="Например: Поступление новой партии масел Lopal 1">
            </div>
            <div class="grid-2">
              <div>
                <label>Дата публикации</label>
                <input type="text" name="date" id="news_date" value="<?= date('d.m.Y') ?>">
              </div>
              <div>
                <label>Рубрика</label>
                <select name="category" id="news_category">
                  <option value="products">Продукция и стандарты</option>
                  <option value="nev">Гибриды и EV (NEV)</option>
                  <option value="dealers">Дилерская сеть КЗ</option>
                  <option value="corporate">Технологии и OEM</option>
                </select>
              </div>
            </div>
          </div>
          <div class="grid-2">
            <div>
              <label>Короткий бейдж на фото</label>
              <input type="text" name="badge" id="news_badge" placeholder="Например: API SQ / НОВИНКА" value="НОВОСТИ">
            </div>
            <div>
              <label>Изображение (выберите файл ИЛИ укажите путь)</label>
              <div style="display:flex;gap:8px;">
                <input type="text" name="image" id="news_image" value="assets/branding/topmaq-hero-dark.jpg" style="margin-bottom:0;">
                <input type="file" name="image_file" accept="image/*" style="max-width:220px;">
              </div>
            </div>
          </div>
          <label style="margin-top:10px;">Краткий анонс на карточке (1–2 предложения) *</label>
          <textarea name="summary" id="news_summary" required style="min-height:65px;"></textarea>
          <label>Полный текст новости (открывается при нажатии «Читать полностью») *</label>
          <textarea name="content" id="news_content" required style="min-height:140px;"></textarea>
          <div style="display:flex;gap:12px;">
            <button type="submit" class="btn btn-green">💾 Сохранить и опубликовать на сайте</button>
            <button type="button" class="btn btn-outline" onclick="resetNewsForm()">Очистить форму</button>
          </div>
        </form>
      </div>

      <div class="card">
        <h2>Опубликованные новости на сайте</h2>
        <p class="sub">Нажмите «Редактировать», чтобы загрузить новость в форму выше.</p>
        <?php foreach ($newsList as $n): ?>
          <div class="item-row">
            <img src="../<?= htmlspecialchars($n['image'] ?? '') ?>" alt="">
            <div style="flex:1;">
              <div style="font-size:12px;color:var(--muted);font-weight:600;"><?= htmlspecialchars($n['date'] ?? '') ?> · <?= htmlspecialchars($n['category_label'] ?? '') ?> · [ID: <?= htmlspecialchars($n['id']) ?>]</div>
              <div style="font-size:16px;font-weight:700;margin:4px 0;"><?= htmlspecialchars($n['title'] ?? '') ?></div>
              <div style="font-size:13.5px;color:#475569;"><?= htmlspecialchars($n['summary'] ?? '') ?></div>
            </div>
            <div style="display:flex;gap:8px;flex-shrink:0;">
              <button type="button" class="btn btn-outline" onclick='editNews(<?= json_encode($n, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️ Редактировать</button>
              <form method="post" onsubmit="return confirm('Удалить эту новость?');">
                <input type="hidden" name="cms_action" value="delete_news">
                <input type="hidden" name="active_tab" value="news">
                <input type="hidden" name="id" value="<?= htmlspecialchars($n['id']) ?>">
                <button type="submit" class="btn btn-danger">🗑️</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'products'): ?>
      <div class="card">
        <h2>➕ Добавить или изменить продукт в каталоге</h2>
        <p class="sub">Автоматически пересобирает сетку товаров на странице <code>products.html</code>.</p>
        <form method="post" enctype="multipart/form-data">
          <input type="hidden" name="cms_action" value="save_product">
          <input type="hidden" name="active_tab" value="products">
          <input type="hidden" name="id" id="prod_id" value="">
          <div class="grid-2">
            <div>
              <label>Название продукта и вязкость *</label>
              <input type="text" name="name" id="prod_name" required placeholder="Например: Lopal 1 Advance ESF SAE 0W-20">
            </div>
            <div class="grid-2">
              <div>
                <label>Категория</label>
                <select name="category" id="prod_category">
                  <option value="passengers">Легковой транспорт</option>
                  <option value="nev">Электромобили и гибриды (NEV)</option>
                  <option value="commercial">Коммерческий транспорт</option>
                  <option value="fluids">Трансмиссия и техжидкости</option>
                </select>
              </div>
              <div>
                <label>Бейдж (верхний угол)</label>
                <input type="text" name="badge_text" id="prod_badge" value="API SQ">
              </div>
            </div>
          </div>
          <div class="grid-2">
            <div>
              <label>Спецификации и допуски (через запятую)</label>
              <input type="text" name="specs" id="prod_specs" placeholder="API SQ, ILSAC GF-7A, ACEA C3, MB 229.52">
            </div>
            <div>
              <label>Фото канистры (загрузить ИЛИ указать путь)</label>
              <div style="display:flex;gap:8px;">
                <input type="text" name="image" id="prod_image" value="assets/products/lopal-1-advance-esf-fe-plus-0w20.jpg" style="margin-bottom:0;">
                <input type="file" name="image_file" accept="image/*" style="max-width:220px;">
              </div>
            </div>
          </div>
          <label style="margin-top:10px;">Описание продукта *</label>
          <textarea name="description" id="prod_desc" required></textarea>
          <label>Ссылка на детальную страницу</label>
          <input type="text" name="detail_url" id="prod_url" value="products.html">
          <button type="submit" class="btn btn-green">💾 Сохранить продукт в каталоге</button>
        </form>
      </div>

      <div class="card">
        <h2>Товары в каталоге (<?= count($productsList) ?>)</h2>
        <?php foreach ($productsList as $p): ?>
          <div class="item-row">
            <img src="../<?= htmlspecialchars($p['image'] ?? '') ?>" alt="" style="background:#FFF;object-fit:contain;">
            <div style="flex:1;">
              <div style="font-size:12px;color:var(--green);font-weight:700;"><?= htmlspecialchars($p['category_title'] ?? '') ?> · [<?= htmlspecialchars($p['id']) ?>]</div>
              <div style="font-size:16px;font-weight:700;margin:3px 0;"><?= htmlspecialchars($p['name'] ?? '') ?></div>
              <div style="font-size:12.5px;color:var(--muted);margin-bottom:4px;"><?= htmlspecialchars(implode(' · ', $p['specs'] ?? [])) ?></div>
              <div style="font-size:13px;color:#475569;"><?= htmlspecialchars($p['description'] ?? '') ?></div>
            </div>
            <div style="display:flex;gap:8px;">
              <button type="button" class="btn btn-outline" onclick='editProduct(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️ Изменить</button>
              <form method="post" onsubmit="return confirm('Удалить этот товар из каталога?');">
                <input type="hidden" name="cms_action" value="delete_product">
                <input type="hidden" name="active_tab" value="products">
                <input type="hidden" name="id" value="<?= htmlspecialchars($p['id']) ?>">
                <button type="submit" class="btn btn-danger">🗑️</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'dealers'): ?>
      <div class="card">
        <h2>➕ Добавить или изменить точку продаж / ПЗМ</h2>
        <p class="sub">Обновляет интерактивную карту на всех страницах и список партнеров в <code>service-points.html</code>.</p>
        <form method="post">
          <input type="hidden" name="cms_action" value="save_dealer">
          <input type="hidden" name="active_tab" value="dealers">
          <input type="hidden" name="id" id="dealer_id" value="">
          <div class="grid-2">
            <div>
              <label>Название сервиса / магазина *</label>
              <input type="text" name="name" id="dealer_name" required placeholder="Например: ПЗМ АвтоМастер">
            </div>
            <div>
              <label>Специализация (бейдж)</label>
              <input type="text" name="badge" id="dealer_badge" value="Автосервис и замена масла">
            </div>
          </div>
          <div class="grid-2">
            <div>
              <label>Точный адрес *</label>
              <input type="text" name="address" id="dealer_address" required placeholder="г. Алматы, пр. Абая, 100">
            </div>
            <div>
              <label>Телефон *</label>
              <input type="text" name="phone" id="dealer_phone" required value="+7 (707) 523-65-25">
            </div>
          </div>

          <!-- Интерактивная карта для ручного выбора точки -->
          <div style="margin: 16px 0; background: #F8FAFC; border: 1px solid var(--border); border-radius: 14px; padding: 18px;">
            <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; margin-bottom: 12px;">
              <div>
                <strong style="font-size:14.5px; color:var(--ink); display:flex; align-items:center; gap:6px;">
                  🗺️ Указание точки на интерактивной карте:
                </strong>
                <span style="font-size:12.5px; color:var(--muted);">Кликните в любое место на карте или перетащите зеленый маркер 📌</span>
              </div>
              <div style="display:flex; gap:6px; flex-wrap:wrap;">
                <button type="button" class="btn btn-green" style="padding:6px 12px; font-size:12px;" onclick="fitAllDealersOnAdminMap()">🇰🇿 Весь Казахстан (<?= count($dealersList) ?>)</button>
                <button type="button" class="btn btn-outline" style="padding:6px 12px; font-size:12px;" onclick="centerMapOnCoords(43.2550, 76.9120, 12)">🏙️ Алматы</button>
                <button type="button" class="btn btn-outline" style="padding:6px 12px; font-size:12px;" onclick="centerMapOnCoords(51.1450, 71.4491, 12)">🏙️ Астана</button>
                <button type="button" class="btn btn-outline" style="padding:6px 12px; font-size:12px;" onclick="centerMapOnCoords(42.3500, 69.5901, 12)">🏙️ Шымкент</button>
                <button type="button" class="btn btn-outline" style="padding:6px 12px; font-size:12px;" onclick="centerMapOnCoords(43.6550, 51.1750, 12)">🏙️ Актау</button>
                <button type="button" class="btn btn-outline" style="padding:6px 12px; font-size:12px;" onclick="centerMapOnCoords(45.0216, 78.4265, 14)">🏙️ Талдыкорган</button>
                <button type="button" class="btn btn-outline" style="padding:6px 12px; font-size:12px;" onclick="centerMapOnCoords(49.7300, 84.2679, 14)">🏙️ Алтай</button>
                <button type="button" class="btn btn-outline" style="padding:6px 12px; font-size:12px;" onclick="centerMapOnCoords(42.4280, 68.8020, 14)">🏙️ Арысь</button>
                <button type="button" class="btn btn-outline" style="padding:6px 12px; font-size:12px;" onclick="locateUserPosition()">🎯 Моё положение</button>
              </div>
            </div>

            <!-- Поиск адреса на карте -->
            <div style="display:flex; gap:8px; margin-bottom: 10px;">
              <input type="text" id="map_search_query" placeholder="Поиск адреса на карте (например: Алматы, пр. Райымбека 115 или Абая 100)..." style="margin-bottom:0;" onkeydown="if(event.key==='Enter'){event.preventDefault();searchAddressOnMap();}">
              <button type="button" class="btn btn-outline" style="white-space:nowrap; padding:8px 16px;" onclick="searchAddressOnMap()">🔍 Найти на карте</button>
            </div>
            <div id="map_search_status" style="font-size:12.5px; margin-bottom:8px; display:none; padding:8px 12px; border-radius:8px;"></div>

            <!-- Контейнер карты Leaflet -->
            <div id="dealer_map_picker" style="height: 420px; width: 100%; border-radius: 10px; border: 1px solid #CBD5E1; box-shadow: inset 0 1px 3px rgba(0,0,0,0.06); z-index: 1;"></div>
            
            <div style="display:flex; justify-content:space-between; align-items:center; margin-top:10px; font-size:12.5px; color:var(--muted); flex-wrap:wrap; gap:8px;">
              <span>Координаты выбранной точки: <strong id="coords_preview" style="color:var(--green); font-family:monospace; font-size:13.5px; background:#E8F5E9; padding:3px 10px; border-radius:6px; font-weight:700;">43.255000, 76.912000</strong></span>
              <span>🟢 Зеленый маркер — редактируемая точка · ⚫ Темные — остальные дилеры</span>
            </div>
          </div>

          <div class="grid-2">
            <div class="grid-2">
              <div>
                <label>Широта (Latitude) *</label>
                <input type="text" name="lat" id="dealer_lat" value="43.2550" required oninput="onCoordsInputManual()">
              </div>
              <div>
                <label>Долгота (Longitude) *</label>
                <input type="text" name="lng" id="dealer_lng" value="76.9120" required oninput="onCoordsInputManual()">
              </div>
            </div>
            <div>
              <label>Ссылка на 2ГИС (опционально)</label>
              <input type="text" name="dgis" id="dealer_dgis" placeholder="Автоматически сформируется по адресу">
            </div>
          </div>
          <div style="display:flex; gap:12px;">
            <button type="submit" class="btn btn-green">💾 Сохранить точку на карте</button>
            <button type="button" class="btn btn-outline" onclick="resetDealerForm()">Очистить форму</button>
          </div>
        </form>
      </div>

      <div class="card">
        <h2>Действующие точки продаж и СТО по городам Казахстана (<?= count($dealersList) ?>)</h2>
        <?php
          $groupedAdminDealers = [];
          foreach ($dealersList as $d) {
              $city = function_exists('lopal_extract_dealer_city') ? lopal_extract_dealer_city($d) : ($d['city'] ?? 'Другие города');
              if (!isset($groupedAdminDealers[$city])) $groupedAdminDealers[$city] = [];
              $groupedAdminDealers[$city][] = $d;
          }
        ?>
        <?php foreach ($groupedAdminDealers as $cityTitle => $cityItems): ?>
          <div style="margin-top: 22px; margin-bottom: 10px; padding: 10px 14px; background: #F1F5F9; border-left: 4px solid var(--green); border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
            <strong style="font-size: 15px; color: #0F172A;">📍 <?= htmlspecialchars($cityTitle) ?></strong>
            <span style="font-size: 12px; font-weight: 700; background: #fff; color: var(--green); padding: 3px 10px; border-radius: 99px; border: 1px solid #CBD5E1;">Точек: <?= count($cityItems) ?></span>
          </div>
          <?php foreach ($cityItems as $d): ?>
            <div class="item-row">
              <div style="flex:1;">
                <div style="font-size:12px;color:var(--green);font-weight:700;"><?= htmlspecialchars($d['badge'] ?? '') ?> <?= !empty($d['isHq']) ? '⭐ ЦЕНТРАЛЬНЫЙ ХАБ' : '' ?></div>
                <div style="font-size:16px;font-weight:700;margin:2px 0;"><?= htmlspecialchars($d['name'] ?? '') ?></div>
                <div style="font-size:13.5px;color:#475569;">📍 <?= htmlspecialchars($d['address'] ?? '') ?> · 📞 <?= htmlspecialchars($d['phone'] ?? '') ?></div>
              </div>
              <div style="display:flex;gap:8px;">
                <button type="button" class="btn btn-outline" onclick='editDealer(<?= json_encode($d, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>✏️ Изменить</button>
                <?php if (empty($d['isHq'])): ?>
                  <form method="post" onsubmit="return confirm('Удалить точку продаж?');">
                    <input type="hidden" name="cms_action" value="delete_dealer">
                    <input type="hidden" name="active_tab" value="dealers">
                    <input type="hidden" name="id" value="<?= htmlspecialchars($d['id']) ?>">
                    <button type="submit" class="btn btn-danger">🗑️</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'settings'): ?>
      <div class="card">
        <h2>⚙️ Глобальные контакты и реквизиты сайта</h2>
        <p class="sub">При сохранении эти данные автоматически заменяются сразу во всех 25 HTML-файлах сайта (в подвале, контактах и микроразметке).</p>
        <form method="post">
          <input type="hidden" name="cms_action" value="save_settings">
          <input type="hidden" name="active_tab" value="settings">
          <div class="grid-2">
            <div>
              <label>Юридическое лицо</label>
              <input type="text" name="company_name" value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>">
            </div>
            <div>
              <label>Физический адрес центрального склада</label>
              <input type="text" name="address" value="<?= htmlspecialchars($settings['address'] ?? '') ?>">
            </div>
          </div>
          <div class="grid-2">
            <div>
              <label>Основной телефон (Отдел продаж)</label>
              <input type="text" name="phone_main" value="<?= htmlspecialchars($settings['phone_main'] ?? '') ?>">
            </div>
            <div>
              <label>Второй телефон (Сервис и ПЗМ)</label>
              <input type="text" name="phone_service" value="<?= htmlspecialchars($settings['phone_service'] ?? '') ?>">
            </div>
          </div>
          <div class="grid-2">
            <div>
              <label>Электронная почта (Email)</label>
              <input type="email" name="email" value="<?= htmlspecialchars($settings['email'] ?? 'info@lopal.kz') ?>">
            </div>
            <div>
              <label>Номер WhatsApp (только цифры)</label>
              <input type="text" name="whatsapp" value="<?= htmlspecialchars($settings['whatsapp'] ?? '77075236525') ?>">
            </div>
          </div>

          <div style="margin: 20px 0 16px; padding: 18px; background: #F8FAFC; border: 1px solid var(--border); border-radius: 12px;">
            <h3 style="font-size: 15px; font-weight: 700; margin-bottom: 12px; color: var(--ink);">🕒 График работы склада и офиса (отображается на странице Контакты и в сервисах)</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px;">
              <div>
                <label>Понедельник — Пятница *</label>
                <input type="text" name="schedule_weekdays" value="<?= htmlspecialchars($settings['schedule_weekdays'] ?? '09:00 — 18:00') ?>" required placeholder="09:00 — 18:00">
              </div>
              <div>
                <label>Суббота *</label>
                <input type="text" name="schedule_sat" value="<?= htmlspecialchars($settings['schedule_sat'] ?? 'Выходной') ?>" required placeholder="Выходной">
              </div>
              <div>
                <label>Воскресенье *</label>
                <input type="text" name="schedule_sun" value="<?= htmlspecialchars($settings['schedule_sun'] ?? 'Выходной') ?>" required placeholder="Выходной">
              </div>
            </div>
          </div>

          <button type="submit" class="btn btn-green">💾 Применить ко всем страницам сайта</button>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'backups'): ?>
      <div class="card" style="border: 2px solid #FCA5A5;">
        <h2 style="color: #B91C1C;">⏪ Гарантированный откат к исходному состоянию (До внедрения CMS)</h2>
        <p class="sub">Если что-то пошло не так, нажмите кнопку ниже — все 24 оригинальные HTML-страницы, стили и скрипты будут мгновенно восстановлены из эталонной папки <code>initial_snapshot</code>.</p>
        <form method="post" onsubmit="return confirm('Вы уверены? Все HTML-страницы вернутся к исходному состоянию до внедрения CMS.');">
          <input type="hidden" name="cms_action" value="rollback_initial">
          <input type="hidden" name="active_tab" value="backups">
          <button type="submit" class="btn btn-danger">⏪ Выполнить полный откат к исходному состоянию</button>
        </form>
      </div>

      <div class="card">
        <h2>Автоматические точки сохранения базы данных (<?= count($snapshots) ?>)</h2>
        <p class="sub">Создаются автоматически перед каждым изменением через админку или ChatGPT MCP.</p>
        <?php if (empty($snapshots)): ?>
          <p style="font-size:14px;color:var(--muted);">Точки сохранения появятся здесь после первого редактирования.</p>
        <?php else: ?>
          <?php foreach ($snapshots as $snap): ?>
            <div class="item-row" style="align-items:center;">
              <div>
                <strong>📦 <?= htmlspecialchars($snap) ?></strong>
              </div>
              <form method="post">
                <input type="hidden" name="cms_action" value="restore_snapshot">
                <input type="hidden" name="active_tab" value="backups">
                <input type="hidden" name="snapshot" value="<?= htmlspecialchars($snap) ?>">
                <button type="submit" class="btn btn-outline">Восстановить эту версию</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'ai'): ?>
      <?php
        $dsKey = trim($settings['deepseek_api_key'] ?? '');
        $hasDsKey = ($dsKey !== '');
        $maskedDsKey = $hasDsKey ? (substr($dsKey, 0, 5) . '...' . substr($dsKey, -4)) : '';
        $dsModel = $settings['deepseek_model'] ?? 'deepseek-flash';
      ?>
      <div class="card" style="padding: 20px 24px; margin-bottom: 18px; border-left: 4px solid var(--green);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="margin-bottom:4px; display:flex; align-items:center; gap:8px;">
              🤖 AI-Ассистент управления сайтом (DeepSeek)
              <span id="aiKeyStatusBadge" style="font-size:12px; padding:3px 10px; border-radius:99px; font-weight:700; background:<?= $hasDsKey ? '#DCFCE7' : '#FEF3C7' ?>; color:<?= $hasDsKey ? '#166534' : '#92400E' ?>;">
                <?= $hasDsKey ? ('🟢 Подключен (' . htmlspecialchars($maskedDsKey) . ')') : '🔑 Требуется API-ключ DeepSeek' ?>
              </span>
            </h2>
            <p class="sub" style="margin-bottom:0;">Управляйте новостями, каталогом масел, точками продаж на карте, контактами и текстами всех 25 страниц голосом (🎤) или текстом.</p>
          </div>
          <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <div style="display:inline-flex; align-items:center; gap:8px; background:#F8FAFC; padding:5px 12px; border-radius:10px; border:1px solid var(--border);">
              <span style="font-size:12.5px; font-weight:700; color:#475569;">🧠 Модель:</span>
              <select id="aiModelSelect" onchange="changeAiModel(this.value)" style="padding:5px 10px; font-size:12.5px; font-weight:700; border-radius:8px; border:1px solid #CBD5E1; background:#fff; cursor:pointer; color:#0F172A; margin:0;">
                <option value="deepseek-flash" <?= ($dsModel === 'deepseek-flash') ? 'selected' : '' ?>>⚡ DeepSeek V4.1-Flash-1017 (Быстрая)</option>
                <option value="deepseek-chat" <?= ($dsModel === 'deepseek-chat') ? 'selected' : '' ?>>🚀 DeepSeek-V3 (Флагманская)</option>
                <option value="deepseek-reasoner" <?= ($dsModel === 'deepseek-reasoner') ? 'selected' : '' ?>>🔬 DeepSeek-R1 (Глубокое мышление)</option>
              </select>
            </div>
            <button type="button" class="btn btn-outline" style="padding:7px 13px; font-size:12.5px;" onclick="toggleAiKeyBox()">🔑 API-ключ DeepSeek</button>
            <button type="button" class="btn btn-outline" style="padding:7px 13px; font-size:12.5px;" onclick="clearAiHistory()">🗑️ Очистить диалог</button>
          </div>
        </div>

        <div id="aiKeyConfigBox" style="margin-top:14px; padding-top:14px; border-top:1px solid var(--border); display:<?= $hasDsKey ? 'none' : 'block' ?>;">
          <label style="margin-bottom:6px;">DeepSeek API Key (<a href="https://platform.deepseek.com/api_keys" target="_blank" rel="noopener" style="color:var(--green);">platform.deepseek.com</a>)</label>
          <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <input type="password" id="deepseek_api_key_input" placeholder="sk-..." value="<?= htmlspecialchars($dsKey) ?>" style="flex:1; min-width:240px; margin-bottom:0;">
            <button type="button" class="btn btn-green" onclick="saveDeepSeekKey()">💾 Сохранить ключ</button>
          </div>
        </div>
      </div>

      <!-- Быстрые команды-шаблоны -->
      <div style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:14px;">
        <button type="button" class="btn btn-outline" style="padding:6px 12px; font-size:12px; border-radius:99px;" onclick="sendQuickAiPrompt('Покажи полную сводку по сайту: сколько сейчас новостей, товаров, дилеров и какие контакты установлены.')">📊 Сводка по сайту</button>
        <button type="button" class="btn btn-outline" style="padding:6px 12px; font-size:12px; border-radius:99px;" onclick="fillAiPrompt('Опубликуй новую новость: Заголовок — ')">📰 Опубликовать новость</button>
        <button type="button" class="btn btn-outline" style="padding:6px 12px; font-size:12px; border-radius:99px;" onclick="fillAiPrompt('Добавь нового дилера на карту: Название — , Город и адрес — , Телефон — ')">📍 Добавить дилера на карту</button>
        <button type="button" class="btn btn-outline" style="padding:6px 12px; font-size:12px; border-radius:99px;" onclick="fillAiPrompt('Измени график работы на сайте: ')">🕒 Изменить график работы</button>
        <button type="button" class="btn btn-outline" style="padding:6px 12px; font-size:12px; border-radius:99px;" onclick="fillAiPrompt('На странице partners.html измени текст: ')">✏️ Исправить текст на странице</button>
      </div>

      <!-- Основное окно чата -->
      <div class="card" style="padding:0; overflow:hidden; display:flex; flex-direction:column; height: min(640px, 72vh);">
        <div id="aiMainChatMessages" style="flex:1; overflow-y:auto; padding:20px; background:#F8FAFC; display:flex; flex-direction:column; gap:14px;"></div>

        <div id="aiVoiceListeningBanner" style="display:none; background:#FEF2F2; color:#DC2626; padding:8px 18px; font-size:13px; font-weight:700; border-top:1px solid #FECACA; align-items:center; justify-content:space-between;">
          <span>🔴 Идёт запись голоса (говорите по-русски)...</span>
          <button type="button" onclick="stopAiVoice()" style="background:#DC2626; color:#fff; border:none; border-radius:6px; padding:3px 10px; font-size:11.5px; cursor:pointer; font-weight:700;">Остановить ⏹</button>
        </div>

        <div style="padding:14px 18px; background:#fff; border-top:1px solid var(--border);">
          <div style="display:flex; gap:10px; align-items:flex-end;">
            <button type="button" id="aiMainMicBtn" onclick="toggleAiVoice('main')" title="Голосовой ввод (русский язык)" style="width:46px; height:46px; border-radius:12px; border:1px solid var(--border); background:#F1F5F9; color:#0F172A; font-size:20px; cursor:pointer; display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:all 0.2s;">
              🎤
            </button>
            <textarea id="aiMainInput" rows="2" placeholder="Напишите команду или нажмите 🎤 и скажите голосом (например: Добавь дилера в Караганде по адресу пр. Бухар-Жырау 50, телефон +7 701 111 22 33)..." style="flex:1; margin-bottom:0; resize:none; font-size:14px; line-height:1.45;" onkeydown="if(event.key==='Enter' && !event.shiftKey){event.preventDefault();sendAiMessage('main');}"></textarea>
            <button type="button" id="aiMainSendBtn" class="btn btn-green" onclick="sendAiMessage('main')" style="height:46px; padding:0 22px; flex-shrink:0;">
              Отправить ➤
            </button>
          </div>
          <div style="display:flex; justify-content:space-between; align-items:center; margin-top:8px; font-size:12px; color:var(--muted);">
            <label style="display:inline-flex; align-items:center; gap:6px; font-weight:500; margin:0; cursor:pointer; color:var(--muted);">
              <input type="checkbox" id="aiAutoSendAfterVoice" checked style="width:auto; margin:0;">
              Автоматически отправлять сообщение после завершения голосовой фразы
            </label>
            <span>Enter — отправить · Shift+Enter — новая строка</span>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'pages'): ?>
      <?php
        $menuList = lopal_get_menu();
        $allHtmlPaths = glob(LOPAL_ROOT_DIR . '/*.html') ?: [];
        $protectedFiles = [
          'index.html', 'products.html', 'selector.html', 'technologies.html',
          'certificates.html', 'service-points.html', 'news.html', 'partners.html',
          'contacts.html', 'about.html', 'media.html'
        ];
        $pageTitlesRu = [
          'index.html' => '🏠 Главная страница',
          'products.html' => '🛢️ Каталог продукции',
          'selector.html' => '🔍 Онлайн-подбор масла',
          'technologies.html' => '🔬 Технологии R&D',
          'certificates.html' => '📜 Допуски OEM и сертификаты',
          'service-points.html' => '📍 Точки продаж и СТО',
          'news.html' => '📰 Новости компании',
          'partners.html' => '🤝 Партнёрам и дилерам',
          'contacts.html' => '📞 Контакты и реквизиты',
          'about.html' => '🏢 О компании',
          'media.html' => '🎬 Медиа и материалы',
        ];
      ?>

      <!-- 1. ВИЗУАЛЬНЫЙ РЕДАКТОР СТРАНИЦ САЙТА -->
      <div class="card" style="border-left: 4px solid var(--green);">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
          <div>
            <h2>🎨 Визуальный конструктор и редактор всех страниц сайта</h2>
            <p class="sub" style="margin-bottom:0;">
              Нажмите <strong>«✏️ Визуальный редактор»</strong> напротив любой страницы, чтобы менять тексты, заголовки, баннеры и картинки кликом мыши прямо на странице, а также добавлять <strong>Аккордеоны FAQ</strong>, <strong>Таблицы характеристик</strong> и <strong>Видео YouTube</strong>.
            </p>
          </div>
          <a href="visual_editor.php?page=index.html" class="btn btn-green">🚀 Открыть Главную в конструкторе</a>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(360px, 1fr)); gap:12px;">
          <?php foreach ($allHtmlPaths as $fPath): ?>
            <?php
              $bName = basename($fPath);
              if ($bName === 'readme.html') continue;
              $isCore = in_array($bName, $protectedFiles) || strpos($bName, 'product-') === 0;
              $ruTitle = $pageTitlesRu[$bName] ?? null;
              if (!$ruTitle) {
                $rawHtml = @file_get_contents($fPath);
                if ($rawHtml && preg_match('/<h1[^>]*>([\s\S]*?)<\/h1>/i', $rawHtml, $mH1)) {
                  $ruTitle = '📄 ' . mb_substr(trim(strip_tags($mH1[1])), 0, 48);
                } else {
                  $ruTitle = '📄 Страница ' . $bName;
                }
              }
              $modDate = date('d.m.Y H:i', filemtime($fPath));
            ?>
            <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 16px; border:1px solid var(--border); border-radius:12px; background:#FAFBFD;">
              <div style="min-width:0;">
                <div style="font-weight:800; font-size:14.5px; color:var(--ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">
                  <?= htmlspecialchars($ruTitle) ?>
                </div>
                <div style="font-size:12px; color:var(--muted); margin-top:2px;">
                  <code><?= htmlspecialchars($bName) ?></code> · изм. <?= $modDate ?>
                </div>
              </div>
              <div style="display:flex; align-items:center; gap:6px; flex-shrink:0;">
                <a href="visual_editor.php?page=<?= urlencode($bName) ?>" class="btn btn-green" style="padding:7px 12px; font-size:12.5px;">✏️ Редактировать</a>
                <a href="../<?= htmlspecialchars($bName) ?>" target="_blank" class="btn btn-outline" style="padding:7px 10px; font-size:12.5px;" title="Открыть страницу на сайте">↗</a>
                <?php if (!$isCore): ?>
                  <button type="button" class="btn btn-danger" style="padding:7px 10px; font-size:12.5px;" onclick="deleteCustomPage('<?= htmlspecialchars($bName) ?>')" title="Удалить пользовательскую страницу">🗑️</button>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- 2. СОЗДАНИЕ НОВОЙ ВКЛАДКИ / СТРАНИЦЫ -->
      <div class="card">
        <h2>➕ Создать новую страницу (раздел / вкладку)</h2>
        <p class="sub">Система автоматически создаст новую страницу с фирменной шапкой, подвалом и стилями LOPAL, добавит её в главное меню и откроет в визуальном конструкторе.</p>

        <div class="grid-2">
          <div>
            <label>Заголовок новой страницы (H1) *</label>
            <input type="text" id="new_page_title" placeholder="Например: Акции и спецпредложения LOPAL" oninput="autoFillSlug(this.value)">
          </div>
          <div>
            <label>Адрес файла (URL на латинице без пробелов) *</label>
            <input type="text" id="new_page_slug" placeholder="Например: promo или agro">
          </div>
        </div>

        <div class="grid-2">
          <div>
            <label>Надзаголовок (золотой бейдж / Kicker)</label>
            <input type="text" id="new_page_kicker" value="Официальное представительство LOPAL в РК">
          </div>
          <div>
            <label>Название вкладки в главном меню шапки</label>
            <input type="text" id="new_page_menu_title" placeholder="Например: Акции">
          </div>
        </div>

        <label>Краткое описание раздела (подзаголовок баннера и SEO Description)</label>
        <textarea id="new_page_subtitle" rows="2" placeholder="Введите краткое описание новой страницы..."></textarea>

        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; margin-top:6px;">
          <label style="display:inline-flex; align-items:center; gap:8px; margin:0; cursor:pointer; font-size:14px;">
            <input type="checkbox" id="new_page_add_menu" checked style="width:18px; height:18px; margin:0;">
            <strong>Сразу добавить новую вкладку в главное меню шапки на всех страницах сайта</strong>
          </label>
          <button type="button" class="btn btn-green" onclick="createNewSitePage()">🚀 Создать страницу и перейти в конструктор</button>
        </div>
      </div>

      <!-- 3. УПРАВЛЕНИЕ ГЛАВНЫМ МЕНЮ САЙТА (ВКЛАДКИ ШАПКИ) -->
      <div class="card">
        <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:14px;">
          <div>
            <h2>🧭 Управление вкладками главного меню сайта</h2>
            <p class="sub" style="margin-bottom:0;">Меняйте названия, ссылки, порядок следования или видимость вкладок. При нажатии «Сохранить меню» шапка автоматически обновится во всех 25+ файлах сайта.</p>
          </div>
          <div style="display:flex; gap:8px;">
            <button type="button" class="btn btn-outline" onclick="addMenuRow()">➕ Добавить вкладку в меню</button>
            <button type="button" class="btn btn-green" onclick="saveGlobalMenu()">💾 Сохранить меню на всём сайте</button>
          </div>
        </div>

        <div id="menuRowsContainer" style="display:flex; flex-direction:column; gap:10px;"></div>
      </div>

      <!-- 4. ТОЧКИ ВОССТАНОВЛЕНИЯ ОТРЕДАКТИРОВАННЫХ СТРАНИЦ -->
      <?php
        $pBackupsDir = LOPAL_BACKUPS_DIR . '/pages';
        $pBackups = is_dir($pBackupsDir) ? (glob($pBackupsDir . '/*') ?: []) : [];
        rsort($pBackups);
        $pBackups = array_slice($pBackups, 0, 12);
      ?>
      <?php if (!empty($pBackups)): ?>
        <div class="card">
          <h2>⏪ История сохранений визуального редактора</h2>
          <p class="sub">Перед каждым сохранением страницы в визуальном редакторе создается резервная копия файла. Вы можете откатить любую страницу в один клик.</p>
          <div style="display:flex; flex-direction:column; gap:8px;">
            <?php foreach ($pBackups as $pb): ?>
              <?php $pbName = basename($pb); ?>
              <div style="display:flex; align-items:center; justify-content:space-between; padding:10px 14px; background:#F8FAFC; border:1px solid var(--border); border-radius:10px;">
                <div>
                  <strong><code><?= htmlspecialchars($pbName) ?></code></strong>
                  <span style="font-size:12px; color:var(--muted); margin-left:8px;">📅 <?= date('d.m.Y H:i:s', filemtime($pb)) ?></span>
                </div>
                <button type="button" class="btn btn-outline" style="padding:6px 12px; font-size:12px;" onclick="restorePageBackup('<?= htmlspecialchars($pbName) ?>')">⏪ Восстановить версию</button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <script>
      let adminMenuData = <?= json_encode(array_values($menuList), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

      function escMenuVal(str) {
        return String(str || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
      }

      function renderAdminMenuRows() {
        const box = document.getElementById('menuRowsContainer');
        if (!box) return;
        box.innerHTML = adminMenuData.map((item, idx) => `
          <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap; padding:12px 14px; background:#F8FAFC; border:1px solid var(--border); border-radius:12px;">
            <div style="display:flex; gap:4px;">
              <button type="button" class="btn btn-outline" style="padding:6px 10px; font-size:12px;" onclick="moveMenuRow(${idx}, -1)" ${idx === 0 ? 'disabled' : ''}>⬆️</button>
              <button type="button" class="btn btn-outline" style="padding:6px 10px; font-size:12px;" onclick="moveMenuRow(${idx}, 1)" ${idx === adminMenuData.length - 1 ? 'disabled' : ''}>⬇️</button>
            </div>
            <div style="flex:1; min-width:180px;">
              <input type="text" value="${escMenuVal(item.title || '')}" placeholder="Название вкладки" style="margin-bottom:0;" oninput="adminMenuData[${idx}].title = this.value">
            </div>
            <div style="flex:1; min-width:180px;">
              <input type="text" value="${escMenuVal(item.url || '')}" placeholder="Адрес (например: promo.html)" style="margin-bottom:0;" oninput="adminMenuData[${idx}].url = this.value">
            </div>
            <label style="display:inline-flex; align-items:center; gap:6px; margin:0; cursor:pointer; font-size:13px; white-space:nowrap;">
              <input type="checkbox" ${item.visible !== false ? 'checked' : ''} onchange="adminMenuData[${idx}].visible = this.checked" style="width:16px; height:16px; margin:0;">
              Показывать
            </label>
            <a href="visual_editor.php?page=${encodeURIComponent(item.url || 'index.html')}" class="btn btn-outline" style="padding:7px 11px; font-size:12px;" title="Редактировать страницу">✏️</a>
            <button type="button" class="btn btn-danger" style="padding:7px 11px; font-size:12px;" onclick="removeMenuRow(${idx})" title="Удалить вкладку из меню">✕</button>
          </div>
        `).join('');
      }

      function moveMenuRow(idx, dir) {
        const target = idx + dir;
        if (target < 0 || target >= adminMenuData.length) return;
        const tmp = adminMenuData[idx];
        adminMenuData[idx] = adminMenuData[target];
        adminMenuData[target] = tmp;
        renderAdminMenuRows();
      }

      function addMenuRow() {
        adminMenuData.push({
          id: 'nav-' + Date.now(),
          title: 'Новая вкладка',
          url: 'index.html',
          visible: true
        });
        renderAdminMenuRows();
      }

      function removeMenuRow(idx) {
        if (!confirm('Убрать эту вкладку из главного меню? (Сам файл страницы при этом не удаляется)')) return;
        adminMenuData.splice(idx, 1);
        renderAdminMenuRows();
      }

      async function saveGlobalMenu() {
        try {
          const resp = await fetch('../api/page_editor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_menu', menu: adminMenuData })
          });
          const data = await resp.json();
          if (data.ok) {
            alert('✅ ' + data.message);
          } else {
            alert('⚠️ ' + (data.error || 'Ошибка сохранения меню'));
          }
        } catch (e) {
          alert('Ошибка соединения: ' + e.message);
        }
      }

      function autoFillSlug(title) {
        const map = {
          'а':'a','б':'b','в':'v','г':'g','д':'d','е':'e','ё':'e','ж':'zh','з':'z','и':'i','й':'y','к':'k','л':'l','м':'m',
          'н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'sch','ы':'y','э':'e','ю':'yu','я':'ya'
        };
        const slugInput = document.getElementById('new_page_slug');
        const menuTitleInput = document.getElementById('new_page_menu_title');
        if (menuTitleInput && !menuTitleInput.dataset.touched) {
          menuTitleInput.value = title.trim().split(' ').slice(0, 2).join(' ');
        }
        if (slugInput && !slugInput.dataset.touched) {
          let s = title.toLowerCase().split('').map(ch => map[ch] !== undefined ? map[ch] : ch).join('');
          s = s.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').substring(0, 32);
          slugInput.value = s;
        }
      }

      async function createNewSitePage() {
        const title = document.getElementById('new_page_title').value.trim();
        const slug = document.getElementById('new_page_slug').value.trim();
        const kicker = document.getElementById('new_page_kicker').value.trim();
        const menuTitle = document.getElementById('new_page_menu_title').value.trim();
        const subtitle = document.getElementById('new_page_subtitle').value.trim();
        const addToMenu = document.getElementById('new_page_add_menu').checked;

        if (!title || !slug) {
          alert('Пожалуйста, заполните Заголовок и Адрес файла (URL на латинице).');
          return;
        }

        try {
          const resp = await fetch('../api/page_editor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'create_page',
              title: title,
              slug: slug,
              kicker: kicker,
              menu_title: menuTitle || title,
              subtitle: subtitle,
              add_to_menu: addToMenu
            })
          });
          const data = await resp.json();
          if (data.ok && data.filename) {
            window.location.href = 'visual_editor.php?page=' + encodeURIComponent(data.filename);
          } else {
            alert('⚠️ ' + (data.error || 'Не удалось создать страницу'));
          }
        } catch (e) {
          alert('Ошибка сети: ' + e.message);
        }
      }

      async function deleteCustomPage(filename) {
        if (!confirm('Вы действительно хотите удалить страницу ' + filename + ' и убрать её из меню?')) return;
        try {
          const resp = await fetch('../api/page_editor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete_page', page: filename })
          });
          const data = await resp.json();
          if (data.ok) {
            window.location.reload();
          } else {
            alert('⚠️ ' + (data.error || 'Ошибка удаления'));
          }
        } catch (e) {
          alert('Ошибка: ' + e.message);
        }
      }

      async function restorePageBackup(backupName) {
        if (!confirm('Восстановить страницу из резервной копии ' + backupName + '?')) return;
        try {
          const resp = await fetch('../api/page_editor.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'restore_page_backup', backup: backupName })
          });
          const data = await resp.json();
          if (data.ok) {
            alert('✅ ' + data.message);
            window.location.reload();
          } else {
            alert('⚠️ ' + (data.error || 'Ошибка восстановления'));
          }
        } catch (e) {
          alert('Ошибка: ' + e.message);
        }
      }

      document.addEventListener('DOMContentLoaded', () => {
        renderAdminMenuRows();
        const slugEl = document.getElementById('new_page_slug');
        if (slugEl) slugEl.addEventListener('input', () => { slugEl.dataset.touched = '1'; });
        const mtEl = document.getElementById('new_page_menu_title');
        if (mtEl) mtEl.addEventListener('input', () => { mtEl.dataset.touched = '1'; });
      });
      </script>
    <?php endif; ?>

  </div>

  <?php if ($activeTab !== 'ai'): ?>
    <!-- Плавающий виджет AI-ассистента и микрофона на всех вкладках админки -->
    <div id="aiFloatingWidget" style="position:fixed; right:22px; bottom:22px; z-index:9999;">
      <div id="aiDrawerBox" style="display:none; width:min(420px, calc(100vw - 32px)); height:min(560px, 78vh); background:#fff; border-radius:16px; box-shadow:0 16px 48px rgba(15,23,42,0.28); border:1px solid #CBD5E1; flex-direction:column; overflow:hidden; margin-bottom:12px;">
        <div style="background:#0F172A; color:#fff; padding:11px 16px; display:flex; justify-content:space-between; align-items:center; gap:8px;">
          <div style="display:flex; align-items:center; gap:8px; font-weight:800; font-size:13px;">
            <span>🤖 AI</span>
            <select id="aiDrawerModelSelect" onchange="changeAiModel(this.value)" style="background:#1E293B; color:#38BDF8; border:1px solid #334155; border-radius:6px; font-size:11.5px; padding:3px 8px; font-weight:700; cursor:pointer; margin:0;">
              <option value="deepseek-flash" <?= ($dsModel === 'deepseek-flash') ? 'selected' : '' ?>>⚡ V4.1-Flash</option>
              <option value="deepseek-chat" <?= ($dsModel === 'deepseek-chat') ? 'selected' : '' ?>>🚀 V3 Chat</option>
              <option value="deepseek-reasoner" <?= ($dsModel === 'deepseek-reasoner') ? 'selected' : '' ?>>🔬 R1 Reasoner</option>
            </select>
          </div>
          <div style="display:flex; gap:8px; align-items:center;">
            <a href="?tab=ai" style="color:#94A3B8; font-size:11.5px; text-decoration:none;">Развернуть ↗</a>
            <button type="button" onclick="toggleAiDrawer(false)" style="background:none; border:none; color:#fff; font-size:18px; cursor:pointer; line-height:1;">✕</button>
          </div>
        </div>
        <div id="aiReloadNotice" style="display:none; background:#DCFCE7; color:#166534; padding:8px 12px; font-size:12px; font-weight:700; justify-content:space-between; align-items:center; border-bottom:1px solid #BBF7D0;">
          <span>✅ Данные сайта обновлены!</span>
          <button type="button" onclick="window.location.reload()" style="background:#00843D; color:#fff; border:none; border-radius:6px; padding:3px 9px; font-size:11px; font-weight:700; cursor:pointer;">🔄 Обновить страницу</button>
        </div>
        <div id="aiDrawerChatMessages" style="flex:1; overflow-y:auto; padding:14px; background:#F8FAFC; display:flex; flex-direction:column; gap:10px;"></div>
        <div style="padding:10px; background:#fff; border-top:1px solid #E2E8F0; display:flex; gap:8px; align-items:center;">
          <button type="button" id="aiDrawerMicBtn" onclick="toggleAiVoice('drawer')" title="Голосовой ввод" style="width:40px; height:40px; border-radius:10px; border:1px solid #CBD5E1; background:#F1F5F9; font-size:18px; cursor:pointer; flex-shrink:0;">🎤</button>
          <input type="text" id="aiDrawerInput" placeholder="Скажите в 🎤 или напишите..." style="flex:1; margin-bottom:0; padding:9px 12px; font-size:13px;" onkeydown="if(event.key==='Enter'){event.preventDefault();sendAiMessage('drawer');}">
          <button type="button" id="aiDrawerSendBtn" class="btn btn-green" onclick="sendAiMessage('drawer')" style="padding:9px 14px; font-size:13px; flex-shrink:0;">➤</button>
        </div>
      </div>
      <div style="display:flex; justify-content:flex-end;">
        <button type="button" onclick="toggleAiDrawer()" style="background:linear-gradient(135deg,#00843D,#059669); color:#fff; border:2px solid #fff; border-radius:99px; padding:12px 20px; font-size:14px; font-weight:800; cursor:pointer; box-shadow:0 8px 25px rgba(0,132,61,0.45); display:flex; align-items:center; gap:8px;">
          <span>🤖 AI-Управление</span>
          <span style="background:rgba(255,255,255,0.2); padding:2px 8px; border-radius:99px; font-size:12px;">🎤 Голос</span>
        </button>
      </div>
    </div>
  <?php endif; ?>

  <script>
  function editNews(n) {
    document.getElementById('news_id').value = n.id || '';
    document.getElementById('news_title').value = n.title || '';
    document.getElementById('news_date').value = n.date || '';
    document.getElementById('news_category').value = n.category || 'corporate';
    document.getElementById('news_badge').value = n.badge || 'НОВОСТИ';
    document.getElementById('news_image').value = n.image || '';
    document.getElementById('news_summary').value = n.summary || '';
    document.getElementById('news_content').value = n.content || '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  function resetNewsForm() {
    document.getElementById('news_id').value = '';
    document.getElementById('news_title').value = '';
    document.getElementById('news_summary').value = '';
    document.getElementById('news_content').value = '';
  }
  function editProduct(p) {
    document.getElementById('prod_id').value = p.id || '';
    document.getElementById('prod_name').value = p.name || '';
    document.getElementById('prod_category').value = p.category || 'passengers';
    document.getElementById('prod_badge').value = (p.badges && p.badges[0]) ? p.badges[0].text : 'API SQ';
    document.getElementById('prod_specs').value = (p.specs || []).join(', ');
    document.getElementById('prod_image').value = p.image || '';
    document.getElementById('prod_desc').value = p.description || '';
    document.getElementById('prod_url').value = p.detail_url || 'products.html';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  // =================== Интерактивная карта выбора точки дилера (Leaflet) ===================
  let dealerMap = null;
  let currentMarker = null;
  let otherMarkersLayer = null;
  const existingDealersData = <?= json_encode($dealersList, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

  function initDealerMap() {
    const mapContainer = document.getElementById('dealer_map_picker');
    if (!mapContainer || typeof L === 'undefined') return;

    let initLat = parseFloat(document.getElementById('dealer_lat').value) || 43.2550;
    let initLng = parseFloat(document.getElementById('dealer_lng').value) || 76.9120;

    dealerMap = L.map('dealer_map_picker', {
      center: [initLat, initLng],
      zoom: 13,
      zoomControl: true
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      maxZoom: 19,
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank">OpenStreetMap</a>'
    }).addTo(dealerMap);

    // Стильный зеленый маркер LOPAL для выбранной точки (Draggable)
    const activePinIcon = L.divIcon({
      className: 'lopal-active-pin',
      html: '<div style="background:#00843D;color:#FFF;width:38px;height:38px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(0,132,61,0.55);border:3px solid #FFF;cursor:grab;"><span style="transform:rotate(45deg);font-size:18px;">📍</span></div>',
      iconSize: [38, 38],
      iconAnchor: [19, 38],
      popupAnchor: [0, -38]
    });

    currentMarker = L.marker([initLat, initLng], {
      icon: activePinIcon,
      draggable: true,
      title: 'Перетащите в нужное место'
    }).addTo(dealerMap);

    currentMarker.bindPopup('<b>Выбранная точка дилера</b><br>Перетащите маркер мышью или кликните в любое место на карте');

    currentMarker.on('dragend', function (e) {
      const pos = e.target.getLatLng();
      setPointCoordinates(pos.lat, pos.lng, false);
    });

    dealerMap.on('click', function (e) {
      const lat = e.latlng.lat;
      const lng = e.latlng.lng;
      setPointCoordinates(lat, lng, true);
    });

    otherMarkersLayer = L.layerGroup().addTo(dealerMap);
    renderOtherDealersMarkers();
    updateCoordsPreview(initLat, initLng);
    fitAllDealersOnAdminMap();

    setTimeout(() => {
      if (dealerMap) {
        dealerMap.invalidateSize();
        fitAllDealersOnAdminMap();
      }
    }, 250);
  }

  function fitAllDealersOnAdminMap() {
    if (!dealerMap) return;
    const pts = [];
    existingDealersData.forEach(d => {
      if (d.coords && d.coords[0] && d.coords[1]) {
        pts.push([parseFloat(d.coords[0]), parseFloat(d.coords[1])]);
      }
    });
    if (pts.length > 0) {
      dealerMap.fitBounds(pts, { padding: [35, 35], maxZoom: 13 });
    }
  }

  function renderOtherDealersMarkers(excludeId = null) {
    if (!otherMarkersLayer) return;
    otherMarkersLayer.clearLayers();

    const otherPinIcon = L.divIcon({
      className: 'lopal-other-pin',
      html: '<div style="background:#0F172A;color:#FFF;width:26px;height:26px;border-radius:50% 50% 50% 0;transform:rotate(-45deg);display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,0.35);border:2px solid #FFF;opacity:0.88;cursor:pointer;"><span style="transform:rotate(45deg);font-size:11px;">🛢️</span></div>',
      iconSize: [26, 26],
      iconAnchor: [13, 26],
      popupAnchor: [0, -26]
    });

    existingDealersData.forEach(d => {
      if (excludeId && d.id === excludeId) return;
      if (d.coords && d.coords[0] && d.coords[1]) {
        const m = L.marker([d.coords[0], d.coords[1]], { icon: otherPinIcon });
        const popupHtml = `
          <div style="font-size:13px;line-height:1.45;min-width:180px;">
            <strong style="color:#0F172A;">${escapeHtml(d.name || '')}</strong><br>
            <span style="color:#64748B;font-size:12px;">${escapeHtml(d.address || '')}</span><br>
            <span style="color:#00843D;font-weight:600;font-size:12px;">📞 ${escapeHtml(d.phone || '')}</span><br>
            <button type="button" style="margin-top:6px;padding:4px 10px;font-size:11.5px;background:#00843D;color:#FFF;border:none;border-radius:6px;cursor:pointer;font-weight:600;" onclick='editDealerById("${d.id}")'>✏️ Редактировать эту точку</button>
          </div>
        `;
        m.bindPopup(popupHtml);
        otherMarkersLayer.addLayer(m);
      }
    });
  }

  function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, function(m) {
      return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m];
    });
  }

  function setPointCoordinates(lat, lng, moveMarker = true) {
    lat = parseFloat(lat);
    lng = parseFloat(lng);
    if (isNaN(lat) || isNaN(lng)) return;

    document.getElementById('dealer_lat').value = lat.toFixed(6);
    document.getElementById('dealer_lng').value = lng.toFixed(6);
    updateCoordsPreview(lat, lng);

    if (moveMarker && currentMarker) {
      currentMarker.setLatLng([lat, lng]);
    }
  }

  function updateCoordsPreview(lat, lng) {
    const el = document.getElementById('coords_preview');
    if (el) {
      el.textContent = `${parseFloat(lat).toFixed(6)}, ${parseFloat(lng).toFixed(6)}`;
    }
  }

  function onCoordsInputManual() {
    const lat = parseFloat(document.getElementById('dealer_lat').value);
    const lng = parseFloat(document.getElementById('dealer_lng').value);
    if (!isNaN(lat) && !isNaN(lng) && currentMarker && dealerMap) {
      currentMarker.setLatLng([lat, lng]);
      dealerMap.panTo([lat, lng]);
      updateCoordsPreview(lat, lng);
    }
  }

  function centerMapOnCoords(lat, lng, zoom = 14) {
    if (!dealerMap) return;
    setPointCoordinates(lat, lng, true);
    dealerMap.flyTo([lat, lng], zoom);
  }

  function locateUserPosition() {
    if (!navigator.geolocation) {
      alert('Геолокация не поддерживается вашим браузером');
      return;
    }
    const statusEl = document.getElementById('map_search_status');
    if (statusEl) {
      statusEl.style.display = 'block';
      statusEl.style.background = '#EFF6FF';
      statusEl.style.color = '#1D4ED8';
      statusEl.textContent = 'Определение вашего местоположения...';
    }
    navigator.geolocation.getCurrentPosition(
      pos => {
        const lat = pos.coords.latitude;
        const lng = pos.coords.longitude;
        centerMapOnCoords(lat, lng, 16);
        if (statusEl) {
          statusEl.style.background = '#DCFCE7';
          statusEl.style.color = '#166534';
          statusEl.textContent = `Точка определена: ${lat.toFixed(5)}, ${lng.toFixed(5)}`;
        }
      },
      err => {
        if (statusEl) {
          statusEl.style.background = '#FEE2E2';
          statusEl.style.color = '#B91C1C';
          statusEl.textContent = 'Не удалось определить геолокацию: ' + err.message;
        }
      },
      { timeout: 10000 }
    );
  }

  function searchAddressOnMap() {
    const query = (document.getElementById('map_search_query').value || '').trim();
    const statusEl = document.getElementById('map_search_status');
    if (!query) {
      alert('Введите адрес для поиска (например: Алматы, пр. Райымбека 115)');
      return;
    }
    if (statusEl) {
      statusEl.style.display = 'block';
      statusEl.style.background = '#EFF6FF';
      statusEl.style.color = '#1D4ED8';
      statusEl.textContent = 'Поиск адреса на карте...';
    }

    const url = 'https://nominatim.openstreetmap.org/search?format=json&q=' + encodeURIComponent(query) + '&countrycodes=kz&limit=1';
    fetch(url, { headers: { 'Accept-Language': 'ru,kz' } })
      .then(r => r.json())
      .then(data => {
        if (data && data.length > 0) {
          const item = data[0];
          const lat = parseFloat(item.lat);
          const lon = parseFloat(item.lon);
          centerMapOnCoords(lat, lon, 16);
          if (statusEl) {
            statusEl.style.background = '#DCFCE7';
            statusEl.style.color = '#166534';
            statusEl.textContent = `Найдено: ${item.display_name}`;
          }
          const addrInput = document.getElementById('dealer_address');
          if (addrInput && !addrInput.value) {
            addrInput.value = item.display_name;
          }
        } else {
          if (statusEl) {
            statusEl.style.background = '#FEF3C7';
            statusEl.style.color = '#92400E';
            statusEl.textContent = 'По запросу ничего не найдено в Казахстане. Попробуйте уточнить название города и улицы.';
          }
        }
      })
      .catch(err => {
        if (statusEl) {
          statusEl.style.background = '#FEE2E2';
          statusEl.style.color = '#B91C1C';
          statusEl.textContent = 'Ошибка поиска: ' + err.message;
        }
      });
  }

  function editDealerById(id) {
    const d = existingDealersData.find(x => x.id === id);
    if (d) editDealer(d);
  }

  function editDealer(d) {
    document.getElementById('dealer_id').value = d.id || '';
    document.getElementById('dealer_name').value = d.name || '';
    document.getElementById('dealer_badge').value = d.badge || 'Автосервис и замена масла';
    document.getElementById('dealer_address').value = d.address || '';
    document.getElementById('dealer_phone').value = d.phone || '';
    const lat = (d.coords && d.coords[0]) ? parseFloat(d.coords[0]) : 43.2550;
    const lng = (d.coords && d.coords[1]) ? parseFloat(d.coords[1]) : 76.9120;
    document.getElementById('dealer_lat').value = lat.toFixed(6);
    document.getElementById('dealer_lng').value = lng.toFixed(6);
    document.getElementById('dealer_dgis').value = d.dgis || '';
    
    if (dealerMap && currentMarker) {
      currentMarker.setLatLng([lat, lng]);
      dealerMap.flyTo([lat, lng], 15);
      updateCoordsPreview(lat, lng);
      renderOtherDealersMarkers(d.id);
    }

    const formEl = document.getElementById('dealer_name');
    if (formEl) formEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }

  function resetDealerForm() {
    document.getElementById('dealer_id').value = '';
    document.getElementById('dealer_name').value = '';
    document.getElementById('dealer_badge').value = 'Автосервис и замена масла';
    document.getElementById('dealer_address').value = '';
    document.getElementById('dealer_phone').value = '+7 (707) 523-65-25';
    document.getElementById('dealer_lat').value = '43.255000';
    document.getElementById('dealer_lng').value = '76.912000';
    document.getElementById('dealer_dgis').value = '';
    if (dealerMap && currentMarker) {
      currentMarker.setLatLng([43.2550, 76.9120]);
      dealerMap.flyTo([43.2550, 76.9120], 12);
      updateCoordsPreview(43.2550, 76.9120);
      renderOtherDealersMarkers();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDealerMap);
  } else {
    initDealerMap();
  }

  // =================== DEEPSEEK AI-АССИСТЕНТ + ГОЛОСОВОЕ УПРАВЛЕНИЕ (🎤) ===================
  const AI_STORAGE_KEY = 'lopal_deepseek_chat_v1';
  let aiChatHistory = [];
  let aiIsSending = false;
  let aiRecognition = null;
  let aiIsRecording = false;
  let aiActiveVoiceTarget = 'main';
  let aiCurrentModel = '<?= htmlspecialchars($settings['deepseek_model'] ?? 'deepseek-flash') ?>';

  async function changeAiModel(model) {
    aiCurrentModel = model;
    const sel1 = document.getElementById('aiModelSelect');
    const sel2 = document.getElementById('aiDrawerModelSelect');
    if (sel1 && sel1.value !== model) sel1.value = model;
    if (sel2 && sel2.value !== model) sel2.value = model;

    try {
      const resp = await fetch('../api/ai_agent.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_model', model: model })
      });
      const data = await resp.json();
      if (data.ok) {
        const labels = {
          'deepseek-flash': '⚡ DeepSeek V4.1-Flash-1017 (быстрая)',
          'deepseek-chat': '🚀 DeepSeek-V3 (флагманская)',
          'deepseek-reasoner': '🔬 DeepSeek-R1 (глубокое мышление)'
        };
        const modelLabel = labels[model] || model;
        aiChatHistory.push({
          role: 'assistant',
          content: 'ℹ️ Модель переключена на **' + modelLabel + '**. Все последующие текстовые и голосовые команды будут выполняться через эту модель.'
        });
        saveAiHistory();
        renderAiMessages();
      }
    } catch (e) {
      console.error('Ошибка переключения модели:', e);
    }
  }

  try {
    const saved = sessionStorage.getItem(AI_STORAGE_KEY);
    if (saved) aiChatHistory = JSON.parse(saved);
  } catch (e) {}

  if (!Array.isArray(aiChatHistory) || aiChatHistory.length === 0) {
    aiChatHistory = [
      {
        role: 'assistant',
        content: 'Здравствуйте! Я ваш AI-ассистент на базе **DeepSeek**.\n\nВы можете писать команды текстом или нажать на **🎤 микрофон** и сказать голосом, что нужно сделать на сайте:\n• добавить или изменить новость;\n• добавить дилера или СТО на карту Казахстана;\n• обновить телефоны, контакты или график работы;\n• исправить любой текст на любой из 25 страниц сайта.',
        actions: []
      }
    ];
  }

  function saveAiHistory() {
    try {
      sessionStorage.setItem(AI_STORAGE_KEY, JSON.stringify(aiChatHistory.slice(-24)));
    } catch (e) {}
  }

  function clearAiHistory() {
    aiChatHistory = [
      {
        role: 'assistant',
        content: 'Диалог очищен. Чем могу помочь по управлению сайтом lopal.kz?',
        actions: []
      }
    ];
    saveAiHistory();
    renderAiMessages();
  }

  function formatAiText(text) {
    let safe = escapeHtml(text || '');
    safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    safe = safe.replace(/`([^`]+)`/g, '<code style="background:rgba(0,0,0,0.07);padding:2px 5px;border-radius:4px;font-size:12px;">$1</code>');
    safe = safe.replace(/\n/g, '<br>');
    return safe;
  }

  const TOOL_LABELS = {
    get_site_overview: '📊 Сводка по сайту',
    list_news: '📰 Чтение списка новостей',
    upsert_news: '📰 Публикация/обновление новости',
    delete_news: '🗑️ Удаление новости',
    list_products: '🛢️ Чтение каталога масел',
    upsert_product: '🛢️ Обновление товара в каталоге',
    delete_product: '🗑️ Удаление товара',
    list_dealers: '📍 Чтение базы дилеров',
    upsert_dealer: '📍 Добавление/обновление дилера на карте',
    delete_dealer: '🗑️ Удаление точки продаж',
    update_site_settings: '⚙️ Обновление глобальных контактов и графика',
    list_html_pages: '📄 Список страниц сайта',
    read_page_text: '🔍 Чтение текста страницы',
    replace_page_text: '✏️ Замена текста на странице',
    list_backups: '📦 Проверка резервных копий',
    rollback_site: '⏪ Откат версии сайта'
  };

  function renderAiContainer(containerEl) {
    if (!containerEl) return;
    let html = '';
    aiChatHistory.forEach(msg => {
      const isUser = msg.role === 'user';
      const align = isUser ? 'flex-end' : 'flex-start';
      const bg = isUser ? '#00843D' : '#FFFFFF';
      const color = isUser ? '#FFFFFF' : '#0F172A';
      const border = isUser ? 'none' : '1px solid #E2E8F0';

      let actionsHtml = '';
      if (!isUser && Array.isArray(msg.actions) && msg.actions.length > 0) {
        actionsHtml = '<div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px;">' +
          msg.actions.map(a => {
            const label = TOOL_LABELS[a.tool] || a.tool;
            return `<span style="display:inline-flex;align-items:center;gap:4px;font-size:11.5px;font-weight:700;background:#DCFCE7;color:#166534;padding:3px 9px;border-radius:99px;border:1px solid #BBF7D0;">✅ ${escapeHtml(label)}</span>`;
          }).join('') +
        '</div>';
      }

      html += `
        <div style="display:flex; flex-direction:column; align-items:${align};">
          <div style="max-width:85%; background:${bg}; color:${color}; border:${border}; padding:11px 15px; border-radius:14px; font-size:13.5px; line-height:1.5; box-shadow:0 2px 5px rgba(15,23,42,0.04);">
            ${actionsHtml}
            <div>${formatAiText(msg.content)}</div>
          </div>
        </div>
      `;
    });

    if (aiIsSending) {
      html += `
        <div style="display:flex; align-items:flex-start;">
          <div style="background:#FFFFFF; color:#475569; border:1px solid #E2E8F0; padding:10px 15px; border-radius:14px; font-size:13px; font-weight:600;">
            ⏳ DeepSeek анализирует запрос и выполняет изменения на сайте...
          </div>
        </div>
      `;
    }

    containerEl.innerHTML = html;
    containerEl.scrollTop = containerEl.scrollHeight;
  }

  function renderAiMessages() {
    renderAiContainer(document.getElementById('aiMainChatMessages'));
    renderAiContainer(document.getElementById('aiDrawerChatMessages'));
  }

  function toggleAiKeyBox() {
    const box = document.getElementById('aiKeyConfigBox');
    if (box) {
      box.style.display = (box.style.display === 'none') ? 'block' : 'none';
    }
  }

  async function saveDeepSeekKey() {
    const input = document.getElementById('deepseek_api_key_input');
    if (!input) return;
    const key = input.value.trim();
    try {
      const resp = await fetch('../api/ai_agent.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'save_key', api_key: key })
      });
      const data = await resp.json();
      if (data.ok) {
        const badge = document.getElementById('aiKeyStatusBadge');
        if (badge) {
          if (data.hasKey) {
            badge.style.background = '#DCFCE7';
            badge.style.color = '#166534';
            badge.textContent = '🟢 Подключен (' + data.maskedKey + ')';
            const box = document.getElementById('aiKeyConfigBox');
            if (box) box.style.display = 'none';
          } else {
            badge.style.background = '#FEF3C7';
            badge.style.color = '#92400E';
            badge.textContent = '🔑 Требуется API-ключ DeepSeek';
          }
        }
        alert(data.hasKey ? '✅ API-ключ DeepSeek успешно сохранен!' : 'Ключ очищен.');
      }
    } catch (e) {
      alert('Ошибка сохранения ключа: ' + e.message);
    }
  }

  function fillAiPrompt(text) {
    const input = document.getElementById('aiMainInput') || document.getElementById('aiDrawerInput');
    if (input) {
      input.value = text;
      input.focus();
    }
  }

  function sendQuickAiPrompt(text) {
    const input = document.getElementById('aiMainInput') || document.getElementById('aiDrawerInput');
    if (input) {
      input.value = text;
      sendAiMessage(document.getElementById('aiMainInput') ? 'main' : 'drawer');
    }
  }

  async function sendAiMessage(source = 'main') {
    if (aiIsSending) return;
    const inputEl = document.getElementById(source === 'main' ? 'aiMainInput' : 'aiDrawerInput');
    if (!inputEl) return;
    const text = inputEl.value.trim();
    if (!text) return;

    if (aiIsRecording) stopAiVoice();

    inputEl.value = '';
    aiChatHistory.push({ role: 'user', content: text });
    aiIsSending = true;
    saveAiHistory();
    renderAiMessages();

    try {
      const resp = await fetch('../api/ai_agent.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'chat',
          model: aiCurrentModel,
          messages: aiChatHistory.map(m => ({ role: m.role, content: m.content }))
        })
      });
      const data = await resp.json();
      aiIsSending = false;

      if (!data.ok) {
        if (data.needKey) {
          const box = document.getElementById('aiKeyConfigBox');
          if (box) box.style.display = 'block';
        }
        aiChatHistory.push({
          role: 'assistant',
          content: '⚠️ ' + (data.error || 'Произошла ошибка при обращении к DeepSeek.'),
          actions: data.actions || []
        });
      } else {
        aiChatHistory.push({
          role: 'assistant',
          content: data.reply || 'Выполнено!',
          actions: data.actions || []
        });
        if (data.siteChanged) {
          const reloadNotice = document.getElementById('aiReloadNotice');
          if (reloadNotice) reloadNotice.style.display = 'flex';
        }
      }
    } catch (err) {
      aiIsSending = false;
      aiChatHistory.push({
        role: 'assistant',
        content: '⚠️ Ошибка сети: ' + err.message,
        actions: []
      });
    }

    saveAiHistory();
    renderAiMessages();
  }

  function toggleAiDrawer(forceState) {
    const drawer = document.getElementById('aiDrawerBox');
    if (!drawer) return;
    const show = (typeof forceState === 'boolean') ? forceState : (drawer.style.display === 'none');
    drawer.style.display = show ? 'flex' : 'none';
    if (show) renderAiMessages();
  }

  // Голосовой ввод через браузерный Web Speech API (ru-RU)
  function toggleAiVoice(target = 'main') {
    if (aiIsRecording) {
      stopAiVoice();
      return;
    }

    const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SpeechRec) {
      alert('Ваш браузер не поддерживает голосовой ввод Web Speech API. Пожалуйста, используйте Chrome, Edge или Safari.');
      return;
    }

    aiActiveVoiceTarget = target;
    aiRecognition = new SpeechRec();
    aiRecognition.lang = 'ru-RU';
    aiRecognition.interimResults = true;
    aiRecognition.continuous = false;

    const inputEl = document.getElementById(target === 'main' ? 'aiMainInput' : 'aiDrawerInput');
    const baseText = inputEl ? (inputEl.value ? inputEl.value + ' ' : '') : '';

    aiRecognition.onstart = function () {
      aiIsRecording = true;
      updateVoiceUi(true);
    };

    aiRecognition.onresult = function (event) {
      let transcript = '';
      for (let i = event.resultIndex; i < event.results.length; i++) {
        transcript += event.results[i][0].transcript;
      }
      if (inputEl) {
        inputEl.value = baseText + transcript;
      }
    };

    aiRecognition.onerror = function () {
      aiIsRecording = false;
      updateVoiceUi(false);
    };

    aiRecognition.onend = function () {
      const wasRec = aiIsRecording;
      aiIsRecording = false;
      updateVoiceUi(false);
      const autoSendEl = document.getElementById('aiAutoSendAfterVoice');
      const shouldAutoSend = !autoSendEl || autoSendEl.checked;
      if (wasRec && shouldAutoSend && inputEl && inputEl.value.trim().length > 1) {
        sendAiMessage(aiActiveVoiceTarget);
      }
    };

    aiRecognition.start();
  }

  function stopAiVoice() {
    if (aiRecognition && aiIsRecording) {
      aiIsRecording = false;
      try { aiRecognition.stop(); } catch (e) {}
    }
    updateVoiceUi(false);
  }

  function updateVoiceUi(recording) {
    const mainBtn = document.getElementById('aiMainMicBtn');
    const drawerBtn = document.getElementById('aiDrawerMicBtn');
    const banner = document.getElementById('aiVoiceListeningBanner');
    [mainBtn, drawerBtn].forEach(btn => {
      if (!btn) return;
      btn.style.background = recording ? '#DC2626' : '#F1F5F9';
      btn.style.color = recording ? '#FFFFFF' : '#0F172A';
      btn.style.borderColor = recording ? '#DC2626' : '#CBD5E1';
      btn.textContent = recording ? '⏹' : '🎤';
    });
    if (banner) {
      banner.style.display = recording ? 'flex' : 'none';
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', renderAiMessages);
  } else {
    renderAiMessages();
  }
  </script>
<?php endif; ?>

</body>
</html>

