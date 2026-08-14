<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\PartnerContext;
use App\Services\Schedule\TrainerSalary\TrainerSalaryScheme;
use App\Services\Schedule\TrainerSalary\TrainerSalarySchemeResolver;
use App\Support\TrainerSalaryAccess;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTrainerSalaryDraftLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return TrainerSalaryAccess::canManageModule($this->user());
    }

    public function rules(): array
    {
        $base = [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'trainer_profile_id' => [
                'prohibited',
            ],
        ];

        $scheme = $this->resolvedScheme();
        if ($scheme === null) {
            return $base;
        }

        return array_merge($base, $scheme->draftRules());
    }

    public function attributes(): array
    {
        $attributes = [
            'year' => 'год',
            'month' => 'месяц',
        ];

        $scheme = $this->resolvedScheme();
        if ($scheme !== null) {
            $attributes = array_merge($attributes, $scheme->draftAttributes());
        }

        return $attributes;
    }

    public function messages(): array
    {
        $messages = [
            'year.integer' => 'Некорректный год.',
            'month.integer' => 'Некорректный месяц.',
        ];

        $scheme = $this->resolvedScheme();
        if ($scheme !== null) {
            $messages = array_merge($messages, $scheme->draftMessages());
        }

        return $messages;
    }

    /**
     * @return array<string, mixed>
     */
    public function draftPayload(): array
    {
        $data = [];
        $keys = $this->resolvedScheme()?->draftFieldKeys() ?? [];

        foreach ($keys as $key) {
            if ($this->exists($key)) {
                $data[$key] = $this->input($key);
            }
        }

        return $data;
    }

    private function resolvedScheme(): ?TrainerSalaryScheme
    {
        $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);
        if ($partnerId <= 0) {
            return null;
        }

        $year = $this->input('year');
        $month = $this->input('month');

        return app(TrainerSalarySchemeResolver::class)->schemeForPartnerPeriod(
            $partnerId,
            is_numeric($year) ? (int) $year : null,
            is_numeric($month) ? (int) $month : null,
        );
    }
}
