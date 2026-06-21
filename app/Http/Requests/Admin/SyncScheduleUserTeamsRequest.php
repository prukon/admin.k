<?php

namespace App\Http\Requests\Admin;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncScheduleUserTeamsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('team_ids')) {
            $ids = $this->input('team_ids');
            if (! is_array($ids)) {
                $ids = [];
            }

            $this->merge([
                'team_ids' => array_values(array_filter(array_map('intval', $ids), fn (int $id) => $id > 0)),
            ]);
        } else {
            $this->merge(['team_ids' => []]);
        }
    }

    public function rules(): array
    {
        $partnerId = (int) app(PartnerContext::class)->partnerId();

        return [
            'team_ids' => ['nullable', 'array'],
            'team_ids.*' => [
                'integer',
                'min:1',
                Rule::exists('teams', 'id')->where(
                    fn ($query) => $query->where('partner_id', $partnerId)
                ),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'team_ids' => 'группы',
            'team_ids.*' => 'группа',
        ];
    }

    public function messages(): array
    {
        return [
            'team_ids.array' => 'Некорректный формат списка групп.',
            'team_ids.*.integer' => 'Некорректный формат группы.',
            'team_ids.*.min' => 'Некорректный формат группы.',
            'team_ids.*.exists' => 'Выберите группы из списка.',
        ];
    }
}
