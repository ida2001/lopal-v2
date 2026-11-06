<?php
/**
 * LOPAL KAZAKHSTAN (lopal.kz) — Kazakh Version Builder
 * Генерирует страницы на казахском языке в папку /kz/
 */

require_once __DIR__ . '/builder.php';

define('LOPAL_KZ_DIR', LOPAL_ROOT_DIR . '/kz');

function lopal_build_kz_all(): array {
    if (!is_dir(LOPAL_KZ_DIR)) {
        @mkdir(LOPAL_KZ_DIR, 0755, true);
    }

    $i18n = lopal_read_json('i18n');
    $settings = lopal_read_json('settings');
    $menu = lopal_get_menu();
    $news = lopal_read_json('news');
    $products = lopal_read_json('products');
    $dealers = lopal_read_json('dealers');

    $generated = [];

    // 1. Главная страница kz/index.html
    $generated[] = lopal_build_kz_page('index.html', 'Басты бет', 'Jiangsu Lopal New Material Tech. Co., Ltd. компаниясының Қазақстан Республикасындағы ресми дистрибьюторы — «Topmaq oil» ЖШС.');

    // 2. Каталог продукции kz/products.html
    $generated[] = lopal_build_kz_page('products.html', 'Өнімдер каталогы', 'LOPAL мотор және трансмиссия майлары, антифриздер мен техникалық сұйықтықтардың ресми каталогы.');

    // 3. Подбор масла kz/selector.html
    $generated[] = lopal_build_kz_page('selector.html', 'Май таңдау онлайн', 'Автокөлігіңіздің маркасы мен қозғалтқышына сәйкес келетін LOPAL майын онлайн таңдаңыз.');

    // 4. Технологии kz/technologies.html
    $generated[] = lopal_build_kz_page('technologies.html', 'Технологиялар мен R&D', 'LOPAL ғылыми-зерттеу зертханалары және инновациялық майлау технологиялары.');

    // 5. Сертификаты и допуски kz/certificates.html
    $generated[] = lopal_build_kz_page('certificates.html', 'OEM рұқсаттары мен сертификаттары', 'Әлемдік автоөндірушілердің ресми мақұлдаулары (Mercedes-Benz, BMW, Porsche, Volkswagen, Cummins, Volvo, API SQ/SP).');

    // 6. Точки продаж и СТО kz/service-points.html
    $generated[] = lopal_build_kz_page('service-points.html', 'Сату нүктелері мен ТҚО картасы', 'Қазақстан қалаларындағы ресми дилерлер, автодүкендер және май ауыстыру орталықтары.');

    // 7. Новости kz/news.html
    $generated[] = lopal_build_kz_page('news.html', 'Жаңалықтар', 'LOPAL брендінің және «Topmaq oil» ЖШС ресми жаңалықтары мен оқиғалары.');

    // 8. Партнерам kz/partners.html
    $generated[] = lopal_build_kz_page('partners.html', 'Серіктестерге', 'Автопарктерге, ТҚО және аймақтық дилерлерге арналған тиімді ынтымақтастық шарттары.');

    // 9. Контакты kz/contacts.html
    $generated[] = lopal_build_kz_page('contacts.html', 'Байланыс', 'Қазақстандағы ресми өкілдік: Алматы қаласы, Соқпақбаев көшесі, 67а.');

    // 10. О компании kz/about.html
    $generated[] = lopal_build_kz_page('about.html', 'Компания туралы', 'Jiangsu Lopal New Material Tech. Co., Ltd. және Қазақстандағы ресми өкілі «Topmaq oil» ЖШС туралы мәлімет.');

    return [
        'ok' => true,
        'count' => count($generated),
        'pages' => $generated,
        'message' => 'Казахская версия успешно сгенерирована в папку /kz/ (' . count($generated) . ' страниц)'
    ];
}

/**
 * Генерация отдельной страницы казахской версии
 */
