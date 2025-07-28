//
// <!-- Скрипт для «принудительного» перехода по якорю -->
// var menuEl = document.getElementById('mainNav');
// var bsCollapse = new bootstrap.Collapse(menuEl, {toggle: false});
//
// // Вешаем на все ссылки‑якоря и кнопки внутри меню
// menuEl.querySelectorAll('a.nav-link[href^="#"], .navbar-nav .btn[href^="#"]').forEach(function (el) {
//     el.addEventListener('click', function (e) {
//         e.preventDefault();
//         var hash = this.getAttribute('href');
//         // Скрываем collapse
//         bsCollapse.hide();
//         // Ждём момента, когда меню свернулось
//         menuEl.addEventListener('hidden.bs.collapse', function handler() {
//             menuEl.removeEventListener('hidden.bs.collapse', handler);
//             // Принудительно перезагружаем страницу на тот же путь + хэш
//             window.location.replace(window.location.pathname + hash);
//         });
//     });
// });
//
