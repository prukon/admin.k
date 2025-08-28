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
        position: relative;          /* обязательно! чтобы крестик позиционировался именно от картинки */
    }

    /* Фото вписывается целиком, без обрезки */
    .modal-avatar .zoom-rect img{
        width: 100%;
        height: auto;
        max-height: 90vh;            /* контроль по высоте окна */
        object-fit: contain;         /* сохранить пропорции, не обрезать */
        display: block;
    }

    /* --- ДАЛЕЕ ИДУТ СТАРЫЕ ВАРИАНТЫ СТИЛЕЙ ДЛЯ КРЕСТИКА: ОСТАВЛЯЕМ В КОДЕ, НО НЕ ИСПОЛЬЗУЕМ --- */

    /* Крестик закрытия (вверху справа, на тёмном фоне белый) — старый вариант, ЗАКОММЕНТИРОВАН
    .modal-avatar .btn-close{
        position: absolute;
        right: .5rem; top: .5rem;
        filter: invert(1) grayscale(100%);
        opacity: .9;
    }
    .modal-avatar .btn-close:hover{ opacity: 1; }
    */

    /* Повторные определения .zoom-rect и .btn-close — дубли, ЗАКОММЕНТИРОВАНЫ
    .modal-avatar .zoom-rect {
        border: 4px solid #FFD700;
        border-radius: .5rem;
        overflow: hidden;
        box-shadow: 0 12px 40px rgba(0,0,0,.4);
        position: relative;
    }
    .modal-avatar .btn-close {
        filter: invert(1) grayscale(100%);
        opacity: .9;
    }
    .modal-avatar .btn-close:hover {
        opacity: 1;
    }
    */

    /* Вариант со синим фоном — больше не используем, ЗАКОММЕНТИРОВАН
    .modal-avatar .btn-close {
        background: #0057d8;
        border-radius: 50%;
        padding: 0.5rem;
        filter: invert(1) grayscale(100%);
        opacity: 1;
    }
    .modal-avatar .btn-close:hover {
        background: #003c9d;
    }
    */

    /* Ещё один дубль с полупрозрачным фоном — старый, ЗАКОММЕНТИРОВАН
    .modal-avatar .btn-close {
        background: rgba(0,0,0,0.5);
        border-radius: 50%;
        padding: 0.5rem;
        filter: invert(1) grayscale(100%);
        opacity: 1;
    }
    .modal-avatar .btn-close:hover {
        background: rgba(0,0,0,0.7);
    }
    */

    /* -- ЕДИНЫЙ АКТУАЛЬНЫЙ ВАРИАНТ ДЛЯ КНОПКИ ЗАКРЫТИЯ (ИСПОЛЬЗОВАТЬ ЭТОТ) -- */

    /* Кнопка закрытия поверх самой картинки */
    .modal-avatar .btn-close {
        position: absolute;
        top: .5rem;
        right: .5rem;
        background: rgba(0,0,0,0.6); /* тёмный полупрозрачный кружок */
        border-radius: 50%;
        padding: 0.5rem;
        line-height: 1;
        opacity: 1;
        z-index: 10;
        filter: invert(1) grayscale(100%); /* белый крестик (SVG Bootstrap) */
    }
    .modal-avatar .btn-close:hover {
        background: rgba(0,0,0,0.8);
    }

    /* Выравнивание обёртки аватарки, как было */
    .avatar-wrap {
        text-align: center;
    }

    /*--*/

    .modal-avatar .btn-close {
        position: absolute;
        top: .5rem;
        right: .5rem;
        background: rgba(0,0,0,0.6); /* тёмный полупрозрачный кружок */
        border-radius: 50%;
        padding: 0.5rem;
        line-height: 1;
        opacity: 1;
        z-index: 10;

        /* выключаем filter (он портит svg) */
        filter: none;

        /* заставляем крест быть белым */
        --bs-btn-close-color: #fff;
        --bs-btn-close-bg: transparent;
        --bs-btn-close-opacity: 1;
        --bs-btn-close-hover-opacity: 1;
    }

    /* hover — фон чуть темнее */
    .modal-avatar .btn-close:hover {
        background: rgba(0,0,0,0.8);
    }

    /* Контейнер с картинкой уже position:relative; — ок */
    .modal-avatar .zoom-rect { position: relative; }

    /* Кнопка закрытия поверх картинки */
    .modal-avatar .avatar-close{
        position: absolute;
        top: .5rem;
        right: .5rem;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;

        background: rgba(0,0,0,.65);   /* тёмный кружок */
        color: #fff;                   /* цвет иконки/символа */
        border: 0;
        border-radius: 50%;
        box-shadow: 0 2px 8px rgba(0,0,0,.35);
        cursor: pointer;
        z-index: 20;
        line-height: 1;                /* чтобы не было смещения по вертикали */
    }

    /* Размер креста Font Awesome */
    .modal-avatar .avatar-close i{
        font-size: 18px;
        line-height: 1;
    }

    /* Ховер/фокус */
    .modal-avatar .avatar-close:hover,
    .modal-avatar .avatar-close:focus{
        background: rgba(0,0,0,.85);
        outline: none;
    }

