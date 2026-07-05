@can('users.import')
<div class="modal fade" id="usersImportModal" tabindex="-1" aria-labelledby="usersImportModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable users-import-modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="usersImportModalLabel">Импорт учеников из Excel</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body text-start">
                <div class="accordion user-modal-accordion users-import-accordion mb-3" id="usersImportMemoAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="usersImportMemoHeading">
                            <button class="accordion-button collapsed py-2"
                                    type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#usersImportMemo"
                                    aria-expanded="false"
                                    aria-controls="usersImportMemo">
                                <i class="fas fa-circle-info me-2 text-muted"></i>Как работает импорт
                            </button>
                        </h2>
                        <div id="usersImportMemo"
                             class="accordion-collapse collapse"
                             aria-labelledby="usersImportMemoHeading"
                             data-bs-parent="#usersImportMemoAccordion">
                            <div class="accordion-body users-import-memo text-muted">
                                <p class="mb-2 fw-semibold text-body">Общие правила</p>
                                <ul class="mb-2 ps-3">
                                    <li>Одна строка = один ученик. Формат — <code>.xlsx</code>, один лист, даты <code>ДД.ММ.ГГГГ</code>.</li>
                                    <li>Обязательные колонки: <b>фамилия</b> и <b>имя</b> ученика.</li>
                                    <li>Сначала «Проверить» — при любой ошибке <b>ничего не записывается</b>.</li>
                                    <li>После проверки — «Импортировать»: все строки в одной транзакции.</li>
                                </ul>

                                <p class="mb-2 fw-semibold text-body">Ученики</p>
                                <ul class="mb-2 ps-3">
                                    <li><b>Email пустой</b> → <b>создание</b>. <b>Email совпал</b> с учеником организации → <b>обновление</b> всех полей из строки.</li>
                                    <li>Пустые телефон/email при обновлении → <code>null</code>. Чужой/не-ученик email → ошибка. Один email — одна строка.</li>
                                </ul>

                                <p class="mb-2 fw-semibold text-body">Группы</p>
                                <ul class="mb-2 ps-3">
                                    <li><b>Группа необязательна.</b> Пустая ячейка при создании → ученик без группы; при обновлении → снимает все группы.</li>
                                    <li>Если указана — поиск по названию (без регистра). Нет в справочнике → <b>создаётся</b> (нужно юр. лицо в строке).</li>
                                    <li>Удалённая группа → ошибка. Название уникально. При обновлении — <b>замена</b> всех групп.</li>
                                </ul>

                                <p class="mb-2 fw-semibold text-body">Юр. лица</p>
                                <ul class="mb-2 ps-3">
                                    <li>Обязательно, только если в строке указана <b>группа</b>. Ссылка на активное юр. лицо; из Excel <b>не создаётся</b>.</li>
                                </ul>

                                <p class="mb-2 fw-semibold text-body">Родители</p>
                                <ul class="mb-0 ps-3">
                                    <li>Все поля пустые при обновлении → <b>отвязка</b>. Email пустой, ФИО есть → новый родитель на строку.</li>
                                    <li>Одинаковый email родителя → один родитель, если <b>все поля</b> совпадают. В справочнике — только при полном совпадении.</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="users-import-step-upload">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                        <a href="{{ route('admin.users.import.template') }}"
                           class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                            <i class="fas fa-download" aria-hidden="true"></i>
                            <span>Скачать шаблон</span>
                        </a>
                        <span class="text-muted small">Все строки валидны → запись в БД.</span>
                    </div>
                    <div class="mb-0">
                        <label for="users-import-file" class="form-label">Файл Excel</label>
                        <input type="file"
                               class="form-control"
                               id="users-import-file"
                               accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet">
                        <div class="invalid-feedback" id="users-import-file-error"></div>
                    </div>
                </div>

                <div id="users-import-step-preview" class="d-none">
                    <div class="alert alert-success mb-3" id="users-import-preview-success" role="status"></div>
                    <div class="table-responsive users-import-scroll-table">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead class="sticky-top bg-white">
                            <tr>
                                <th>#</th>
                                <th>Ученик</th>
                                <th>Группа</th>
                                <th>Режим</th>
                            </tr>
                            </thead>
                            <tbody id="users-import-preview-body"></tbody>
                        </table>
                    </div>
                </div>

                <div id="users-import-step-errors" class="d-none">
                    <div class="alert alert-danger mb-3" id="users-import-errors-summary" role="alert"></div>
                    <div class="table-responsive users-import-scroll-table">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="sticky-top bg-white">
                            <tr>
                                <th>Строка</th>
                                <th>Поле</th>
                                <th>Ошибка</th>
                            </tr>
                            </thead>
                            <tbody id="users-import-errors-body"></tbody>
                        </table>
                    </div>
                </div>

                <div id="users-import-step-success" class="d-none">
                    <div class="users-import-success-panel text-center py-2 py-md-3">
                        <div class="users-import-success-icon mb-3" aria-hidden="true">
                            <i class="fas fa-circle-check"></i>
                        </div>
                        <h6 class="fw-semibold mb-2">Импорт завершён</h6>
                        <p class="text-muted mb-3" id="users-import-success-message"></p>
                        <div class="d-flex flex-wrap justify-content-center gap-2">
                            <span class="badge rounded-pill text-bg-success users-import-success-stat">
                                Создано: <span id="users-import-success-created">0</span>
                            </span>
                            <span class="badge rounded-pill text-bg-primary users-import-success-stat">
                                Обновлено: <span id="users-import-success-updated">0</span>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                <button type="button" class="btn btn-outline-secondary d-none" id="users-import-reset-btn">Другой файл</button>
                <button type="button" class="btn btn-primary" id="users-import-check-btn">
                    <span class="users-import-check-label">Проверить</span>
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="users-import-check-spinner" role="status" aria-hidden="true"></span>
                </button>
                <button type="button" class="btn btn-success d-none" id="users-import-commit-btn">
                    <span class="users-import-commit-label">Импортировать</span>
                    <span class="spinner-border spinner-border-sm ms-1 d-none" id="users-import-commit-spinner" role="status" aria-hidden="true"></span>
                </button>
            </div>
        </div>
    </div>
</div>
@endcan
