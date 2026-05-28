@can('users.other.update')
    <div class="col-12 js-user-health-wrap d-none">
        <div class="mb-2 mt-1">
            <span class="form-label d-block mb-2">Сведения об ученике</span>
        </div>
        <div class="row g-3">
            <div class="col-12">
                <label for="edit-is_individual_traits" class="form-label">
                    Индивидуальные особенности воспитанника (физические, психологические)
                </label>
                <select id="edit-is_individual_traits" name="is_individual_traits" class="form-select js-user-health-field">
                    <option value="">Не указано</option>
                    <option value="1">Да</option>
                    <option value="0">Нет</option>
                </select>
            </div>
            <div class="col-12">
                <label for="edit-is_on_medical_register" class="form-label">
                    Состоит на учёте у медицинских специалистов
                </label>
                <select id="edit-is_on_medical_register" name="is_on_medical_register" class="form-select js-user-health-field">
                    <option value="">Не указано</option>
                    <option value="1">Да</option>
                    <option value="0">Нет</option>
                </select>
            </div>
            <div class="col-12">
                <label for="edit-is_with_disability" class="form-label">Наличие инвалидности</label>
                <select id="edit-is_with_disability" name="is_with_disability" class="form-select js-user-health-field">
                    <option value="">Не указано</option>
                    <option value="1">Да</option>
                    <option value="0">Нет</option>
                </select>
            </div>
        </div>
    </div>
@endcan
