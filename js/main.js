/* Lopal Kazakhstan — базовые интерактивные функции скелета сайта */
(function () {
  'use strict';

  /* Тень шапки при прокрутке */
  var header = document.getElementById('siteHeader');
  if (header) {
    var onScroll = function () {
      header.classList.toggle('is-scrolled', window.scrollY > 8);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* Мобильное меню */
  var toggle = document.getElementById('navToggle');
  if (toggle) {
    toggle.addEventListener('click', function () {
      var open = document.body.classList.toggle('nav-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      toggle.setAttribute('aria-label', open ? 'Закрыть меню' : 'Открыть меню');
    });
    document.querySelectorAll('.site-nav a').forEach(function (link) {
      link.addEventListener('click', function () {
        document.body.classList.remove('nav-open');
        toggle.setAttribute('aria-expanded', 'false');
      });
    });
  }

  /* Появление блоков при прокрутке */
  var reveals = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window && reveals.length) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('is-visible'); });
  }

  /* Тост-уведомления */
  var toastTimer;
  function toast(message) {
    var el = document.querySelector('.toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'toast';
      el.setAttribute('role', 'status');
      document.body.appendChild(el);
    }
    el.textContent = message;
    el.classList.add('is-shown');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.classList.remove('is-shown'); }, 4000);
  }

  /* Заглушки (языковые версии, недоступные действия) */
  document.querySelectorAll('[data-stub]').forEach(function (el) {
    el.addEventListener('click', function (event) {
      event.preventDefault();
      toast(el.getAttribute('data-stub'));
    });
  });

  /* Демо-формы: бэкенд будет подключён на этапе разработки */
  document.querySelectorAll('form[data-demo-form]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      toast('Демо-режим: отправка формы будет подключена при разработке бэкенда.');
      form.reset();
    });
  });

  /* Каталог: фильтр по категории (демо на карточках-заглушках) */
  var categorySelect = document.getElementById('filterCategory');
  if (categorySelect) {
    var groups = document.querySelectorAll('[data-category-group]');
    var emptyNote = document.getElementById('catalogEmpty');

    var applyFilter = function () {
      var value = categorySelect.value;
      var anyVisible = false;
      groups.forEach(function (group) {
        var show = value === 'all' || group.getAttribute('data-category-group') === value;
        group.hidden = !show;
        if (show) { anyVisible = true; }
      });
      if (emptyNote) { emptyNote.hidden = anyVisible; }
    };

    categorySelect.addEventListener('change', applyFilter);

    var resetBtn = document.getElementById('filterReset');
    if (resetBtn) {
      resetBtn.addEventListener('click', function () {
        categorySelect.value = 'all';
        applyFilter();
      });
    }

    /* Глубокие ссылки вида products.html#motor */
    var hash = window.location.hash.replace('#', '');
    if (hash && categorySelect.querySelector('option[value="' + hash + '"]')) {
      categorySelect.value = hash;
    }
    applyFilter();
  }

  /* Год в подвале */
  var year = document.getElementById('year');
  if (year) { year.textContent = String(new Date().getFullYear()); }
})();
