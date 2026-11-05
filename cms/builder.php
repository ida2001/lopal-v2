<?php
/**
 * LOPAL KAZAKHSTAN (lopal.kz) — Static & Hybrid HTML Builder + Rollback Engine
 * Генерирует чистые статические HTML-страницы для 100% скорости и SEO
 */

require_once __DIR__ . '/config.php';

/**
 * Полная пересборка всех динамических узлов сайта
 */
function lopal_rebuild_all(): array {
    $results = [];
    $results['news_page'] = lopal_build_news_page();
    $results['products_page'] = lopal_build_products_page();
    $results['dealers'] = lopal_build_dealers();
    $results['sitemap'] = lopal_build_sitemap();
    return $results;
}

/**
 * Генерация страницы Новостей (news.html)
 */
function lopal_build_news_page(): bool {
    $newsList = lopal_read_json('news');
    $settings = lopal_read_json('settings');

    $company = htmlspecialchars($settings['company_name'] ?? 'ТОО «Topmaq oil» (TOPMAQ OIL)', ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars($settings['address'] ?? 'Казахстан, г. Алматы, ул. Сокпакбаева, 67а', ENT_QUOTES, 'UTF-8');
    $phoneMain = htmlspecialchars($settings['phone_main'] ?? '+7 (707) 523-65-25', ENT_QUOTES, 'UTF-8');
    $phoneMainHref = htmlspecialchars($settings['phone_main_href'] ?? '+77075236525', ENT_QUOTES, 'UTF-8');
    $phoneService = !empty($settings['phone_service']) ? htmlspecialchars($settings['phone_service'], ENT_QUOTES, 'UTF-8') : '';
    $phoneServiceHref = (!empty($settings['phone_service_href']) && $settings['phone_service_href'] !== '+') ? htmlspecialchars($settings['phone_service_href'], ENT_QUOTES, 'UTF-8') : '';
    $email = htmlspecialchars($settings['email'] ?? 'info@lopal.kz', ENT_QUOTES, 'UTF-8');
    $insta = htmlspecialchars($settings['instagram'] ?? '@lopal.kz', ENT_QUOTES, 'UTF-8');
    $instaUrl = htmlspecialchars($settings['instagram_url'] ?? 'https://instagram.com/lopal.kz', ENT_QUOTES, 'UTF-8');
    $wa = htmlspecialchars($settings['whatsapp'] ?? '77075236525', ENT_QUOTES, 'UTF-8');

        $phoneHtml = '<div>📞 <strong>Телефон:</strong> <a href="tel:' . $phoneMainHref . '" style="color:var(--topmaq-gold);">' . $phoneMain . '</a>';
    if (!empty($phoneService) && !empty($phoneServiceHref)) {
        $phoneHtml .= ', <a href="tel:' . $phoneServiceHref . '" style="color:var(--topmaq-gold);">' . $phoneService . '</a>';
    }
    $phoneHtml .= '</div>';

    $cardsHtml = '';
    foreach ($newsList as $idx => $item) {
        if (isset($item['published']) && !$item['published']) {
            continue;
        }
        $id = htmlspecialchars($item['id'] ?? ('news-' . $idx), ENT_QUOTES, 'UTF-8');
        $cat = htmlspecialchars($item['category'] ?? 'corporate', ENT_QUOTES, 'UTF-8');
        $catLabel = htmlspecialchars($item['category_label'] ?? 'Новости LOPAL', ENT_QUOTES, 'UTF-8');
        $badge = htmlspecialchars($item['badge'] ?? 'НОВОСТИ', ENT_QUOTES, 'UTF-8');
        $date = htmlspecialchars($item['date'] ?? date('d.m.Y'), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($item['title'] ?? '', ENT_QUOTES, 'UTF-8');
        $summary = htmlspecialchars($item['summary'] ?? '', ENT_QUOTES, 'UTF-8');
        $img = htmlspecialchars($item['image'] ?? 'assets/branding/topmaq-hero-dark.jpg', ENT_QUOTES, 'UTF-8');

        $badgeClass = 'badge-sq';
        if ($cat === 'nev') $badgeClass = 'badge-nev';
        elseif ($cat === 'dealers') $badgeClass = 'badge-gf7';
        elseif ($cat === 'corporate') $badgeClass = 'badge-oem';

        $cardsHtml .= <<<HTML
          <article class="product-card news-item-card" data-category="{$cat}" data-news-id="{$id}">
            <div class="product-card-top" style="height: 220px; padding: 0; overflow: hidden; background: #0F172A;">
              <div class="product-badges" style="top: 14px; left: 14px; z-index: 2;">
                <span class="badge-tag {$badgeClass}">{$badge}</span>
              </div>
              <img class="product-card-thumb" src="{$img}" alt="{$title}" style="width: 100%; height: 100%; max-height: none; object-fit: cover; transition: transform 0.35s ease;">
            </div>
            <div class="product-card-body">
              <div style="display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 6px;">
                <span class="product-category-title" style="margin-bottom: 0;">{$catLabel}</span>
                <span style="font-size: 12px; font-weight: 600; color: var(--ink-muted);">📅 {$date}</span>
              </div>
              <h3 class="product-name" style="font-size: 18px; line-height: 1.35; margin-bottom: 10px;">{$title}</h3>
              <p class="product-desc" style="margin-bottom: 18px;">{$summary}</p>
              <div class="product-card-footer">
                <button class="btn-card-details" type="button" data-read-news="{$id}" style="background:none;border:none;cursor:pointer;padding:0;font:inherit;font-weight:700;color:var(--lopal-green);">Читать полностью →</button>
                <a class="btn-card-order" href="service-points.html" data-open-dealers>Где купить</a>
              </div>
            </div>
          </article>

HTML;
    }

    $newsJsonEncoded = json_encode(array_values($newsList), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $html = <<<HTML
<!DOCTYPE html>
<html lang="ru" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="color-scheme" content="light">
  <meta name="theme-color" content="#FFFFFF">
  <title>Новости компании и отрасли — LOPAL Казахстан | {$company}</title>
  <meta name="description" content="Официальные новости представительства LOPAL в Казахстане ({$company}): поступление новых линеек моторных масел API SQ / ILSAC GF-7, жидкости для гибридов NEV, расширение дилерской сети и СТО.">
  <link rel="icon" type="image/png" sizes="32x32" href="assets/favicon-32.png">
  <link rel="icon" type="image/png" sizes="192x192" href="assets/favicon-192.png">
  <link rel="apple-touch-icon" sizes="180x180" href="assets/apple-touch-icon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/styles.css?v=10">

  <!-- SEO, OpenGraph & Schema.org -->
  <meta name="robots" content="index, follow">
  <meta name="geo.region" content="KZ-ALA">
  <meta name="geo.placename" content="Алматы, Казахстан">
  <link rel="canonical" href="https://lopal.kz/news.html">
  <meta property="og:type" content="website">
  <meta property="og:locale" content="ru_KZ">
  <meta property="og:site_name" content="LOPAL Казахстан · {$company}">
  <meta property="og:title" content="Новости LOPAL в Казахстане — {$company}">
  <meta property="og:description" content="Свежие новости официального дистрибьютора LOPAL в РК: новинки продукции, допуски автопроизводителей и развитие дилерской сети.">
  <meta property="og:url" content="https://lopal.kz/news.html">
  <meta property="og:image" content="https://lopal.kz/assets/branding/topmaq-hero-dark.jpg">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Новости LOPAL в Казахстане — {$company}">
  <meta name="twitter:description" content="Свежие новости официального дистрибьютора LOPAL в РК: новинки продукции, допуски автопроизводителей и развитие дилерской сети.">
  <meta name="twitter:image" content="https://lopal.kz/assets/branding/topmaq-hero-dark.jpg">
  <script type="application/ld+json">{"@context": "https://schema.org", "@type": "AutoPartsStore", "name": "LOPAL Казахстан — {$company}", "description": "Официальный эксклюзивный дистрибьютор смазочных материалов, моторных и трансмиссионных масел Jiangsu Lopal New Material Tech. Co., Ltd. в Республике Казахстан.", "url": "https://lopal.kz/", "logo": "https://lopal.kz/assets/branding/lopal-logo.png", "image": "https://lopal.kz/assets/branding/topmaq-hero-dark.jpg", "telephone": "+7-707-523-65-25", "email": "{$email}", "address": {"@type": "PostalAddress", "streetAddress": "ул. Сокпакбаева, 67а", "addressLocality": "Алматы", "addressCountry": "KZ"}, "geo": {"@type": "GeoCoordinates", "latitude": 43.2685, "longitude": 76.9125}, "openingHoursSpecification": [{"@type": "OpeningHoursSpecification", "dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"], "opens": "09:00", "closes": "18:00"}], "sameAs": ["{$instaUrl}"]}</script>
</head>
<body>

  <header class="site-header" id="siteHeader">
    <div class="container header-inner">
      
      <a href="index.html" class="brand-combo" aria-label="Lopal Kazakhstan — На главную">
        <img class="brand-lopal-img" src="assets/branding/lopal-logo.png" alt="Lopal Tech 龙蟠科技" height="34">
        <span class="brand-divider"></span>
        <img class="brand-topmaq-img brand-topmaq-dark" src="assets/branding/topmaq-logo-dark.jpg" alt="ТОО Topmaq oil" height="32">
      </a>

      <nav class="site-nav" id="siteNav" aria-label="Главное меню">
        <ul>
          <li><a href="index.html">Главная</a></li>
          <li><a href="products.html">Каталог</a></li>
          <li><a href="selector.html">Подбор масла</a></li>
          <li><a href="technologies.html">Технологии</a></li>
          <li><a href="certificates.html">Допуски OEM</a></li>
          <li><a href="service-points.html">Точки продаж</a></li>
          <li><a href="news.html" class="is-active">Новости</a></li>
          <li><a href="partners.html">Партнёрам</a></li>
          <li><a href="contacts.html">Контакты</a></li>
        </ul>
      </nav>

      <div class="header-actions">
        <button class="nav-toggle" id="navToggle" type="button" aria-label="Открыть меню">
          <span></span><span></span><span></span>
        </button>
      </div>

    </div>
  </header>

  <main id="main">

    <section class="section" style="padding-top: 28px; padding-bottom: 56px;">
      <div class="container">
        
        <div class="section-head" style="margin-bottom: 28px;">
          <span class="kicker kicker-gold">Пресс-центр официального представительства</span>
          <h1 class="section-title">Новости LOPAL в Казахстане</h1>
          <p class="section-subtitle">
            Актуальные события ТОО «Topmaq oil» и корпорации Jiangsu Lopal New Material Tech. Co., Ltd.: поступление новых партий на склад в Алматы, 
            обзоры стандартов <strong>API SQ / ILSAC GF-7</strong>, решения для гибридов <strong>NEV</strong> и развитие сети партнерских ПЗМ.
          </p>
        </div>

        <!-- Фильтры категорий новостей -->
        <div class="category-tabs">
          <button class="cat-tab-btn is-active" data-cat="all" type="button">Все публикации</button>
          <button class="cat-tab-btn" data-cat="products" type="button">Продукция и стандарты</button>
          <button class="cat-tab-btn" data-cat="nev" type="button">Гибриды и EV (NEV)</button>
          <button class="cat-tab-btn" data-cat="dealers" type="button">Дилерская сеть КЗ</button>
          <button class="cat-tab-btn" data-cat="corporate" type="button">Технологии и OEM</button>
        </div>

        <!-- Сетка новостей -->
        <div class="products-grid" id="productsContainer">
{$cardsHtml}
        </div>

        <div id="productsEmptyState" style="display: none; text-align: center; padding: 60px 20px;">
          <h3>В выбранной категории пока нет публикаций</h3>
          <p style="color: var(--ink-secondary);">Выберите «Все публикации», чтобы просмотреть все материалы.</p>
        </div>

      </div>
    </section>

  </main>

  <!-- Модальное окно чтения полной новости -->
  <div id="newsArticleModal" class="selector-modal-backdrop" aria-hidden="true" style="z-index: 1200;">
    <div class="selector-modal-window" style="max-width: 780px; height: auto; max-height: 90vh; display: flex; flex-direction: column; border-radius: 20px; overflow: hidden;">
      <div class="selector-modal-header" style="border-bottom: 1px solid var(--border-light); background: #FFFFFF;">
        <div style="display: flex; align-items: center; gap: 10px;">
          <span id="newsModalBadge" class="badge-tag badge-sq">НОВОСТИ</span>
          <span id="newsModalDate" style="font-size: 13px; font-weight: 600; color: var(--ink-muted);"></span>
        </div>
        <button type="button" id="closeNewsModalBtn" class="selector-modal-close" aria-label="Закрыть">✕</button>
      </div>
      <div style="padding: 28px 32px; overflow-y: auto; background: #FFFFFF;">
        <div id="newsModalImgWrap" style="margin-bottom: 22px; border-radius: 14px; overflow: hidden; max-height: 360px; background: #0F172A;">
          <img id="newsModalImg" src="" alt="" style="width: 100%; height: 100%; max-height: 360px; object-fit: cover; display: block;">
        </div>
        <h2 id="newsModalTitle" style="font-size: clamp(20px, 2.6vw, 26px); font-weight: 800; color: var(--ink-primary); margin-bottom: 16px; line-height: 1.3;"></h2>
        <div id="newsModalBody" style="font-size: 15.5px; line-height: 1.75; color: var(--ink-secondary); white-space: pre-line; margin-bottom: 28px;"></div>
        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 14px; padding-top: 20px; border-top: 1px solid var(--border-light);">
          <a href="products.html" class="btn btn-primary btn-sm">Перейти в каталог продукции</a>
          <a href="https://wa.me/{$wa}?text=Здравствуйте,%20вопрос%20по%20продукции%20Lopal" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Связаться в WhatsApp</a>
        </div>
      </div>
    </div>
  </div>

  <!-- Подвал сайта -->
  <footer class="site-footer">
    <div class="container">
      <div class="footer-top">
        <div>
          <div class="brand-combo" style="margin-bottom: 16px;">
            <img class="brand-lopal-img" src="assets/branding/lopal-logo-white.png" alt="Lopal" height="34">
            <span class="brand-divider"></span>
            <img class="brand-topmaq-img brand-topmaq-dark" src="assets/branding/topmaq-logo-dark.jpg" alt="ТОО Topmaq oil" height="30">
          </div>
          <p class="footer-desc">
            {$company} — официальный дистрибьютор и генеральный представитель Jiangsu Lopal New Material Tech. Co., Ltd. в Республике Казахстан.
          </p>
          <div style="font-size: 13.5px; color: #CBD5E1; line-height: 1.8;">
            <div>🏢 <strong>Юр. лицо:</strong> {$company}</div>
            <div>📍 <strong>Адрес:</strong> {$address}</div>
            {$phoneHtml}
            <div>✉️ <strong>Email:</strong> <a href="mailto:{$email}" style="color:var(--topmaq-gold);">{$email}</a></div>
            <div>📸 <strong>Instagram:</strong> <a href="{$instaUrl}" target="_blank" rel="noopener" style="color:#38BDF8;">{$insta}</a></div>
          </div>
        </div>

        <div class="footer-col">
          <h4>Продукция</h4>
          <ul class="footer-links">
            <li><a href="products.html">Легковые масла API SQ / SP</a></li>
            <li><a href="products.html">Линейка для гибридов (NEV)</a></li>
            <li><a href="products.html">Редукторные жидкости EV</a></li>
            <li><a href="products.html">Дизельные масла CK-4</a></li>
            <li><a href="products.html">Трансмиссионные жидкости</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Разделы</h4>
          <ul class="footer-links">
            <li><a href="selector.html">Подбор масла онлайн</a></li>
            <li><a href="technologies.html">Технологии и R&D</a></li>
            <li><a href="certificates.html">Сертификаты и допуски</a></li>
            <li><a href="service-points.html">Точки продаж и СТО</a></li>
            <li><a href="news.html">Новости компании</a></li>
            <li><a href="partners.html">Партнёрам и дилерам</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h4>Прямой контакт</h4>
          <p style="font-size: 13px; margin-bottom: 14px; color: #94A3B8;">
            Запросите оптовый прайс-лист или консультацию технического специалиста.
          </p>
          <a href="https://wa.me/{$wa}?text=Здравствуйте,%20интересует%20продукция%20Lopal" target="_blank" rel="noopener" class="btn btn-primary btn-sm" style="width: 100%; margin-bottom: 10px;">
            Написать в WhatsApp
          </a>
        </div>
      </div>

      <div class="footer-bottom">
        <div>© 2026 Lopal Kazakhstan · {$company}. Все права защищены.</div>
        <div class="footer-developer">
          Разработано в <a href="https://ai-studio-api-nz5z.onrender.com/" target="_blank" rel="noopener">AI-Studio-Mark</a>
        </div>
        <div style="display: flex; gap: 20px;">
          <a href="news.html">Новости</a>
          <a href="contacts.html">Контакты</a>
          <a href="partners.html">Сотрудничество</a>
          <a href="selector.html">Lopal Oil Selector</a>
        </div>
      </div>
    </div>
  </footer>

  <script id="lopalNewsData" type="application/json">{$newsJsonEncoded}</script>
  <script src="js/main.js?v=20"></script>
  <script>
  (function(){
    var dataEl = document.getElementById('lopalNewsData');
    var modal = document.getElementById('newsArticleModal');
    var closeBtn = document.getElementById('closeNewsModalBtn');
    if (!dataEl || !modal) return;
    var items = [];
    try { items = JSON.parse(dataEl.textContent || '[]'); } catch(e){}

    function openNews(id) {
      var art = items.find(function(x){ return x.id === id; });
      if (!art) return;
      document.getElementById('newsModalBadge').textContent = art.badge || art.category_label || 'НОВОСТИ';
      document.getElementById('newsModalDate').textContent = '📅 ' + (art.date || '');
      document.getElementById('newsModalTitle').textContent = art.title || '';
      document.getElementById('newsModalBody').textContent = art.content || art.summary || '';
      var imgEl = document.getElementById('newsModalImg');
      if (art.image) {
        imgEl.src = art.image;
        imgEl.alt = art.title || '';
        document.getElementById('newsModalImgWrap').style.display = 'block';
      } else {
        document.getElementById('newsModalImgWrap').style.display = 'none';
      }
      modal.classList.add('is-open');
      modal.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
    }

    function closeNews() {
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
    }

    document.addEventListener('click', function(e){
      var btn = e.target.closest('[data-read-news]');
      if (btn) {
        e.preventDefault();
        openNews(btn.getAttribute('data-read-news'));
      } else if (e.target === modal || e.target === closeBtn) {
        closeNews();
      }
    });
    document.addEventListener('keydown', function(e){
      if (e.key === 'Escape' && modal.classList.contains('is-open')) closeNews();
    });
  })();
  </script>
</body>
</html>
HTML;

    return file_put_contents(LOPAL_ROOT_DIR . '/news.html', $html, LOCK_EX) !== false;
}

/**
 * Пересборка каталога товаров в products.html (с сохранением всей верстки страницы)
 */
function lopal_build_products_page(): bool {
    $products = lopal_read_json('products');
    $prodFile = LOPAL_ROOT_DIR . '/products.html';
    if (!file_exists($prodFile) || empty($products)) {
        return false;
    }

    $cardsHtml = "\n";
    foreach ($products as $idx => $p) {
        $cat = htmlspecialchars($p['category'] ?? 'passengers', ENT_QUOTES, 'UTF-8');
        $catTitle = htmlspecialchars($p['category_title'] ?? 'Легковые автомобили', ENT_QUOTES, 'UTF-8');
        $name = htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $img = htmlspecialchars($p['image'] ?? 'assets/products/lopal-1-advance-esf-fe-plus-0w20.jpg', ENT_QUOTES, 'UTF-8');
        $desc = htmlspecialchars($p['description'] ?? '', ENT_QUOTES, 'UTF-8');
        $detailUrl = htmlspecialchars($p['detail_url'] ?? 'products.html', ENT_QUOTES, 'UTF-8');
        $detailBtn = htmlspecialchars($p['detail_btn_text'] ?? 'Подробное описание →', ENT_QUOTES, 'UTF-8');

        $badgesHtml = '';
        if (!empty($p['badges']) && is_array($p['badges'])) {
            foreach ($p['badges'] as $b) {
                $bClass = htmlspecialchars($b['class'] ?? 'badge-sq', ENT_QUOTES, 'UTF-8');
                $bText = htmlspecialchars($b['text'] ?? '', ENT_QUOTES, 'UTF-8');
                $badgesHtml .= "                <span class=\"badge-tag {$bClass}\">{$bText}</span>\n";
            }
        }

        $specsHtml = '';
        if (!empty($p['specs']) && is_array($p['specs'])) {
            foreach ($p['specs'] as $s) {
                $sText = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
                $specsHtml .= "                <span class=\"spec-pill\">{$sText}</span>\n";
            }
        }

        $num = $idx + 1;
        $cardsHtml .= <<<HTML
          <!-- {$num}. {$name} -->
          <article class="product-card" data-category="{$cat}">
            <div class="product-card-top">
              <div class="product-badges">
{$badgesHtml}              </div>
              <img class="product-card-thumb" src="{$img}" alt="{$name}">
            </div>
            <div class="product-card-body">
              <div class="product-category-title">{$catTitle}</div>
              <h3 class="product-name">{$name}</h3>
              <div class="product-specs-list">
{$specsHtml}              </div>
              <p class="product-desc">
                {$desc}
              </p>
              <div class="product-card-footer">
                <a class="btn-card-details" href="{$detailUrl}">{$detailBtn}</a>
                <a class="btn-card-order" href="service-points.html" data-open-dealers>Где купить</a>
              </div>
            </div>
          </article>

HTML;
    }

    $raw = file_get_contents($prodFile);
    $pattern = '/(<div class="products-grid" id="productsContainer">)([\s\S]*?)(<\/div>\s*<div id="productsEmptyState")/';
    if (preg_match($pattern, $raw)) {
        $updated = preg_replace_callback($pattern, function($m) use ($cardsHtml) {
            return $m[1] . $cardsHtml . "        " . $m[3];
        }, $raw, 1);
        return file_put_contents($prodFile, $updated, LOCK_EX) !== false;
    }
    return false;
}

/**
 * Обновление точек продаж (DEALERS_DATA в js/main.js и карточек в service-points.html)
 */
function lopal_extract_dealer_city(array $d): string {
    if (!empty($d['city'])) return trim($d['city']);
    $addr = $d['address'] ?? '';
    if (mb_stripos($addr, 'Алматы') !== false) return 'г. Алматы';
    if (mb_stripos($addr, 'Астана') !== false) return 'г. Астана';
    if (mb_stripos($addr, 'Шымкент') !== false) return 'г. Шымкент';
    if (mb_stripos($addr, 'Актау') !== false) return 'г. Актау';
    if (mb_stripos($addr, 'Талдыкорган') !== false) return 'г. Талдыкорган';
    if (mb_stripos($addr, 'Алтай') !== false) return 'г. Алтай (ВКО)';
    if (mb_stripos($addr, 'Арысь') !== false) return 'г. Арысь (Туркестанская обл.)';
    if (preg_match('/г\.\s*([А-Яа-яЁёA-Za-z\-]+)/u', $addr, $m)) {
        return 'г. ' . $m[1];
    }
    return 'Другие регионы';
}

function lopal_plural_points(int $n): string {
    $mod10 = $n % 10;
    $mod100 = $n % 100;
    if ($mod10 === 1 && $mod100 !== 11) return $n . ' точка';
    if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 10 || $mod100 >= 20)) return $n . ' точки';
    return $n . ' точек';
}

