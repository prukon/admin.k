<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTrainerSalaryDraftLineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('schedule.trainerSalary.manage') ?? false;
    }

    public function rules(): array
    {
        $partnerId = (int) (app(PartnerContext::class)->partnerId() ?? 0);

        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'base_salary' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'rate_per_training' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'bonuses' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'deductions' => ['sometimes', 'required', 'numeric', 'min:0', 'max:99999999'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'trainer_profile_id' => [
                'prohibited',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'year' => 'год',
            'month' => 'месяц',
            'base_salary' => 'оклад',
            'rate_per_training' => 'ставка за тренировку',
            'bonuses' => 'бонусы',
            'deductions' => 'вычеты',
            'comment' => 'комментарий',
        ];
    }

    public function messages(): array
    {
        return [
            'base_salary.numeric' => 'Оклад должен быть числом (рубли, можно с копейками).',
            'base_salary.min' => 'Оклад не может быть отрицательным.',
            'rate_per_training.numeric' => 'Ставка должна быть числом (рубли, можно с копейками).',
            'rate_per_training.min' => 'Ставка не может быть отрицательной.',
            'bonuses.numeric' => 'Бонусы должны быть числом (рубли, можно с копейками).',
            'bonuses.min' => 'Бонусы не могут быть отрицательными.',
            'deductions.numeric' => 'Вычеты должны быть числом (рубли, можно с копейками).',
            'deductions.min' => 'Вычеты не могут быть отрицательными.',
            'comment.max' => 'Комментарий слишком длинный (максимум :max символов).',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function draftPayload(): array
    {
        $data = [];

        foreach (['base_salary', 'rate_per_training', 'bonuses', 'deductions', 'comment'] as $key) {
            if ($this->exists($key)) {
                $data[$key] = $this->input($key);
            }
        }

        return $data;
    }
}
