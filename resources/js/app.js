import './bootstrap';
import 'bootstrap/dist/js/bootstrap.bundle';

import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';

document.addEventListener('DOMContentLoaded', function() {
    let calendarEl = document.getElementById('calendar');

    if (calendarEl) {
        let calendar = new Calendar(calendarEl, {
            plugins: [ dayGridPlugin ],
            initialView: 'dayGridMonth',
            events: [] // Здесь можно передавать события, например, через Blade-шаблон
        });

        calendar.render();  // Закрываем скобку здесь, чтобы завершить вызов функции
    }
});  // Закрываем скобку здесь, чтобы завершить вызов addEventListener
