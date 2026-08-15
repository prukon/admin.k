<div class="modal fade" id="trainerSalaryKansasMonthSettingsModal" tabindex="-1" aria-labelledby="trainerSalaryKansasMonthSettingsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content schedule-modal-content cell-edit-modal">
            <div class="modal-header">
                <h5 class="modal-title" id="trainerSalaryKansasMonthSettingsModalLabel">{{ $month_settings_title ?? ($draft_view_data['month_settings_title'] ?? 'Настройки базовых значений') }}</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
            </div>
            <div class="modal-body" id="trainer-salary-kansas-month-settings-host">
                @include('admin.schedule.trainer-salary.kansas._month_settings_body', array_merge([
                    'canManage' => $canManage ?? false,
                ], $draft_view_data ?? []))
            </div>
            <div class="modal-footer cell-edit-modal__footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Закрыть</button>
            </div>
        </div>
    </div>
</div>
