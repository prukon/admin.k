/* ===== Подключения библиотек (локально через Vite) =====
 * Порядок: Bootstrap CSS → (прочие CSS) → jQuery UI → DataTables → Select2 → Croppie → moment+Daterangepicker → FullCalendar (JS)
 * ВАЖНО: jQuery уже подключён инлайн в layout.
 */

/* --- CSS --- */
/* Bootstrap 5 стили — ВЫПИЛЕНЫ, так как уже подключаются через layout */

/* jQuery UI (datepicker, draggable, dialog…) */
import 'jquery-ui-dist/jquery-ui.css';

/* DataTables (Bootstrap 5 + Responsive) */
import 'datatables.net-bs5/css/dataTables.bootstrap5.css';
import 'datatables.net-responsive-bs5/css/responsive.bootstrap5.css';

/* Select2 */
import 'select2/dist/css/select2.min.css';

/* Croppie */
import 'croppie/croppie.css';

/* Daterangepicker */
import 'daterangepicker/daterangepicker.css';


/* --- JS --- */
/* jQuery UI — использует глобальный window.jQuery (он из layout) */
import 'jquery-ui-dist/jquery-ui';

/* Bootstrap JS (bundle включает Popper) — ВЫПИЛЕН, т.к. подключается через layout */

/* DataTables: jQuery-плагин, просто импортов достаточно */
import 'datatables.net-bs5';
import 'datatables.net-responsive-bs5';

/* Select2 (тоже jQuery-плагин) */
import 'select2';

/* Croppie (чистая JS-либа) */
import Croppie from 'croppie';
window.Croppie = Croppie;

/* moment + Daterangepicker (ожидает global moment + jQuery) */
import moment from 'moment';
window.moment = moment;
import 'daterangepicker';

/* FullCalendar v6: подключаем ТОЛЬКО JS. CSS он инжектит сам. */
import { Calendar } from '@fullcalendar/core';
import dayGridPlugin from '@fullcalendar/daygrid';
window.FullCalendar = { Calendar, dayGridPlugin };

// Cropper
// import Cropper from 'cropperjs';
// import 'cropperjs/dist/cropper.css';
//
// // чтобы можно было использовать в твоих скриптах без сборки модулей:
// window.Cropper = Cropper;