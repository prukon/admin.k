//general-script.js

// —— Сначала: стили (порядок не критичен) ——
import 'select2/dist/css/select2.min.css';
import 'datatables.net-dt/css/dataTables.dataTables.min.css';
import 'croppie/croppie.css';
import 'daterangepicker/daterangepicker.css';

// —— Плагины ——

/* Select2 */
import 'select2';

/* DataTables */
import 'datatables.net';
import 'datatables.net-dt';

/* Croppie */
import Croppie from 'croppie';
window.Croppie = Croppie;

/* Moment & DateRangePicker */
import moment from 'moment';
window.moment = moment;
import 'daterangepicker';

/* Axios (если нужен) */
import axios from 'axios';
window.axios = axios;

// —— Инициализация плагинов без jQuery ——
document.addEventListener('DOMContentLoaded', () => {
    // Здесь можно инициализировать плагины без jQuery,
    // или подключить jQuery инициализацию позже при необходимости.
});
