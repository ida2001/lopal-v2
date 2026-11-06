<?php
/**
 * LOPAL KAZAKHSTAN (lopal.kz) — Визуальный In-Place конструктор и редактор страниц
 * Позволяет редактировать любую из 25+ страниц сайта кликом по тексту, картинкам и баннерам,
 * а также добавлять специфические блоки (Аккордеон FAQ, Таблица характеристик, Видео YouTube, Промо-баннеры).
 */

session_start();
require_once dirname(__DIR__) . '/cms/builder.php';

if (empty($_SESSION['lopal_admin_logged'])) {
    header('Location: index.php');
    exit;
}

$requestedPage = basename($_GET['page'] ?? 'index.html');
if ($requestedPage === '' || substr($requestedPage, -5) !== '.html' || !file_exists(LOPAL_ROOT_DIR . '/' . $requestedPage)) {
    $requestedPage = 'index.html';
}

// Собираем список всех страниц сайта для быстрого переключения
$allHtmlFiles = glob(LOPAL_ROOT_DIR . '/*.html') ?: [];
$pageNamesMap = [
    'index.html' => '🏠 Главная страница (index.html)',
    'products.html' => '🛢️ Каталог продукции (products.html)',
    'selector.html' => '🔍 Подбор масла (selector.html)',
    'technologies.html' => '🔬 Технологии R&D (technologies.html)',
    'certificates.html' => '📜 Допуски OEM и сертификаты (certificates.html)',
    'service-points.html' => '📍 Точки продаж и СТО (service-points.html)',
    'news.html' => '📰 Новости компании (news.html)',
    'partners.html' => '🤝 Партнёрам и дилерам (partners.html)',
    'contacts.html' => '📞 Контакты (contacts.html)',
    'about.html' => '🏢 О компании (about.html)',
    'media.html' => '🎬 Медиа и материалы (media.html)',
];
$pagesList = [];
foreach ($allHtmlFiles as $f) {
    $b = basename($f);
    if ($b === 'readme.html') continue;
    $label = $pageNamesMap[$b] ?? ('📄 ' . $b);
    $pagesList[$b] = $label;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Визуальный редактор: <?= htmlspecialchars($requestedPage) ?> — LOPAL CMS</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: #0F172A;
      color: #F8FAFC;
      height: 100vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    /* Верхняя панель конструктора */
    .ve-topbar {
      min-height: 52px;
      height: 52px;
      background: #0F172A;
      border-bottom: 1px solid #334155;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 10px;
      gap: 6px;
      flex-shrink: 0;
      z-index: 100;
      width: 100%;
      box-sizing: border-box;
      overflow-x: auto;
      overflow-y: hidden;
      scrollbar-width: thin;
      scrollbar-color: #334155 transparent;
    }
    .ve-topbar::-webkit-scrollbar { height: 3px; }
    .ve-topbar::-webkit-scrollbar-thumb { background: #334155; border-radius: 3px; }

    .ve-topbar-left, .ve-topbar-center, .ve-topbar-right {
      display: flex;
      align-items: center;
      gap: 5px;
      flex-shrink: 0;
    }
    .ve-topbar-left {
      flex: 0 1 auto;
      min-width: 0;
    }
    .ve-topbar-center {
      flex: 1 1 auto;
      justify-content: center;
      min-width: 0;
    }
    .ve-topbar-right {
      margin-left: auto;
      flex-shrink: 0;
      display: flex;
      align-items: center;
      gap: 5px;
    }
    .ve-brand {
      font-weight: 800;
      font-size: 13px;
      color: #fff;
      display: flex;
      align-items: center;
      gap: 4px;
      padding-right: 6px;
      border-right: 1px solid #334155;
      white-space: nowrap;
      flex-shrink: 0;
    }
    .ve-brand span { color: #10B981; }
    .ve-page-select {
      background: #1E293B;
      color: #F8FAFC;
      border: 1px solid #475569;
      border-radius: 7px;
      padding: 5px 8px;
      font-size: 12px;
      font-weight: 600;
      cursor: pointer;
      max-width: 170px;
      min-width: 105px;
      text-overflow: ellipsis;
      white-space: nowrap;
      overflow: hidden;
      flex-shrink: 1;
    }

    /* Кнопки панели */
    .ve-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
      padding: 6px 9px;
      border-radius: 7px;
      font-size: 12px;
      font-weight: 700;
      cursor: pointer;
      border: 1px solid #334155;
      background: #1E293B;
      color: #E2E8F0;
      text-decoration: none;
      transition: all 0.15s ease;
      white-space: nowrap;
      flex-shrink: 0;
      line-height: 1.2;
    }
    .ve-btn:hover { background: #334155; color: #fff; }
    .ve-btn.is-active { background: #00843D; border-color: #10B981; color: #fff; }
    .ve-btn-green {
      background: #00843D;
      border-color: #059669;
      color: #fff;
      font-weight: 800;
      box-shadow: 0 2px 8px rgba(0, 132, 61, 0.4);
    }
    .ve-btn-green:hover {
      background: #059669;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.5);
    }
    .ve-btn-gold { background: #D97706; border-color: #F59E0B; color: #fff; }
    .ve-btn-gold:hover { background: #B45309; }
    .ve-btn-icon { padding: 5px 8px; font-size: 12px; min-width: 27px; }

    /* Группа кнопок форматирования B / I / U / Link */
    .ve-format-group {
      display: inline-flex;
      align-items: center;
      background: #1E293B;
      border: 1px solid #334155;
      border-radius: 7px;
      overflow: hidden;
      flex-shrink: 0;
    }
    .ve-format-group button {
      background: transparent;
      border: none;
      border-right: 1px solid #334155;
      color: #E2E8F0;
      font-size: 12px;
      font-weight: 700;
      padding: 5px 8px;
      min-width: 27px;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      transition: background 0.15s;
    }
    .ve-format-group button:last-child {
      border-right: none;
    }
    .ve-format-group button:hover {
      background: #334155;
      color: #fff;
    }

    /* Адаптивность для экранов 1366px, 1280px, 1024px и зума браузера */
    .ve-label-short { display: none; }
    @media (max-width: 1420px) {
      .ve-brand span { display: none; }
      .ve-label-full { display: none !important; }
      .ve-label-short { display: inline !important; }
      .ve-page-select { max-width: 140px; }
      .ve-btn { padding: 5px 8px; font-size: 11.5px; }
    }
    @media (max-width: 1140px) {
      .ve-format-group, .ve-sep-format { display: none !important; }
      .ve-page-select { max-width: 120px; }
      .ve-brand { display: none; }
    }
    @media (max-width: 900px) {
      .ve-topbar-left a span:last-child { display: none; }
    }

    /* Контекстная панель выбранного элемента (внизу экрана) */
    .ve-context-bar {
      position: fixed;
      bottom: 18px;
      left: 50%;
      transform: translateX(-50%);
      background: #0F172A;
      border: 1px solid #475569;
      border-radius: 14px;
      padding: 10px 16px;
      display: none;
      align-items: center;
      gap: 10px;
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.65);
      z-index: 200;
      flex-wrap: wrap;
      max-width: 95vw;
    }
    .ve-context-bar.is-visible { display: flex; }
    .ve-context-label {
      font-size: 11.5px;
      font-weight: 800;
      text-transform: uppercase;
      padding: 4px 9px;
      border-radius: 6px;
      background: rgba(16, 185, 129, 0.15);
      color: #34D399;
      border: 1px solid rgba(16, 185, 129, 0.3);
    }
    .ve-input {
      background: #1E293B;
      border: 1px solid #475569;
      color: #fff;
      padding: 6px 10px;
      border-radius: 7px;
      font-size: 12.5px;
      min-width: 210px;
    }

    /* Рабочая область с iframe */
    .ve-workspace {
      flex: 1;
      position: relative;
      background: #1E293B;
      display: flex;
      justify-content: center;
      overflow: hidden;
    }
    #editorFrame {
      width: 100%;
      height: 100%;
      border: 0;
      background: #FFFFFF;
      transition: width 0.25s ease;
    }

    /* Модальные окна редактора (Библиотека блоков и Медиатека) */
    .ve-modal-backdrop {
      position: fixed;
      inset: 0;
      background: rgba(15, 23, 42, 0.8);
      backdrop-filter: blur(5px);
      z-index: 500;
      display: none;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .ve-modal-backdrop.is-open { display: flex; }
    .ve-modal {
      background: #1E293B;
      border: 1px solid #475569;
      border-radius: 18px;
      width: min(880px, 100%);
      max-height: 88vh;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      box-shadow: 0 25px 70px rgba(0, 0, 0, 0.65);
    }
    .ve-modal-header {
      padding: 16px 22px;
      background: #0F172A;
      border-bottom: 1px solid #334155;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .ve-modal-header h3 { font-size: 16px; font-weight: 800; color: #fff; }
    .ve-modal-body {
      padding: 22px;
      overflow-y: auto;
      flex: 1;
    }

    /* Сетка шаблонов блоков */
    .ve-blocks-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 16px;
    }
    .ve-block-card {
      background: #0F172A;
      border: 1px solid #334155;
      border-radius: 14px;
      padding: 18px;
      cursor: pointer;
      transition: all 0.2s ease;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .ve-block-card:hover {
      border-color: #10B981;
      transform: translateY(-2px);
      box-shadow: 0 10px 25px rgba(16, 185, 129, 0.15);
    }
    .ve-block-icon {
      font-size: 26px;
      margin-bottom: 4px;
    }
    .ve-block-title {
      font-size: 14.5px;
      font-weight: 800;
      color: #F8FAFC;
    }
    .ve-block-desc {
      font-size: 12.5px;
      color: #94A3B8;
      line-height: 1.45;
    }

    /* Сетка медиафайлов */
    .ve-upload-dropzone {
      border: 2px dashed #10B981;
      background: rgba(16, 185, 129, 0.07);
      border-radius: 14px;
      padding: 24px;
      text-align: center;
      margin-bottom: 20px;
      cursor: pointer;
      transition: background 0.2s;
    }
    .ve-upload-dropzone:hover { background: rgba(16, 185, 129, 0.14); }
    .ve-media-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
      gap: 12px;
    }
    .ve-media-item {
      background: #0F172A;
      border: 2px solid #334155;
      border-radius: 10px;
      overflow: hidden;
      cursor: pointer;
      transition: all 0.15s;
    }
    .ve-media-item:hover { border-color: #10B981; transform: scale(1.02); }
    .ve-media-thumb {
      width: 100%;
      height: 105px;
      object-fit: cover;
      background: #020617;
      display: block;
    }
    .ve-media-name {
      padding: 6px 8px;
      font-size: 11px;
      color: #CBD5E1;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .ve-toast {
      position: fixed;
      top: 72px;
      right: 20px;
      background: #00843D;
      color: #fff;
      padding: 12px 20px;
      border-radius: 10px;
      font-size: 13.5px;
      font-weight: 700;
      box-shadow: 0 10px 30px rgba(0,0,0,0.4);
      z-index: 999;
      display: none;
    }
  </style>
</head>
<body>

  <!-- Верхняя панель студии визуального редактирования -->
  <header class="ve-topbar">
    <div class="ve-topbar-left">
      <a href="index.php?tab=pages" class="ve-btn" title="Вернуться в панель управления (Админка)">
        <span>←</span>
        <span class="ve-label-full"> В админку</span>
        <span class="ve-label-short"> Админка</span>
      </a>
      <div class="ve-brand">LOPAL <span>Studio</span></div>
      <select class="ve-page-select" id="pageSwitcher" onchange="switchPage(this.value)" title="Выбрать страницу для редактирования">
        <?php foreach ($pagesList as $file => $label): ?>
          <option value="<?= htmlspecialchars($file) ?>" <?= ($file === $requestedPage) ? 'selected' : '' ?>>
            <?= htmlspecialchars($label) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="ve-topbar-center">
      <button type="button" class="ve-btn is-active" id="btnEditMode" onclick="toggleEditMode(true)" title="Режим интерактивной правки текста и элементов">
        <span>✏️</span>
        <span class="ve-label-full"> Режим правки</span>
        <span class="ve-label-short"> Правка</span>
      </button>
      <button type="button" class="ve-btn" id="btnPreviewMode" onclick="toggleEditMode(false)" title="Предпросмотр без управляющих элементов">
        <span>👁️</span>
        <span> Просмотр</span>
      </button>

      <span class="ve-sep-format" style="width:1px; height:20px; background:#334155; margin:0 2px;"></span>

      <div class="ve-format-group" title="Форматирование выделенного текста">
        <button type="button" onclick="execFormat('bold')" title="Жирный (Ctrl+B)"><b>B</b></button>
        <button type="button" onclick="execFormat('italic')" title="Курсив (Ctrl+I)"><i>I</i></button>
        <button type="button" onclick="execFormat('underline')" title="Подчеркнутый"><u>U</u></button>
        <button type="button" onclick="promptLink()" title="Добавить/изменить ссылку">🔗</button>
      </div>

      <span class="ve-sep-format" style="width:1px; height:20px; background:#334155; margin:0 2px;"></span>

      <button type="button" class="ve-btn ve-btn-gold" onclick="openBlocksModal()" title="Добавить готовый блок (FAQ, Таблица, Видео, Баннер...)">
        <span>➕</span>
        <span class="ve-label-full"> Добавить блок</span>
        <span class="ve-label-short"> Блок</span>
      </button>
      <button type="button" class="ve-btn" onclick="openMediaModal(null)" title="Медиатека изображений и баннеров">
        <span>🖼️</span>
        <span class="ve-label-full"> Картинки / Баннеры</span>
        <span class="ve-label-short"> Медиа</span>
      </button>
    </div>

    <div class="ve-topbar-right">
      <button type="button" class="ve-btn" onclick="openGuideModal()" title="Инструкция по визуальному редактору">
        <span>❓</span>
        <span class="ve-label-full"> Инструкция</span>
      </button>
      <a href="../<?= htmlspecialchars($requestedPage) ?>" target="_blank" class="ve-btn" title="Открыть страницу на сайте в новой вкладке">
        <span>↗</span>
        <span class="ve-label-full"> На сайте</span>
        <span class="ve-label-short"> Сайт</span>
      </a>
      <button type="button" class="ve-btn ve-btn-green" id="savePageBtn" onclick="saveCurrentPage()" title="Сохранить изменения на сервере (Ctrl+S)">
        <span>💾</span>
        <span class="ve-label-full"> Сохранить изменения</span>
        <span class="ve-label-short"> Сохранить</span>
      </button>
    </div>
  </header>

  <!-- Рабочая область со страницей -->
  <div class="ve-workspace">
    <iframe id="editorFrame" src="../<?= htmlspecialchars($requestedPage) ?>?ve_ts=<?= time() ?>"></iframe>
  </div>

  <!-- Всплывающая нижняя панель контекстных действий для выбранного элемента -->
  <div class="ve-context-bar" id="contextBar">
    <span class="ve-context-label" id="ctxTagName">Элемент</span>

    <!-- Для ссылок и кнопок -->
    <div id="ctxLinkControls" style="display:none; align-items:center; gap:6px;">
      <span style="font-size:12px; color:#94A3B8;">URL ссылки:</span>
      <input type="text" id="ctxLinkHref" class="ve-input" placeholder="products.html или https://...">
      <button type="button" class="ve-btn ve-btn-green" onclick="applyLinkHref()">Применить</button>
    </div>

    <!-- Для картинок -->
    <div id="ctxImgControls" style="display:none; align-items:center; gap:6px;">
      <button type="button" class="ve-btn ve-btn-green" onclick="replaceSelectedImage()">📷 Заменить картинку / баннер</button>
      <input type="text" id="ctxImgAlt" class="ve-input" placeholder="Alt-описание фото..." style="min-width:150px;" onchange="applyImgAlt()">
    </div>

    <!-- Для таблиц -->
    <div id="ctxTableControls" style="display:none; align-items:center; gap:6px;">
      <button type="button" class="ve-btn" onclick="tableAddRow()">+ Строка</button>
      <button type="button" class="ve-btn" onclick="tableAddCol()">+ Столбец</button>
      <button type="button" class="ve-btn" onclick="tableDelRow()">− Строка</button>
    </div>

    <!-- Для FAQ-аккордеона -->
    <div id="ctxFaqControls" style="display:none; align-items:center; gap:6px;">
      <button type="button" class="ve-btn ve-btn-green" onclick="faqAddItem()">+ Добавить вопрос FAQ</button>
    </div>

    <!-- Для видео YouTube -->
    <div id="ctxVideoControls" style="display:none; align-items:center; gap:6px;">
      <button type="button" class="ve-btn ve-btn-gold" onclick="changeYoutubeUrl()">🎬 Изменить ссылку YouTube</button>
    </div>

    <!-- Общие действия с выбранным элементом / карточкой -->
    <button type="button" class="ve-btn" onclick="duplicateSelectedElement()" title="Дублировать элемент или карточку">📋 Дублировать</button>
    <button type="button" class="ve-btn" style="background:#7F1D1D; border-color:#DC2626; color:#FCA5A5;" onclick="deleteSelectedElement()" title="Удалить выбранный элемент">🗑️ Удалить</button>
    <button type="button" class="ve-btn ve-btn-icon" onclick="hideContextBar()" title="Скрыть панель">✕</button>
  </div>

  <!-- МОДАЛЬНОЕ ОКНО: БИБЛИОТЕКА ГОТОВЫХ БЛОКОВ -->
  <div class="ve-modal-backdrop" id="blocksModal">
    <div class="ve-modal">
      <div class="ve-modal-header">
        <h3>➕ Добавить готовый блок на страницу</h3>
        <button type="button" class="ve-btn ve-btn-icon" onclick="closeBlocksModal()">✕</button>
      </div>
      <div class="ve-modal-body">
        <p style="font-size:13px; color:#94A3B8; margin-bottom:16px;">
          Выберите нужный блок — он будет добавлен на страницу, после чего вы сможете кликнуть по любому тексту или картинке и заменить их своими данными.
        </p>
        <div class="ve-blocks-grid">

          <div class="ve-block-card" onclick="insertBlock('promo_banner')">
            <div class="ve-block-icon">🖼️</div>
            <div class="ve-block-title">Широкий Промо-Баннер (Hero)</div>
            <div class="ve-block-desc">Крупный баннер с фоновым изображением, бейджем, заголовком, описанием и кнопками действия.</div>
          </div>

          <div class="ve-block-card" onclick="insertBlock('faq')">
            <div class="ve-block-icon">❓</div>
            <div class="ve-block-title">Аккордеон «Вопрос-ответ (FAQ)»</div>
            <div class="ve-block-desc">Интерактивный раскрывающийся список популярных вопросов и ответов с кнопкой добавления новых пунктов.</div>
          </div>

          <div class="ve-block-card" onclick="insertBlock('specs_table')">
            <div class="ve-block-icon">📊</div>
            <div class="ve-block-title">Таблица характеристик</div>
            <div class="ve-block-desc">Фирменная таблица технических параметров, допусков, вязкости или комплектаций с добавлением строк.</div>
          </div>

          <div class="ve-block-card" onclick="insertBlock('youtube_video')">
            <div class="ve-block-icon">🎬</div>
            <div class="ve-block-title">Видео-баннер с YouTube</div>
            <div class="ve-block-desc">Секция с заголовком и широкоформатным адаптивным видеоплеером YouTube.</div>
          </div>

          <div class="ve-block-card" onclick="insertBlock('cards_3col')">
            <div class="ve-block-icon">🃏</div>
            <div class="ve-block-title">Сетка карточек / Преимущества</div>
            <div class="ve-block-desc">3 карточки в ряд с иллюстрациями или бейджами, заголовками и подробным описанием.</div>
          </div>

          <div class="ve-block-card" onclick="insertBlock('image_text_split')">
            <div class="ve-block-icon">📝</div>
            <div class="ve-block-title">Текст + Крупная картинка (2 колонки)</div>
            <div class="ve-block-desc">Двухколоночный блок: слева описание и преимущества, справа крупный баннер или фото продукта.</div>
          </div>

        </div>
      </div>
    </div>
  </div>

  <!-- МОДАЛЬНОЕ ОКНО: ЗАГРУЗКА КАРТИНОК И МЕДИАТЕКА -->
  <div class="ve-modal-backdrop" id="mediaModal">
    <div class="ve-modal">
      <div class="ve-modal-header">
        <h3 id="mediaModalTitle">🖼️ Загрузка и выбор изображения / баннера</h3>
        <button type="button" class="ve-btn ve-btn-icon" onclick="closeMediaModal()">✕</button>
      </div>
      <div class="ve-modal-body">
        <div class="ve-upload-dropzone" onclick="document.getElementById('mediaFileInput').click()">
          <input type="file" id="mediaFileInput" accept="image/*" style="display:none;" onchange="handleFileUpload(this.files)">
          <div style="font-size:28px; margin-bottom:6px;">📤</div>
          <div style="font-size:14.5px; font-weight:800; color:#F8FAFC; margin-bottom:4px;">Нажмите сюда, чтобы загрузить картинку или баннер с компьютера</div>
          <div style="font-size:12px; color:#94A3B8;">Поддерживаются форматы JPG, PNG, WebP, SVG, GIF</div>
        </div>

        <div style="margin-bottom:14px; display:flex; gap:8px; align-items:center;">
          <span style="font-size:12.5px; color:#94A3B8;">Или укажите прямой URL картинки:</span>
          <input type="text" id="customImageUrlInput" class="ve-input" placeholder="assets/branding/... или https://..." style="flex:1;">
          <button type="button" class="ve-btn ve-btn-green" onclick="selectMediaUrl(document.getElementById('customImageUrlInput').value)">Выбрать URL</button>
        </div>

        <h4 style="font-size:13px; color:#94A3B8; text-transform:uppercase; margin-bottom:10px;">Галерея изображений на сервере:</h4>
        <div class="ve-media-grid" id="mediaGalleryGrid">
          <div style="color:#94A3B8; font-size:13px;">Загрузка галереи...</div>
        </div>
      </div>
    </div>
  </div>

  <!-- МОДАЛЬНОЕ ОКНО: ИНСТРУКЦИЯ ПО РАБОТЕ С РЕДАКТОРОМ -->
  <div class="ve-modal-backdrop" id="guideModal">
    <div class="ve-modal" style="width: min(880px, 96vw);">
      <div class="ve-modal-header">
        <h3>📖 Инструкция по работе с визуальным редактором LOPAL</h3>
        <button type="button" class="ve-btn ve-btn-icon" onclick="closeGuideModal()">✕</button>
      </div>
      <div class="ve-modal-body" style="font-size:13px; line-height:1.6; color:#CBD5E1;">
        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap:14px;">
          
          <div style="background:#0F172A; border:1px solid #334155; border-radius:12px; padding:16px;">
            <h4 style="color:#10B981; margin-bottom:8px; display:flex; align-items:center; gap:6px; font-size:14px;">✏️ 1. Редактирование текстов</h4>
            <p style="margin-bottom:6px;">Кликните по любому заголовку, параграфу, бейджу или кнопке прямо на странице. Появится текстовый курсор — вы можете менять текст как в обычном текстовом редакторе.</p>
            <p style="color:#94A3B8; font-size:12px;">В верхней панели можно выделить текст и применить форматирование: <b>B</b> (жирный), <i>I</i> (курсив), <u>U</u> (подчеркнутый) или 🔗 (ссылка).</p>
          </div>

          <div style="background:#0F172A; border:1px solid #334155; border-radius:12px; padding:16px;">
            <h4 style="color:#38BDF8; margin-bottom:8px; display:flex; align-items:center; gap:6px; font-size:14px;">🖼️ 2. Замена картинок и баннеров</h4>
            <p style="margin-bottom:6px;">Кликните по любой фотографии или баннеру — внизу экрана появится панель управления с кнопкой <strong>«📷 Заменить картинку»</strong>.</p>
            <p style="color:#94A3B8; font-size:12px;">Вы можете выбрать готовое изображение из медиатеки сервера или загрузить новое фото прямо с компьютера.</p>
          </div>

          <div style="background:#0F172A; border:1px solid #334155; border-radius:12px; padding:16px;">
            <h4 style="color:#F59E0B; margin-bottom:8px; display:flex; align-items:center; gap:6px; font-size:14px;">➕ 3. Добавление готовых блоков</h4>
            <p style="margin-bottom:6px;">Нажмите золотую кнопку <strong>«➕ Добавить блок»</strong> в верхней панели:</p>
            <ul style="padding-left:18px; font-size:12px; color:#94A3B8; line-height:1.5;">
              <li><strong>Аккордеон FAQ:</strong> список частых вопросов и ответов с раскрытием по клику;</li>
              <li><strong>Таблица характеристик:</strong> таблица допусков и параметров с кнопками добавления строк;</li>
              <li><strong>Видео YouTube:</strong> широкоформатный плеер с адаптивным видео;</li>
              <li><strong>Hero-баннер и 3 колонки преимуществ.</strong></li>
            </ul>
          </div>

          <div style="background:#0F172A; border:1px solid #334155; border-radius:12px; padding:16px;">
            <h4 style="color:#A78BFA; margin-bottom:8px; display:flex; align-items:center; gap:6px; font-size:14px;">↕️ 4. Управление секциями страницы</h4>
            <p style="margin-bottom:6px;">При наведении курсора на любую секцию в правом верхнем углу всплывает панель управления:</p>
            <ul style="padding-left:18px; font-size:12px; color:#94A3B8; line-height:1.5;">
              <li><strong>⬆️ / ⬇️</strong> — поднять или опустить секцию по странице;</li>
              <li><strong>🖼️ Фон-баннер</strong> — установить или сменить фоновое изображение;</li>
              <li><strong>📋 Копия</strong> — продублировать секцию целиком;</li>
              <li><strong>🗑️ Удалить</strong> — удалить ненужный блок со страницы.</li>
            </ul>
          </div>

          <div style="background:#0F172A; border:1px solid #334155; border-radius:12px; padding:16px;">
            <h4 style="color:#34D399; margin-bottom:8px; display:flex; align-items:center; gap:6px; font-size:14px;">💾 5. Быстрое сохранение (Ctrl+S)</h4>
            <p style="margin-bottom:6px;">Нажмите зеленую кнопку <strong>«💾 Сохранить изменения»</strong> в правом верхнем углу.</p>
            <p style="color:#94A3B8; font-size:12px;">Также работает горячая клавиша <strong>Ctrl + S</strong> (Cmd + S) в любой момент редактирования прямо внутри страницы.</p>
          </div>

          <div style="background:#0F172A; border:1px solid #334155; border-radius:12px; padding:16px;">
            <h4 style="color:#F43F5E; margin-bottom:8px; display:flex; align-items:center; gap:6px; font-size:14px;">🤖 6. Создание страниц через AI</h4>
            <p style="margin-bottom:6px;">Вы можете поручить создание новой страницы AI-помощнику в админке:</p>
            <p style="color:#94A3B8; font-size:12px;">Скажите в микрофон (🎤) или напишите: <em>«Создай страницу "Гарантия" с адресом warranty.html и добавь в главное меню»</em>. Страница создастся и сразу появится в конструкторе!</p>
          </div>

        </div>
      </div>
    </div>
  </div>

  <div class="ve-toast" id="veToast"></div>

  <script>
  const currentPage = <?= json_encode($requestedPage) ?>;
  const frame = document.getElementById('editorFrame');
  let isEditMode = true;
  let selectedEl = null;
  let targetAfterSection = null;
  let mediaCallback = null;

  function showVeToast(msg, isError = false) {
    const t = document.getElementById('veToast');
    t.style.background = isError ? '#DC2626' : '#00843D';
    t.textContent = msg;
    t.style.display = 'block';
    clearTimeout(window.__veToastTimer);
    window.__veToastTimer = setTimeout(() => { t.style.display = 'none'; }, 3500);
  }

  function openGuideModal() {
    const m = document.getElementById('guideModal');
    if (m) m.classList.add('is-open');
  }

  function closeGuideModal() {
    const m = document.getElementById('guideModal');
    if (m) m.classList.remove('is-open');
  }

  function switchPage(page) {
    if (!page) return;
    window.location.href = 'visual_editor.php?page=' + encodeURIComponent(page);
  }

  // Инициализация визуального редактора внутри iframe после загрузки страницы
  frame.addEventListener('load', () => {
    const doc = frame.contentDocument;
    const win = frame.contentWindow;
    if (!doc) return;

    win.__LOPAL_VISUAL_EDITOR_ACTIVE = true;

    // Внедряем вспомогательные стили визуального редактора внутрь iframe
    let styleEl = doc.getElementById('lopalVeInjectedStyles');
    if (!styleEl) {
      styleEl = doc.createElement('style');
      styleEl.id = 'lopalVeInjectedStyles';
      styleEl.textContent = `
        body.ve-editing-active main [contenteditable="true"]:hover {
          outline: 1px dashed rgba(0, 132, 61, 0.65) !important;
          outline-offset: 3px !important;
          cursor: text !important;
        }
        body.ve-editing-active main [contenteditable="true"]:focus {
          outline: 2px solid #00843D !important;
          outline-offset: 4px !important;
          background-color: rgba(0, 132, 61, 0.03);
          border-radius: 4px;
        }
        body.ve-editing-active main img:hover {
          outline: 3px solid #F59E0B !important;
          outline-offset: 2px !important;
          cursor: pointer !important;
        }
        body.ve-editing-active .ve-selected-element {
          outline: 2px solid #0EA5E9 !important;
          outline-offset: 3px !important;
        }
        .ve-section-toolbar {
          position: absolute;
          top: 10px;
          right: 16px;
          z-index: 950;
          display: none;
          align-items: center;
          gap: 5px;
          background: rgba(15, 23, 42, 0.92);
          backdrop-filter: blur(6px);
          padding: 5px 8px;
          border-radius: 10px;
          border: 1px solid rgba(255,255,255,0.2);
          box-shadow: 0 8px 20px rgba(0,0,0,0.35);
          font-family: 'Inter', sans-serif;
        }
        body.ve-editing-active main > section,
        body.ve-editing-active main > div.section {
          position: relative !important;
        }
        body.ve-editing-active main > section:hover > .ve-section-toolbar,
        body.ve-editing-active main > div.section:hover > .ve-section-toolbar {
          display: flex !important;
        }
        .ve-sec-btn {
          background: #1E293B;
          color: #F8FAFC;
          border: 1px solid #475569;
          border-radius: 6px;
          padding: 4px 8px;
          font-size: 11.5px;
          font-weight: 700;
          cursor: pointer;
          line-height: 1.2;
        }
        .ve-sec-btn:hover { background: #00843D; border-color: #10B981; color: #fff; }
        .ve-sec-btn-del:hover { background: #DC2626; border-color: #EF4444; color: #fff; }
        body:not(.ve-editing-active) .ve-section-toolbar { display: none !important; }
      `;
      doc.head.appendChild(styleEl);
    }

    refreshEditableElements();

    // Перехват кликов внутри страницы в режиме редактирования
    doc.addEventListener('click', (e) => {
      if (!isEditMode) return;

      // Кнопки панели секции обрабатываются отдельно
      if (e.target.closest('.ve-section-toolbar')) return;

      const mainEl = doc.querySelector('main');
      if (!mainEl || !mainEl.contains(e.target)) return;

      // Запрещаем переход по ссылкам в режиме правки, чтобы их можно было редактировать
      const link = e.target.closest('a, button');
      if (link) {
        e.preventDefault();
        e.stopPropagation();
      }

      selectElementInPage(e.target);
    }, true);

    // Поддержка Ctrl+S внутри редактируемой страницы
    doc.addEventListener('keydown', (e) => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
        e.preventDefault();
        saveCurrentPage();
      }
    });
  });

  function refreshEditableElements() {
    const doc = frame.contentDocument;
    if (!doc) return;
    doc.body.classList.toggle('ve-editing-active', isEditMode);

    const mainEl = doc.querySelector('main');
    if (!mainEl) return;

    // 1. Делаем все текстовые узлы внутри <main> редактируемыми
    const textSelector = 'h1, h2, h3, h4, h5, h6, p, span, li, a, button, td, th, label, strong, em, blockquote, .kicker, .hero-tag, .spec-pill, .badge-tag, .product-category-title, .product-name, .product-desc';
    mainEl.querySelectorAll(textSelector).forEach(el => {
      if (el.closest('.ve-section-toolbar')) return;
      if (isEditMode) {
        el.setAttribute('contenteditable', 'true');
        el.setAttribute('spellcheck', 'false');
      } else {
        el.removeAttribute('contenteditable');
      }
    });

    // 2. Добавляем плавающую панель управления к каждой секции внутри <main>
    mainEl.querySelectorAll(':scope > section, :scope > div.section').forEach(sec => {
      let bar = sec.querySelector(':scope > .ve-section-toolbar');
      if (!bar) {
        bar = doc.createElement('div');
        bar.className = 've-section-toolbar ve-ui-only';
        bar.setAttribute('contenteditable', 'false');
        bar.innerHTML = `
          <button type="button" class="ve-sec-btn" data-sec-act="up" title="Переместить секцию выше">⬆️</button>
          <button type="button" class="ve-sec-btn" data-sec-act="down" title="Переместить секцию ниже">⬇️</button>
          <button type="button" class="ve-sec-btn" data-sec-act="bg" title="Установить или сменить фоновый баннер секции">🖼️ Фон-баннер</button>
          <button type="button" class="ve-sec-btn" data-sec-act="add" title="Вставить новый блок сразу под этой секцией">➕ Блок ниже</button>
          <button type="button" class="ve-sec-btn" data-sec-act="dup" title="Дублировать секцию">📋 Копия</button>
          <button type="button" class="ve-sec-btn ve-sec-btn-del" data-sec-act="del" title="Удалить эту секцию">🗑️ Удалить</button>
        `;
        bar.addEventListener('click', (ev) => {
          ev.preventDefault();
          ev.stopPropagation();
          const btn = ev.target.closest('[data-sec-act]');
          if (!btn) return;
          handleSectionAction(sec, btn.getAttribute('data-sec-act'));
        });
        sec.insertBefore(bar, sec.firstChild);
      }
    });
  }

  function handleSectionAction(sec, act) {
    const parent = sec.parentNode;
    if (act === 'up' && sec.previousElementSibling) {
      parent.insertBefore(sec, sec.previousElementSibling);
      showVeToast('Секция перемещена выше');
    } else if (act === 'down' && sec.nextElementSibling) {
      parent.insertBefore(sec.nextElementSibling, sec);
      showVeToast('Секция перемещена ниже');
    } else if (act === 'dup') {
      const clone = sec.cloneNode(true);
      const oldBar = clone.querySelector(':scope > .ve-section-toolbar');
      if (oldBar) oldBar.remove();
      parent.insertBefore(clone, sec.nextElementSibling);
      refreshEditableElements();
      showVeToast('Секция продублирована');
    } else if (act === 'del') {
      if (confirm('Вы уверены, что хотите удалить эту секцию со страницы?')) {
        sec.remove();
        hideContextBar();
        showVeToast('Секция удалена');
      }
    } else if (act === 'add') {
      targetAfterSection = sec;
      openBlocksModal();
    } else if (act === 'bg') {
      openMediaModal((url) => {
        if (!url) {
          sec.style.backgroundImage = 'none';
          showVeToast('Фоновый баннер убран');
        } else {
          sec.style.background = `linear-gradient(90deg, rgba(8, 14, 26, 0.72) 0%, rgba(8, 14, 26, 0.45) 60%, rgba(8, 14, 26, 0.25) 100%), url('${url}') center / cover no-repeat`;
          showVeToast('Фоновый баннер секции обновлен!');
        }
      });
    }
  }

  function selectElementInPage(el) {
    const doc = frame.contentDocument;
    if (selectedEl && selectedEl !== el) {
      selectedEl.classList.remove('ve-selected-element');
    }
    selectedEl = el;
    selectedEl.classList.add('ve-selected-element');

    const bar = document.getElementById('contextBar');
    const tagLabel = document.getElementById('ctxTagName');
    const linkCtrl = document.getElementById('ctxLinkControls');
    const imgCtrl = document.getElementById('ctxImgControls');
    const tblCtrl = document.getElementById('ctxTableControls');
    const faqCtrl = document.getElementById('ctxFaqControls');
    const vidCtrl = document.getElementById('ctxVideoControls');

    linkCtrl.style.display = 'none';
    imgCtrl.style.display = 'none';
    tblCtrl.style.display = 'none';
    faqCtrl.style.display = 'none';
    vidCtrl.style.display = 'none';

    const tag = el.tagName.toLowerCase();
    tagLabel.textContent = tag.toUpperCase();

    // Если кликнули по картинке
    if (tag === 'img') {
      tagLabel.textContent = '🖼️ КАРТИНКА';
      imgCtrl.style.display = 'flex';
      document.getElementById('ctxImgAlt').value = el.getAttribute('alt') || '';
    }

    // Если кликнули по ссылке или кнопке-ссылке
    const linkEl = el.closest('a');
    if (linkEl) {
      tagLabel.textContent = '🔗 ССЫЛКА / КНОПКА';
      linkCtrl.style.display = 'flex';
      document.getElementById('ctxLinkHref').value = linkEl.getAttribute('href') || '';
    }

    // Если кликнули внутри таблицы
    if (el.closest('table')) {
      tagLabel.textContent = '📊 ТАБЛИЦА';
      tblCtrl.style.display = 'flex';
    }

    // Если кликнули внутри FAQ
    if (el.closest('.lopal-faq-list')) {
      tagLabel.textContent = '❓ АККОРДЕОН FAQ';
      faqCtrl.style.display = 'flex';
      // Раскрываем вопрос в редакторе, чтобы удобно править ответ
      const faqItem = el.closest('.lopal-faq-item');
      if (faqItem) faqItem.classList.add('is-open');
    }

    // Если кликнули по блоку видео
    if (el.closest('[data-lopal-video-section]') || el.closest('.lopal-video-wrapper')) {
      tagLabel.textContent = '🎬 YOUTUBE ВИДЕО';
      vidCtrl.style.display = 'flex';
    }

    bar.classList.add('is-visible');
  }

  function hideContextBar() {
    if (selectedEl) selectedEl.classList.remove('ve-selected-element');
    selectedEl = null;
    document.getElementById('contextBar').classList.remove('is-visible');
  }

  function toggleEditMode(edit) {
    isEditMode = edit;
    document.getElementById('btnEditMode').classList.toggle('is-active', edit);
    document.getElementById('btnPreviewMode').classList.toggle('is-active', !edit);
    if (frame.contentWindow) {
      frame.contentWindow.__LOPAL_VISUAL_EDITOR_ACTIVE = edit;
    }
    if (!edit) hideContextBar();
    refreshEditableElements();
    showVeToast(edit ? '✏️ Режим визуальной правки включен' : '👁️ Режим предпросмотра (как видят посетители)');
  }

  function execFormat(cmd) {
    if (!frame.contentDocument) return;
    frame.contentDocument.execCommand(cmd, false, null);
  }

  function promptLink() {
    if (!frame.contentDocument) return;
    const url = prompt('Введите адрес ссылки (например: products.html или https://wa.me/77075236525):', 'products.html');
    if (url) {
      frame.contentDocument.execCommand('createLink', false, url);
    }
  }

  function applyLinkHref() {
    if (!selectedEl) return;
    const linkEl = selectedEl.closest('a');
    if (linkEl) {
      linkEl.setAttribute('href', document.getElementById('ctxLinkHref').value.trim());
      showVeToast('Ссылка обновлена!');
    }
  }

  function applyImgAlt() {
    if (selectedEl && selectedEl.tagName.toLowerCase() === 'img') {
      selectedEl.setAttribute('alt', document.getElementById('ctxImgAlt').value.trim());
    }
  }

  function replaceSelectedImage() {
    if (!selectedEl || selectedEl.tagName.toLowerCase() !== 'img') return;
    const imgTarget = selectedEl;
    openMediaModal((url) => {
      if (url) {
        imgTarget.setAttribute('src', url);
        showVeToast('Изображение успешно заменено!');
      }
    });
  }

  function duplicateSelectedElement() {
    if (!selectedEl) return;
    const card = selectedEl.closest('.product-card, .tech-highlight-card, .lopal-faq-item, .quick-fact') || selectedEl;
    const clone = card.cloneNode(true);
    clone.classList.remove('ve-selected-element');
    card.parentNode.insertBefore(clone, card.nextElementSibling);
    refreshEditableElements();
    showVeToast('Элемент продублирован!');
  }

  function deleteSelectedElement() {
    if (!selectedEl) return;
    const target = selectedEl.closest('.product-card, .tech-highlight-card, .lopal-faq-item') || selectedEl;
    if (confirm('Удалить выбранный элемент?')) {
      target.remove();
      hideContextBar();
      showVeToast('Элемент удален');
    }
  }

  // Операции с таблицами
  function tableAddRow() {
    if (!selectedEl) return;
    const tbl = selectedEl.closest('table');
    if (!tbl) return;
    const tbody = tbl.querySelector('tbody') || tbl;
    const lastRow = tbody.querySelector('tr:last-child');
    if (lastRow) {
      const clone = lastRow.cloneNode(true);
      clone.querySelectorAll('td, th').forEach(c => { c.textContent = 'Значение'; });
      tbody.appendChild(clone);
      refreshEditableElements();
    }
  }

  function tableAddCol() {
    if (!selectedEl) return;
    const tbl = selectedEl.closest('table');
    if (!tbl) return;
    const doc = frame.contentDocument;
    tbl.querySelectorAll('tr').forEach((tr, idx) => {
      const cell = doc.createElement(idx === 0 && tr.querySelector('th') ? 'th' : 'td');
      cell.textContent = idx === 0 ? 'Параметр' : '—';
      tr.appendChild(cell);
    });
    refreshEditableElements();
  }

  function tableDelRow() {
    if (!selectedEl) return;
    const tr = selectedEl.closest('tr');
    if (tr && tr.parentNode.children.length > 1) {
      tr.remove();
      hideContextBar();
    }
  }

  // Операции с FAQ
  function faqAddItem() {
    if (!selectedEl) return;
    const list = selectedEl.closest('.lopal-faq-list');
    if (!list) return;
    const doc = frame.contentDocument;
    const item = doc.createElement('div');
    item.className = 'lopal-faq-item is-open';
    item.innerHTML = `
      <button type="button" class="lopal-faq-question">
        <span>Новый вопрос: кликните, чтобы ввести текст вопроса?</span>
        <span class="lopal-faq-icon">+</span>
      </button>
      <div class="lopal-faq-answer">
        Введите подробный ответ на вопрос здесь. Этот блок плавно раскрывается при нажатии посетителя на вопрос.
      </div>
    `;
    list.appendChild(item);
    refreshEditableElements();
    showVeToast('Добавлен новый вопрос в FAQ');
  }

  // Операции с YouTube видео
  function parseYoutubeEmbedUrl(inputUrl) {
    if (!inputUrl) return '';
    let videoId = '';
    const m1 = inputUrl.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/shorts\/)([a-zA-Z0-9_-]{6,15})/);
    if (m1 && m1[1]) {
      videoId = m1[1];
    } else if (/^[a-zA-Z0-9_-]{8,15}$/.test(inputUrl.trim())) {
      videoId = inputUrl.trim();
    }
    return videoId ? ('https://www.youtube.com/embed/' + videoId + '?rel=0') : inputUrl;
  }

  function changeYoutubeUrl() {
    if (!selectedEl) return;
    const sec = selectedEl.closest('[data-lopal-video-section]') || selectedEl.closest('section');
    const iframe = sec ? sec.querySelector('iframe') : null;
    if (!iframe) return;
    const raw = prompt('Вставьте ссылку на видео YouTube (например: https://www.youtube.com/watch?v=... или https://youtu.be/...):', iframe.getAttribute('src') || '');
    if (raw) {
      iframe.setAttribute('src', parseYoutubeEmbedUrl(raw));
      showVeToast('Видео YouTube обновлено!');
    }
  }

  // =================== БИБЛИОТЕКА БЛОКОВ ===================
  function openBlocksModal() {
    document.getElementById('blocksModal').classList.add('is-open');
  }
  function closeBlocksModal() {
    document.getElementById('blocksModal').classList.remove('is-open');
    targetAfterSection = null;
  }

  const BLOCK_TEMPLATES = {
    promo_banner: `
      <section class="hero" style="min-height: 360px; padding: 64px 0; background: linear-gradient(90deg, rgba(8, 14, 26, 0.8) 0%, rgba(8, 14, 26, 0.5) 60%, rgba(8, 14, 26, 0.25) 100%), url('assets/branding/catalog-hero-sunrise.jpg') center / cover no-repeat;">
        <div class="container">
          <div class="hero-text" style="max-width: 760px;">
            <div class="hero-tag"><span>Специальное предложение LOPAL</span></div>
            <h1>Заголовок промо-баннера или акции</h1>
            <p class="hero-lead">Кликните по любому тексту, чтобы изменить описание. Чтобы сменить фоновую картинку баннера, нажмите кнопку «🖼️ Фон-баннер» в правом верхнем углу этой секции.</p>
            <div class="hero-buttons">
              <a href="products.html" class="btn btn-primary">Смотреть каталог</a>
              <a href="https://wa.me/77075236525" target="_blank" rel="noopener" class="btn btn-outline" style="color:#fff; border-color:rgba(255,255,255,0.4);">Написать в WhatsApp</a>
            </div>
          </div>
        </div>
      </section>
    `,
    faq: `
      <section class="section">
        <div class="container">
          <div class="section-head">
            <span class="kicker kicker-gold">База знаний LOPAL</span>
            <h2 class="section-title">Часто задаваемые вопросы (FAQ)</h2>
            <p class="section-subtitle">Ответы технических специалистов ТОО «Topmaq oil» по подбору, допускам и оригинальности смазочных материалов LOPAL.</p>
          </div>
          <div class="lopal-faq-list">
            <div class="lopal-faq-item is-open">
              <button type="button" class="lopal-faq-question">
                <span>Как проверить оригинальность моторного масла LOPAL в Казахстане?</span>
                <span class="lopal-faq-icon">+</span>
              </button>
              <div class="lopal-faq-answer">
                Официальным эксклюзивным дистрибьютором продукции Jiangsu Lopal New Material Tech. Co., Ltd. в Республике Казахстан является ТОО «Topmaq oil». Покупайте продукцию в авторизованных точках продаж, указанных на интерактивной карте на странице «Точки продаж».
              </div>
            </div>
            <div class="lopal-faq-item">
              <button type="button" class="lopal-faq-question">
                <span>Чем стандарт API SQ / ILSAC GF-7 превосходит предыдущие поколения масел?</span>
                <span class="lopal-faq-icon">+</span>
              </button>
              <div class="lopal-faq-answer">
                Новейший стандарт API SQ / ILSAC GF-7 обеспечивает усиленную защиту от преждевременного воспламенения смеси на низких оборотах (LSPI), повышенную чистоту поршней турбированных двигателей TGDI и экономию топлива.
              </div>
            </div>
            <div class="lopal-faq-item">
              <button type="button" class="lopal-faq-question">
                <span>Подходят ли масла LOPAL для китайских гибридов (Li Auto, BYD, Geely, Zeekr)?</span>
                <span class="lopal-faq-icon">+</span>
              </button>
              <div class="lopal-faq-answer">
                Да, специальная серия LOPAL NEV (Guardian HPO, редукторные жидкости E21/E22/E23 и антифриз Coolion) разработана специально для гибридных установок PHEV/EREV и электромобилей и поставляется на конвейеры ведущих производителей.
              </div>
            </div>
          </div>
        </div>
      </section>
    `,
    specs_table: `
      <section class="section">
        <div class="container">
          <div class="section-head">
            <span class="kicker kicker-gold">Техническая документация</span>
            <h2 class="section-title">Таблица физико-химических характеристик</h2>
            <p class="section-subtitle">Кликните по любой ячейке таблицы для редактирования данных или добавьте новые строки через нижнюю панель.</p>
          </div>
          <div class="lopal-specs-table-wrap">
            <table class="lopal-custom-table">
              <thead>
                <tr>
                  <th>Наименование показателя</th>
                  <th>Метод испытания</th>
                  <th>Ед. изм.</th>
                  <th>Типичное значение</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td>Класс вязкости по SAE</td>
                  <td>SAE J300</td>
                  <td>—</td>
                  <td>0W-20</td>
                </tr>
                <tr>
                  <td>Кинематическая вязкость при 100 °C</td>
                  <td>ASTM D445</td>
                  <td>мм²/с</td>
                  <td>8.45</td>
                </tr>
                <tr>
                  <td>Индекс вязкости</td>
                  <td>ASTM D2270</td>
                  <td>—</td>
                  <td>172</td>
                </tr>
                <tr>
                  <td>Температура застывания</td>
                  <td>ASTM D97</td>
                  <td>°C</td>
                  <td>-45</td>
                </tr>
                <tr>
                  <td>Температура вспышки в открытом тигле</td>
                  <td>ASTM D92</td>
                  <td>°C</td>
                  <td>232</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>
    `,
    youtube_video: `
      <section class="section" data-lopal-video-section="true">
        <div class="container">
          <div class="section-head">
            <span class="kicker kicker-gold">Видеообзор и технологии</span>
            <h2 class="section-title">Производство и лаборатории LOPAL</h2>
            <p class="section-subtitle">Кликните по блоку и нажмите «🎬 Изменить ссылку YouTube» на нижней панели, чтобы вставить ссылку на ваш видеоролик.</p>
          </div>
          <div class="lopal-video-wrapper">
            <iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0" title="LOPAL Video" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
          </div>
        </div>
      </section>
    `,
    cards_3col: `
      <section class="section">
        <div class="container">
          <div class="section-head">
            <span class="kicker kicker-gold">Ключевые направления</span>
            <h2 class="section-title">Заголовок секции с карточками</h2>
            <p class="section-subtitle">Вы можете изменить текст, заменить фотографии или продублировать любую карточку.</p>
          </div>
          <div class="products-grid">
            <article class="product-card">
              <div class="product-card-top" style="height:210px; padding:0; overflow:hidden;">
                <img class="product-card-thumb" src="assets/products_2026/lopal1-sq-gf7a.jpg" alt="Баннер 1" style="width:100%; height:100%; max-height:none; object-fit:cover;">
              </div>
              <div class="product-card-body">
                <div class="product-category-title">Направление 01</div>
                <h3 class="product-name">Заголовок первой карточки</h3>
                <p class="product-desc">Подробное описание преимущества, акции или категории продукции. Кликните, чтобы изменить текст.</p>
              </div>
            </article>
            <article class="product-card">
              <div class="product-card-top" style="height:210px; padding:0; overflow:hidden;">
                <img class="product-card-thumb" src="assets/products_2026/nev-guardian-hybrid-oils.jpg" alt="Баннер 2" style="width:100%; height:100%; max-height:none; object-fit:cover;">
              </div>
              <div class="product-card-body">
                <div class="product-category-title">Направление 02</div>
                <h3 class="product-name">Заголовок второй карточки</h3>
                <p class="product-desc">Подробное описание преимущества, акции или категории продукции. Кликните, чтобы изменить текст.</p>
              </div>
            </article>
            <article class="product-card">
              <div class="product-card-top" style="height:210px; padding:0; overflow:hidden;">
                <img class="product-card-thumb" src="assets/products_2026/commercial-extra-k10-ck4.jpg" alt="Баннер 3" style="width:100%; height:100%; max-height:none; object-fit:cover;">
              </div>
              <div class="product-card-body">
                <div class="product-category-title">Направление 03</div>
                <h3 class="product-name">Заголовок третьей карточки</h3>
                <p class="product-desc">Подробное описание преимущества, акции или категории продукции. Кликните, чтобы изменить текст.</p>
              </div>
            </article>
          </div>
        </div>
      </section>
    `,
    image_text_split: `
      <section class="section">
        <div class="container">
          <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(320px, 1fr)); gap:36px; align-items:center;">
            <div>
              <span class="kicker kicker-gold">Официальный дистрибьютор</span>
              <h2 class="section-title" style="margin-bottom:16px;">Заголовок текстового блока с иллюстрацией</h2>
              <p style="font-size:15.5px; color:var(--ink-secondary); line-height:1.75; margin-bottom:20px;">
                Введите здесь описание технологии, условий сотрудничества или преимуществ продукта. Нажмите на изображение справа, чтобы загрузить свой баннер или фотографию.
              </p>
              <a href="contacts.html" class="btn btn-primary">Связаться с нами</a>
            </div>
            <div style="border-radius:20px; overflow:hidden; box-shadow:0 16px 40px rgba(15,23,42,0.12);">
              <img src="assets/branding/topmaq-hero-dark.jpg" alt="Иллюстрация LOPAL" style="width:100%; height:auto; display:block; object-fit:cover;">
            </div>
          </div>
        </div>
      </section>
    `
  };

  function insertBlock(type) {
    const doc = frame.contentDocument;
    if (!doc) return;
    const mainEl = doc.querySelector('main');
    if (!mainEl) return;

    const html = BLOCK_TEMPLATES[type];
    if (!html) return;

    if (type === 'youtube_video') {
      const ytUrl = prompt('Вставьте ссылку на видео YouTube (или оставьте поле пустым, чтобы настроить позже):', '');
      const tempWrap = doc.createElement('div');
      tempWrap.innerHTML = html.trim();
      const newSec = tempWrap.firstElementChild;
      if (ytUrl) {
        const ifr = newSec.querySelector('iframe');
        if (ifr) ifr.setAttribute('src', parseYoutubeEmbedUrl(ytUrl));
      }
      if (targetAfterSection && targetAfterSection.parentNode === mainEl) {
        mainEl.insertBefore(newSec, targetAfterSection.nextElementSibling);
      } else {
        mainEl.appendChild(newSec);
      }
      closeBlocksModal();
      refreshEditableElements();
      newSec.scrollIntoView({ behavior: 'smooth', block: 'center' });
      showVeToast('Видео-блок добавлен на страницу!');
      return;
    }

    const temp = doc.createElement('div');
    temp.innerHTML = html.trim();
    const newSection = temp.firstElementChild;

    if (targetAfterSection && targetAfterSection.parentNode === mainEl) {
      mainEl.insertBefore(newSection, targetAfterSection.nextElementSibling);
    } else {
      mainEl.appendChild(newSection);
    }

    closeBlocksModal();
    refreshEditableElements();
    newSection.scrollIntoView({ behavior: 'smooth', block: 'center' });
    showVeToast('Новый блок добавлен на страницу!');
  }

  // =================== МЕДИАТЕКА И ЗАГРУЗКА КАРТИНОК ===================
  async function openMediaModal(callback) {
    mediaCallback = callback;
    document.getElementById('mediaModal').classList.add('is-open');
    await loadMediaGallery();
  }

  function closeMediaModal() {
    document.getElementById('mediaModal').classList.remove('is-open');
    mediaCallback = null;
  }

  async function loadMediaGallery() {
    const grid = document.getElementById('mediaGalleryGrid');
    try {
      const resp = await fetch('../api/page_editor.php?action=list_media');
      const data = await resp.json();
      if (!data.ok || !Array.isArray(data.media)) {
        grid.innerHTML = '<div style="color:#EF4444;">Ошибка загрузки списка изображений</div>';
        return;
      }
      grid.innerHTML = data.media.map(item => `
        <div class="ve-media-item" onclick="selectMediaUrl('${item.url}')">
          <img class="ve-media-thumb" src="../${item.url}" alt="${item.name}" loading="lazy">
          <div class="ve-media-name" title="${item.name} (${item.group})">${item.name}</div>
        </div>
      `).join('');
    } catch (e) {
      grid.innerHTML = '<div style="color:#EF4444;">Ошибка соединения</div>';
    }
  }

  async function handleFileUpload(files) {
    if (!files || !files.length) return;
    const formData = new FormData();
    formData.append('file', files[0]);
    showVeToast('⏳ Загрузка изображения на сервер...');

    try {
      const resp = await fetch('../api/page_editor.php', {
        method: 'POST',
        body: formData
      });
      const data = await resp.json();
      if (data.ok && data.url) {
        showVeToast('✅ Картинка загружена: ' + data.name);
        await loadMediaGallery();
        selectMediaUrl(data.url);
      } else {
        showVeToast('⚠️ ' + (data.error || 'Ошибка загрузки'), true);
      }
    } catch (e) {
      showVeToast('⚠️ Ошибка сети при загрузке файла', true);
    }
  }

  function selectMediaUrl(url) {
    if (!url) return;
    if (typeof mediaCallback === 'function') {
      mediaCallback(url);
      closeMediaModal();
    } else if (selectedEl && selectedEl.tagName.toLowerCase() === 'img') {
      selectedEl.setAttribute('src', url);
      showVeToast('✅ Картинка обновлена!');
      closeMediaModal();
    } else {
      navigator.clipboard?.writeText(url);
      showVeToast('Ссылка скопирована: ' + url);
      closeMediaModal();
    }
  }

  // =================== СОХРАНЕНИЕ СТРАНИЦЫ ===================
  async function saveCurrentPage() {
    const doc = frame.contentDocument;
    if (!doc) return;
    const mainEl = doc.querySelector('main');
    if (!mainEl) {
      showVeToast('Ошибка: контейнер <main> не найден', true);
      return;
    }

    const btn = document.getElementById('savePageBtn');
    const origHtml = btn.innerHTML;
    btn.innerHTML = '<span>⏳</span><span class="ve-label-full"> Сохранение...</span><span class="ve-label-short"> Сохранение...</span>';
    btn.disabled = true;

    // Клонируем <main> и очищаем от служебных атрибутов и панелей редактора
    const cloneMain = mainEl.cloneNode(true);
    cloneMain.querySelectorAll('.ve-ui-only, .ve-section-toolbar').forEach(el => el.remove());
    cloneMain.querySelectorAll('[contenteditable]').forEach(el => {
      el.removeAttribute('contenteditable');
      el.removeAttribute('spellcheck');
    });
    cloneMain.querySelectorAll('.ve-selected-element').forEach(el => {
      el.classList.remove('ve-selected-element');
      if (el.className.trim() === '') el.removeAttribute('class');
    });

    try {
      const resp = await fetch('../api/page_editor.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'save_page',
          page: currentPage,
          main_html: cloneMain.innerHTML.trim(),
          page_title: doc.title || ''
        })
      });
      const data = await resp.json();
      if (data.ok) {
        showVeToast('✅ ' + (data.message || 'Страница успешно сохранена!'));
      } else {
        showVeToast('⚠️ ' + (data.error || 'Ошибка сохранения'), true);
      }
    } catch (e) {
      showVeToast('⚠️ Ошибка соединения с сервером', true);
    } finally {
      btn.innerHTML = origHtml;
      btn.disabled = false;
    }
  }

  // Горячая клавиша Ctrl+S (Cmd+S) для быстрого сохранения
  window.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
      e.preventDefault();
      saveCurrentPage();
    }
  });
  </script>
</body>
</html>
