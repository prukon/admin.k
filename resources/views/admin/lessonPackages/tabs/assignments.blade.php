<div class="tab-content">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 pt-3">
        <h4 class="mb-0">Назначение абонементов</h4>
    </div>

    <hr>

    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @can('lessonPackages.manage')
        <div class="card mb-3">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.lesson-packages.assignments.store') }}" novalidate>
                    @csrf

                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-4">
                            <label class="form-label">Ученик *</label>
                            <select name="user_id"
                                    id="ulp_user_id"
                                    class="form-select @error('user_id') is-invalid @enderror"
                                    required>
                                <option value="">Выберите ученика</option>
                            </select>
                            @error('user_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-4">
                            <label class="form-label">Абонемент *</label>
                            <select name="lesson_package_id"
                                    id="ulp_lesson_package_id"
                                    class="form-select @error('lesson_package_id') is-invalid @enderror"
                                    required>
                                <option value="">Выберите абонемент</option>
                                @foreach ($packagesList as $p)
                                    <option value="{{ $p->id }}"
                                            data-schedule-type="{{ $p->schedule_type }}"
                                            data-duration-days="{{ $p->duration_days }}"
                                            data-lessons-count="{{ $p->lessons_count }}"
                                        {{ (int)old('lesson_package_id') === (int)$p->id ? 'selected' : '' }}>
                                        {{ $p->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('lesson_package_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-2">
                            <label class="form-label">Дата начала *</label>
                            <input type="date"
                                   name="starts_at"
                                   value="{{ old('starts_at', now()->format('Y-m-d')) }}"
                                   class="form-control @error('starts_at') is-invalid @enderror"
                                   required>
                            @error('starts_at')
                            <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12 col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                Назначить
                            </button>
                        </div>
                    </div>

                    <div class="mt-3" id="ulp-flexible-slots">
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div class="text-muted">
                                Слоты назначения (только для гибких абонементов). Можно оставить пустым.
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="ulp-add-slot">
                                Добавить слот
                            </button>
                        </div>

                        @error('time_slots')
                        <div class="text-danger mt-2">{{ $message }}</div>
                        @enderror

                        @php
                            $oldSlots = old('time_slots');
                            $slots = is_array($oldSlots) ? $oldSlots : [];
                        @endphp

                        <div class="table-responsive mt-2">
                            <table class="table table-bordered align-middle" id="ulp-slots-table">
                                <thead>
                                <tr>
                                    <th style="width: 140px;">День</th>
                                    <th style="width: 160px;">Начало</th>
                                    <th style="width: 160px;">Окончание</th>
                                    <th style="width: 80px;"></th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach ($slots as $i => $slot)
                                    <tr>
                                        <td>
                                            <select name="time_slots[{{ $i }}][weekday]"
                                                    class="form-select @error("time_slots.$i.weekday") is-invalid @enderror">
                                                <option value="">—</option>
                                                @foreach ($weekdays as $k => $label)
                                                    <option value="{{ $k }}" {{ (int)($slot['weekday'] ?? 0) === (int)$k ? 'selected' : '' }}>
                                                        {{ $label }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            @error("time_slots.$i.weekday")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td>
                                            <input type="time"
                                                   name="time_slots[{{ $i }}][time_start]"
                                                   value="{{ $slot['time_start'] ?? '' }}"
                                                   class="form-control @error("time_slots.$i.time_start") is-invalid @enderror">
                                            @error("time_slots.$i.time_start")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td>
                                            <input type="time"
                                                   name="time_slots[{{ $i }}][time_end]"
                                                   value="{{ $slot['time_end'] ?? '' }}"
                                                   class="form-control @error("time_slots.$i.time_end") is-invalid @enderror">
                                            @error("time_slots.$i.time_end")
                                            <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-outline-danger btn-sm ulp-remove-slot">×</button>
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle w-100">
            <thead>
            <tr>
                <th>#</th>
                <th>Ученик</th>
                <th>Абонемент</th>
                <th>Период</th>
                <th>Остаток</th>
                <th>Тип</th>
            </tr>
            </thead>
            <tbody>
            @forelse ($assignments as $a)
                <tr>
                    <td class="text-center">{{ $a->id }}</td>
                    <td>{{ trim(($a->user->lastname ?? '').' '.($a->user->name ?? '')) }}</td>
                    <td>{{ $a->lessonPackage->name ?? '—' }}</td>
                    <td class="text-center">
                        {{ $a->starts_at?->locale('ru')->isoFormat('D MMMM YYYY') }} - {{ $a->ends_at?->locale('ru')->isoFormat('D MMMM YYYY') }}
                    </td>
                    <td class="text-center">{{ $a->lessons_remaining }} / {{ $a->lessons_total }}</td>
                    <td class="text-center">
                        @php $t = (string)($a->lessonPackage->schedule_type ?? ''); @endphp
                        @if ($t === 'fixed') фикс @elseif($t === 'flexible') гибк @else без @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center text-muted">Назначений пока нет.</td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-center">
        {{ $assignments->links() }}
    </div>
</div>

@section('scripts')
    @parent
    <script>
        (function () {
            const scheduleSelect = document.getElementById('ulp_lesson_package_id');
            const flexibleBlock = document.getElementById('ulp-flexible-slots');
            const addSlotBtn = document.getElementById('ulp-add-slot');
            const tbody = document.querySelector('#ulp-slots-table tbody');

            function toggleFlexible() {
                const opt = scheduleSelect.options[scheduleSelect.selectedIndex];
                const type = opt ? (opt.getAttribute('data-schedule-type') || '') : '';
                flexibleBlock.style.display = (type === 'flexible') ? '' : 'none';
            }

            function nextIndex() {
                return tbody.querySelectorAll('tr').length;
            }

            function buildSlotRow(i) {
                const weekdays = @json($weekdays);
                const weekdayOptions = ['<option value="">—</option>'].concat(Object.keys(weekdays).map(function (k) {
                    return '<option value="' + k + '">' + weekdays[k] + '</option>';
                })).join('');

                const tr = document.createElement('tr');
                tr.innerHTML =
                    '<td>' +
                    '  <select name="time_slots[' + i + '][weekday]" class="form-select">' + weekdayOptions + '</select>' +
                    '</td>' +
                    '<td>' +
                    '  <input type="time" name="time_slots[' + i + '][time_start]" class="form-control">' +
                    '</td>' +
                    '<td>' +
                    '  <input type="time" name="time_slots[' + i + '][time_end]" class="form-control">' +
                    '</td>' +
                    '<td class="text-center">' +
                    '  <button type="button" class="btn btn-outline-danger btn-sm ulp-remove-slot">×</button>' +
                    '</td>';
                return tr;
            }

            addSlotBtn?.addEventListener('click', function () {
                const i = nextIndex();
                tbody.appendChild(buildSlotRow(i));
            });

            tbody?.addEventListener('click', function (e) {
                const btn = e.target.closest('.ulp-remove-slot');
                if (!btn) return;
                btn.closest('tr')?.remove();
            });

            scheduleSelect?.addEventListener('change', toggleFlexible);
            toggleFlexible();
        })();
    </script>

    <script>
        // Select2 для выбора ученика (если select2 подключён в layout)
        $(document).ready(function () {
            if (!$.fn.select2) return;

            $('#ulp_user_id').select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: 'Выберите ученика',
                allowClear: true,
                ajax: {
                    url: @json(route('admin.lesson-packages.assignments.users-search')),
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return { q: params.term };
                    },
                    processResults: function (data) {
                        return data;
                    }
                }
            });

            const oldUserId = @json(old('user_id'));
            if (oldUserId) {
                const option = new Option('Выбранный ученик', oldUserId, true, true);
                $('#ulp_user_id').append(option).trigger('change');
            }
        });
    </script>
@endsection

