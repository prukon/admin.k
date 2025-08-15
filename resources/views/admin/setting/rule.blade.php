{{-- resources/views/admin/setting/rule.blade.php --}}
@php
    /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\Role[] $roles */
    /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\Permission[] $permissions */
    /** @var \Illuminate\Database\Eloquent\Collection|\App\Models\PermissionGroup[] $groups */
@endphp

<h4 class="pt-3 text-start">Права и роли</h4>
<hr>

<div class="col-12 d-flex justify-content-between align-items-center mb-3">
    <button id="new-role" type="button" class="btn btn-primary new-role width-170"
            data-bs-toggle="modal"
            data-bs-target="#createRoleModal">
        Настройки
    </button>

    <div class="wrap-icon btn" data-bs-toggle="modal" data-bs-target="#historyModal">
        <i class="fa-solid fa-clock-rotate-left logs"></i>
    </div>
</div>

<hr>

<style>
    :root { --perm-desc-w: 420px; } /* единая ширина колонки описания для всех групп */

    /* лёгкий стиль без теней */
    .perm-group { padding: .25rem 0 1rem; border-bottom: 1px solid rgba(0,0,0,.06); }
    .perm-group:last-child { border-bottom: 0; }

    .perm-badge { font-size: .75rem; padding: .15rem .45rem; border: 1px solid rgba(0,0,0,.08); border-radius: 999px; }

    .btn-disclosure { border: 1px solid rgba(0,0,0,.12); background: transparent; padding: .15rem .45rem; }
    .btn-disclosure .chev { display:inline-block; transition: transform .2s ease; }
    .btn-disclosure[aria-expanded="true"] .chev { transform: rotate(180deg); }

    .perm-table { font-size: .92rem; }
    .perm-table.table { --bs-table-bg: transparent; }
    .perm-table thead th {
        position: sticky; top: 0; background: #fff; z-index: 2; font-weight: 400; /* без bold */
    }
    .perm-table td, .perm-table th { padding: .55rem .6rem; vertical-align: middle !important; }
    .perm-table tbody tr { border-color: rgba(0,0,0,.06); }

    /* липкая первая колонка с фиксированной шириной */
    .perm-col-sticky {
        position: sticky; left: 0; background: #fff; z-index: 1;
        width: var(--perm-desc-w); min-width: var(--perm-desc-w); max-width: var(--perm-desc-w);
    }

    .table-success { background-color: rgba(25,135,84,.08) !important; transition: background-color .5s; }

    @media (max-width: 768px) { :root { --perm-desc-w: 300px; } }
    @media (max-width: 576px) { :root { --perm-desc-w: 260px; } }
</style>

<div id="permission-accordion">
    @foreach($groups->sortBy('sort_order') as $g)
        @php
            $groupId = $g->id;
            $permsInGroup = $g->permissions ?? collect();
        @endphp

        <section class="perm-group">
            <div class="table-responsive">
                <table class="table perm-table align-middle table-sm">
                    <thead>
                    <tr>
                        <th class="perm-col-sticky">
                            <button class="btn btn-disclosure btn-sm"
                                    type="button"
                                    aria-expanded="true">
                                <span class="chev"><i class="fa-solid fa-chevron-down"></i></span>
                            </button>
                            <span class="ms-2">{{ $g->name }}</span>
                            <span class="perm-badge ms-2">{{ $permsInGroup->count() }}</span>
                        </th>
                        @foreach($roles as $role)
                            <th class="text-center">
                                {{ $role->label ?? $role->name }}
                                @if(auth()->user()?->role?->name === 'superadmin' && !$role->is_visible)
                                    <br><span class="badge bg-warning text-dark ms-2">Невидимое</span>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                    </thead>

                    <tbody>
                    @foreach($permsInGroup as $permission)
                        <tr>
                            <td class="perm-col-sticky">
                                <div class="d-flex flex-column">
                                    <span>{{ $permission->description ?? $permission->name }}</span>
                                    <small class="text-muted">
                                        <code>{{ $permission->name }}</code>
                                        @if(auth()->user()?->role?->name === 'superadmin' && !$permission->is_visible)
                                            <span class="badge bg-warning text-dark ms-2">Невидимое</span>
                                        @endif
                                    </small>
                                </div>
                            </td>

                            @foreach($roles as $role)
                                @php $hasPermission = $role->permissions->contains($permission->id); @endphp
                                <td class="text-center">
                                    <input type="checkbox"
                                           class="permission-checkbox"
                                           data-role-id="{{ $role->id }}"
                                           data-permission-id="{{ $permission->id }}"
                                           data-group-id="{{ $groupId }}"
                                           @checked($hasPermission) />
                                </td>
                            @endforeach
                        </tr>
                    @endforeach

                    @if($permsInGroup->isEmpty())
                        <tr>
                            <td colspan="{{ 1 + $roles->count() }}" class="text-center text-muted py-4">
                                В этой группе пока нет прав.
                            </td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        </section>
    @endforeach
</div>

