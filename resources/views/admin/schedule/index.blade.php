@extends('layouts.admin2')

@section('content')
    <div class="main-content schedule-section mt-3">
        @include('admin.schedule._schedule_section_tabs', ['activeTab' => $activeTab ?? 'journal'])

        <div class="tab-content">
            @if(($activeTab ?? 'journal') === 'journal')
                @include('admin.schedule.journal')
            @elseif(($activeTab ?? '') === 'trainer-workload')
                @include('admin.schedule.trainer_workload')
            @elseif(($activeTab ?? '') === 'trainer-salary')
                @include('admin.schedule.trainer_salary', [
                    'year' => $year ?? null,
                    'month' => $month ?? null,
                    'rows' => $rows ?? [],
                    'canManageTrainerSalary' => $canManageTrainerSalary ?? false,
                ])
            @elseif(($activeTab ?? '') === 'trainer-salary-sheets')
                @include('admin.schedule.trainer_salary_sheets', [
                    'year' => $year ?? null,
                    'month' => $month ?? null,
                    'latest_only' => $latest_only ?? false,
                    'sheets' => $sheets ?? [],
                    'latest_by_trainer' => $latest_by_trainer ?? [],
                ])
            @endif
        </div>
    </div>
@endsection

@push('scripts')
    @vite(['resources/css/schedule.css'])
    @if(($activeTab ?? 'journal') === 'journal')
        @include('partials.select2.generic-multiselect')
        <script>
            window.SCHEDULE_VISITED_STATUS_ID = @json($visitedStatusId ?? null);
        </script>
        @vite(['resources/js/schedule.js'])
        <script>
            (function () {
                function escapeHtml(value) {
                    return String(value ?? '')
                        .replace(/&/g, '&amp;')
                        .replace(/</g, '&lt;')
                        .replace(/>/g, '&gt;')
                        .replace(/"/g, '&quot;');
                }

                window.reloadScheduleStatusesTable = function () {
                    var tbody = document.getElementById('statuses-table-body');
                    if (!tbody) {
                        return;
                    }

                    fetch('/schedule/statuses', {
                        headers: { 'Accept': 'application/json' },
                    })
                        .then(function (resp) { return resp.json(); })
                        .then(function (data) {
                            if (!Array.isArray(data.statuses)) {
                                return;
                            }

                            tbody.innerHTML = '';
                            data.statuses.forEach(function (st) {
                                var sortOrder = (st.sort_order !== undefined && st.sort_order !== null)
                                    ? Number(st.sort_order)
                                    : 0;
                                var isSystem = !!st.is_system;
                                var tr = document.createElement('tr');

                                var nameHtml = escapeHtml(st.name);
                                if (isSystem) {
                                    nameHtml += ' <i class="fas fa-question-circle ms-1" data-bs-toggle="tooltip" title="Системный статус"></i>';
                                }

                                var iconHtml = '';
                                if (st.icon) {
                                    iconHtml = '<i class="' + escapeHtml(st.icon) + '" style="background-color:' + escapeHtml(st.color || '') + ';color:#000;padding:5px;border-radius:3px;"></i>';
                                }

                                var actionsHtml = '';
                                if (!isSystem) {
                                    actionsHtml =
                                        '<button type="button" class="btn btn-sm btn-success me-1" data-action="edit"'
                                        + ' data-id="' + st.id + '"'
                                        + ' data-name="' + escapeHtml(st.name) + '"'
                                        + ' data-icon="' + escapeHtml(st.icon || '') + '"'
                                        + ' data-color="' + escapeHtml(st.color || '') + '"'
                                        + ' data-sort-order="' + sortOrder + '">Изменить</button>'
                                        + '<button type="button" class="btn btn-sm btn-danger" data-action="delete" data-id="' + st.id + '">Удалить</button>';
                                }

                                tr.innerHTML =
                                    '<td>' + nameHtml + '</td>'
                                    + '<td class="status-sort-order text-center">' + sortOrder + '</td>'
                                    + '<td>' + iconHtml + '</td>'
                                    + '<td>' + actionsHtml + '</td>';

                                tbody.appendChild(tr);
                            });
                        })
                        .catch(function (err) { console.error(err); });
                };

                function bindSettingsButton() {
                    var btn = document.getElementById('btn-settings');
                    if (!btn) {
                        return;
                    }
                    var clone = btn.cloneNode(true);
                    btn.parentNode.replaceChild(clone, btn);
                    clone.addEventListener('click', function () {
                        bootstrap.Modal.getOrCreateInstance(document.getElementById('settingsModal')).show();
                    });
                }

                document.addEventListener('DOMContentLoaded', function () {
                    setTimeout(function () {
                        bindSettingsButton();

                        var tbody = document.getElementById('statuses-table-body');
                        if (tbody) {
                            tbody.addEventListener('click', function (e) {
                                var target = e.target.closest('[data-action]');
                                if (!target) {
                                    return;
                                }
                                if (target.dataset.action === 'edit') {
                                    document.getElementById('editStatusId').value = target.dataset.id;
                                    document.getElementById('editName').value = target.dataset.name || '';
                                    document.getElementById('editIcon').value = target.dataset.icon || '';
                                    document.getElementById('editColor').value = target.dataset.color || '#ffffff';
                                    var sortEl = document.getElementById('editSortOrder');
                                    if (sortEl) {
                                        sortEl.value = target.dataset.sortOrder || '0';
                                    }
                                    bootstrap.Modal.getOrCreateInstance(document.getElementById('editStatusModal')).show();
                                }
                            });
                        }
                    }, 0);
                });
            })();
        </script>
    @elseif(($activeTab ?? '') === 'trainer-workload')
        @vite(['resources/js/trainer-workload.js'])
    @elseif(($activeTab ?? '') === 'trainer-salary')
        @vite(['resources/js/trainer-salary.js'])
    @elseif(($activeTab ?? '') === 'trainer-salary-sheets')
        @vite(['resources/js/trainer-salary-sheets.js'])
    @endif
@endpush
