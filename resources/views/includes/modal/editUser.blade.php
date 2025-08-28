{{--Cropie--}}

<!-- Модальное окно редактирования пользователя -->
<div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
    <div class="modal-dialog ">
        <div class="modal-content background-color-grey">
            <div class="modal-header">
                <h5 class="modal-title" id="editUserModalLabel">Редактирование пользователя</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="edit-user-form" class="text-start" method="post">
                @csrf
                @method('patch')

                <!-- Блок для аватарки -->
                    <div class="mb-3 d-flex flex-column align-items-center">
                        <div>
                            {{----1----------------------}}

                            <style>

                                /* ========== АВАТАР НА СТРАНИЦЕ ========== */

                                /* Внешний контейнер: тут hover и меню. ВАЖНО: overflow: visible */
                                .avatar {
                                    position: relative;
                                    display: inline-block;
                                }

                                /* Внутренний круг: обрезка фото + жёлтый бордер */
                                .avatar-clip {
                                    width: 130px;
                                    aspect-ratio: 1 / 1; /* всегда квадрат */
                                    border: 2px solid #FFD700; /* жёлтый бордер 2px */
                                    border-radius: 50%;
                                    overflow: hidden; /* обрезаем ТОЛЬКО фото */
                                    display: grid;
                                    place-items: center; /* центрирование img */
                                    background: #f3f3f3;
                                    box-sizing: border-box; /* бордер внутри размеров */
                                }

                                /* Картинка заполняет круг без искажений */
                                .avatar-clip img {
                                    width: 100% !important;
                                    height: 100% !important;
                                    object-fit: cover;
                                    object-position: center;
                                    display: block;
                                    border-radius: 50%;
                                }

                                /* ========== МЕНЮ ДЕЙСТВИЙ ПОД АВАТАРКОЙ ========== */

                                .avatar-actions {
                                    position: absolute;
                                    top: 100%;
                                    left: 50%;
                                    transform: translate(-50%, 8px); /* визуальный отступ без gap */
                                    background: #fff;
                                    border: 1px solid rgba(0, 0, 0, .12);
                                    border-radius: .5rem;
                                    box-shadow: 0 8px 24px rgba(0, 0, 0, .15);
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
                                .avatar-actions::before {
                                    content: "";
                                    position: absolute;
                                    top: -8px;
                                    left: 0;
                                    right: 0;
                                    height: 8px;
                                }

                                /* Открытое состояние — добавляется JS-классом .is-open на .avatar */
                                .avatar.is-open .avatar-actions {
                                    opacity: 1;
                                    visibility: visible;
                                    pointer-events: auto;
                                }

                                /* Пункты меню + подсветка */
                                .avatar-actions .dropdown-item {
                                    display: flex;
                                    align-items: center;
                                    gap: .5rem;
                                    padding: .5rem 1rem;
                                    cursor: pointer;
                                    border-radius: .25rem;
                                    transition: background-color .15s ease, color .15s ease;
                                }

                                .avatar-actions .dropdown-item:hover {
                                    background-color: #f0f0f0;
                                    color: #0057d8; /* лёгкая «VK»-подсветка текста/иконки */
                                }

                                .avatar-actions .dropdown-item:hover i {
                                    color: #0057d8;
                                }

                                /* ========== МОДАЛКА ПРОСМОТРА БОЛЬШОЙ АВАТАРКИ (ПРЯМОУГОЛЬНИК) ========== */

                                .modal-avatar .modal-dialog {
                                    max-width: none;
                                }

                                .modal-avatar .modal-content {
                                    background: rgba(0, 0, 0, .85);
                                }

                                /* Контейнер: вписываемся в экран и по ширине, и по высоте */
                                .modal-avatar .zoom-wrap {
                                    max-width: 92vw;
                                    max-height: 92vh;
                                }

                                /* Прямоугольный кадр с жёлтым бордером (не круг!) */
                                .modal-avatar .zoom-rect {
                                    width: 100%;
                                    height: auto;
                                    border: 4px solid #FFD700;
                                    border-radius: .5rem; /* если не нужно скругление — поставь 0 */
                                    overflow: hidden;
                                    box-shadow: 0 12px 40px rgba(0, 0, 0, .4);
                                }

                                /* Фото вписывается целиком, без обрезки */
                                .modal-avatar .zoom-rect img {
                                    width: 100%;
                                    height: auto;
                                    max-height: 90vh; /* контроль по высоте окна */
                                    object-fit: contain; /* сохранить пропорции, не обрезать */
                                    display: block;
                                }

                                /* Крестик закрытия (вверху справа, на тёмном фоне белый) */
                                .modal-avatar .btn-close {
                                    position: absolute;
                                    right: .5rem;
                                    top: .5rem;
                                    filter: invert(1) grayscale(100%);
                                    opacity: .9;
                                }

                                .modal-avatar .btn-close:hover {
                                    opacity: 1;
                                }

                            </style>

                            <div class="avatar_wrapper">
                                <div class="avatar">                         <!-- ВНЕШНИЙ контейнер (hover + меню) -->
                                    <div class="avatar-clip">
                                        <!-- ВНУТРЕННИЙ круг (обрезка фото + бордер) -->
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
                            </div>

                            <!-- Модалка: Большого фото -->
                            <!-- Модалка: Большое фото (адаптивный зум) -->
                            <div class="modal fade modal-avatar" id="avatarZoomModal" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content bg-transparent border-0 shadow-none position-relative">
                                        <button type="button"
                                                class="btn-close btn-close-white position-absolute end-0 top-0 m-2"
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
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                                    aria-label="Закрыть"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p class="text-muted mb-3">
                                                Загрузите изображение в формате JPG, PNG, WEBP или GIF.
                                            </p>

                                            <div class="d-flex flex-column flex-lg-row gap-3">
                                                <div class="flex-grow-1">
                                                    <div class="border rounded p-2">
                                                        <img id="cropperSource" alt="Source"
                                                             style="max-width:100%; display:none;">
                                                        <div id="placeholder" class="text-center text-muted p-5">
                                                            <div class="mb-2">Выберите файл для редактирования</div>
                                                            <button class="btn btn-primary js-choose-file">Выбрать
                                                                файл
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex-shrink-0" style="width:280px;">
                                                    <div class="mb-2 fw-semibold">Предпросмотр миниатюры</div>
                                                    <div class="border rounded-circle overflow-hidden"
                                                         style="width:256px;height:256px;">
                                                        <img id="previewThumb" alt="Preview"
                                                             style="width:100%;height:100%;object-fit:cover;display:none;">
                                                    </div>
                                                </div>
                                            </div>

                                            <input type="file" class="d-none" id="avatarFile" accept="image/*">
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-outline-secondary"
                                                    data-bs-dismiss="modal">Отмена
                                            </button>
                                            <button type="button" class="btn btn-primary js-save-avatar" disabled>
                                                Сохранить
                                            </button>
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
                                $(function () {

                                    $.ajaxSetup({
                                        headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') }
                                    });

                                    const $currentAvatar = $('.avatar-clip img');   // вместо .js-current-avatar
                                    const $zoomImg = $('.js-zoom-image');



                                    // универсальная загрузка аватарки. Грузим текущего юзера, или ajax если есть.
                                    $(function () {
                                        const $zoomImg = $('.js-zoom-image');

                                        // По умолчанию (текущий авторизованный пользователь)
                                        let zoomSrc = "{{ auth()->user()->image
        ? asset('storage/avatars/'.auth()->user()->image)
        : asset('/img/default-avatar.png') }}";

                                        // Единый обработчик открытия
                                        $(document).off('click', '.js-open-photo').on('click', '.js-open-photo', function () {
                                            // на всякий случай актуализируем src перед показом
                                            $zoomImg.attr('src', zoomSrc);
                                            const el = document.getElementById('avatarZoomModal');
                                            const modal = bootstrap.Modal.getOrCreateInstance(el);
                                            modal.show();
                                        });

                                        // === КОГДА ПРИШЁЛ AJAX С ДАННЫМИ ЮЗЕРА (в твоём success) ===
                                        // Вызови эту функцию и передай response.user
                                        window.setZoomImageFromUser = function (user) {
                                            if (user && user.image) {
                                                zoomSrc = "{{ asset('storage/avatars') }}/" + user.image;
                                            } else if (user && user.image_crop) {
                                                // фолбэк: если нет большой — покажем кроп
                                                zoomSrc = "{{ asset('storage/avatars') }}/" + user.image_crop;
                                            } else {
                                                zoomSrc = "{{ asset('img/default-avatar.png') }}";
                                            }
                                            // можно сразу обновить картинку в модалке (на случай, если она уже открыта)
                                            $zoomImg.attr('src', zoomSrc);
                                        };
                                    });


                                    // Удаление
                                    $('.js-delete-photo').on('click', function () {
                                        showConfirmDeleteModal(
                                            "Удаление пользователя",
                                            "Вы уверены, что хотите удалить аватар пользователя?",
                                            function() {
                                                $.ajax({
                                                    url: '/profile/avatar',
                                                    method: 'POST',
                                                    data: {_method: 'DELETE'},
                                                    success: function (res) {
                                                        // Ставим дефолт
                                                        const def = '/img/default-avatar.png';
                                                        $currentAvatar.attr('src', def);
                                                        $zoomImg.attr('src', def);
                                                        // Тост/уведомление по желанию
                                                    },
                                                    error: function (xhr) {
                                                        alert('Ошибка удаления: ' + (xhr.responseJSON?.message || xhr.statusText));
                                                    }
                                                });
                                            });
                                    });

                                    // ====== Редактор (Cropper.js) ======
                                    let cropper = null;
                                    const $fileInput = $('#avatarFile');
                                    const $sourceImg = $('#cropperSource');
                                    const $placeholder = $('#placeholder');
                                    const $preview = $('#previewThumb');
                                    const $saveBtn = $('.js-save-avatar');

                                    // Открыть выбор файла
                                    $('.js-choose-file').on('click', () => $fileInput.trigger('click'));
                                    $('.js-change-photo').on('click', () => {
                                        // при каждом открытии оставляем как есть; если нужно — можно делать reset
                                    });

                                    // Загрузка файла в редактор
                                    $fileInput.on('change', function () {
                                        const file = this.files[0];
                                        if (!file) return;

                                        const url = URL.createObjectURL(file);
                                        $sourceImg.attr('src', url).show();
                                        $placeholder.hide();
                                        $preview.hide();

                                        // Инициируем/пересоздаём кроппер
                                        if (cropper) {
                                            cropper.destroy();
                                            cropper = null;
                                        }
                                        cropper = new Cropper($sourceImg[0], {
                                            aspectRatio: 1,            // квадрат для аватарки
                                            viewMode: 1,               // внутри холста
                                            dragMode: 'move',
                                            guides: false,
                                            center: true,
                                            background: false,
                                            autoCropArea: 1,
                                            movable: true,
                                            zoomable: true,
                                            rotatable: false,
                                            scalable: false,
                                            ready() {
                                                $saveBtn.prop('disabled', false);
                                            },
                                            crop() {
                                                // Превью миниатюры (256x256)
                                                if (!cropper) return;
                                                const canvas = cropper.getCroppedCanvas({width: 256, height: 256});
                                                $preview.attr('src', canvas.toDataURL('image/jpeg', 0.9)).show();
                                            }
                                        });
                                    });

                                    // Сохранение: собираем два Blob-а — big (1024x1024) и crop (256x256)
                                    $saveBtn.on('click', function () {
                                        if (!cropper) return;

                                        const bigCanvas = cropper.getCroppedCanvas({width: 1024, height: 1024}); // «большая»
                                        const cropCanvas = cropper.getCroppedCanvas({width: 256, height: 256}); // миниатюра

                                        // Преобразуем в Blob и отправляем FormData
                                        Promise.all([
                                            new Promise(res => bigCanvas.toBlob(b => res(b), 'image/jpeg', 0.92)),
                                            new Promise(res => cropCanvas.toBlob(b => res(b), 'image/jpeg', 0.92))
                                        ]).then(function ([bigBlob, cropBlob]) {
                                            const fd = new FormData();
                                            fd.append('image_big', bigBlob, 'big.jpg');
                                            fd.append('image_crop', cropBlob, 'crop.jpg');

                                            $.ajax({
                                                url: '/profile/avatar',
                                                method: 'POST',
                                                processData: false,
                                                contentType: false,
                                                data: fd,
                                                success: function (res) {
                                                    // Обновляем аватар (крошку) и картинку для зума
                                                    $currentAvatar.attr('src', res.image_crop_url + '?v=' + Date.now());
                                                    $zoomImg.attr('src', res.image_url + '?v=' + Date.now());

                                                    // Закрываем модалку и чистим состояние
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

                                    function resetEditor() {
                                        $fileInput.val('');
                                        $saveBtn.prop('disabled', true);
                                        if (cropper) {
                                            cropper.destroy();
                                            cropper = null;
                                        }
                                        $sourceImg.hide().attr('src', '');
                                        $preview.hide().attr('src', '');
                                        $placeholder.show();
                                    }

                                    // Сброс при закрытии модалки
                                    document.getElementById('avatarEditModal').addEventListener('hidden.bs.modal', resetEditor);
                                });
                            </script>

                            {{-----2---------------------}}

                        </div>

                    </div>

                    <!-- Поле "Имя" -->
                    <div class="mb-3">
                        <label for="edit-name" class="form-label">Имя ученика *</label>
                        <input type="text"
                               name="name"
                               class="form-control"
                               id="edit-name"
                               @cannot('users-name-update') disabled aria-disabled="true" @endcannot>
                    </div>

                    <!-- Поле "Дата рождения" -->
                    <div class="mb-3">
                        <label for="edit-birthday" class="form-label">Дата рождения</label>
                        <input
                                type="date"
                                name="birthday"
                                id="edit-birthday"
                                class="form-control"
                                @cannot('users-birthdate-update') disabled aria-disabled="true" @endcannot
                        >
                        @cannot('users-birthdate-update')
                            <div class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i>Нет прав на изменение
                                даты рождения
                            </div>
                        @endcannot
                    </div>

                    <!-- Поле "Группа" -->
                    <div class="mb-3">
                        <label for="edit-team" class="form-label">Группа</label>
                        <select
                                id="edit-team"
                                name="team_id"
                                class="form-select"
                                @cannot('users-group-update') disabled aria-disabled="true" @endcannot
                        >
                            <option value="">Без группы</option>
                            @foreach($allTeams as $team)
                                <option value="{{ $team->id }}">{{ $team->title }}</option>
                            @endforeach
                        </select>

                        @cannot('users-group-update')
                            <div class="form-text text-muted">
                                <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение группы
                            </div>
                        @endcannot
                    </div>

                    <!-- Поле "Дата начала занятий" -->
                    <div class="mb-3">
                        <label for="edit-start_date" class="form-label">Дата начала занятий</label>
                        <input
                                type="date"
                                id="edit-start_date"
                                name="start_date"
                                class="form-control"
                                @cannot('users-startDate-update') disabled aria-disabled="true" @endcannot
                        >
                        @cannot('users-startDate-update')
                            <div class="form-text text-muted">
                                <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение даты начала
                            </div>
                        @endcannot
                    </div>

                {{--Пользовательские поля--}}
                @if($fields->isNotEmpty()) <!-- Проверяем, есть ли пользовательские поля -->
                    <div class="mb-3">
                        <div id="custom-fields-container"> <!-- Контейнер для пользовательских полей -->


                        </div>
                    </div>
                @endif

                <!-- Поле "Email" -->
                    <div class="mb-3">
                        <label for="edit-email" class="form-label">Адрес электронной почты*</label>
                        <input
                                type="email"
                                id="edit-email"
                                name="email"
                                class="form-control"
                                required
                                @cannot('users-email-update') disabled aria-disabled="true" @endcannot
                        >
                        @cannot('users-email-update')
                            <div class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i>Нет прав на изменение
                                email
                            </div>
                        @endcannot
                    </div>

                    {{-- Поле "Телефон" --}}
                    @php $canPhone = auth()->user()->can('users-phone-update'); @endphp

                    <div class="mb-3">
                        <label for="edit-phone" class="form-label">Телефон</label>

                        <div class="input-group">
                            <input
                                    type="tel"
                                    class="form-control"
                                    id="edit-phone"
                                    name="phone"
                                    value="{{ old('phone', $user->phone) }}"
                                    placeholder="+7 (___) ___-__-__"
                                    data-original="{{ $user->phone ?? '' }}"
                                    data-verified="{{ $user->phone_verified_at ? 1 : 0 }}"
                                    @unless($canPhone) disabled aria-disabled="true" @endunless
                            >

                            {{-- Индикатор статуса (галка/крест) --}}
                            <span id="phone-verify-icon" class="input-group-text d-none">
            <i class="fa-solid fa-circle-check"></i>
        </span>
                        </div>

                        @php
                            $verifiedAt = $user->phone_verified_at ? \Carbon\Carbon::parse($user->phone_verified_at) : null;
                        @endphp
                        <small
                                id="phone-verify-status"
                                class="small {{ $verifiedAt ? 'text-success' : 'd-none' }}"
                                data-verified-at="{{ $verifiedAt ? $verifiedAt->format('Y-m-d H:i:s') : '' }}"
                        >
                            @if($verifiedAt)
                                Подтверждён {{ $verifiedAt->format('d.m.Y H:i') }}
                            @endif
                        </small>

                        @unless($canPhone)
                            <div class="form-text text-muted mt-1">
                                <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение телефона
                            </div>
                        @endunless
                    </div>

                    <!-- Поле "Активность" -->
                    <div class="mb-3">
                        <label for="edit-activity" class="form-label">Активность</label>
                        <select
                                id="edit-activity"
                                name="is_enabled"
                                class="form-select"
                                @cannot('users-activity-update') disabled aria-disabled="true" @endcannot
                        >
                            <option value="0">Неактивен</option>
                            <option value="1">Активен</option>
                        </select>
                        @cannot('users-activity-update')
                            <div class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i>Нет прав на изменение
                                активности
                            </div>
                        @endcannot
                    </div>

                    <!-- Поле "Роль" -->
                    <div class="mb-3">
                        <label for="role_id" class="form-label">Роль</label>
                        <select
                                id="role_id"
                                name="role_id"
                                class="form-select"
                                @cannot('users-role-update') disabled aria-disabled="true" @endcannot
                        >
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}"
                                        @if(($editingUser->role_id ?? $user->role_id ?? null) === $role->id) selected @endif>
                                    {{ $role->label }}
                                </option>
                            @endforeach
                        </select>
                        @cannot('users-role-update')
                            <div class="form-text text-muted"><i class="fa-solid fa-lock me-1"></i>Нет прав на изменение
                                роли
                            </div>
                        @endcannot
                    </div>

                    <!-- Блок изменения пароля -->
                    <div class="buttons-wrap change-pass-wrap" id="change-pass-wrap" style="display: none;">
                        <div class="d-flex align-items-center mt-3">
                            <div class="position-relative wrap-change-password">
                                <input type="password" id="new-password" class="form-control"
                                       placeholder="Новый пароль">
                                <span toggle="#new-password" class="fa fa-fw fa-eye field-icon toggle-password"></span>
                            </div>
                            <button type="button" id="apply-password-btn" class="btn btn-primary ml-2">Применить
                            </button>
                            <button type="button" id="cancel-change-password-btn" class="btn btn-danger ml-2">Отмена
                            </button>
                        </div>
                        <div id="error-message" class="text-danger mt-2" style="display:none;">Пароль должен быть не
                            менее 8 символов
                        </div>
                    </div>

                    @php $canChange = auth()->user()->can('users-password-update'); @endphp

                    <div class="button-group buttons-wrap mt-3">
                        <button type="button"
                                id="change-password-btn"
                                class="btn btn-primary change-password-btn {{ $canChange ? '' : 'opacity-50 pe-none' }}"
                                @unless($canChange)
                                aria-disabled="true"
                                tabindex="-1"
                                data-bs-toggle="tooltip"
                                title="Нет прав на изменение пароля"
                                @endunless
                        >
                            <i class="fa-solid fa-key me-1"></i> Изменить пароль
                        </button>

                        @unless($canChange)
                            <div class="form-text text-muted mt-2">
                                <i class="fa-solid fa-lock me-1"></i>Нет прав на изменение пароля
                            </div>
                        @endunless
                    </div>

                    @unless($canChange)
                        <script>
                            document.addEventListener('DOMContentLoaded', function () {
                                const btn = document.getElementById('change-password-btn');
                                if (btn) new bootstrap.Tooltip(btn);
                            });
                        </script>
                    @endunless

                <!-- Кнопка для сохранения данных -->
                    <button type="submit" class="btn btn-primary mt-3 save-change-modal">Сохранить изменения</button>
                    <!-- Кнопка для сохранения данных -->

                    {{--<button type="submit" class="btn btn-danger mt-3 save-change-modal">Удалить</button>--}}
                    <button type="button" id="delete-user-btn" class="btn btn-danger mt-3 confirm-delete-modal">
                        Удалить
                    </button>

                </form>
            </div>

        </div>
    </div>
</div>

<script>

    $(document).ready(function () {

        // Функция редактирования пользователя
        function editMidalUser() {

            // Функция для показа/скрытия пароля с помощью иконки глаза  fix
            function showPassword() {
                const togglePassword = document.querySelector('.wrap-change-password .toggle-password');
                const passwordInput = document.querySelector('.wrap-change-password #new-password');

                togglePassword.addEventListener('click', function () {
                    // Переключаем тип input между 'password' и 'text'
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);
                    // Меняем иконку глаза
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }

            showPassword();


            // Показать/скрыть изменение пароля
            $('#change-password-btn').on('click', function () {
                $('#change-password-btn').hide();
                $('#change-pass-wrap').show();
            });

            // Применение нового пароля
            $('#apply-password-btn').on('click', function () {
                var userId = $('#edit-user-form').attr('action').split('/').pop();
                console.log('Применение нового пароля для пользователя с ID:', userId);
                var newPassword = $('#new-password').val();
                var token = $('input[name="_token"]').val();
                var errorMessage = $('#error-modal-message');

                // Проверка длины пароля
                if (newPassword.length < 8) {
                    errorMessage.show(); // Показываем сообщение об ошибке
                    return; // Прерываем выполнение, если пароль слишком короткий
                } else {
                    errorMessage.hide(); // Скрываем сообщение об ошибке
                }

                $.ajax({
                    url: `/admin/user/${userId}/update-password`,
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': token
                    },
                    data: {
                        password: newPassword
                    },
                    success: function (response) {
                        console.log('Ответ сервера на обновление пароля:', response);
                        if (response.success) {
                            $('#change-password-btn').show();
                            $('#change-pass-wrap').hide();
                            $('#password-change-message').show().delay(3000).fadeOut();
                            showSuccessModal("Обновление пароля", "Пароль успешно обновлен.");
                        }
                    },
                    // error: function () {
                    //     $('#errorModal').modal('show');
                    // }

                    error: function (response) {
                        let errorMessage = 'Произошла ошибка при сохранении данных.';
                        if (response.responseJSON && response.responseJSON.message) {
                            errorMessage = response.responseJSON.message; // Используем сообщение с сервера, если оно есть
                        }
                        $('#error-modal-message').text(errorMessage); // Устанавливаем сообщение ошибки
                        $('#errorModal').modal('show');    // Показываем модалку ошибки
                    }
                });
            });

            // Отмена изменения пароля
            $('#cancel-change-password-btn').on('click', function () {
                $('#change-password-btn').show();
                $('#change-pass-wrap').hide();
                $('#error-message').hide();
            });
        }

        // ОКРЫТЫТЬ МОДАЛКУ ЮЗЕРА и загружаем его данные для редактирования UserController edit
        function editUserLink() {
            $('.edit-user-link').on('click', function () {
                let userId = $(this).data('id'); // Получаем ID пользователя
                console.log('Открываем модалку для редактирования пользователя с ID:', userId);

                // AJAX-запрос для получения данных пользователя
                $.ajax({
                    url: `/admin/users/${userId}/edit`,
                    method: 'GET',

                    success: function (response) {
                        const current = response.currentUser;
                        const isSuperadmin = current.isSuperadmin;
                        console.log('\\Log full response =', response);

                        // 1) Заполняем стандартные поля
                        $('#edit-user-form #edit-name').val(response.user.name);
                        $('#edit-user-form #edit-birthday').val(response.user.birthday);
                        $('#edit-user-form #edit-team').val(response.user.team_id);
                        $('#edit-user-form #edit-start_date').val(response.user.start_date);
                        $('#edit-user-form #edit-email').val(response.user.email);
                        $('#edit-user-form #edit-phone').val(response.user.phone);
                        $('#edit-user-form #edit-activity').val(response.user.is_enabled);

                        // 2) Рисуем <option> для ролей из response.roles
                        const roleSelect = $('#edit-user-form #role_id');
                        roleSelect.empty();
                        response.roles.forEach(function (role) {
                            roleSelect.append(
                                $('<option>', {value: role.id, text: role.label})
                            );
                        });
                        roleSelect.val(response.user.role_id);

                        // 3) Устанавливаем action формы
                        $('#edit-user-form').attr('action', `/admin/users/${response.user.id}`);

                        if (response.user && response.user.image_crop) {
                            $('.avatar-clip img').attr('src', "{{ asset('storage/avatars') }}/" + response.user.image_crop);
                        } else {
                            $('.avatar-clip img').attr('src', "{{ asset('img/default-avatar.png') }}");
                        }


                        // большая аватарка (для модалки просмотра)
                        setZoomImageFromUser(response.user);



                        // 5) Заполняем кастомные поля
                        const container = $('#custom-fields-container');
                        container.empty();

                        const currentRoleId = response.currentUser.role_id;
                        console.log('\\Log currentRoleId =', currentRoleId);

                        response.fields.forEach(function (field, idx) {
                            // 1) Значение поля для текущего пользователя
                            let userValue = '';
                            if (response.user.fields) {
                                const uf = response.user.fields.find(uf => uf.slug === field.slug);
                                if (uf) userValue = uf.pivot.value || '';
                            }

                            console.log(`\Log [Field ${field.slug}] editable =`, field.editable); // Изменено: логируем для отладки

                            const roles = Array.isArray(field.roles) ? field.roles : [];
                            const disabledAttr = field.editable ? '' : 'disabled'; // Изменено: вместо расчёта по role_id

                            // 3) Генерируем HTML
                            const html = `
            <div class="mb-3 custom-field" data-slug="${field.slug}">
                <label for="custom-${field.slug}" class="form-label">${field.name}</label>
                <input
                    type="text"
                    name="custom[${field.slug}]"
                    class="form-control"
                    id="custom-${field.slug}"
                    value="${userValue}"
                    ${disabledAttr}
                />
            </div>
        `;
                            container.append(html);
                        });

                        // 6) Открываем модалку
                        $('#editUserModal').modal('show');
                    },

                    error: function () {
                        console.error('Ошибка при загрузке данных пользователя');
                    }
                });
            });
        }

        // ОТПРАВКА AJAX.-> /UserController->Update Обработчик обновления данных пользователя
        function editUserForm() {
            $('#edit-user-form').on('submit', function (e) {
                e.preventDefault();

                let form = $(this);
                let url = form.attr('action');

                console.log('Отправляем форму для обновления пользователя с URL:', url);
                console.log('form.serialize():' + form.serialize());

                // AJAX-запрос для обновления данных пользователя
                $.ajax({
                    url: url,
                    method: 'PATCH',
                    data: form.serialize(),
                    success: function (response) {
                        showSuccessModal("Редактирование пользователя", "Пользователь успешно обновлен.", 1);
                        console.log(response);
                    },
                    error: function (response) {
                        eroorRespone(response);
                    }
                });
            });
        }

        // Вызов модалки удаления
        $(document).on('click', '.confirm-delete-modal', function () {
            deleteUser();
        });

        function deleteUser() {
            // Показываем модалку с текстом и передаём колбэк, который удалит пользователя
            showConfirmDeleteModal(
                "Удаление пользователя",
                "Вы уверены, что хотите удалить пользователя?",
                function () {
                    let userId = $('#edit-user-form').attr('action').split('/').pop(); // Получаем ID пользователя
                    let token = $('input[name="_token"]').val();

                    $.ajax({
                        url: `/admin/user/${userId}`,
                        method: 'DELETE',
                        headers: {'X-CSRF-TOKEN': token},
                        success: function (response) {
                            if (response.success) {
                                showSuccessModal("Удаление пользователя", "Пользователь успешно удален.", 1);
                            } else {
                                $('#error-modal-message').text('Произошла ошибка при удалении пользователя.');
                                $('#errorModal').modal('show');
                            }
                        },
                        error: function () {
                            $('#error-modal-message').text('Произошла ошибка при удалении пользователя.');
                            $('#errorModal').modal('show');
                        }
                    });
                }
            );
        }

        editMidalUser();
        editUserLink();
        editUserForm();
    });
</script>

<script>
    (function () {
        const $doc = $(document);
        const digits = s => String(s || '').replace(/\D/g, '');

        function formatPhone(raw) {
            let d = digits(raw).replace(/^8/, '7');
            if (d && d[0] !== '7') d = '7' + d;
            d = d.slice(0, 11);
            const p1 = d.slice(1, 4), p2 = d.slice(4, 7), p3 = d.slice(7, 9), p4 = d.slice(9, 11);
            return '+7 (' + (p1.padEnd(3, '_')) + ') ' + (p2.padEnd(3, '_')) + '-' + (p3.padEnd(2, '_')) + '-' + (p4.padEnd(2, '_'));
        }

        function sameAsOriginal($input) {
            const orig = digits($input.data('original') || '').replace(/^8/, '7');
            const cur = digits($input.val()).replace(/^8/, '7');
            return !!orig && orig === cur;
        }

        function updateIcon($input) {
            const $iconWrap = $('#phone-verify-icon');
            const $icon = $iconWrap.find('i');
            const $status = $('#phone-verify-status');
            const hasAny = digits($input.val()).length > 0;
            const wasVerified = Number($input.data('verified')) === 1;
            const isSame = sameAsOriginal($input);

            if (!hasAny) {
                $iconWrap.addClass('d-none');
                $status.addClass('d-none');
                return;
            }

            // Верифицирован и номер не меняли -> зелёная галка + "Подтверждён …"
            if (wasVerified && isSame) {
                $iconWrap.removeClass('d-none text-danger').addClass('text-success');
                $icon.removeClass('fa-circle-xmark').addClass('fa-circle-check');
                const vAt = $status.data('verified-at');
                if (vAt) {
                    $status.removeClass('d-none').addClass('text-success')
                        .text('Подтверждён ' + formatDate(vAt));
                } else {
                    $status.addClass('d-none');
                }
            } else {
                // Иначе — только красный крестик, без подписи
                $iconWrap.removeClass('d-none text-success').addClass('text-danger');
                $icon.removeClass('fa-circle-check').addClass('fa-circle-xmark');
                $status.addClass('d-none');
            }
        }

        function formatDate(str) {
            const d = new Date(String(str).replace(' ', 'T'));
            const pad = n => String(n).padStart(2, '0');
            return pad(d.getDate()) + '.' + pad(d.getMonth() + 1) + '.' + d.getFullYear() + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
        }

        // Делегированные обработчики — работают и в модалках
        $doc.on('input', '#edit-phone', function () {
            const pos = this.selectionStart || 0;
            this.value = formatPhone(this.value);
            try {
                this.setSelectionRange(pos, pos);
            } catch (e) {
            }
            updateIcon($(this));
        });
        $doc.on('paste blur', '#edit-phone', function () {
            const el = this;
            setTimeout(() => {
                el.value = formatPhone(el.value);
                updateIcon($(el));
            }, 0);
        });

        // Инициализация при показе модалки/страницы
        $doc.on('shown.bs.modal', function (e) {
            const $inp = $(e.target).find('#edit-phone');
            if ($inp.length) {
                if ($inp.val()) $inp.val(formatPhone($inp.val()));
                updateIcon($inp);
            }
        });
        $(function () {
            const $inp = $('#edit-phone');
            if ($inp.length) {
                if ($inp.val()) $inp.val(formatPhone($inp.val()));
                updateIcon($inp);
            }
        });
    })();
</script>