function lopal_build_kz_page(string $pageFilename, string $kzTitle, string $kzMetaDesc): string {
    $srcPath = LOPAL_ROOT_DIR . '/' . $pageFilename;
    $dstPath = LOPAL_KZ_DIR . '/' . $pageFilename;

    if (!file_exists($srcPath)) {
        return '';
    }

    $html = file_get_contents($srcPath);

    // 1. Атрибут языка
    $html = preg_replace('/<html[^>]*lang=["\'][^"\']*["\']/i', '<html lang="kk"', $html);

    // 2. Метатеги
    $canonicalKz = "https://lopal.kz/kz/{$pageFilename}";
    $canonicalRu = "https://lopal.kz/{$pageFilename}";
    
    // Внедряем hreflang
    $hreflangTags = <<<HTML
  <link rel="alternate" hreflang="kk-KZ" href="{$canonicalKz}">
  <link rel="alternate" hreflang="ru-KZ" href="{$canonicalRu}">
HTML;
    $html = preg_replace('/(<\/head>)/i', $hreflangTags . "\n$1", $html, 1);

    // 3. Обновляем пути к ресурсам (assets/, css/, js/)
    $html = preg_replace('/href="css\//', 'href="../css/', $html);
    $html = preg_replace('/src="js\//', 'src="../js/', $html);
    $html = preg_replace('/src="assets\//', 'src="../assets/', $html);
    $html = preg_replace('/href="assets\//', 'href="../assets/', $html);
    $html = preg_replace('/url\([\'"]?assets\//', 'url(\'../assets/', $html);

    // 4. Главное навигационное меню (перевод пунктов на казахский)
    $i18n = lopal_read_json('i18n');
    $navKz = $i18n['nav'] ?? [];

    $navUlLines = ["        <ul>"];
    $menu = lopal_get_menu();
    foreach ($menu as $mItem) {
        if (isset($mItem['visible']) && !$mItem['visible']) continue;
        $url = $mItem['url'] ?? 'index.html';
        $basename = basename($url);
        $titleKz = $navKz[$basename]['kz'] ?? ($mItem['title'] ?? 'Бөлім');
        $activeClass = ($basename === $pageFilename) ? ' class="is-active"' : '';
        $navUlLines[] = "          <li><a href=\"{$basename}\"{$activeClass}>" . htmlspecialchars($titleKz, ENT_QUOTES, 'UTF-8') . "</a></li>";
    }
    $navUlLines[] = "        </ul>";
    $navUlHtml = implode("\n", $navUlLines);

    // Заменяем блок <nav class="site-nav" ...>
    $html = preg_replace('/<nav class="site-nav"[^>]*>[\s\S]*?<\/nav>/i', "<nav class=\"site-nav\" id=\"siteNav\" aria-label=\"Басты мәзір\">\n{$navUlHtml}\n      </nav>", $html);

    // 5. Внедряем переключатель языков RU | KZ в шапку
    $langSwitchHtml = <<<HTML
      <div class="lang-switch" id="langSwitch">
        <a href="../{$pageFilename}" class="lang-btn" title="Орысша нұсқа (RU)">RU</a>
        <span class="lang-sep">|</span>
        <a href="{$pageFilename}" class="lang-btn is-active" title="Қазақша нұсқа (KZ)">KZ</a>
      </div>
HTML;

    // Вставляем переключатель перед <button class="nav-toggle"
    if (strpos($html, 'class="lang-switch"') === false) {
        $html = preg_replace('/(<div class="header-actions">)/i', "$1\n{$langSwitchHtml}", $html);
    } else {
        $html = preg_replace('/<div class="lang-switch"[^>]*>[\s\S]*?<\/div>/i', $langSwitchHtml, $html);
    }

    // 6. Перевод базовых общих элементов интерфейса (подвал, кнопки)
    $translations = [
      'Официальный дистрибьютор' => 'Ресми дистрибьютор',
      'Официальное представительство' => 'Ресми өкілдік',
      'Перейти в каталог продукции' => 'Өнімдер каталогына өту',
      'Перейти в каталог' => 'Каталогқа өту',
      'Связаться в WhatsApp' => 'WhatsApp арқылы байланысу',
      'Написать в WhatsApp' => 'WhatsApp-қа жазу',
      'Подобрать масло онлайн' => 'Майды онлайн таңдау',
      'Онлайн-подбор масла' => 'Майды онлайн таңдау',
      'Получить консультацию' => 'Кеңес алу',
      'Запросить прайс-лист' => 'Бағалар тізімін сұрау',
      'Запросите оптовый прайс-лист' => 'Көтерме бағалар тізімін сұраңыз',
      'Все права защищены' => 'Барлық құқықтар қорғалған',
      'ПРОДУКЦИЯ' => 'ӨНІМДЕР',
      'РАЗДЕЛЫ' => 'БӨЛІМДЕР',
      'ПРЯМОЙ КОНТАКТ' => 'ТІКЕЛЕЙ БАЙЛАНЫС',
      'Точки продаж и СТО' => 'Сату нүктелері мен ТҚО',
      'Новости компании' => 'Компания жаңалықтары',
      'Партнерам и дилерам' => 'Серіктестер мен дилерлерге',
      'Технологии и R&D' => 'Технологиялар мен R&D',
      'Допуски OEM и сертификаты' => 'OEM рұқсаттары мен сертификаттары',
      'Контакты и реквизиты' => 'Байланыс және деректемелер',
      'О компании' => 'Компания туралы',
      'Заказать звонок' => 'Кері қоңырау тапсырысы',
      'Читать новость' => 'Толығырақ оқу',
      'Читать полностью' => 'Толығырақ оқу',
      'Подробнее →' => 'Толығырақ →'
    ];

    foreach ($translations as $ruPhrase => $kzPhrase) {
        $html = str_replace($ruPhrase, $kzPhrase, $html);
    }

    // 7. Ссылки на внутренние страницы внутри казахоязычной версии
    // (например href="products.html" -> href="products.html", но если было href="index.html" остается href="index.html")
    // Логотип в шапке ведет на казахскую главную index.html
    $html = preg_replace('/href="index\.html" class="brand-combo"/i', 'href="index.html" class="brand-combo"', $html);

    file_put_contents($dstPath, $html);
    return $pageFilename;
}
