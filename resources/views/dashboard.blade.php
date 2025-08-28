@extends('layouts.admin2')
@section('content')






    <div class=" main-content">
        <h4 class="pt-3 text-start">Консоль</h4>
        @can('student-filter-console')
            <h5 class="choose-user-header text-start">Выбор ученика:</h5>

            {{--Выбор ученика, группы, кнопка установить--}}
            <div class="row choose-user">
                <div class="col-md-3 col-12 mb-3 team-select text-start">

                    <select class="form-select text-start" id="single-select-team" data-placeholder="Группа">
                        <option value="all">Все группы</option>
                        <option value="withoutTeam">Без группы</option>
                        <option></option>
                        @foreach($allTeams as $index => $team)
                            <option value="{{ $team->title }}" label="{{ $team->label }}"
                                    data-team-id="{{ $team->id }}">
                                {{ $index + 1 }}. {{ $team->title }}
                            </option>
                        @endforeach
                    </select>


                    <i class="fa-thin fa-calendar-lines"></i>
                </div>
 
                <div class="col-md-3 col-12 mb-3 user-select">


                    <select class="form-select" id="single-select-user" data-placeholder="ФИО">
                        <option value="">Выберите пользователя</option>
                        @foreach($allUsersSelect as $index => $user)
                            <option value="{{ $user->name }}" label="{{ $user->label }}" data-user-id="{{ $user->id }}">
                                {{ $index + 1 }}. {{ $user->name }}
                            </option>
                        @endforeach
                    </select>

                </div>
            </div>
        @endcan

        {{--Аватарка и личные данные--}}
        <div class="row personal-data align-items-center">
                <div class="col-5 col-lg-3 avatar-wrap align-items-center" >


                {{----1----------------------}}

                <style>

                    /* ========== АВАТАР НА СТРАНИЦЕ ========== */

                    /* Внешний контейнер: тут hover и меню. ВАЖНО: overflow: visible */
                    .avatar{
                        position: relative;
                        display: inline-block;
                    }

                    /* Внутренний круг: обрезка фото + жёлтый бордер */
                    .avatar-clip{
                        width: 130px;
                        aspect-ratio: 1 / 1;           /* всегда квадрат */
                        border: 2px solid #FFD700;     /* жёлтый бордер 2px */
                        border-radius: 50%;
                        overflow: hidden;               /* обрезаем ТОЛЬКО фото */
                        display: grid;
                        place-items: center;            /* центрирование img */
                        background: #f3f3f3;
                        box-sizing: border-box;         /* бордер внутри размеров */
                    }

                    /* Картинка заполняет круг без искажений */
                    .avatar-clip img{
                        width: 100% !important;
                        height: 100% !important;
                        object-fit: cover;
                        object-position: center;
                        display: block;
                        border-radius: 50%;
                    }

                    /* ========== МЕНЮ ДЕЙСТВИЙ ПОД АВАТАРКОЙ ========== */

                    .avatar-actions{
                        position: absolute;
                        top: 100%;
                        left: 50%;
                        transform: translate(-50%, 8px); /* визуальный отступ без gap */
                        background: #fff;
                        border: 1px solid rgba(0,0,0,.12);
                        border-radius: .5rem;
                        box-shadow: 0 8px 24px rgba(0,0,0,.15);
                        z-index: 20;
                        padding: 10px;

                        /* «скрыто» по умолчанию — но без display:none (чтобы не мигало) */
                        display: block;
                        opacity: 0;
                        visibility: hidden;
                        pointer-events: none;
                        transition: opacity .12s ease, visibility .12s ease;
                        text-align: left;
                    }

                    /* «Мостик», чтобы курсор не терял hover между аватаркой и меню */
                    .avatar-actions::before{
                        content: "";
                        position: absolute;
                        top: -8px; left: 0; right: 0;
                        height: 8px;
                    }

                    /* Открытое состояние — добавляется JS-классом .is-open на .avatar */
                    .avatar.is-open .avatar-actions{
                        opacity: 1;
                        visibility: visible;
                        pointer-events: auto;
                    }

                    /* Пункты меню + подсветка */
                    .avatar-actions .dropdown-item{
                        display: flex;
                        align-items: center;
                        gap: .5rem;
                        padding: .5rem 1rem;
                        cursor: pointer;
                        border-radius: .25rem;
                        transition: background-color .15s ease, color .15s ease;
                    }

                    .avatar-actions .dropdown-item:hover{
                        background-color: #f0f0f0;
                        color: #0057d8;                 /* лёгкая «VK»-подсветка текста/иконки */
                    }
                    .avatar-actions .dropdown-item:hover i{
                        color: #0057d8;
                    }

                    /* ========== МОДАЛКА ПРОСМОТРА БОЛЬШОЙ АВАТАРКИ (ПРЯМОУГОЛЬНИК) ========== */

                    .modal-avatar .modal-dialog{ max-width: none; }
                    .modal-avatar .modal-content{ background: rgba(0,0,0,.85); }

                    /* Контейнер: вписываемся в экран и по ширине, и по высоте */
                    .modal-avatar .zoom-wrap{
                        max-width: 92vw;
                        max-height: 92vh;
                    }

                    /* Прямоугольный кадр с жёлтым бордером (не круг!) */
                    .modal-avatar .zoom-rect{
                        width: 100%;
                        height: auto;
                        border: 4px solid #FFD700;
                        border-radius: .5rem;        /* если не нужно скругление — поставь 0 */
                        overflow: hidden;
                        box-shadow: 0 12px 40px rgba(0,0,0,.4);
                    }

                    /* Фото вписывается целиком, без обрезки */
                    .modal-avatar .zoom-rect img{
                        width: 100%;
                        height: auto;
                        max-height: 90vh;            /* контроль по высоте окна */
                        object-fit: contain;         /* сохранить пропорции, не обрезать */
                        display: block;
                    }

                    /* Крестик закрытия (вверху справа, на тёмном фоне белый) */
                    .modal-avatar .btn-close{
                        position: absolute;
                        right: .5rem; top: .5rem;
                        filter: invert(1) grayscale(100%);
                        opacity: .9;
                    }
                    .modal-avatar .btn-close:hover{ opacity: 1; }

                    .avatar-wrap {
                        text-align: center;
                    }

                </style>
                

                    {{--Аватар--}}

                        <div class="avatar" >                         <!-- ВНЕШНИЙ контейнер (hover + меню) -->
                            <div class="avatar-clip">                  <!-- ВНУТРЕННИЙ круг (обрезка фото + бордер) -->
                                <img
                                        src="{{ auth()->user()->image_crop ? asset('storage/avatars/'.auth()->user()->image_crop) : asset('/img/default-avatar.png') }}"
                                        alt="Avatar">
                            </div>

                            <div class="avatar-actions">
                                <button class="dropdown-item js-open-photo" type="button">
                                    <i class="fa-solid fa-image"></i> Открыть фото
                                </button>
                                <button class="dropdown-item js-change-photo" type="button"
                                        data-bs-toggle="modal" data-bs-target="#avatarEditModal">
                                    <i class="fa-solid fa-pen-to-square"></i> Изменить фото
                                </button>
                                <button class="dropdown-item text-danger js-delete-photo" type="button">
                                    <i class="fa-solid fa-trash"></i> Удалить фото
                                </button>
                            </div>
                        </div>

                    <!-- Модалка: Большого фото -->
                    <div class="modal fade modal-avatar" id="avatarZoomModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content bg-transparent border-0 shadow-none position-relative">
                                <button type="button" class="btn-close btn-close-white position-absolute end-0 top-0 m-2"
                                        data-bs-dismiss="modal" aria-label="Закрыть"></button>

                                <div class="d-flex justify-content-center align-items-center p-3">
                                    <div class="zoom-wrap">
                                        <div class="zoom-rect">
                                            <img src="{{ auth()->user()->image ? asset('storage/avatars/'.auth()->user()->image) : asset('/img/default-avatar.png') }}"
                                                 alt="Avatar large"
                                                 class="js-zoom-image">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Модалка: Редактирование/замена -->
                    <div class="modal fade" id="avatarEditModal" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-xl">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Загрузка новой фотографии</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                                </div>
                                <div class="modal-body">
                                    <p class="text-muted mb-3">
                                        Загрузите изображение в формате JPG, PNG, WEBP или GIF.
                                    </p>

                                    <div class="d-flex flex-column flex-lg-row gap-3">
                                        <div class="flex-grow-1">
                                            <div class="border rounded p-2">
                                                <img id="cropperSource" alt="Source" style="max-width:100%; display:none;">
                                                <div id="placeholder" class="text-center text-muted p-5">
                                                    <div class="mb-2">Выберите файл для редактирования</div>
                                                    <button class="btn btn-primary js-choose-file">Выбрать файл</button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="flex-shrink-0" style="width:280px;">
                                            <div class="mb-2 fw-semibold">Предпросмотр миниатюры</div>
                                            <div class="border rounded-circle overflow-hidden" style="width:256px;height:256px;">
                                                <img id="previewThumb" alt="Preview"
                                                     style="width:100%;height:100%;object-fit:cover;display:none;">
                                            </div>
                                        </div>
                                    </div>

                                    <input type="file" class="d-none" id="avatarFile" accept="image/*">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
                                    <button type="button" class="btn btn-primary js-save-avatar" disabled>Сохранить</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <script>
                        // задержка пропадания меню при ховере
                        document.addEventListener('DOMContentLoaded', function () {
                            const avatar = document.querySelector('.avatar');
                            const menu = avatar.querySelector('.avatar-actions');
                            let hideTimer;

                            const open = () => {
                                clearTimeout(hideTimer);
                                avatar.classList.add('is-open');
                            };
                            const close = () => {
                                avatar.classList.remove('is-open');
                            };

                            avatar.addEventListener('mouseenter', open);
                            avatar.addEventListener('mouseleave', () => {
                                hideTimer = setTimeout(close, 200); // можно 200–300 мс; если хочешь 3 сек — поставь 3000
                            });

                            // На самом меню тоже страхуемся
                            menu.addEventListener('mouseenter', open);
                            menu.addEventListener('mouseleave', () => {
                                hideTimer = setTimeout(close, 200);
                            });
                        });


                        {{--новая аватарка--}}
                        {{--$(function () {--}}

                            {{--$.ajaxSetup({--}}
                                {{--headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }--}}
                            {{--});--}}

                            {{--const $currentAvatar = $('.avatar-clip img');   // вместо .js-current-avatar--}}
                            {{--const $zoomImg = $('.js-zoom-image');--}}


                            {{--// универсальная загрузка аватарки. Грузим текущего юзера, или ajax если есть.--}}
                            {{--$(function () {--}}
                                {{--const $zoomImg = $('.js-zoom-image');--}}

                                {{--// По умолчанию (текущий авторизованный пользователь)--}}
                                {{--let zoomSrc = "{{ auth()->user()->image--}}
        {{--? asset('storage/avatars/'.auth()->user()->image)--}}
        {{--: asset('/img/default-avatar.png') }}";--}}

                                {{--// Единый обработчик открытия--}}
                                {{--$(document).off('click', '.js-open-photo').on('click', '.js-open-photo', function () {--}}
                                    {{--// на всякий случай актуализируем src перед показом--}}
                                    {{--$zoomImg.attr('src', zoomSrc);--}}
                                    {{--const el = document.getElementById('avatarZoomModal');--}}
                                    {{--const modal = bootstrap.Modal.getOrCreateInstance(el);--}}
                                    {{--modal.show();--}}
                                {{--});--}}

                                {{--// === КОГДА ПРИШЁЛ AJAX С ДАННЫМИ ЮЗЕРА (в твоём success) ===--}}
                                {{--// Вызови эту функцию и передай response.user--}}
                                {{--window.setZoomImageFromUser = function (user) {--}}
                                    {{--if (user && user.image) {--}}
                                        {{--zoomSrc = "{{ asset('storage/avatars') }}/" + user.image;--}}
                                    {{--} else if (user && user.image_crop) {--}}
                                        {{--// фолбэк: если нет большой — покажем кроп--}}
                                        {{--zoomSrc = "{{ asset('storage/avatars') }}/" + user.image_crop;--}}
                                    {{--} else {--}}
                                        {{--zoomSrc = "{{ asset('img/default-avatar.png') }}";--}}
                                    {{--}--}}
                                    {{--// можно сразу обновить картинку в модалке (на случай, если она уже открыта)--}}
                                    {{--$zoomImg.attr('src', zoomSrc);--}}
                                {{--};--}}
                            {{--});--}}


                            {{--// Удаление аватарки--}}
                            {{--$(document).off('click', '.js-delete-photo').on('click', '.js-delete-photo', function () {--}}
                                {{--const userId = $(this).data('id'); // может быть undefined--}}
                                {{--const url = userId--}}
                                    {{--? `/admin/users/${userId}/avatar`   // админ удаляет у другого--}}
                                    {{--: `/profile/avatar`;                // пользователь сам себя--}}

                                {{--showConfirmDeleteModal(--}}
                                    {{--"Удаление пользователя",--}}
                                    {{--"Вы уверены, что хотите удалить аватар пользователя?",--}}
                                    {{--function () {--}}
                                        {{--$.ajax({--}}
                                            {{--url: url,--}}
                                            {{--method: 'POST',--}}
                                            {{--data: { _method: 'DELETE' },--}}
                                            {{--success: function () {--}}
                                                {{--const def = "{{ asset('img/default-avatar.png') }}";--}}
                                                {{--$('.avatar-clip img').attr('src', def);--}}
                                                {{--$('.js-zoom-image').attr('src', def);--}}
                                            {{--},--}}
                                            {{--error: function (xhr) {--}}
                                                {{--alert('Ошибка удаления: ' + (xhr.responseJSON?.message || xhr.statusText));--}}
                                            {{--}--}}
                                        {{--});--}}
                                    {{--}--}}
                                {{--);--}}
                            {{--});--}}



                            {{--// ====== Редактор (Cropper.js) ======--}}
                            {{--let cropper = null;--}}
                            {{--const $fileInput = $('#avatarFile');--}}
                            {{--const $sourceImg = $('#cropperSource');--}}
                            {{--const $placeholder = $('#placeholder');--}}
                            {{--const $preview = $('#previewThumb');--}}
                            {{--const $saveBtn = $('.js-save-avatar');--}}

                            {{--// Открыть выбор файла--}}
                            {{--$('.js-choose-file').on('click', () => $fileInput.trigger('click'));--}}
                            {{--$('.js-change-photo').on('click', () => {--}}
                                {{--// при каждом открытии оставляем как есть; если нужно — можно делать reset--}}
                            {{--});--}}

                            {{--// Загрузка файла в редактор--}}
                            {{--$fileInput.on('change', function () {--}}
                                {{--const file = this.files[0];--}}
                                {{--if (!file) return;--}}

                                {{--const url = URL.createObjectURL(file);--}}
                                {{--$sourceImg.attr('src', url).show();--}}
                                {{--$placeholder.hide();--}}
                                {{--$preview.hide();--}}

                                {{--// Инициируем/пересоздаём кроппер--}}
                                {{--if (cropper) {--}}
                                    {{--cropper.destroy();--}}
                                    {{--cropper = null;--}}
                                {{--}--}}
                                {{--cropper = new Cropper($sourceImg[0], {--}}
                                    {{--aspectRatio: 1,            // квадрат для аватарки--}}
                                    {{--viewMode: 1,               // внутри холста--}}
                                    {{--dragMode: 'move',--}}
                                    {{--guides: false,--}}
                                    {{--center: true,--}}
                                    {{--background: false,--}}
                                    {{--autoCropArea: 1,--}}
                                    {{--movable: true,--}}
                                    {{--zoomable: true,--}}
                                    {{--rotatable: false,--}}
                                    {{--scalable: false,--}}
                                    {{--ready() {--}}
                                        {{--$saveBtn.prop('disabled', false);--}}
                                    {{--},--}}
                                    {{--crop() {--}}
                                        {{--// Превью миниатюры (256x256)--}}
                                        {{--if (!cropper) return;--}}
                                        {{--const canvas = cropper.getCroppedCanvas({width: 256, height: 256});--}}
                                        {{--$preview.attr('src', canvas.toDataURL('image/jpeg', 0.9)).show();--}}
                                    {{--}--}}
                                {{--});--}}
                            {{--});--}}

                            {{--// Сохранение: собираем два Blob-а — big (1024x1024) и crop (256x256)--}}
                            {{--$saveBtn.on('click', function () {--}}
                                {{--if (!cropper) return;--}}

                                {{--const bigCanvas = cropper.getCroppedCanvas({width: 1024, height: 1024}); // «большая»--}}
                                {{--const cropCanvas = cropper.getCroppedCanvas({width: 256, height: 256}); // миниатюра--}}

                                {{--// Преобразуем в Blob и отправляем FormData--}}
                                {{--Promise.all([--}}
                                    {{--new Promise(res => bigCanvas.toBlob(b => res(b), 'image/jpeg', 0.92)),--}}
                                    {{--new Promise(res => cropCanvas.toBlob(b => res(b), 'image/jpeg', 0.92))--}}
                                {{--]).then(function ([bigBlob, cropBlob]) {--}}
                                    {{--const fd = new FormData();--}}
                                    {{--fd.append('image_big', bigBlob, 'big.jpg');--}}
                                    {{--fd.append('image_crop', cropBlob, 'crop.jpg');--}}

                                    {{--$.ajax({--}}
                                        {{--url: '/profile/avatar',--}}
                                        {{--method: 'POST',--}}
                                        {{--processData: false,--}}
                                        {{--contentType: false,--}}
                                        {{--data: fd,--}}
                                        {{--success: function (res) {--}}
                                            {{--// Обновляем аватар (крошку) и картинку для зума--}}
                                            {{--$currentAvatar.attr('src', res.image_crop_url + '?v=' + Date.now());--}}
                                            {{--$zoomImg.attr('src', res.image_url + '?v=' + Date.now());--}}

                                            {{--// Закрываем модалку и чистим состояние--}}
                                            {{--const modal = bootstrap.Modal.getInstance(document.getElementById('avatarEditModal'));--}}
                                            {{--modal?.hide();--}}
                                            {{--resetEditor();--}}
                                        {{--},--}}
                                        {{--error: function (xhr) {--}}
                                            {{--alert('Ошибка загрузки: ' + (xhr.responseJSON?.message || xhr.statusText));--}}
                                        {{--}--}}
                                    {{--});--}}
                                {{--});--}}
                            {{--});--}}

                            {{--function resetEditor() {--}}
                                {{--$fileInput.val('');--}}
                                {{--$saveBtn.prop('disabled', true);--}}
                                {{--if (cropper) {--}}
                                    {{--cropper.destroy();--}}
                                    {{--cropper = null;--}}
                                {{--}--}}
                                {{--$sourceImg.hide().attr('src', '');--}}
                                {{--$preview.hide().attr('src', '');--}}
                                {{--$placeholder.show();--}}
                            {{--}--}}

                            {{--// Сброс при закрытии модалки--}}
                            {{--document.getElementById('avatarEditModal').addEventListener('hidden.bs.modal', resetEditor);--}}
                        {{--});--}}

                    </script>


                    <script>
                        $(function () {
                            // CSRF для всех ajax
                            $.ajaxSetup({
                                headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
                            });

                            const $zoomImg   = $('.js-zoom-image');
                            const $fileInput = $('#avatarFile');
                            const $sourceImg = $('#cropperSource');
                            const $placeholder = $('#placeholder');
                            const $preview   = $('#previewThumb');

                            // дефолт для текущего (авторизованного) пользователя
                            let zoomSrc = "{{ auth()->user()->image
        ? asset('storage/avatars/'.auth()->user()->image)
        : asset('img/default-avatar.png') }}";

                            let cropper = null;

                            /* ----------------- ПУБЛИЧНЫЕ ХЕЛПЕРЫ ДЛЯ АДМИН-СТРАНИЦЫ ----------------- */

                            // 1) подставить «большую» картинку выбранного пользователя (для зума)
                            window.setZoomImageFromUser = function (user) {
                                if (user && user.image) {
                                    zoomSrc = "{{ asset('storage/avatars') }}/" + user.image;
                                } else if (user && user.image_crop) {
                                    zoomSrc = "{{ asset('storage/avatars') }}/" + user.image_crop; // фолбэк
                                } else {
                                    zoomSrc = "{{ asset('img/default-avatar.png') }}";
                                }
                                $zoomImg.attr('src', zoomSrc);
                            };

                            // 2) сообщить скрипту «контекст» — для кого загружаем/удаляем (id пользователя)
                            //    вызывать в success после подгрузки юзера через AJAX
                            window.setSelectedUserContext = function (user) {
                                const id = user && user.id ? user.id : '';
                                // обе кнопки получают data-id:
                                $('.js-save-avatar').attr('data-id', id);
                                $('.js-delete-photo').attr('data-id', id);
                            };

                            /* ----------------- ОТКРЫТЬ БОЛЬШОЕ ФОТО ----------------- */
                            $(document).off('click', '.js-open-photo').on('click', '.js-open-photo', function () {
                                $zoomImg.attr('src', zoomSrc);
                                const el = document.getElementById('avatarZoomModal');
                                const modal = bootstrap.Modal.getOrCreateInstance(el);
                                modal.show();
                            });

                            /* ----------------- УДАЛЕНИЕ АВАТАРКИ (своей/чужой) ----------------- */
                            $(document).off('click', '.js-delete-photo').on('click', '.js-delete-photo', function () {
                                const userId = $(this).data('id');  // если есть — удаляем у этого юзера
                                const url = userId ? `/admin/users/${userId}/avatar` : `/profile/avatar`;

                                showConfirmDeleteModal(
                                    "Удаление пользователя",
                                    "Вы уверены, что хотите удалить аватар пользователя?",
                                    () => {
                                        $.ajax({
                                            url: url,
                                            method: 'POST',
                                            data: { _method: 'DELETE' },
                                            success: function () {
                                                const def = "{{ asset('img/default-avatar.png') }}";
                                                // миниатюра + большая
                                                $('.avatar-clip img').attr('src', def);
                                                $zoomImg.attr('src', def);
                                                zoomSrc = def; // чтобы следующее открытие показывало дефолт
                                            },
                                            error: function (xhr) {
                                                alert('Ошибка удаления: ' + (xhr.responseJSON?.message || xhr.statusText));
                                            }
                                        });
                                    }
                                );
                            });

                            /* ----------------- ВЫБОР ФАЙЛА И КРОП ----------------- */
                            $('.js-choose-file').on('click', () => $fileInput.trigger('click'));

                            $fileInput.on('change', function () {
                                const file = this.files[0];
                                if (!file) return;

                                const url = URL.createObjectURL(file);
                                $sourceImg.attr('src', url).show();
                                $placeholder.hide();
                                $preview.hide();

                                if (cropper) { cropper.destroy(); cropper = null; }

                                cropper = new Cropper($sourceImg[0], {
                                    aspectRatio: 1,
                                    viewMode: 1,
                                    dragMode: 'move',
                                    guides: false,
                                    center: true,
                                    background: false,
                                    autoCropArea: 1,
                                    movable: true,
                                    zoomable: true,
                                    rotatable: false,
                                    scalable: false,
                                    ready() { $('.js-save-avatar').prop('disabled', false); },
                                    crop() {
                                        if (!cropper) return;
                                        const canvas = cropper.getCroppedCanvas({width: 256, height: 256});
                                        $preview.attr('src', canvas.toDataURL('image/jpeg', 0.9)).show();
                                    }
                                });
                            });

                            /* ----------------- СОХРАНЕНИЕ (своё/чужое) ----------------- */
                            $(document).off('click', '.js-save-avatar').on('click', '.js-save-avatar', function () {
                                if (!cropper) return;

                                // ВАЖНО: берём id именно с нажатой кнопки, а не с заранее сохранённой переменной
                                const userId = $(this).data('id');
                                const url = userId ? `/admin/users/${userId}/avatar` : `/profile/avatar`;

                                const bigCanvas  = cropper.getCroppedCanvas({width: 1024, height: 1024});
                                const cropCanvas = cropper.getCroppedCanvas({width: 256, height: 256});

                                Promise.all([
                                    new Promise(res => bigCanvas.toBlob(b => res(b),  'image/jpeg', 0.92)),
                                    new Promise(res => cropCanvas.toBlob(b => res(b), 'image/jpeg', 0.92))
                                ]).then(([bigBlob, cropBlob]) => {
                                    const fd = new FormData();
                                    fd.append('image_big',  bigBlob,  'big.jpg');
                                    fd.append('image_crop', cropBlob, 'crop.jpg');

                                    $.ajax({
                                        url: url,
                                        method: 'POST',
                                        processData: false,
                                        contentType: false,
                                        data: fd,
                                        success: function (res) {
                                            // сервер возвращает готовые URL — используем их
                                            const thumbUrl = (res.image_crop_url || '') + '?v=' + Date.now();
                                            const bigUrl   = (res.image_url      || '') + '?v=' + Date.now();

                                            $('.avatar-clip img').attr('src', thumbUrl);
                                            $zoomImg.attr('src', bigUrl);
                                            zoomSrc = bigUrl; // чтобы .js-open-photo открывал свежую

                                            const modal = bootstrap.Modal.getInstance(document.getElementById('avatarEditModal'));
                                            modal?.hide();
                                            resetEditor();
                                        },
                                        error: function (xhr) {
                                            alert('Ошибка загрузки: ' + (xhr.responseJSON?.message || xhr.statusText));
                                        }
                                    });
                                });
                            });

                            /* ----------------- СБРОС РЕДАКТОРА ----------------- */
                            function resetEditor() {
                                $fileInput.val('');
                                $('.js-save-avatar').prop('disabled', true);
                                if (cropper) { cropper.destroy(); cropper = null; }
                                $sourceImg.hide().attr('src', '');
                                $preview.hide().attr('src', '');
                                $placeholder.show();
                            }
                            document.getElementById('avatarEditModal')?.addEventListener('hidden.bs.modal', resetEditor);
                        });
                    </script>


                    {{-----2---------------------}}







            </div>
            <div class="col-7 col-lg-3 header-wrap">
                <div class="personal-data-header">
                    <div class="name">Имя: <span class="name-value"> @if($curUser)
                                {{$curUser->name}}
                            @else
                                -
                            @endif </span></div>

                    <div class="birthday">Дата рождения: <span class="birthday-value"> @if($curUser->birthday)
                                {{ \Carbon\Carbon::parse($curUser->birthday)->format('d.m.Y') }}
                            @else
                                -
                            @endif </span></div>


                    <div class="email">Почта: <span class="email-value"> @if($curUser)
                                {{$curUser->email}}
                            @else
                                -
                            @endif </span></div>
                    <div class="group">Группа: <span class="group-value"> @if($curTeam)
                                {{$curTeam->title}}
                            @else
                                -
                            @endif </span></div>


                    <div class="fields-wrap">
                        @foreach($allFields as $field)
                            <div class="fields-title" data-id="{{$field->id}}">
                                {{ $field->name }}:
                                <span class="fields-value">{{ $userFieldValues[$field->id] ?? '-' }}</span>
                            </div>
                        @endforeach
                    </div>

                    {{--<div class="display-none count-training">Количество тренировок: <span--}}
                    {{--class="count-training-value">223</span></div>--}}
                </div>
                @can('payment-clubfee')

                    <div class="mt-3">
                        <a href="/payment/club-fee">
                            <button type="button" id="club-fee" class="btn btn-primary">Клубный взнос</button>
                        </a>
                    </div>
                @endcan
            </div>

            @can('paying-classes')
                <div class="col-12 col-lg-4 mt-3 mb-3 credit-notice  align-items-center justify-content-center text-center">
                    <i class="close fa-solid fa-circle-xmark"></i>
                    У вас образовалась задолженность в размере <span class="summ"></span> руб.
                </div>
            @endcan

        </div>

        @if(!empty($textForUsers))
            <div class="notification-wrap mt-3 mb-3">
                <div class="notification">{{ $textForUsers }}</div>
            </div>
        @endif

        <h5 class="header-shedule display-none mt-3 mb-2">Расписание:</h5>

        <div class="mt-3 mb-3 calendar">
            <div class="calendar-header">
                <div id="prev-month">←</div>
                <div id="calendar-title"></div>
                <div id="next-month">→</div>
            </div>
            <div class="days-header">
                <div>Пн</div>
                <div>Вт</div>
                <div>Ср</div>
                <div>Чт</div>
                <div>Пт</div>
                <div>Сб</div>
                <div>Вс</div>
            </div>
            <div class="days" id="days"></div>

            <!-- Контекстное меню -->
            <div id="context-menu" class="context-menu">
                <div class="context-menu-item" data-action="add-training">Добавление тренировки</div>
                <div class="context-menu-item" data-action="remove-training">Удаление тренировки</div>
                <div class="context-menu-item" data-action="add-freeze">Добавление заморозки</div>
                <div class="context-menu-item" data-action="remove-freeze">Удаление заморозки</div>
            </div>
        </div>

        {{--Сезоны--}}
        <div class="row seasons">
            <div class="col-12">

                <div class="season season-2026" id="season-2026">
                    <div class="header-season">Сезон 2025 - 2026 <i class="fa fa-chevron-up"></i><span
                                class="display-none from">2025</span><span class="display-none to">2026</span></div>
                    <span class="is_credit">Имеется просроченная задолженность в размере <span
                                class="is_credit_value">0</span> руб.</span>
                    <span class="display-none1 total-summ"></span>
                    <div class="row justify-content-center align-items-center container" data-season="2026"></div>
                </div>

                <div class="season season-2025" id="season-2025">
                    <div class="header-season">Сезон 2024 - 2025 <i class="fa fa-chevron-up"></i><span
                                class="display-none from">2024</span><span class="display-none to">2025</span></div>
                    <span class="is_credit">Имеется просроченная задолженность в размере <span
                                class="is_credit_value">0</span> руб.</span>
                    <span class="display-none1 total-summ"></span>
                    <div class="row justify-content-center align-items-center container" data-season="2025"></div>
                </div>

                <div class="season season-2024" id="season-2024">
                    <div class="header-season">Сезон 2023 - 2024 <i class="fa fa-chevron-up"></i><span
                                class="display-none from">2023</span><span class="display-none to">2024</span></div>
                    <span class="is_credit">Имеется просроченная задолженность в размере <span
                                class="is_credit_value">0</span> руб.</span>
                    <span class="display-none1 total-summ"></span>
                    <div class="row justify-content-center align-items-center container" data-season="2024"></div>
                </div>

                <div class="season season-2023" id="season-2023">
                    <div class="header-season">Сезон 2022 - 2023 <i class="fa fa-chevron-up"></i><span
                                class="display-none from">2022</span><span class="display-none to">2023</span></div>
                    <span class="is_credit">Имеется просроченная задолженность в размере <span
                                class="is_credit_value">0</span> руб.</span>
                    <div class="row justify-content-center align-items-center container" data-season="2023"></div>
                </div>

                <div class="season season-2022" id="season-2022">
                    <div class="header-season">Сезон 2021 - 2022 <i class="fa fa-chevron-up"></i></div>
                    <span class="is_credit">Имеется просроченная задолженность в размере <span
                                class="is_credit_value">0</span> руб.</span>
                    <div class="row justify-content-center align-items-center container" data-season="2022"></div>
                </div>
            </div>
        </div>

    </div>



@endsection

@section('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {

            window.Laravel = {
                csrfToken: '{{ csrf_token() }}',
                paymentUrl: '{{ route('payment') }}'
            };

            let currentUserName = "{{$curUser->name}}";
            let currentUserRole = "{{$curUser->role}}";
            // Глобальная переменная для хранения данных расписания юзера из AJAX
            var globalScheduleData = [];
            // передача расписания юзера для календаря
            var scheduleUser = {!! json_encode($scheduleUserArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) !!};
            updateGlobalScheduleData(scheduleUser);
            var userPrice = {!! json_encode($userPriceArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_NUMERIC_CHECK) !!};

            // закрытие плашки с задолженностью у юзера
            function closeNotice() {
                var $closeButton = $('.credit-notice .close');
                if ($closeButton.length > 0) { // Проверяем, что элемент существует
                    $closeButton.on('click', function () {
                        $('.credit-notice').hide();
                    });
                }
            }

            // Показывать плашку с задолженностью юзеру
            function showCreditNotice() {
                let creditNotice = document.querySelector(".credit-notice");
                let creditNoticeSumElement = document.querySelector(".credit-notice .summ");

                // Проверяем, что элемент уведомления и элемент суммы существуют
                if (creditNotice && creditNoticeSumElement) {
                    const creditNoticeSum = creditNoticeSumElement.textContent;
                    // При необходимости можно привести к числовому типу
                    if (parseFloat(creditNoticeSum) > 0) {
                        creditNotice.style.display = 'block';
                    }
                }
            }

            function convertStringToDate(dateStr) {
                const months = {
                    "Январь": 0,
                    "Февраль": 1,
                    "Март": 2,
                    "Апрель": 3,
                    "Май": 4,
                    "Июнь": 5,
                    "Июль": 6,
                    "Август": 7,
                    "Сентябрь": 8,
                    "Октябрь": 9,
                    "Ноябрь": 10,
                    "Декабрь": 11
                };

                const [monthName, year] = dateStr.split(' ');
                const month = months[monthName];

                if (month === undefined || isNaN(year)) {
                    throw new Error('Некорректный формат даты. Ожидается формат "Месяц Год".');
                }

                return new Date(year, month);
            }

            // Добавление сумм с задолженностями в плашки над сезонами и в общую плашку
            function apendCreditTotalSummtoNotice() {
                const seasons = document.querySelectorAll('.season');
                let totalSumAllSeasons = 0;
                const monthsInRussian = ["Январь", "Февраль", "Март", "Апрель", "Май", "Июнь", "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"];

                const currentDate = new Date();
                const currentMonth = monthsInRussian[currentDate.getMonth()];
                const currentYear = currentDate.getFullYear();
                const currentFormatedDate = `${currentMonth} ${currentYear}`;

                // Перебираем каждый сезон
                seasons.forEach(function (season) {
                    let seasonOnlyYear = season.id.match(/\d+/)[0];

                    let totalSum = 0;

                    // Ищем все контейнеры с классом border_price внутри текущего сезона
                    const priceContainers = season.querySelectorAll('.border_price');

                    // Перебираем все контейнеры с ценами
                    priceContainers.forEach(function (container) {

                        // Находим кнопку внутри контейнера
                        const button = container.querySelector('button.new-main-button');
                        const date = container.querySelector('.new-price-description').textContent;

                        // const month = parseFloat(container.querySelector('.new-price-description').textContent);
                        const parts = date.split(' ');
                        const seasonOnlyMonth = parts[0]; // "Апрель"
                        const seasonOnlyYear = parts[1];  // "2022"

                        currentFormatedDatetoDate = convertStringToDate(currentFormatedDate)
                        FormatedToDate = convertStringToDate(date);
                        if (FormatedToDate >= currentFormatedDatetoDate) {
                            return
                        }
                        // Проверяем, если кнопка называется "Оплатить" и не отключена
                        if (button && button.textContent.trim() === 'Оплатить' && !button.disabled) {
                            // Получаем значение из price-value
                            const priceValue = parseFloat(container.querySelector('.price-value').textContent.trim());

                            // Добавляем значение к общей сумме для этого сезона

                            totalSum += priceValue;
                        }
                    });

                    // Обновляем значение в is_credit_value для текущего сезона
                    const creditValueField = season.querySelector('.is_credit_value');
                    const creditValueWrap = season.querySelector('.is_credit')

                    creditValueField.textContent = totalSum;
                    // if (totalSum == 0) {
                    //     creditValueWrap.classList.add('display-none');
                    // } else {
                    //     creditValueWrap.classList.remove('display-none');
                    // }

                    if (totalSum == 0) {
                        creditValueWrap.classList.add('visibility-hidden');
                    } else {
                        creditValueWrap.classList.remove('visibility-hidden');
                    }

                    totalSumAllSeasons += totalSum;
                });

                // Обновляем notice с суммой долга
                const creditNoticeSumm = document.querySelector('.credit-notice .summ');
                // if (totalSumAllSeasons) {
                if (creditNoticeSumm && totalSumAllSeasons) {

                    creditNoticeSumm.textContent = totalSumAllSeasons;
                }


            }

            function disabledPaymentForm(role) {
                @cannot('paying-classes')

                // Получаем все формы на странице
                const forms = document.querySelectorAll('.seasons form');

// Перебираем каждую форму и отключаем её
                forms.forEach((form) => {
                    form.addEventListener('submit', (event) => {
                        event.preventDefault(); // Отменяем отправку формы
                    });

                    // Отключаем кнопку отправки, если она есть
                    const submitButton = form.querySelector('button[type="submit"]');
                    if (submitButton) {
                        submitButton.disabled = true; // Делаем кнопку неактивной
                    }

                    // Добавляем визуальные эффекты, чтобы показать, что форма отключена
                    form.style.opacity = '0.5';
                    form.style.pointerEvents = 'none';
                });
                @endcan
            }

            // AJAX User
            $('#single-select-user').change(function () {
                let userName = $(this).val();


                const selectedOption = this.options[this.selectedIndex];
                const userId = selectedOption.getAttribute('data-user-id');

                if (!userId) {
                    console.log('Ошибка: идентификатор пользователя не найден.');
                    // return;
                }

                $.ajax({
                    url: '/get-user-details',
                    type: 'GET',
                    data: {
                        // userName: userName,
                        userId: userId,
                        // inputDate: inputDate,
                    },

                        success: function (response) {
                            if (response.success) {
                                let user = response.user;
                                let userTeam = response.userTeam;
                                let userPrice = response.userPrice;
                                let scheduleUser = response.scheduleUser;
                                // let inputDate = response.inputDate;
                                let team = response.team;
                                let formattedBirthday = response.formattedBirthday;

                                let userFieldValues = response.userFieldValues;
                                let userFields = response.userFields;


                                //Сброс всех значений цен до нуля
                                function refreshPrice() {
                                    // Получаем все элементы с классом 'price-value' и устанавливаем значение '0'
                                    document.querySelectorAll('.price-value').forEach(function (element) {
                                        element.textContent = '0';
                                    });
                                    // Получаем все кнопки внутри 'new-main-button-wrap' и удаляем все классы
                                    document.querySelectorAll('.new-main-button-wrap button').forEach(function (button) {
                                        button.classList.remove('buttonPaided');
                                    });
                                }


                                // Вставка имени
                                function apendNameToUser() {
                                    if (user.name) {
                                        $('.name-value').html(user.name);
                                    } else $('.name-value').html("-");
                                }

                                // Вставка почты
                                function apendEmailToUser() {
                                    if (user.email) {
                                        $('.email-value').html(user.email);
                                    } else $('.email-value').html("-");
                                }

                                // Вставка дня рождения
                                function apendBirthdayToUser() {
                                    if (formattedBirthday) {
                                        $('.birthday-value').html(formattedBirthday);
                                    } else $('.birthday-value').html("-");

                                }

                                // Вставка кастомных полей
                                function apendUserFieldValues(userFieldValues) {

                                    // Очищаем значения перед заполнением
                                    const fields = document.querySelectorAll('.fields-title');
                                    fields.forEach(field => {
                                        const valueElement = field.querySelector('.fields-value');
                                        if (valueElement) {
                                            valueElement.textContent = '-';
                                        }
                                    });


                                    if (userFieldValues) {
                                        const fields = document.querySelectorAll('.fields-title');
                                        fields.forEach(field => {
                                            const id = field.getAttribute('data-id');
                                            if (userFieldValues[id]) {
                                                const valueElement = field.querySelector('.fields-value');
                                                valueElement.textContent = userFieldValues[id];
                                            }
                                        });
                                    }
                                }

                                // Вставка аватарки юзеру
                                const baseUrl = "{{ asset('storage/avatars') }}"; // даст полный путь к /storage/avatars
                                const defaultAvatar = "{{ asset('img/default-avatar.png') }}";

                                function apendImageToUser() {
                                    const $img = $('.avatar .avatar-clip img');

                                    if (user.image_crop) {
                                        $img.attr('src', baseUrl + '/' + user.image_crop)
                                            .attr('alt', user.name ?? 'avatar');
                                    } else {
                                        $img.attr('src', defaultAvatar)
                                            .attr('alt', 'avatar');
                                    }
                                }


                                // Вставка большой  аватарки юзеру
                                setZoomImageFromUser(response.user);

                                //Вставка data-id в кнопку добавления аватарки (для добавления чужих аватаров)
                                setSelectedUserContext(response.user); // <- это ключевое, сюда кладём data-id


                                // Вставим data-id в кнопку удаления аватарки (для удаления чужих аватаров)
                                $('.js-delete-photo').attr('data-id', response.user.id);





                                // Вставка счетчика тренировок юзеру
                                function apendTrainingCountToUser() {
                                    $('.personal-data-value .count-training').html(123);
                                }

                                // Отображение заголовка расписания
                                function showHeaderShedule() {
                                    let headerShedule = document.querySelector('.header-shedule');
                                    headerShedule.classList.remove('display-none');
                                }

                                // Добавление название группы юзеру
                                function apendTeamNameToUser() {
                                    if (userTeam) {
                                        $('.group-value').html(userTeam.title);
                                    } else
                                        $('.group-value').html('-');
                                }


                                //отключение форм для юзеров и суперюзеров
                                function disabledPaymentForm(role) {
                                    if (role == "admin" || role == "superadmin") {
                                        // Получаем все формы на странице
                                        const forms = document.querySelectorAll('form');

    // Перебираем каждую форму и отключаем её
                                        forms.forEach((form) => {
                                            form.addEventListener('submit', (event) => {
                                                event.preventDefault(); // Отменяем отправку формы
                                            });

                                            // Отключаем кнопку отправки, если она есть
                                            const submitButton = form.querySelector('button[type="submit"]');
                                            if (submitButton) {
                                                submitButton.disabled = true; // Делаем кнопку неактивной
                                            }

                                            // Добавляем визуальные эффекты, чтобы показать, что форма отключена
                                            form.style.opacity = '0.5';
                                            form.style.pointerEvents = 'none';
                                        });
                                    }
                                }

                                showHeaderShedule();
                                refreshPrice();
                                apendPrice(userPrice);
                                showSessons();
                                apendCreditTotalSumm();
                                apendTeamNameToUser();
                                apendBirthdayToUser();
                                apendNameToUser();
                                apendEmailToUser();
                                apendImageToUser();
                                apendTrainingCountToUser();
                                updateGlobalScheduleData(scheduleUser);
                                setBackgroundToCalendar(globalScheduleData);
                                createCalendar();
                                openFirstSeason();
                                disabledPaymentForm(currentUserRole);
                                apendUserFieldValues(userFieldValues);

                            } else {
                                $('#user-details').html('<p>' + response.message + '</p>');
                            }
                        },
                    error: function (xhr, status, error) {
                        console.log(error);
                    }
                });
            });

            // AJAX Team
            $('#single-select-team').change(function () {
                let teamName = $(this).val();
                let userName = $('#single-select-user').val();
                // Получаем выбранный option и извлекаем teamId из data-атрибута
                const selectedOption = this.options[this.selectedIndex];
                const teamId = selectedOption.getAttribute('data-team-id');

                function initializeSelect2() {
                    $('#single-select-user').select2({
                        theme: "bootstrap-5",
                        width: '100%',
                        placeholder: $('#single-select-user').data('placeholder'),
                        templateResult: formatUserOption,
                        templateSelection: formatUserOption // Применяем кастомный шаблон для отображения выбранного элемента
                    });
                }

                function formatUserOption(user) {
                    if (!user.id) {
                        return user.text; // Возвращаем текст для пустой опции (например, placeholder)
                    }


                    // Проверяем наличие команды у пользователя
                    let hasTeam = $(user.element).data('team');

                    let $userOption = $('<span></span>').text(user.text);

                    // Если у пользователя нет команды, применяем красный цвет
                    if (!hasTeam) {
                        $userOption.css('color', '#f3a12b');
                    }

                    return $userOption;
                }

                $.ajax({
                    url: '/get-team-details',
                    type: 'GET',
                    data: {
                        teamName: teamName,
                        userName: userName,
                        teamId: teamId,
                    },


                    success: function (response) {
                        if (response.success) {
                            let team = response.team;
                            let teamWeekDayId = response.teamWeekDayId;
                            let usersTeam = response.usersTeam;
                            let userWithoutTeam = response.userWithoutTeam;
                            // let inputDate = response.inputDate;
                            let user = response.user;
                            // let weekdays = document.querySelectorAll('.weekday-checkbox .form-check');
                            let usersTeamWithUnteamUsers = userWithoutTeam.concat(usersTeam);

                            // Новое изменение состава
                            function newUpdateSelectUsers() {

                                // Очищаем текущий список
                                $('#single-select-user').empty();

                                // Добавляем пустой элемент
                                $('#single-select-user').append('<option></option>');

                                // Счетчик для нумерации пользователей
                                let counter = 1;

                                // Проходим по каждому пользователю и добавляем опцию в select

                                let userList;
                                if (team == "Без групппы") {
                                    userList = userWithoutTeam;

                                } else if (team != null) {
                                    userList = usersTeamWithUnteamUsers;
                                } else {
                                    userList = usersTeam;
                                }

                                userList.forEach(function (user) {
                                    let option = $('<option></option>')
                                        .attr('value', user.name)
                                        .attr('label', user.label)
                                        .attr('data-team', user.team_id ? 'true' : 'false') // Проверяем наличие команды и добавляем data-атрибут
                                        .attr('data-user-id', user.id) // Добавляем id пользователя в DOM
                                        .text(counter + '. ' + user.name); // Добавляем нумерацию перед именем

                                    // Добавляем опцию в select
                                    $('#single-select-user').append(option);

                                    // Увеличиваем счетчик
                                    counter++;
                                });

                                // Инициализируем Select2 с кастомными шаблонами
                                initializeSelect2();
                            }


                            // enableSetupBtn(user, team, inputDate);
                            // apendWeekdays(weekdays);
                            newUpdateSelectUsers();

                        }
                    },
                    error: function (xhr, status, error) {
                    }
                });
            });

            // Создание сезонов
            function createSeasons() {

                const csrfToken = window.Laravel.csrfToken;
                const paymentUrl = window.Laravel.paymentUrl;

// Данные для каждого месяца
                const months = [
                    'september', 'october', 'november', 'december', 'january', 'february', 'march', 'april', 'may', 'june',
                    'july', 'august'
                ];
                const monthsRu = [
                    'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь', 'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                    'Июль', 'Август'
                ];
                var season2024;

                document.querySelectorAll('.season .container').forEach(container => {
                    var season = container.dataset.season;
                    // console.log('Season:', season); // Отладка: Выводим текущий сезон
                    // Цикл по месяцам
                    for (const [key, month] of months.entries()) {
                        // console.log('Processing month:', month); // Отладка: Выводим текущий месяц
                        const div = document.createElement('div');
                        div.className = `border_price col-3 ${month}`;

                        var displaySeason;
                        if (monthsRu[key] == "Сентябрь" ||
                            monthsRu[key] == "Октябрь" ||
                            monthsRu[key] == "Ноябрь" ||
                            monthsRu[key] == "Декабрь"
                        ) {
                            displaySeason = season - 1;
                        } else {
                            displaySeason = season;
                        }

                        const paymentDate = `${monthsRu[key]} ${displaySeason}`;
                        // const formatedPaymentDate = paymentDate;

                        // console.log("paymentDate: " +  paymentDate);
                        // console.log("formatedPaymentDate: " +  formatedPaymentDate);

                        var outSum = 22;
                        div.innerHTML = `
            <div class="row align-items-center justify-content-center">
                <span class="price-value">0</span>
                <span class="hide-currency">₽</span>
            </div>
            <div class="row justify-content-center align-items-center">
                <div class="new-price-description">${monthsRu[key]} ${displaySeason}</div>
            </div>
            <div class="row new-main-button-wrap">
                <div class="justify-content-center align-items-center">

                    <form action="${paymentUrl}" method="POST">
                        <input type="hidden" name="_token" value="${csrfToken}">
                        <input type="hidden" name="paymentDate" value="${paymentDate}">
                        <input class="outSum" type="hidden" name="outSum" value="">
                        <button type="submit" disabled class="btn btn-lg btn-bd-primary new-main-button">Оплатить</button>
                    </form>

                </div>
            </div>
        `;

                        // Добавляем созданный div в контейнер
                        container.appendChild(div);
                    }
                });


            }

// Открытие, закрытие сезонов при клике
            function clickSeason() {

                var chevronDownIcons = document.querySelectorAll('.header-season');
                // Добавляем обработчик события клика для каждого элемента
                chevronDownIcons.forEach(function (icon) {
                    icon.addEventListener('click', function () {
                        // Изменяем класс элемента в зависимости от текущего класса
                        if (icon.children[0].classList.contains('fa-chevron-down')) {
                            icon.children[0].classList.remove('fa-chevron-down');
                            icon.children[0].classList.add('fa-chevron-up');
                        } else {
                            icon.children[0].classList.remove('fa-chevron-up');
                            icon.children[0].classList.add('fa-chevron-down');
                        }

                        // Находим соответствующий элемент "season"
                        var seasonElement = icon.children[0].closest('.season');

                        // Находим все элементы с классом "border_price col-3 february" внутри "season"
                        var borderPriceElements = seasonElement.querySelectorAll('.border_price');

                        // Скрываем/показываем все элементы в зависимости от текущего класса "fa-chevron-down/fa-chevron-up"
                        borderPriceElements.forEach(function (borderPrice) {
                            if (icon.children[0].classList.contains('fa-chevron-up')) {
                                borderPrice.style.display = 'none';
                            } else {
                                borderPrice.style.display = 'block   ';
                            }
                        });
                    });
                });
            }

//Скрытие всех сезонов при загрузке страницы
            function hideAllSeason() {
                var seasons = document.querySelectorAll('.season');
                for (var i = 0; i < seasons.length; i++) {
                    seasons[i].classList.add('display-none');
                }
            }

            // Добавление Select2 к Юзерам
            function addSelect2ToUser() {
                $('#single-select-user').select2({
                    theme: "bootstrap-5",
                    width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
                    placeholder: $(this).data('placeholder'),
                });
            }

            // Добавление Select2 к Группам
            function addSelect2ToTeam() {
                $('#single-select-team').select2({
                    theme: "bootstrap-5",
                    width: $(this).data('width') ? $(this).data('width') : $(this).hasClass('w-100') ? '100%' : 'style',
                    placeholder: $(this).data('placeholder'),
                });
            }

            // Добавление datapicker к календарю
            function addDatapicker() {
                try {
                    $(function () {
                        $('#inlineCalendar').datepicker({
                            firstDay: 1,
                            dateFormat: "dd.mm.yy",
                            defaultDate: new Date(),
                            monthNames: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                                'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
                            dayNames: ['воскресенье', 'понедельник', 'вторник', 'среда', 'четверг', 'пятница', 'суббота'],
                            dayNamesShort: ['вск', 'пнд', 'втр', 'срд', 'чтв', 'птн', 'сбт'],
                            dayNamesMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
                            prevText: '<i class="fa-solid fa-caret-left"></i>', // Добавляем иконку для кнопки назад
                            nextText: '<i class="fa-solid fa-caret-right"></i>'  // Добавляем иконку для кнопки вперед

                        });
                        $('#inlineCalendar').datepicker('setDate', new Date());
                    });
                } catch (e) {
                }
            }

            // Скрипт открытия верхнего сезона
            function openFirstSeason() {
                // Найти все элементы с классом 'season'
                const seasons = document.querySelectorAll(".season");

                // Если найден хотя бы один сезон
                if (seasons.length > 0) {
                    // Открыть верхний сезон (первый в списке)
                    const topSeason = seasons[0];

                    // Найти кнопку для открытия сезона
                    const header = topSeason.querySelector(".header-season");

                    // Проверить, не открыт ли сезон уже
                    const isOpen = topSeason.querySelector(".fa-chevron-up") !== null;
                    // console.log(isOpen);
                    // Если кнопка найдена и сезон не открыт, кликнуть на неё
                    if (header && isOpen) {
                        header.click();
                    }
                }
            }

// Скрываем/отображаем сезоны, в которых не установленны/установлены суммы.
            function showSessons() {
                var seasons = document.querySelectorAll('.season');
                var borderPrice = {};
                var totalSumm = {};

                for (var i = 0; i < seasons.length; i++) {
                    var seasonId = seasons[i].id;

                    // Initialize the arrays for each season
                    borderPrice[seasonId] = [];
                    totalSumm[seasonId] = 0;

                    var borderPrices = seasons[i].querySelectorAll('.border_price');
                    var priceValues = seasons[i].querySelectorAll('.price-value');

                    for (var j = 0; j < borderPrices.length; j++) {
                        // Store the border price (if needed)
                        borderPrice[seasonId].push(borderPrices[j]);
                        totalSumm[seasonId] += Number(priceValues[j].textContent);
                    }

                    seasons[i].classList.remove('display-none');
                    if (totalSumm[seasonId] === 0) {
                        seasons[i].classList.add('display-none');
                    }
                    // отобразить последний сезон
                    seasons[0].classList.remove('display-none')
                }
            }

            //Поиск и установка соответствующих установленных цен на странице
            function apendPrice(userPrice) {
                if (userPrice) {
                    for (j = 0; j < userPrice.length; j++) {

                        // Получаем все блоки с классом border_price
                        const borderPrices = document.querySelectorAll('.border_price');

                        // Проходим по каждому блоку
                        for (let i = 0; i < borderPrices.length; i++) {
                            const borderPrice = borderPrices[i];
                            const button = borderPrice.querySelector('.new-main-button');
                            button.setAttribute('disabled', 'disabled');

                            // Находим элемент с классом new-price-description внутри текущего блока
                            const newPriceDescription = borderPrice.querySelector('.new-price-description');

                            // Проверяем, есть ли такой элемент
                            if (newPriceDescription) {
                                // Получаем текст месяца из блока и убираем пробелы
                                const monthText = newPriceDescription.textContent.trim();

                                // Преобразуем дату из БД (new_month) в строку вида "Месяц ГГГГ" для сравнения
                                const formatMonth = (dateString) => {
                                    const date = new Date(dateString);
                                    const monthNames = [
                                        "Январь", "Февраль", "Март", "Апрель", "Май", "Июнь",
                                        "Июль", "Август", "Сентябрь", "Октябрь", "Ноябрь", "Декабрь"
                                    ];
                                    const month = monthNames[date.getMonth()];
                                    const year = date.getFullYear();
                                    return `${month} ${year}`;
                                };

                                // Ищем объект в массиве, у которого преобразованная new_month совпадает с текстом месяца
                                const matchedData = userPrice.find(item => formatMonth(item.new_month) === monthText);

                                // Если найдено совпадение, обновляем цену
                                if (matchedData) {

                                    const priceValue = borderPrice.querySelector('.price-value');
                                    const outSum = borderPrice.querySelector('.outSum');

                                    if (priceValue) {
                                        if (matchedData.price > 0) {
                                            priceValue.textContent = matchedData.price;
                                            outSum.value = matchedData.price;
                                        }
                                    }

                                    // Получаем кнопку

                                    // Проверяем, если is_paid == true, меняем текст и делаем кнопку неактивной
                                    button.textContent = "Оплатить";

                                    if (matchedData.is_paid) {
                                        button.textContent = "Оплачено";
                                        button.setAttribute('disabled', 'disabled');
                                        button.classList.add('buttonPaided');
                                    } else {
                                        button.removeAttribute('disabled');
                                    }
                                    if (matchedData.price == 0) {
                                        button.setAttribute('disabled', 'disabled');
                                    }
                                }
                            }
                        }
                    }
                }
            }

            // Закрашивание ячеек в календаре
            function setBackgroundToCalendar(scheduleUser) {
                if (scheduleUser) {
                    scheduleUser.forEach(entry => {
                        // Формат даты в dataset.date в элементе календаря совпадает с форматом в объекте scheduleUser
                        const dayElement = document.querySelector(`[data-date="${entry.date}"]`);

                        if (dayElement) {
                            // dayElement.classList.add('scheduled-day');  // Добавляем общий класс для всех дней с расписанием

                            // Закрашиваем в зависимости от состояния оплаты
                            if (entry.is_enabled) {
                                dayElement.classList.add('is_enabled');
                            }
                            if (entry.is_hospital) {
                                dayElement.classList.add('is_hospital');
                            }
                        }
                    });
                }
            }

            // Функция для обновления глобальной переменной после получения данных через AJAX
            function updateGlobalScheduleData(scheduleUser) {
                if (scheduleUser) {
                    globalScheduleData = scheduleUser;
                }
            }

            //Создание календаря
            function createCalendar() {
                let currentYear = new Date().getFullYear();
                let currentMonth = new Date().getMonth();

                // Создаем календарь для текущего месяца
                function createCalendar(year, month) {
                    const firstDayOfMonth = new Date(year, month, 1).getDay();
                    const lastDateOfMonth = new Date(year, month + 1, 0).getDate();
                    const calendarTitle = document.getElementById('calendar-title');
                    const daysContainer = document.getElementById('days');
                    const monthNames = [
                        'Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь',
                        'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'
                    ];

                    // Заполняем заголовок календаря
                    calendarTitle.textContent = `${monthNames[month]} ${year}`;

                    // Очищаем предыдущие дни
                    daysContainer.innerHTML = '';

                    // Определяем, с какого дня недели начинается месяц (с учётом того, что воскресенье в JS это 0)
                    const adjustedFirstDay = (firstDayOfMonth === 0) ? 6 : firstDayOfMonth - 1;

                    // Заполняем дни до первого числа месяца пустыми блоками
                    for (let i = 0; i < adjustedFirstDay; i++) {
                        const emptyDiv = document.createElement('div');
                        daysContainer.appendChild(emptyDiv);
                    }

                    // Заполняем календарь числами текущего месяца
                    for (let i = 1; i <= lastDateOfMonth; i++) {
                        const dayDiv = document.createElement('div');
                        dayDiv.textContent = i;
                        dayDiv.dataset.date = `${year}-${String(month + 1).padStart(2, '0')}-${String(i).padStart(2, '0')}`;
                        daysContainer.appendChild(dayDiv);
                    }
                    // Закрашивание сегодняшней даты
                    highlightToday();
                    // Закрашиваем ячейки на текущем месяце в соответствии с данными расписания

                    {{--// updateGlobalScheduleData(@json($scheduleUserJson));--}}
                    //
                    {{--// console.log("@json($scheduleUserJson):");--}}
                    {{--// console.log(@json($scheduleUserJson));--}}
                    setBackgroundToCalendar(globalScheduleData);

                }

                //Предыдущие месяц
                function preMonth() {
                    document.getElementById('prev-month').addEventListener('click', () => {
                        currentMonth--;
                        if (currentMonth < 0) {
                            currentMonth = 11;
                            currentYear--;
                        }
                        createCalendar(currentYear, currentMonth);
                    });
                }

                // Следующий месяц
                function nextMonth() {
                    document.getElementById('next-month').addEventListener('click', () => {
                        currentMonth++;
                        if (currentMonth > 11) {
                            currentMonth = 0;
                            currentYear++;
                        }
                        createCalendar(currentYear, currentMonth);
                    });
                }

                // Вызов контекстного меню. Обработчик правого клика на дате.
                // function getContextMenu() {
                //     document.getElementById('days').addEventListener('contextmenu', function (event) {
                //         event.preventDefault();
                //         const target = event.target;
                //         let userName = $('#single-select-user').val();
                //
                //         if (target.dataset.date && userName) {
                //             // showContextMenu(event.clientX, event.clientY, target.dataset.date);
                //             showContextMenu(target);
                //
                //         }
                //     });
                // }

                //Позиционирование контекстного меню
                // function showContextMenu(target) {
                //     const contextMenu = document.getElementById('context-menu');
                //
                //     // Получаем отступы от верхнего левого угла календаря
                //     const x = target.offsetLeft + target.offsetWidth;
                //     const y = target.offsetTop + target.offsetHeight;
                //
                //     // Устанавливаем позицию контекстного меню
                //     contextMenu.style.left = `${x}px`;
                //     contextMenu.style.top = `${y}px`;
                //     contextMenu.style.display = 'block';
                //     contextMenu.dataset.date = target.dataset.date;
                // }

                // Скрытие контекстного меню при клике вне его
                // function hideContextMenuMissClick() {
                //     document.addEventListener('click', function (event) {
                //         const contextMenu = document.getElementById('context-menu');
                //         if (!contextMenu.contains(event.target)) {
                //             contextMenu.style.display = 'none';
                //         }
                //     });
                // }

                // Обработчик кликов по пунктам контекстного меню
                function clickContextmenu() {
                    document.getElementById('context-menu').addEventListener('click', function (event) {
                        const action = event.target.dataset.action;
                        const date = this.dataset.date;
                        let userName = $('#single-select-user').val();

                        if (action && date && userName) {
                            sendActionRequest(date, action, userName);
                        }
                        this.style.display = 'none';
                    });
                }

                // Функция отправки AJAX-запроса
                // function sendActionRequest(date, action, userName) {
                //
                //     $.ajax({
                //         url: '/content-menu-calendar',
                //         method: 'GET',
                //         data: {
                //             date: date,
                //             action: action,
                //             userName: userName,
                //         },
                //         success: function (response) {
                //             let scheduleUser = response.scheduleUser;
                //             updateGlobalScheduleData(scheduleUser);
                //             createCalendar(currentYear, currentMonth);
                //         },
                //         error: function () {
                //             alert('An error occurred while processing your request.');
                //         }
                //     });
                // }

                // Вызов функции для закрашивания сегодняшней даты
                function highlightToday() {
                    // Получаем сегодняшнюю дату
                    const today = new Date();
                    const formattedToday = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;

                    // Ищем элемент календаря, соответствующий сегодняшней дате
                    const todayElement = document.querySelector(`[data-date="${formattedToday}"]`);

                    if (todayElement) {
                        // Добавляем класс для закрашивания сегодняшней даты
                        todayElement.classList.add('today');
                    }
                }

                preMonth();
                nextMonth();
                createCalendar(currentYear, currentMonth);
                // getContextMenu();
                // hideContextMenuMissClick();
                // clickContextmenu();
            }

            //Расчет сумм долга за сезон и добавление долга в шапку сезона
            function apendCreditTotalSumm() {
                // Ищем все контейнеры с классом season
                const seasons = document.querySelectorAll('.season');

                // Перебираем каждый сезон
                seasons.forEach(function (season) {
                    let totalSum = 0;

                    // Ищем все контейнеры с классом border_price внутри текущего сезона
                    const priceContainers = season.querySelectorAll('.border_price');

                    // Перебираем все контейнеры с ценами
                    priceContainers.forEach(function (container) {
                        // Находим кнопку внутри контейнера
                        const button = container.querySelector('button.new-main-button');

                        // Проверяем, если кнопка называется "Оплатить" и не отключена
                        if (button && button.textContent.trim() === 'Оплатить' && !button.disabled) {
                            // Получаем значение из price-value
                            const priceValue = parseFloat(container.querySelector('.price-value').textContent.trim());
                            // Добавляем значение к общей сумме для этого сезона
                            totalSum += priceValue;
                        } else {
                        }
                    });

                    // Обновляем значение в is_credit_value для текущего сезона
                    const creditValueField = season.querySelector('.is_credit_value');
                    const creditValueWrap = season.querySelector('.is_credit')

                    creditValueField.textContent = totalSum;
                    // if (totalSum == 0) {
                    //     creditValueWrap.classList.add('display-none');
                    // } else {
                    //     creditValueWrap.classList.remove('display-none');
                    // }

                    if (totalSum == 0) {
                        creditValueWrap.classList.add('visibility-hidden');
                    } else {
                        creditValueWrap.classList.remove('visibility-hidden');
                    }
                });
            }

            addDatapicker(); // можно удалить
            createSeasons();    //Создание сезонов
            clickSeason();       //Измерение иконок при клике
            hideAllSeason();     //Скрытие всех сезонов при загрузке страницы
            createCalendar();
            apendPrice(userPrice);
            showSessons();
            apendCreditTotalSumm();
            apendCreditTotalSummtoNotice();
            openFirstSeason();
            closeNotice();
            showCreditNotice();
            disabledPaymentForm(currentUserRole);
            addSelect2ToUser();
            addSelect2ToTeam();

        });
    </script>
@endsection