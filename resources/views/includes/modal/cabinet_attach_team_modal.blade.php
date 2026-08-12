{{-- Модалка добавления группы из ЛК. Рендерить вне сайдбара (overflow/z-index). --}}
@can('account.user.team.update')
    @if(!empty($cabinetTeamAttach) && is_array($cabinetTeamAttach))
        <div class="modal fade" id="cabinetAttachTeamModal" tabindex="-1" aria-labelledby="cabinetAttachTeamModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="cabinetAttachTeamModalLabel">Добавить группу</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
                    </div>
                    <form id="cabinetAttachTeamForm" method="post" action="{{ route('cabinet.teams.attach') }}" novalidate>
                        @csrf
                        <div class="modal-body">
                            <div class="mb-3">
                                <div class="form-label text-muted small mb-0">ФИО ученика</div>
                                <div class="fw-semibold">{{ $cabinetTeamAttach['student_full_name'] }}</div>
                            </div>
                            <div class="mb-3">
                                <div class="form-label text-muted small mb-0">Текущая группа</div>
                                <div>{{ $cabinetTeamAttach['current_teams_label'] }}</div>
                            </div>
                            <div class="mb-3">
                                <div class="form-label text-muted small mb-0">Объект</div>
                                <div>{{ $cabinetTeamAttach['locations_label'] }}</div>
                            </div>
                            <div class="mb-0">
                                <label class="form-label" for="cabinetAttachTeamSelect">Новая группа</label>
                                <select class="form-select" id="cabinetAttachTeamSelect" name="team_id" required>
                                    <option value="">Выберите группу</option>
                                    @php
                                        $locationGroups = $cabinetTeamAttach['available_by_location'] ?? [];
                                        $useOptgroups = count($locationGroups) > 1;
                                    @endphp
                                    @foreach($locationGroups as $locationGroup)
                                        @if($useOptgroups)
                                            <optgroup label="{{ $locationGroup['location_name'] }}">
                                                @foreach($locationGroup['teams'] as $teamOption)
                                                    <option value="{{ $teamOption['id'] }}">{{ $teamOption['title'] }}</option>
                                                @endforeach
                                            </optgroup>
                                        @else
                                            @foreach($locationGroup['teams'] as $teamOption)
                                                <option value="{{ $teamOption['id'] }}">{{ $teamOption['title'] }}</option>
                                            @endforeach
                                        @endif
                                    @endforeach
                                </select>
                                <div class="invalid-feedback d-block" data-error-for="team_id"></div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                            <button type="submit" class="btn btn-primary" id="cabinetAttachTeamSubmit">Добавить</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            (function () {
                const form = document.getElementById('cabinetAttachTeamForm');
                if (!form) {
                    return;
                }

                const select = document.getElementById('cabinetAttachTeamSelect');
                const submitBtn = document.getElementById('cabinetAttachTeamSubmit');
                const errorBox = form.querySelector('[data-error-for="team_id"]');
                const modalEl = document.getElementById('cabinetAttachTeamModal');

                function clearFieldError() {
                    if (errorBox) {
                        errorBox.textContent = '';
                    }
                    if (select) {
                        select.classList.remove('is-invalid');
                    }
                }

                function showFieldError(message) {
                    if (errorBox) {
                        errorBox.textContent = message || '';
                    }
                    if (select) {
                        select.classList.add('is-invalid');
                    }
                }

                if (modalEl) {
                    modalEl.addEventListener('hidden.bs.modal', function () {
                        clearFieldError();
                        form.reset();
                        if (submitBtn) {
                            submitBtn.disabled = false;
                        }
                    });
                }

                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    clearFieldError();

                    const teamId = select ? String(select.value || '').trim() : '';
                    if (!teamId) {
                        showFieldError('Выберите группу.');
                        return;
                    }

                    if (submitBtn) {
                        submitBtn.disabled = true;
                    }

                    const tokenMeta = document.querySelector('meta[name="csrf-token"]');
                    const csrf = tokenMeta ? tokenMeta.getAttribute('content') : '';

                    fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({
                            team_id: parseInt(teamId, 10),
                            _token: csrf
                        })
                    })
                        .then(function (response) {
                            return response.json().then(function (payload) {
                                return { ok: response.ok, status: response.status, payload: payload || {} };
                            }).catch(function () {
                                return { ok: response.ok, status: response.status, payload: {} };
                            });
                        })
                        .then(function (result) {
                            if (result.ok) {
                                window.location.reload();
                                return;
                            }

                            const errors = result.payload.errors || {};
                            const teamErrors = errors.team_id;
                            const message = (Array.isArray(teamErrors) && teamErrors[0])
                                ? teamErrors[0]
                                : (result.payload.message || 'Не удалось добавить группу.');
                            showFieldError(message);

                            if (submitBtn) {
                                submitBtn.disabled = false;
                            }
                        })
                        .catch(function () {
                            showFieldError('Не удалось добавить группу. Попробуйте ещё раз.');
                            if (submitBtn) {
                                submitBtn.disabled = false;
                            }
                        });
                });
            })();
        </script>
    @endif
@endcan
