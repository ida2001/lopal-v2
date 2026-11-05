/* ============================================================
   LOPAL KAZAKHSTAN & ТОО «Topmaq oil» (TOPMAQ OIL)
   Основной скрипт сайта: тема (светлая/тёмная), фильтрация каталога,
   модальное окно подбора масла, интерактивная карта дилеров ("Где купить")
   ============================================================ */

(function () {
  'use strict';

  /* ------------------------------------------------------------
     1. Светлая тема (Light Theme Only)
     ------------------------------------------------------------ */
  var root = document.documentElement;
  root.setAttribute('data-theme', 'light');
  localStorage.setItem('lopal_theme', 'light');

  /* ------------------------------------------------------------
     2. База дилеров и точек продаж LOPAL в Казахстане
     ------------------------------------------------------------ */
  var DEALERS_DATA = [
  {
    "id": "hq",
    "isHq": true,
    "badge": "Центральный склад и клубный сервис GEELY",
    "name": "Topmaq oil service (@topmaqoil_service)",
    "address": "г. Алматы, ул. Сокпакбаева, 67а (по 2ГИС)",
    "phone": "+7 (707) 523-65-25",
    "phoneHref": "+77075236525",
    "coords": [
      43.2685,
      76.9125
    ],
    "dgis": "https://2gis.kz/almaty/search/%D0%A1%D0%BE%D0%BA%D0%BF%D0%B0%D0%BA%D0%B1%D0%B0%D0%B5%D0%B2%D0%B0%2067%D0%B0"
  },
  {
    "id": "astana-oilbt-1",
    "isHq": false,
    "badge": "Автосервис, Магазин, оптовая и розничная торговля",
    "name": "ИП OILBT",
    "address": "г. Астана, Проспект Абылай хана, 50а",
    "phone": "+7 (701) 037-07-04",
    "phoneHref": "+77010370704",
    "coords": [
      51.152367,
      71.49176
    ],
    "dgis": "https://2gis.kz/astana/search/%D0%90%D0%B1%D1%8B%D0%BB%D0%B0%D0%B9%20%D1%85%D0%B0%D0%BD%D0%B0%2050%D0%B0"
  },
  {
    "id": "astana-oilbt-2",
    "isHq": false,
    "badge": "Автосервис, Магазин, оптовая и розничная торговля",
    "name": "ИП OILBT (WhatsApp)",
    "address": "г. Астана, Улица Динмухамеда Конаева, 12/1",
    "phone": "+7 701 707 00 72, +7 702 483 60 49, +7 701 037 07 05",
    "phoneHref": "+77017070072",
    "coords": [
      51.1278,
      71.4312
    ],
    "dgis": "https://2gis.kz/astana/search/%D0%9A%D0%BE%D0%BD%D0%B0%D0%B5%D0%B2%D0%B0%2012%2F1"
  },
  {
    "id": "astana-evgroup",
    "isHq": false,
    "badge": "Автосервис, Магазин, оптовая и розничная торговля",
    "name": "ТОО EVGROUP KAZAKHSTAN (@Dacarkz, @Dacar_astana01)",
    "address": "г. Астана",
    "phone": "+7 (707) 105-00-71",
    "phoneHref": "+77071050071",
    "coords": [
      51.12828,
      71.430471
    ],
    "dgis": "https://2gis.kz/astana/search/EVGROUP"
  },
  {
    "id": "almaty-azrem",
    "isHq": false,
    "badge": "Автосервис, Магазин, оптовая и розничная торговля",
    "name": "ТОО AZ РемСервис (@AzRemService)",
    "address": "г. Алматы, ул. Казыбаева, 1/2",
    "phone": "+7 (747) 489-03-07",
    "phoneHref": "+77474890307",
    "coords": [
      43.279986,
      76.922394
    ],
    "dgis": "https://2gis.kz/almaty/search/%D0%9A%D0%B0%D0%B7%D1%8B%D0%B1%D0%B0%D0%B5%D0%B2%D0%B0%201%2F2"
  },
  {
    "id": "almaty-maslenka",
    "isHq": false,
    "badge": "Магазин, оптовая и розничная торговля",
    "name": "ИП Maslenka (@Maslenka.kz)",
    "address": "г. Алматы, ул. Яссауи, 57а, ТЦ Car City, 4 ярус, 44 бутик",
    "phone": "+7 (777) 422-33-06",
    "phoneHref": "+77774223306",
    "coords": [
      43.238785,
      76.822319
    ],
    "dgis": "https://2gis.kz/almaty/search/Car%20City%20%D0%AF%D1%81%D1%81%D0%B0%D1%83%D0%B8%2057%D0%B0"
  },
  {
    "id": "almaty-suleon",
    "isHq": false,
    "badge": "Автосервис, Магазин, оптовая и розничная торговля",
    "name": "ИП SULEON PARTS",
    "address": "г. Алматы, ул. Прокофьева, 244",
    "phone": "+7 (777) 801-12-49",
    "phoneHref": "+77778011249",
    "coords": [
      43.236009,
      76.876902
    ],
    "dgis": "https://2gis.kz/almaty/search/%D0%9F%D1%80%D0%BE%D0%BA%D0%BE%D1%84%D1%8C%D0%B5%D0%B2%D0%B0%20244"
  },
  {
    "id": "almaty-oilbt",
    "isHq": false,
    "badge": "Автосервис, Магазин, оптовая и розничная торговля",
    "name": "ИП Oilbt.kz",
    "address": "г. Алматы, ул. Ораза Жандосова, 94а/1",
    "phone": "+7 (701) 218-26-75",
    "phoneHref": "+77012182675",
    "coords": [
      43.218883,
      76.878453
    ],
    "dgis": "https://2gis.kz/almaty/search/%D0%96%D0%B0%D0%BD%D0%B4%D0%BE%D1%81%D0%BE%D0%B2%D0%B0%2094%D0%B0%2F1"
  },
  {
    "id": "almaty-yerkin",
    "isHq": false,
    "badge": "Магазин и автосервис",
    "name": "ПЗМ «Yerkin Oil Service»",
    "address": "г. Алматы, Медеуский район, ул. Халиуллина, 160/1, боксы 15 и 16",
    "phone": "+7 (708) 374-12-32, +7 (777) 799-99-84",
    "phoneHref": "+77083741232",
    "coords": [
      43.2795,
      76.9792
    ],
    "dgis": "https://2gis.kz/almaty/search/%D0%A5%D0%B0%D0%BB%D0%B8%D1%83%D0%BB%D0%BB%D0%B8%D0%BD%D0%B0%20160%2F1"
  },
  {
    "id": "almaty-remzona",
    "isHq": false,
    "badge": "Магазин и автосервис",
    "name": "Ремзона такси",
    "address": "г. Алматы, ул. Толе би, 285/4",
    "phone": "+7 (747) 202-43-43",
    "phoneHref": "+77472024343",
    "coords": [
      43.2512,
      76.8615
    ],
    "dgis": "https://2gis.kz/almaty/search/%D0%A2%D0%BE%D0%BB%D0%B5%20%D0%B1%D0%B8%20285%2F4"
  },
  {
    "id": "almaty-erudit",
    "isHq": false,
    "badge": "Интернет магазин",
    "name": "ИП Эрудит",
    "address": "г. Алматы, мкр. Тастыбулак, ул. Алмалы, 2/66",
    "phone": "+7 (701) 593-50-25",
    "phoneHref": "+77015935025",
    "coords": [
      43.1874,
      76.8123
    ],
    "dgis": "https://2gis.kz/almaty/search/%D0%A2%D0%B0%D1%81%D1%82%D1%8B%D0%B1%D1%83%D0%BB%D0%B0%D0%BA%20%D0%90%D0%BB%D0%BC%D0%B0%D0%BB%D1%8B%202%2F66"
  },
  {
    "id": "almaty-alex",
    "isHq": false,
    "badge": "Магазин и автосервис",
    "name": "Автосервис ALEX (@CTO_alex_)",
    "address": "г. Алматы, ул. Островского, 23",
    "phone": "+7 (705) 169-10-10",
    "phoneHref": "+77051691010",
    "coords": [
      43.279116,
      76.935492
    ],
    "dgis": "https://2gis.kz/almaty/search/%D0%9E%D1%81%D1%82%D1%80%D0%BE%D0%B2%D1%81%D0%BA%D0%BE%D0%B3%D0%BE%2023"
  },
  {
    "id": "aktau-eneos",
    "isHq": false,
    "badge": "Магазин и автосервис",
    "name": "Eneos oil bar",
    "address": "г. Актау, Мангистауская область, мкр. 1, здание 1/7, 1/8 напротив теннисного корта",
    "phone": "+7 (701) 174-51-54",
    "phoneHref": "+77011745154",
    "coords": [
      43.6375,
      51.159
    ],
    "dgis": "https://2gis.kz/aktau/search/%D0%BC%D0%BA%D1%80%201%20%D0%B7%D0%B4%D0%B0%D0%BD%D0%B8%D0%B5%201%2F7"
  },
  {
    "id": "aktau-grm",
    "isHq": false,
    "badge": "Магазин и автосервис",
    "name": "Авто комплекс GRM",
    "address": "г. Актау, Мангистауская область, Промышленная зона 1, здание 67",
    "phone": "+7 (777) 200-77-78",
    "phoneHref": "+77772007778",
    "coords": [
      43.680101,
      51.195298
    ],
    "dgis": "https://2gis.kz/aktau/search/%D0%9F%D1%80%D0%BE%D0%BC%D1%8B%D1%88%D0%BB%D0%B5%D0%BD%D0%BD%D0%B0%D1%8F%20%D0%B7%D0%BE%D0%BD%D0%B0%201%20%D0%B7%D0%B4%D0%B0%D0%BD%D0%B8%D0%B5%2067"
  },
  {
    "id": "altay-partner",
    "isHq": false,
    "badge": "Магазин и автосервис",
    "name": "ИП Partner Auto",
    "address": "г. Алтай, ВКО, ул. Астана, 69",
    "phone": "+7 (776) 437-73-33",
    "phoneHref": "+77764377333",
    "coords": [
      49.73001,
      84.267875
    ],
    "dgis": "https://2gis.kz/search/%D0%90%D0%BB%D1%82%D0%B0%D0%B9%20%D0%90%D1%81%D1%82%D0%B0%D0%BD%D0%B0%2069"
  },
  {
    "id": "taldykorgan-west",
    "isHq": false,
    "badge": "Магазин и автосервис",
    "name": "West oil",
    "address": "г. Талдыкорган, ул. Гагарина, 103",
    "phone": "+7 (708) 070-24-24",
    "phoneHref": "+77080702424",
    "coords": [
      45.021577,
      78.426515
    ],
    "dgis": "https://2gis.kz/taldykorgan/search/%D0%93%D0%B0%D0%B3%D0%B0%D1%80%D0%B8%D0%BD%D0%B0%20103"
  },
  {
    "id": "shymkent-ysoil",
    "isHq": false,
    "badge": "Автосервис, Магазин, оптовая и розничная торговля",
    "name": "Y.S.OIL service",
    "address": "г. Шымкент, район Туран, ул. Кожанова, 31",
    "phone": "+7 (701) 990-85-44",
    "phoneHref": "+77019908544",
    "coords": [
      42.33251,
      69.582119
    ],
    "dgis": "https://2gis.kz/shymkent/search/%D0%9A%D0%BE%D0%B6%D0%B0%D0%BD%D0%BE%D0%B2%D0%B0%2031"
  },
  {
    "id": "shymkent-china",
    "isHq": false,
    "badge": "Магазин и автосервис",
    "name": "Автосервис CHINA + (@china_plus_17)",
    "address": "г. Шымкент, ул. Сырым батыра, 173/1",
    "phone": "+7 (707) 722-01-03",
    "phoneHref": "+77077220103",
    "coords": [
      42.380752,
      69.611793
    ],
    "dgis": "https://2gis.kz/shymkent/search/%D0%A1%D1%8B%D1%80%D1%8B%D0%BC%20%D0%B1%D0%B0%D1%82%D1%8B%D1%80%D0%B0%20173%2F1"
  },
  {
    "id": "arys-china",
    "isHq": false,
    "badge": "Магазин и автосервис",
    "name": "Автосервис CHINA + (@china_plus_17)",
    "address": "г. Арысь, Туркестанская область, ул. Алаш, дом 20",
    "phone": "+7 (777) 577-03-13",
    "phoneHref": "+77775770313",
    "coords": [
      42.428,
      68.802
    ],
    "dgis": "https://2gis.kz/search/%D0%90%D1%80%D1%8B%D1%81%D1%8C%20%D0%90%D0%BB%D0%B0%D1%88%2020"
  }
];

  var leafletLoaded = false;
  var leafletLoadingCallbacks = [];

  function loadLeaflet(callback) {
    if (window.L) {
      callback();
      return;
    }
    leafletLoadingCallbacks.push(callback);
    if (leafletLoaded) return;
    leafletLoaded = true;

    var link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    document.head.appendChild(link);

    var script = document.createElement('script');
    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    script.onload = function () {
      leafletLoadingCallbacks.forEach(function (cb) { cb(); });
      leafletLoadingCallbacks = [];
    };
    document.head.appendChild(script);
  }

  function createMapMarkers(mapInstance) {
    var markers = {};
    DEALERS_DATA.forEach(function (d) {
      var color = d.isHq ? '#C5A059' : '#00843D';
      var iconHtml = '<div style="width:28px;height:28px;border-radius:50%;background:' + color + ';border:3px solid #fff;box-shadow:0 3px 10px rgba(0,0,0,0.35);display:flex;align-items:center;justify-content:center;color:#fff;font-size:12px;font-weight:800;">L</div>';
      var icon = window.L.divIcon({
        html: iconHtml,
        className: 'lopal-map-pin',
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -14]
      });

      var popupContent =
        '<div style="font-family:Inter,sans-serif;min-width:210px;padding:4px 2px;">' +
          '<div style="font-size:10px;font-weight:700;text-transform:uppercase;color:' + color + ';margin-bottom:3px;">' + d.badge + '</div>' +
          '<div style="font-size:14px;font-weight:800;color:#0F172A;margin-bottom:4px;">' + d.name + '</div>' +
          '<div style="font-size:12.5px;color:#475569;margin-bottom:8px;">' + d.address + '</div>' +
          '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;padding-top:6px;border-top:1px solid #E2E8F0;">' +
            '<a href="tel:' + d.phoneHref + '" style="font-size:12.5px;font-weight:700;color:#00843D;text-decoration:none;">' + d.phone + '</a>' +
            '<a href="' + d.dgis + '" target="_blank" rel="noopener" style="font-size:11.5px;font-weight:700;background:#00843D;color:#fff;padding:4px 10px;border-radius:99px;text-decoration:none;">2ГИС ↗</a>' +
          '</div>' +
        '</div>';

      var marker = window.L.marker(d.coords, { icon: icon }).addTo(mapInstance).bindPopup(popupContent);
      markers[d.id] = marker;
    });
    return markers;
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.theme-toggle-btn, .btn-oil-selector').forEach(function (el) {
      el.remove();
    });

    /* ------------------------------------------------------------
       3. Официальный подбор масла Lopal (открытие в новой вкладке)
       ------------------------------------------------------------ */
    var openBtns = document.querySelectorAll('[data-open-selector]');
    var SELECTOR_URL = 'selector.html';

    openBtns.forEach(function (btn) {
      if (btn.tagName === 'A') {
        btn.setAttribute('href', SELECTOR_URL);
        btn.setAttribute('target', '_blank');
        btn.setAttribute('rel', 'noopener noreferrer');
      } else {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          window.open(SELECTOR_URL, '_blank', 'noopener,noreferrer');
        });
      }
    });

    /* ------------------------------------------------------------
       4. Модальное окно с интерактивной картой дилеров ("Где купить")
       ------------------------------------------------------------ */
    var dealersModal = null;
    var dealersMap = null;
    var dealersMarkers = {};

    function ensureDealersModal() {
      if (dealersModal) return dealersModal;

      var overlay = document.createElement('div');
      overlay.className = 'selector-modal-overlay';
      overlay.id = 'dealersMapModal';
      overlay.setAttribute('role', 'dialog');
      overlay.setAttribute('aria-modal', 'true');

      var listHtml = DEALERS_DATA.map(function (d, idx) {
        return (
          '<button type="button" class="dealer-list-item' + (d.isHq ? ' is-hq' : '') + (idx === 0 ? ' is-active' : '') + '" data-dealer-id="' + d.id + '">' +
            '<span class="dealer-list-badge">' + d.badge + '</span>' +
            '<div class="dealer-list-title">' + d.name + '</div>' +
            '<div class="dealer-list-addr">' + d.address + '</div>' +
            '<div class="dealer-list-phone">' + d.phone + '</div>' +
          '</button>'
        );
      }).join('');

      overlay.innerHTML =
        '<div class="selector-modal" style="width: min(1100px, 100%); height: min(760px, 90vh);">' +
          '<div class="selector-modal-header">' +
            '<div class="selector-modal-title">' +
              '<span>Где купить продукцию LOPAL — Карта дилеров и ПЗМ</span>' +
            '</div>' +
            '<div class="selector-modal-actions">' +
              '<a href="service-points.html" class="btn btn-sm btn-outline" style="font-size: 12.5px;">Все точки продаж →</a>' +
              '<button class="modal-close-btn" data-close-dealers type="button" aria-label="Закрыть карту">✕</button>' +
            '</div>' +
          '</div>' +
          '<div class="dealers-modal-body">' +
            '<div class="dealers-sidebar">' + listHtml + '</div>' +
            '<div class="dealers-map-canvas" id="dealersModalMapCanvas"></div>' +
          '</div>' +
        '</div>';

      document.body.appendChild(overlay);
      dealersModal = overlay;

      overlay.querySelector('[data-close-dealers]').addEventListener('click', closeDealersModal);
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeDealersModal();
      });

      var items = overlay.querySelectorAll('.dealer-list-item');
      items.forEach(function (item) {
        item.addEventListener('click', function () {
          items.forEach(function (el) { el.classList.remove('is-active'); });
          item.classList.add('is-active');
          var id = item.getAttribute('data-dealer-id');
          var dealer = DEALERS_DATA.find(function (d) { return d.id === id; });
          if (dealer && dealersMap && dealersMarkers[id]) {
            dealersMap.flyTo(dealer.coords, 15, { duration: 0.6 });
            dealersMarkers[id].openPopup();
          }
        });
      });

      return overlay;
    }

    function openDealersModal(e) {
      if (e) e.preventDefault();
      var modal = ensureDealersModal();
      modal.classList.add('is-active');
      document.body.style.overflow = 'hidden';

      loadLeaflet(function () {
        if (!dealersMap) {
          dealersMap = window.L.map('dealersModalMapCanvas');
          window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
          }).addTo(dealersMap);
          dealersMarkers = createMapMarkers(dealersMap);
          var pts = [];
          DEALERS_DATA.forEach(function(d) {
            if (d.coords && d.coords[0] && d.coords[1]) pts.push(d.coords);
          });
          if (pts.length > 0) {
            dealersMap.fitBounds(pts, { padding: [30, 30] });
          } else {
            dealersMap.setView([48.0196, 66.9237], 5);
          }
          if (dealersMarkers['hq']) dealersMarkers['hq'].openPopup();
        }
        setTimeout(function () {
          dealersMap.invalidateSize();
        }, 200);
      });
    }

    function closeDealersModal() {
      if (!dealersModal) return;
      dealersModal.classList.remove('is-active');
      document.body.style.overflow = '';
    }

    document.querySelectorAll('[data-open-dealers], .btn-card-order').forEach(function (btn) {
      btn.addEventListener('click', openDealersModal);
    });

    /* Открытие карты дилеров по клику на кнопку продукта внутри интерактивного каталога (postMessage) */
    window.addEventListener('message', function (event) {
      if (event && event.data && event.data.type === 'OPEN_DEALERS_MAP') {
        openDealersModal();
        if (event.data.productNo && dealersModal) {
          var titleSpan = dealersModal.querySelector('.selector-modal-title span');
          if (titleSpan) {
            titleSpan.textContent = 'Где купить ' + event.data.productNo + ' — Карта дилеров и ПЗМ';
          }
        }
      }
    });

    /* Если на странице service-points.html есть контейнер карты #dealersPageMap */
    var pageMapEl = document.getElementById('dealersPageMap');
    if (pageMapEl) {
      loadLeaflet(function () {
        var pMap = window.L.map('dealersPageMap');
        window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
          maxZoom: 19,
          attribution: '© OpenStreetMap'
        }).addTo(pMap);
        var pMarkers = createMapMarkers(pMap);
        var pts = [];
        DEALERS_DATA.forEach(function(d) {
          if (d.coords && d.coords[0] && d.coords[1]) pts.push(d.coords);
        });
        if (pts.length > 0) {
          pMap.fitBounds(pts, { padding: [40, 40] });
        } else {
          pMap.setView([48.0196, 66.9237], 5);
        }
        if (pMarkers['hq']) pMarkers['hq'].openPopup();
      });
    }

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') {
        if (dealersModal && dealersModal.classList.contains('is-active')) closeDealersModal();
      }
    });

    /* ------------------------------------------------------------
       5. Тень шапки при скролле и мобильное меню
       ------------------------------------------------------------ */
    var header = document.getElementById('siteHeader');
    if (header) {
      var checkScroll = function () {
        header.classList.toggle('is-scrolled', window.scrollY > 15);
      };
      checkScroll();
      window.addEventListener('scroll', checkScroll, { passive: true });
    }

    var navToggle = document.getElementById('navToggle');
    if (navToggle) {
      navToggle.addEventListener('click', function () {
        var open = document.body.classList.toggle('nav-open');
        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
      document.querySelectorAll('.site-nav a').forEach(function (a) {
        a.addEventListener('click', function () {
          document.body.classList.remove('nav-open');
          if (navToggle) navToggle.setAttribute('aria-expanded', 'false');
        });
      });
    }

    /* ------------------------------------------------------------
       6. Интерактивная фильтрация каталога по категориям
       ------------------------------------------------------------ */
    var catTabs = document.querySelectorAll('.cat-tab-btn');
    var productCards = document.querySelectorAll('.product-card');
    var emptyMessage = document.getElementById('productsEmptyState');

    if (catTabs.length && productCards.length) {
      catTabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          var targetCat = tab.getAttribute('data-cat');
          catTabs.forEach(function (t) { t.classList.remove('is-active'); });
          tab.classList.add('is-active');

          var visibleCount = 0;
          productCards.forEach(function (card) {
            var cardCat = card.getAttribute('data-category');
            var isMatch = targetCat === 'all' || cardCat === targetCat;
            if (isMatch) {
              card.style.display = '';
              visibleCount++;
            } else {
              card.style.display = 'none';
            }
          });

          if (emptyMessage) {
            emptyMessage.style.display = visibleCount === 0 ? 'block' : 'none';
          }
        });
      });
    }

    /* ------------------------------------------------------------
       7. Тост-уведомления и демо-формы
       ------------------------------------------------------------ */
    var toastTimer;
    function showToast(msg) {
      var toastEl = document.querySelector('.site-toast');
      if (!toastEl) {
        toastEl = document.createElement('div');
        toastEl.className = 'site-toast';
        toastEl.style.cssText = 'position:fixed;bottom:90px;left:50%;transform:translateX(-50%);background:#12171E;color:#fff;padding:12px 24px;border-radius:30px;box-shadow:0 8px 30px rgba(0,0,0,0.5);z-index:999;font-size:14px;border:1px solid rgba(255,255,255,0.1);transition:opacity 0.25s ease;';
        document.body.appendChild(toastEl);
      }
      toastEl.textContent = msg;
      toastEl.style.opacity = '1';
      clearTimeout(toastTimer);
      toastTimer = setTimeout(function () {
        toastEl.style.opacity = '0';
      }, 3500);
    }

    document.querySelectorAll('[data-stub]').forEach(function (el) {
      el.addEventListener('click', function (e) {
        e.preventDefault();
        showToast(el.getAttribute('data-stub'));
      });
    });

    document.querySelectorAll('form[data-demo-form]').forEach(function (form) {
      form.addEventListener('submit', function (e) {
        e.preventDefault();
        showToast('Спасибо за обращение! Представитель ТОО «Topmaq oil» свяжется с вами в ближайшее время.');
        form.reset();
      });
    });

  });
})();
