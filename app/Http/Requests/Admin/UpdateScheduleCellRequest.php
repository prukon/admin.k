<?php

namespace App\Http\Requests\Admin;

use App\Models\Status;
use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateScheduleCellRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $partnerId = (int) app(PartnerContext::class)->partnerId();

        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'status_id' => ['required', 'exists:statuses,id'],
            'description' => ['nullable', 'string', 'max:2000'],
            'trainer_profile_id' => [
                'nullable',
                'integer',
                Rule::exists('trainer_profiles', 'id')->where(
                    fn ($query) => $query->where('partner_id', $partnerId)
                ),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $raw = $this->input('trainer_profile_id');

        if ($raw === '' || $raw === 'none' || $raw === '0') {
            $this->merge(['trainer_profile_id' => null]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $visitedId = Status::globalVisitedId();
            $statusId = (int) $this->input('status_id');

            if ($visitedId === null || $statusId !== $visitedId) {
                return;
            }

            $trainerId = $this->input('trainer_profile_id');
            if ($trainerId !== null && $trainerId !== '') {
                return;
            }

            // «Без тренера» допустимо для «Посетил»
        });
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'date' => 'дата',
            'status_id' => 'статус',
            'description' => 'комментарий',
            'trainer_profile_id' => 'тренер',
        ];
    }

    public function messages(): array
    {
        return [
            'status_id.required' => 'Выберите статус.',
            'status_id.exists' => 'Выбранный статус не найден.',
            'trainer_profile_id.exists' => 'Выбранный тренер не найден.',
        ];
    }
}
