@php
    $saveTrainerId = (int) ($save_trainer_id ?? 0);
    $monthGroups = $month_groups ?? [];
    $canEditMonth = ($canManage ?? false) && $saveTrainerId > 0;
    $incrementFull = (string) ($premium_increment ?? '0.00');
    $incrementInt = (string) ($premium_increment_int ?? '0');
    $monthSettingsTitle = (string) ($month_settings_title ?? 'Настройки базовых значений');
@endphp
<div class="trainer-salary-kansas-month-settings"
     data-modal-title="{{ $monthSettingsTitle }}"
     @if($saveTrainerId > 0) data-save-trainer-id="{{ $saveTrainerId }}" @endif>
    <div class="cell-edit-section trainer-salary-kansas-x"
         @if($saveTrainerId > 0) data-save-trainer-id="{{ $saveTrainerId }}" @endif>
        <label class="cell-edit-section__label" for="trainer-salary-kansas-premium-increment">
            Базовая надбавка к премии
        </label>
        <div class="trainer-salary-kansas-x-field">
            <input type="number"
                   class="form-control trainer-salary-input text-end"
                   id="trainer-salary-kansas-premium-increment"
                   data-field="premium_increment"
                   data-kids-tooltip-hint
                   data-bs-toggle="tooltip"
                   data-bs-placement="top"
                   title="{{ $incrementFull }}"
                   @if($saveTrainerId > 0) data-save-trainer-id="{{ $saveTrainerId }}" @endif
                   @unless($canEditMonth) disabled @endunless
                   min="0"
                   step="0.01"
                   value="{{ $incrementInt }}">
            <div class="invalid-feedback d-none" data-error-for="premium_increment"></div>
        </div>
    </div>

    <div class="cell-edit-section cell-edit-section--last trainer-salary-kansas-month-groups">
        <div class="cell-edit-section__label">Баз. среднее по группам</div>
        @forelse($monthGroups as $group)
            @php
                $avgFull = (string) ($group['base_avg_students'] ?? '0.0');
                $avgInt = (string) ($group['base_avg_students_int'] ?? '0');
            @endphp
            <div class="trainer-salary-kansas-month-group"
                 data-team-id="{{ (int) $group['team_id'] }}">
                <label class="trainer-salary-kansas-month-group-title"
                       for="trainer-salary-kansas-base-avg-{{ (int) $group['team_id'] }}">{{ $group['team_title'] }}</label>
                <div class="trainer-salary-kansas-month-group-field">
                    <input type="number"
                           class="form-control trainer-salary-input text-end"
                           id="trainer-salary-kansas-base-avg-{{ (int) $group['team_id'] }}"
                           data-field="base_avg_students"
                           data-kids-tooltip-hint
                           data-bs-toggle="tooltip"
                           data-bs-placement="top"
                           title="{{ $avgFull }}"
                           min="0"
                           max="999.9"
                           step="0.1"
                           @unless($canEditMonth) disabled @endunless
                           value="{{ $avgInt }}">
                    <div class="invalid-feedback d-none" data-error-for="base_avg_students"></div>
                    <div class="invalid-feedback d-none" data-error-for="team_id"></div>
                </div>
            </div>
        @empty
            <p class="small text-muted mb-0">Нет активных групп</p>
        @endforelse
    </div>
    @if($saveTrainerId <= 0)
        <p class="small text-muted mb-0 mt-2">Нет активных тренеров — сохранение недоступно.</p>
    @endif
</div>