</style>


<!-- Модалка: Большое фото -->
{{--<div class="modal fade modal-avatar" id="avatarZoomModal" tabindex="-1" aria-hidden="true">--}}
    {{--<div class="modal-dialog modal-dialog-centered">--}}
        {{--<div class="modal-content bg-transparent border-0 shadow-none">--}}
            {{--<div class="d-flex justify-content-center align-items-center p-3">--}}
                {{--<div class="zoom-wrap">--}}
                    {{--<div class="zoom-rect position-relative">--}}
                        {{--<!-- крестик теперь тут -->--}}
                        {{--<button type="button"--}}
                                {{--class="btn-close btn-close-white position-absolute end-0 top-0 m-2"--}}
                                {{--data-bs-dismiss="modal"--}}
                                {{--aria-label="Закрыть"></button>--}}

                        {{--<img src="{{ auth()->user()->image--}}
                            {{--? asset('storage/avatars/'.auth()->user()->image)--}}
                            {{--: asset('/img/default-avatar.png') }}"--}}
                             {{--alt="Avatar large"--}}
                             {{--class="js-zoom-image">--}}
                    {{--</div>--}}
                {{--</div>--}}
            {{--</div>--}}
        {{--</div>--}}
    {{--</div>--}}
{{--</div>--}}

<div class="modal fade modal-avatar" id="avatarZoomModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <div class="d-flex justify-content-center align-items-center p-3">
                <div class="zoom-wrap">
                    <div class="zoom-rect position-relative">
                        <!-- НАША КНОПКА ЗАКРЫТИЯ -->
                        <button type="button"
                                class="avatar-close"
                                data-bs-dismiss="modal"
                                aria-label="Закрыть">
                            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                            <!-- если Font Awesome не подключён, можно оставить просто ×
                            ×
                            -->
                        </button>

                        <img src="{{ auth()->user()->image
                ? asset('storage/avatars/'.auth()->user()->image)
                : asset('/img/default-avatar.png') }}"
                             alt="Avatar large"
                             class="js-zoom-image">
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Модалка: Редактирование/замена фото-->
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

    // CRUD аватарки
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
                            showSuccessModal("Удаление аватарки ", "Аватар успешно удален.", 1);

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

    // задержка пропадания меню при ховере
    function initAvatarHoverMenu() {
        const avatar = document.querySelector('.avatar');
        if (!avatar) return; // если аватарки нет на странице — просто выходим

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
            hideTimer = setTimeout(close, 200); // задержка скрытия
        });

        // На самом меню тоже страхуемся
        menu.addEventListener('mouseenter', open);
        menu.addEventListener('mouseleave', () => {
            hideTimer = setTimeout(close, 200);
        });
    }


    document.addEventListener('DOMContentLoaded', function () {
        initAvatarHoverMenu();
    });

    //Скрытие "открыть фото" для страниц без смены юзера
    function toggleOpenPhotoButton(avatarEl) {
        const img = avatarEl.querySelector('.avatar-clip img');
        const openBtn = avatarEl.querySelector('.js-open-photo');
        if (!img || !openBtn) return;

        const defaultSrc = "{{ asset('img/default-avatar.png') }}"; // дефолт

        if (img.getAttribute('src').includes(defaultSrc)) {
            openBtn.style.display = 'none'; // скрыть
        } else {
            openBtn.style.display = ''; // показать (возврат к стандартному состоянию)
        }
    }

    //Скрытие "открыть фото". Для вызова в success
    function setOpenPhotoVisibilityByUser(user) {
        const $btn = $('.avatar .js-open-photo');
        const hasAvatar = !!(user && (user.image || user.image_crop));
        $btn.toggle(hasAvatar);
    }

    // Вызываем при загрузке
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.avatar').forEach(toggleOpenPhotoButton);
    });

</script>