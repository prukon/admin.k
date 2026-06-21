<?php

namespace App\Http\Requests\Admin;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetScheduleCellContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('context_team_id') && $this->input('context_team_id') === '') {
            $this->merge(['context_team_id' => null]);
        }
    }

    public function rules(): array
    {
        $partnerId = (int) app(PartnerContext::class)->partnerId();

        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'context_team_id' => [
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
            'user_id' => 'ученик',
            'date' => 'дата',
            'context_team_id' => 'группа контекста',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Не указан ученик.',
            'date.required' => 'Не указана дата.',
            'date.date_format' => 'Некорректный формат даты.',
            'context_team_id.integer' => 'Некорректный формат группы.',
            'context_team_id.exists' => 'Выберите группу из списка.',
        ];
    }
}
