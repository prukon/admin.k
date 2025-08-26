    //common-script.js

    // 1. Подключаем jQuery и сразу выставляем в глобал
    import $ from 'jquery';
    window.$ = window.jQuery = $;

    // 2. Подключаем всё остальное, что можно (Bootstrap, FontAwesome…)
    // import 'bootstrap';
    // Импортируем всё в локальный объект
    import * as bootstrap from 'bootstrap';
    // Выкладываем в глобал, чтобы window.bootstrap был определён
    window.bootstrap = bootstrap;

 
    import '@fortawesome/fontawesome-free/js/all';
    import '@fortawesome/fontawesome-free/css/all.css';

    // 3. Динамически подгружаем jquery-ui уже после выставления window.jQuery
    (async () => {
        await import('jquery-ui-dist/jquery-ui');

        // 4. Инициализация и проверка всех библиотек
        // $(document).ready(function () {
        //     console.log('✅ jQuery, jQuery UI, Bootstrap и FontAwesome подключены!');
        //     console.log('• jQuery v' + $.fn.jquery);
        //     console.log('• jQuery UI v' + ($.ui && $.ui.version));
        // });


    })();

    import './contact-form.js';