{{-- Модал: создание / управление ролями --}}
<div class="modal fade" id="createRoleModal" tabindex="-1" aria-labelledby="createRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title" id="createRoleModalLabel">Управление ролями</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>

            <div class="modal-body">

                <form id="createRoleForm" class="d-flex align-items-center mb-3 gap-2">
                    @csrf
                    <input placeholder="Название роли" type="text" class="form-control" id="roleName" name="name" required style="width:auto;">
                    <button type="submit" class="btn btn-success">Создать роль</button>
                </form>

                <hr/>

                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="rolesTable">
                        <thead>
                        <tr>
                            <th>№</th>
                            <th>Название</th>
                            <th>Удаление</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($roles as $index => $role)
                            <tr data-role-id="{{ $role->id }}">
                                <td>{{ $index + 1 }}</td>
                                <td>
                                    {{ $role->label ?? $role->name }}
                                    @if(auth()->user()?->role?->name === 'superadmin' && !$role->is_visible)
                                        <span class="badge bg-warning text-dark ms-2">Невидимое</span>
                                    @endif
                                </td>
                                <td>
                                    @if(!$role->is_sistem)
                                        <button class="btn btn-danger btn-sm delete-role"
                                                data-role-id="{{ $role->id }}">
                                            Удалить
                                        </button>
                                    @else
                                        <small class="text-muted">Системная роль</small>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

            </div><!-- modal-body -->

        </div>
    </div>
</div>

@section('scripts')
    <script>
        $(function () {
            // чекбокс: единичное изменение права
            $(document).on('change', '.permission-checkbox', function () {
                const $cb = $(this);
                const roleId = $cb.data('role-id');
                const permissionId = $cb.data('permission-id');
                const isChecked = $cb.is(':checked');

                $.ajax({
                    url: "{{ route('admin.setting.rule.toggle') }}",
                    type: "POST",
                    data: {
                        _token: "{{ csrf_token() }}",
                        role_id: roleId,
                        permission_id: permissionId,
                        value: isChecked ? 'true' : 'false'
                    },
                    success: (response) => {
                        if (response.success) {
                            const $td = $cb.closest('td');
                            $td.addClass('table-success');
                            setTimeout(() => $td.removeClass('table-success'), 800);
                        } else {
                            alert('Ошибка при обновлении прав!');
                            $cb.prop('checked', !isChecked);
                        }
                    },
                    error: () => {
                        alert('Ошибка при соединении!');
                        $cb.prop('checked', !isChecked);
                    }
                });
            });

            // аккордион: кнопка в первом th сворачивает/разворачивает tbody её таблицы
            $(document).on('click', '.btn-disclosure', function () {
                const $btn = $(this);
                const $table = $btn.closest('table');
                const $tbody = $table.find('tbody').first();
                const expanded = $btn.attr('aria-expanded') === 'true';

                if (expanded) {
                    $tbody.addClass('d-none');
                    $btn.attr('aria-expanded', 'false');
                } else {
                    $tbody.removeClass('d-none');
                    $btn.attr('aria-expanded', 'true');
                }
            });

            // создание роли
            $('#createRoleForm').on('submit', function (e) {
                e.preventDefault();
                let formData = $(this).serialize();

                showConfirmDeleteModal(
                    "Создание роли",
                    "Вы уверены, что хотите создать новую роль?",
                    function () {
                        $.ajax({
                            url: "{{ route('admin.setting.role.create') }}",
                            type: "POST",
                            data: formData,
                            success: (response) => {
                                if (response.success) {
                                    showSuccessModal("Создание роли", "Роль успешно создана.", 0);
                                    location.reload();
                                } else {
                                    $('#errorModal').modal('show');
                                    $('#error-modal-message').text(response.message || 'Ошибка при создании роли!');
                                }
                            },
                            error: function(xhr) {
                                let json = xhr.responseJSON;
                                let message = 'Ошибка при соединении!';
                                if (json) {
                                    if (json.message) message = json.message;
                                    else if (json.errors) message = Object.values(json.errors).flat().join('\n');
                                }
                                $('#error-modal-message').text(message);
                                $('#errorModal').modal('show');
                            }
                        });
                    }
                );
            });

            // удаление роли
            $(document).on('click', '.delete-role', function () {
                let roleId = $(this).data('role-id');

                showConfirmDeleteModal(
                    "Удаление роли",
                    "Вы уверены, что хотите удалить роль?",
                    function () {
                        $.ajax({
                            url: "{{ route('admin.setting.role.delete') }}",
                            type: "DELETE",
                            data: {
                                _token: "{{ csrf_token() }}",
                                role_id: roleId
                            },
                            success: (response) => {
                                if (response.success) {
                                    showSuccessModal("Удаление роли", "Роль успешно удалена.", 0);
                                    location.reload();
                                } else {
                                    $('#errorModal').modal('show');
                                    $('#error-modal-message').text(response.message || 'Ошибка при удалении роли!');
                                }
                            },
                            error: () => {
                                $('#errorModal').modal('show');
                                $('#error-modal-message').text('Ошибка при соединении!');
                            }
                        });
                    }
                );
            });

            // логи
            showLogModal("{{ route('logs.data.rule') }}");
        });
    </script>
@endsection
