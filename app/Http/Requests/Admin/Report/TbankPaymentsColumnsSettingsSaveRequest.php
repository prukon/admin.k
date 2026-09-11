<?php

namespace App\Http\Requests\Admin\Report;

use App\Http\Requests\Admin\ColumnsSettingsWithPageLengthSaveRequest;

class TbankPaymentsColumnsSettingsSaveRequest extends ColumnsSettingsWithPageLengthSaveRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'view' => ['nullable', 'string', 'in:'.implode(',', TbankPaymentsReportFilterRequest::VIEWS)],
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'view' => 'Вид',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'view.in' => 'Поле «:attribute» содержит недопустимое значение.',
        ]);
    }

    public function view(): string
    {
        $data = $this->validated();
        $view = $data['view'] ?? null;
        if ($view === 'days' || $view === 'months') {
            return $view;
        }

        return 'payments';
    }
}
