<?php

namespace App\Http\Requests\Admin;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetScheduleUserGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('team_id') && $this->input('team_id') === '') {
            $this->merge(['team_id' => null]);
        }
    }

    public function rules(): array
    {
        $partnerId = (int) app(PartnerContext::class)->partnerId();

        return [
            'team_id' => [
                'nullable',
                'integer',
                Rule::exists('teams', 'id')->where(
                    fn ($query) => $query->where('partner_id', $partnerId)
                ),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'team_id' => 'группа',
        ];
    }

    public function messages(): array
    {
        return [
            'team_id.integer' => 'Некорректный формат группы.',
            'team_id.exists' => 'Выберите группу из списка.',
        ];
    }
}