function lopal_build_dealers(): bool {
    $dealers = lopal_read_json('dealers');
    if (empty($dealers)) return false;

    // 1. Обновляем DEALERS_DATA в js/main.js
    $jsFile = LOPAL_ROOT_DIR . '/js/main.js';
    if (file_exists($jsFile)) {
        $jsContent = file_get_contents($jsFile);
        $dealersJson = json_encode(array_values($dealers), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        $pattern = '/var DEALERS_DATA = \[[\s\S]*?\];/';
        if (preg_match($pattern, $jsContent)) {
            $jsUpdated = preg_replace_callback($pattern, function() use ($dealersJson) {
                return "var DEALERS_DATA = " . $dealersJson . ";";
            }, $jsContent, 1);
            file_put_contents($jsFile, $jsUpdated, LOCK_EX);
        }
    }

    // 2. Обновляем сгруппированную по городам сетку карточек в service-points.html
    $spFile = LOPAL_ROOT_DIR . '/service-points.html';
    if (file_exists($spFile)) {
        $spContent = file_get_contents($spFile);
        $grouped = [];
        foreach ($dealers as $d) {
            $city = lopal_extract_dealer_city($d);
            if (!isset($grouped[$city])) $grouped[$city] = [];
            $grouped[$city][] = $d;
        }

        $sectionsHtml = "\n";
        foreach ($grouped as $city => $cityDealers) {
            $cityEsc = htmlspecialchars($city, ENT_QUOTES, 'UTF-8');
            $countLabel = lopal_plural_points(count($cityDealers));
            $sectionsHtml .= <<<HTML
          <div class="dealer-city-section" data-city-section="{$cityEsc}" style="margin-bottom: 42px;">
            <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom: 18px; padding-bottom: 12px; border-bottom: 2px solid var(--border-subtle);">
              <h3 style="font-size: 20px; font-weight: 800; color: var(--ink-primary); margin: 0; display:flex; align-items:center; gap:10px;">
                <span style="display:inline-flex; align-items:center; justify-content:center; width:34px; height:34px; border-radius:10px; background:rgba(0,132,61,0.1); color:var(--lopal-green); font-size:17px;">📍</span>
                <span>{$cityEsc}</span>
                <span style="font-size: 12.5px; font-weight: 700; background: var(--bg-secondary); color: var(--lopal-green); padding: 3px 11px; border-radius: 99px; border: 1px solid rgba(0,132,61,0.25);">{$countLabel}</span>
              </h3>
              <button type="button" data-zoom-city="{$cityEsc}" style="background:var(--bg-secondary); border:1px solid var(--border-medium); color:var(--ink-primary); border-radius:99px; padding:6px 14px; font-size:12.5px; font-weight:700; cursor:pointer;">Показать город на карте ↑</button>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 24px;">

HTML;
            foreach ($cityDealers as $d) {
                $id = htmlspecialchars($d['id'] ?? '', ENT_QUOTES, 'UTF-8');
                $name = htmlspecialchars($d['name'] ?? '', ENT_QUOTES, 'UTF-8');
                $addr = htmlspecialchars($d['address'] ?? '', ENT_QUOTES, 'UTF-8');
                $phone = htmlspecialchars($d['phone'] ?? '', ENT_QUOTES, 'UTF-8');
                $phoneHref = htmlspecialchars($d['phoneHref'] ?? '', ENT_QUOTES, 'UTF-8');
                $badge = htmlspecialchars($d['badge'] ?? 'Точка продаж', ENT_QUOTES, 'UTF-8');
                $hqStyle = !empty($d['isHq']) ? 'border: 2px solid var(--topmaq-gold);' : '';

                $sectionsHtml .= <<<HTML
              <div class="tech-highlight-card" data-dealer-card="{$id}" style="{$hqStyle}">
                <h4 style="font-size: 17px; font-weight: 700; color: var(--ink-primary); margin-bottom: 8px;">{$name}</h4>
                <p style="font-size: 13.5px; color: var(--ink-secondary); margin-bottom: 12px;">📍 {$addr}</p>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;">
                  <span class="spec-pill" style="color:var(--lopal-green);">{$badge}</span>
                  <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <a href="tel:{$phoneHref}" style="font-size: 13.5px; font-weight: 600; color: var(--topmaq-gold);">📞 {$phone}</a>
                    <button type="button" data-focus-dealer="{$id}" style="background:rgba(0,132,61,0.08);border:1px solid rgba(0,132,61,0.25);color:var(--lopal-green);border-radius:99px;padding:3px 10px;font-size:12px;font-weight:700;cursor:pointer;">На карте ↑</button>
                  </div>
                </div>
              </div>

HTML;
            }
            $sectionsHtml .= "            </div>\n          </div>\n\n";
        }

        $patternSp = '/(<div id="dealersListContainer">)([\s\S]*?)(<\/div><!-- \/dealersListContainer -->)/';
        if (preg_match($patternSp, $spContent)) {
            $spUpdated = preg_replace_callback($patternSp, function($m) use ($sectionsHtml) {
                return $m[1] . $sectionsHtml . "        " . $m[3];
            }, $spContent, 1);
            file_put_contents($spFile, $spUpdated, LOCK_EX);
        }
    }

    return true;
}

/**
 * Обновление глобальных контактов во всех HTML-файлах при изменении settings.json
 */
function lopal_apply_global_settings(array $oldSettings, array $newSettings): int {
    $company = htmlspecialchars($newSettings['company_name'] ?? 'ТОО «Topmaq oil» (TOPMAQ OIL)', ENT_QUOTES, 'UTF-8');
    $address = htmlspecialchars($newSettings['address'] ?? 'Казахстан, г. Алматы, ул. Сокпакбаева, 67а', ENT_QUOTES, 'UTF-8');
    $phoneMain = htmlspecialchars($newSettings['phone_main'] ?? '+7 (707) 523-65-25', ENT_QUOTES, 'UTF-8');
    $phoneMainHref = htmlspecialchars($newSettings['phone_main_href'] ?? '+77075236525', ENT_QUOTES, 'UTF-8');
    $phoneService = htmlspecialchars($newSettings['phone_service'] ?? '', ENT_QUOTES, 'UTF-8');
    $phoneServiceHref = htmlspecialchars($newSettings['phone_service_href'] ?? '', ENT_QUOTES, 'UTF-8');
    $email = htmlspecialchars($newSettings['email'] ?? 'info@lopal.kz', ENT_QUOTES, 'UTF-8');
    $whatsapp = preg_replace('/\D+/', '', $newSettings['whatsapp'] ?? '77075236525');
    $insta = htmlspecialchars($newSettings['instagram'] ?? '@lopal.kz', ENT_QUOTES, 'UTF-8');
    $instaUrl = htmlspecialchars($newSettings['instagram_url'] ?? 'https://instagram.com/lopal.kz', ENT_QUOTES, 'UTF-8');
    $schedWeekdays = htmlspecialchars($newSettings['schedule_weekdays'] ?? '09:00 — 18:00', ENT_QUOTES, 'UTF-8');
    $schedSat = htmlspecialchars($newSettings['schedule_sat'] ?? 'Выходной', ENT_QUOTES, 'UTF-8');
    $schedSun = htmlspecialchars($newSettings['schedule_sun'] ?? 'Выходной', ENT_QUOTES, 'UTF-8');

    $phoneHtml = '<div>📞 <strong>Телефон:</strong> <a href="tel:' . $phoneMainHref . '" style="color:var(--topmaq-gold);">' . $phoneMain . '</a>';
    if (!empty($phoneService) && $phoneServiceHref !== '+' && $phoneServiceHref !== '') {
        $phoneHtml .= ', <a href="tel:' . $phoneServiceHref . '" style="color:var(--topmaq-gold);">' . $phoneService . '</a>';
    }
    $phoneHtml .= '</div>';

    $footerBlock = <<<HTML
<div style="font-size: 13.5px; color: #CBD5E1; line-height: 1.8;">
            <div>🏢 <strong>Юр. лицо:</strong> {$company}</div>
            <div>📍 <strong>Адрес:</strong> {$address}</div>
            {$phoneHtml}
            <div>✉️ <strong>Email:</strong> <a href="mailto:{$email}" style="color:var(--topmaq-gold);">{$email}</a></div>
            <div>📸 <strong>Instagram:</strong> <a href="{$instaUrl}" target="_blank" rel="noopener" style="color:#38BDF8;">{$insta}</a></div>
          </div>
HTML;

    $htmlFiles = glob(LOPAL_ROOT_DIR . '/*.html');
    $updatedCount = 0;

    foreach ($htmlFiles as $file) {
        $content = file_get_contents($file);
        $orig = $content;
        $fname = basename($file);

        // 1. Блок контактов в подвале
        $patternFb = '/<div style="font-size: 13\.5px; color: #CBD5E1; line-height: 1\.8;">[\s\S]*?<\/div>(?=\s*<\/div>\s*<div class="footer-col">)/';
        if (preg_match($patternFb, $content)) {
            $content = preg_replace($patternFb, $footerBlock, $content, 1);
        }

        // 2. Описание компании в подвале
        $patternDesc = '/<p class="footer-desc">\s*.*? — официальный дистрибьютор и генеральный представитель Jiangsu Lopal New Material Tech\. Co\., Ltd\. в Республике Казахстан\.\s*<\/p>/';
        $content = preg_replace(
            $patternDesc,
            "<p class=\"footer-desc\">\n            {$company} — официальный дистрибьютор и генеральный представитель Jiangsu Lopal New Material Tech. Co., Ltd. в Республике Казахстан.\n          </p>",
            $content
        );

        // 3. Копирайт в подвале
        $patternCopy = '/© 2026 Lopal Kazakhstan · .*?\. Все права защищены\./';
        $content = preg_replace($patternCopy, "© 2026 Lopal Kazakhstan · {$company}. Все права защищены.", $content);

        // 4. Ссылки на WhatsApp
        if (!empty($whatsapp)) {
            $content = preg_replace('/wa\.me\/7\d{10}/', 'wa.me/' . $whatsapp, $content);
        }

        // 5. Специальные карточки на странице контактов (contacts.html)
        if ($fname === 'contacts.html') {
            $content = preg_replace(
                '/(<h3>Юридическое лицо<\/h3>\s*<p style="[^"]*font-weight: 700;[^"]*">)(.*?)(<\/p>)/',
                '${1}' . $company . '${3}',
                $content
            );
            $content = preg_replace(
                '/(<h3>Адрес офиса и склада<\/h3>\s*<p style="[^"]*font-weight: 700;[^"]*">)(.*?)(<\/p>)/',
                '${1}' . $address . '${3}',
                $content
            );
            $content = preg_replace(
                '/<a href="mailto:[^"]*" style="[^"]*">✉️ [^<]*<\/a>/',
                '<a href="mailto:' . $email . '" style="font-size: 15px; font-weight: 700; color: var(--topmaq-gold);">✉️ ' . $email . '</a>',
                $content
            );

            // Обновление графика работы на странице contacts.html
            $schedUl = '<ul style="font-size: 14px; line-height: 2; color: var(--ink-secondary);">' . "\n" .
                       '                <li>🕒 <strong>Понедельник — Пятница:</strong> ' . $schedWeekdays . '</li>' . "\n" .
                       '                <li>🕒 <strong>Суббота:</strong> ' . $schedSat . '</li>' . "\n" .
                       '                <li>⛔ <strong>Воскресенье:</strong> ' . $schedSun . '</li>' . "\n" .
                       '              </ul>';
            $content = preg_replace(
                '/<h4 style="font-size: 16px; font-weight: 700; margin-bottom: 12px; color: var\(--ink-primary\);">График работы склада и офиса:<\/h4>\s*<ul[\s\S]*?<\/ul>/',
                '<h4 style="font-size: 16px; font-weight: 700; margin-bottom: 12px; color: var(--ink-primary);">График работы склада и офиса:</h4>' . "\n              " . $schedUl,
                $content
            );
        }

        // Обновление режима работы сервиса на service-points.html
        if ($fname === 'service-points.html') {
            $satSunText = ($schedSat === 'Выходной' && $schedSun === 'Выходной') ? ' (Сб–Вс: Выходной)' : " (Сб: {$schedSat}, Вс: {$schedSun})";
            $content = preg_replace(
                '/<div>🕒 <strong>Режим работы:<\/strong>[^<]*<\/div>/',
                "<div>🕒 <strong>Режим работы:</strong> Пн–Пт: {$schedWeekdays}{$satSunText}</div>",
                $content
            );
        }

        // 6. Микроразметка Schema.org JSON-LD
        $content = preg_replace('/"email":\s*"[^"]*"/', '"email": "' . $email . '"', $content);
        $content = preg_replace('/"streetAddress":\s*"[^"]*"/', '"streetAddress": "' . $address . '"', $content);
        $content = preg_replace('/"telephone":\s*"[^"]*"/', '"telephone": "' . $phoneMain . '"', $content);
        if ($schedSat === 'Выходной') {
            $content = str_replace(
                '"dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"]',
                '"dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"]',
                $content
            );
        } else {
            $content = str_replace(
                '"dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"]',
                '"dayOfWeek": ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"]',
                $content
            );
        }

        if ($content !== $orig) {
            file_put_contents($file, $content, LOCK_EX);
            $updatedCount++;
        }
    }

    return $updatedCount;
}

/**
 * Генерация актуального sitemap.xml
 */
function lopal_build_sitemap(): bool {
    $today = date('Y-m-d');
    $htmlFiles = glob(LOPAL_ROOT_DIR . '/*.html');
    $urls = [];
    foreach ($htmlFiles as $f) {
        $base = basename($f);
        if ($base === 'readme.html') continue;
        $loc = ($base === 'index.html') ? 'https://lopal.kz/' : 'https://lopal.kz/' . $base;
        $priority = ($base === 'index.html') ? '1.0' : (in_array($base, ['products.html', 'selector.html', 'news.html', 'service-points.html', 'contacts.html']) ? '0.9' : '0.8');
        $urls[] = "  <url>\n    <loc>{$loc}</loc>\n    <lastmod>{$today}</lastmod>\n    <changefreq>weekly</changefreq>\n    <priority>{$priority}</priority>\n  </url>";
    }
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n" . implode("\n", $urls) . "\n</urlset>\n";
    return file_put_contents(LOPAL_ROOT_DIR . '/sitemap.xml', $xml, LOCK_EX) !== false;
}

/**
 * 100% ОТКАТ САЙТА К ИСХОДНОМУ СОСТОЯНИЮ (initial_snapshot — до внедрения CMS)
 */
function lopal_rollback_to_initial(): array {
    $snapDir = LOPAL_BACKUPS_DIR . '/initial_snapshot';
    if (!is_dir($snapDir)) {
        return ['ok' => false, 'error' => 'Папка initial_snapshot не найдена'];
    }
    $restored = 0;
    foreach (glob($snapDir . '/*.html') as $f) {
        if (@copy($f, LOPAL_ROOT_DIR . '/' . basename($f))) {
            $restored++;
        }
    }
    if (file_exists($snapDir . '/css/styles.css')) {
        @copy($snapDir . '/css/styles.css', LOPAL_ROOT_DIR . '/css/styles.css');
    }
    if (file_exists($snapDir . '/js/main.js')) {
        @copy($snapDir . '/js/main.js', LOPAL_ROOT_DIR . '/js/main.js');
    }
    if (file_exists($snapDir . '/sitemap.xml')) {
        @copy($snapDir . '/sitemap.xml', LOPAL_ROOT_DIR . '/sitemap.xml');
    }
    // Если news.html не было в исходном снимке — удаляем его для чистоты отката
    if (!file_exists($snapDir . '/news.html') && file_exists(LOPAL_ROOT_DIR . '/news.html')) {
        @unlink(LOPAL_ROOT_DIR . '/news.html');
    }
    return ['ok' => true, 'restored_files' => $restored, 'message' => 'Сайт полностью возвращен в исходное состояние до внедрения CMS.'];
}

/**
 * Восстановление данных из конкретного снимка snap_YYYYMMDD_HHMMSS и пересборка
 */
function lopal_restore_data_snapshot(string $snapName): array {
    $snapName = basename($snapName);
    $dir = LOPAL_BACKUPS_DIR . '/' . $snapName;
    if (!is_dir($dir)) {
        return ['ok' => false, 'error' => 'Снимок не найден: ' . $snapName];
    }
    foreach (['settings', 'news', 'products', 'dealers'] as $name) {
        if (file_exists($dir . '/' . $name . '.json')) {
            @copy($dir . '/' . $name . '.json', LOPAL_DATA_DIR . '/' . $name . '.json');
        }
    }
    lopal_rebuild_all();
    return ['ok' => true, 'snapshot' => $snapName];
}
