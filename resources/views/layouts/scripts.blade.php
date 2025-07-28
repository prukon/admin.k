<script>
    // resources/js/app.js

    // ——— Импорт CSS библиотек ———
    // Vite позволит собрать их в единый CSS-бандл
    import 'bootstrap/dist/css/bootstrap.min.css';
    import 'jquery-ui-dist/jquery-ui.min.css';
    import 'select2/dist/css/select2.min.css';

    // ——— Глобальные зависимости ———
    // Подключаем jQuery и делаем его глобальным ($ и jQuery)
    import jquery from 'jquery';
    window.$ = window.jQuery = jquery;

    // Подключаем jQuery UI (требует глобального jQuery)
    import 'jquery-ui-dist/jquery-ui';

    // Подключаем Select2 (требует глобального jQuery)
    import 'select2';

    // ——— Bootstrap JS + Popper ———
    // Импортируем весь бандл и экспортируем его как глобальный объект bootstrap,
    // чтобы ваш код вида `new bootstrap.Collapse(...)` работал без ошибок.
    import * as bootstrap from 'bootstrap/dist/js/bootstrap.bundle';
    window.bootstrap = bootstrap;

    // ——— Другие общие скрипты ———
    // Например, axios
    import axios from 'axios';
    window.axios = axios;

    // Любые другие ваши утилиты или плагины:
    // import './utils';
    // import './notifications';

    // По умолчанию Vite будет ждать, пока этот файл загрузится на странице,
    // затем выполнит остальные модули (например, resources/js/landing.js).

</script>