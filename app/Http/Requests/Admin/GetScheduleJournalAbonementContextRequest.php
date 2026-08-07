<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GetScheduleJournalAbonementContextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $partnerId = (int) app(PartnerContext::class)->partnerId();

        return [
            'context_team_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('teams', 'id')->where(
                    fn ($q) => $q->where('partner_id', $partnerId)->whereNull('deleted_at')
                ),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'context_team_id' => 'группа',
        ];
    }

    public function messages(): array
    {
        return [
            'context_team_id.exists' => 'Группа не найдена.',
        ];
    }
}
