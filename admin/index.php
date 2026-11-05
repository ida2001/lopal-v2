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
            'badge' => trim($_POST['badge'] ?: 'Точка продаж и ПЗМ'),
            'name' => trim($_POST['name'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'phone' => $phone,
            'phoneHref' => $phoneHref,
            'coords' => [$lat, $lng],
            'dgis' => trim($_POST['dgis'] ?: ('https://2gis.kz/almaty/search/' . rawurlencode($_POST['address'] ?? '')))
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
        $new['company_name'] = trim($_POST['company_name'] ?? $old['company_name']);
        $new['address'] = trim($_POST['address'] ?? $old['address']);
        $new['phone_main'] = trim($_POST['phone_main'] ?? $old['phone_main']);
        $new['phone_main_href'] = '+' . preg_replace('/\D+/', '', $new['phone_main']);
        $new['phone_service'] = trim($_POST['phone_service'] ?? $old['phone_service']);
        $new['phone_service_href'] = '+' . preg_replace('/\D+/', '', $new['phone_service']);
        $new['email'] = trim($_POST['email'] ?? $old['email']);
        $new['whatsapp'] = preg_replace('/\D+/', '', $_POST['whatsapp'] ?? $old['whatsapp']);
        $new['instagram'] = trim($_POST['instagram'] ?? $old['instagram']);
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
$snapshots = $isLogged ? array_map('basename', glob(LOPAL_BACKUPS_DIR . '/snap_*', GLOB_ONLYDIR) ?: []) : [];
rsort($snapshots);
$activeTab = $_GET['tab'] ?? ($_POST['active_tab'] ?? 'news');
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Панель управления — LOPAL Казахстан (Headless CMS & MCP)</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
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
      <p class="sub">LOPAL Казахстан · Headless CMS & MCP</p>
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
      <span class="badge-cms">Headless CMS + ChatGPT MCP</span>
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
      <a href="?tab=news" class="tab-btn <?= $activeTab === 'news' ? 'active' : '' ?>">📰 Новости (<?= count($newsList) ?>)</a>
      <a href="?tab=products" class="tab-btn <?= $activeTab === 'products' ? 'active' : '' ?>">🛢️ Каталог масел (<?= count($productsList) ?>)</a>
      <a href="?tab=dealers" class="tab-btn <?= $activeTab === 'dealers' ? 'active' : '' ?>">📍 Точки продаж и СТО (<?= count($dealersList) ?>)</a>
      <a href="?tab=settings" class="tab-btn <?= $activeTab === 'settings' ? 'active' : '' ?>">⚙️ Контакты и Настройки</a>
      <a href="?tab=mcp" class="tab-btn <?= $activeTab === 'mcp' ? 'active' : '' ?>">🤖 Подключение ChatGPT (MCP)</a>
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
          <div class="grid-2">
            <div class="grid-2">
              <div>
                <label>Широта (Latitude)</label>
                <input type="text" name="lat" id="dealer_lat" value="43.2550">
              </div>
              <div>
                <label>Долгота (Longitude)</label>
                <input type="text" name="lng" id="dealer_lng" value="76.9120">
              </div>
            </div>
            <div>
              <label>Ссылка на 2ГИС (опционально)</label>
              <input type="text" name="dgis" id="dealer_dgis" placeholder="Автоматически сгенерируется по адресу">
            </div>
          </div>
          <button type="submit" class="btn btn-green">💾 Сохранить точку на карте</button>
        </form>
      </div>

      <div class="card">
        <h2>Действующие точки продаж и СТО (<?= count($dealersList) ?>)</h2>
        <?php foreach ($dealersList as $d): ?>
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
          <button type="submit" class="btn btn-green">💾 Применить ко всем страницам сайта</button>
        </form>
      </div>
    <?php endif; ?>

    <?php if ($activeTab === 'mcp'): ?>
      <div class="card">
        <h2>🤖 Подключение ChatGPT по протоколу MCP / OpenAPI Actions</h2>
        <p class="sub">Ваш сайт оснащен двусторонним шлюзом искусственного интеллекта. Вы можете управлять новостями, товарами, дилерами и контактами прямо из диалога с ChatGPT.</p>

        <h3 style="font-size:15px;margin-bottom:8px;">1. Адрес MCP-сервера (Streamable HTTP JSON-RPC 2.0):</h3>
        <code class="code-box">https://lopal.kz/api/mcp.php</code>

        <h3 style="font-size:15px;margin-bottom:8px;">2. Ссылка для импорта схемы в ChatGPT (Custom GPT ➔ Actions ➔ Import from URL):</h3>
        <code class="code-box">https://lopal.kz/api/mcp.php?openapi=1</code>

        <h3 style="font-size:15px;margin-bottom:8px;">3. Секретный ключ авторизации (Bearer Token / API Key):</h3>
        <code class="code-box"><?= htmlspecialchars(LOPAL_MCP_API_KEY) ?></code>

        <h3 style="font-size:15px;margin:18px 0 8px;">Примеры команд, которые можно писать в ChatGPT:</h3>
        <ul style="padding-left:20px;font-size:14px;color:#334155;line-height:1.8;">
          <li><em>«Опубликуй на lopal.kz новость о поступлении антифриза Lopal Coolion SE01 на склад в Алматы»</em></li>
          <li><em>«Покажи список всех точек продаж на карте lopal.kz и добавь новое СТО в Алматы»</em></li>
          <li><em>«Добавь новое моторное масло в каталог на сайте»</em></li>
          <li><em>«Откати сайт к исходному состоянию»</em></li>
        </ul>
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

  </div>

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
  function editDealer(d) {
    document.getElementById('dealer_id').value = d.id || '';
    document.getElementById('dealer_name').value = d.name || '';
    document.getElementById('dealer_badge').value = d.badge || '';
    document.getElementById('dealer_address').value = d.address || '';
    document.getElementById('dealer_phone').value = d.phone || '';
    document.getElementById('dealer_lat').value = (d.coords && d.coords[0]) ? d.coords[0] : 43.255;
    document.getElementById('dealer_lng').value = (d.coords && d.coords[1]) ? d.coords[1] : 76.912;
    document.getElementById('dealer_dgis').value = d.dgis || '';
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }
  </script>
<?php endif; ?>

</body>
</html>
