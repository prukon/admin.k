<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Services\PartnerContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceScheduleJournalFlexibleAbonementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $partnerId = (int) app(PartnerContext::class)->partnerId();

        return [
            'user_lesson_package_id' => [
                'required',
                'integer',
                Rule::exists('user_lesson_packages', 'id'),
            ],
            'team_id' => [
                'required',
                'integer',
                Rule::exists('teams', 'id')->where(
                    fn ($q) => $q->where('partner_id', $partnerId)->whereNull('deleted_at')
                ),
            ],
            'occurrence_date' => ['required', 'date_format:Y-m-d'],
            'lesson_occurrence_status_id' => [
                'required',
                'integer',
                Rule::exists('lesson_occurrence_statuses', 'id')->where(
                    fn ($query) => $query
                        ->where('partner_id', $partnerId)
                        ->where('is_active', true)
                ),
            ],
            'comment' => ['nullable', 'string', 'max:2000'],
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

        if ($this->filled('description') && ! $this->filled('comment')) {
            $this->merge(['comment' => $this->input('description')]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $ulpId = (int) $this->input('user_lesson_package_id');
            $date = (string) $this->input('occurrence_date');
            if ($ulpId <= 0 || $date === '') {
                return;
            }

            /** @var \App\Models\UserLessonPackage|null $ulp */
            $ulp = \App\Models\UserLessonPackage::query()->find($ulpId);
            if (! $ulp || ! $ulp->isFromSettingPrices() || $ulp->billing_month === null) {
                return;
            }

            try {
                app(\App\Services\UserLessonPackageCalendarPeriodService::class)
                    ->assertAnchorInsideBillingMonth(
                        $ulp,
                        \Carbon\CarbonImmutable::createFromFormat('Y-m-d', $date)->startOfDay()
                    );
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add('occurrence_date', $e->getMessage());
            }
        });
    }

    public function attributes(): array
    {
        return [
            'user_lesson_package_id' => 'абонемент',
            'team_id' => 'группа',
            'occurrence_date' => 'дата занятия',
            'lesson_occurrence_status_id' => 'статус',
            'comment' => 'комментарий',
            'trainer_profile_id' => 'тренер',
        ];
    }

    public function messages(): array
    {
        return [
            'user_lesson_package_id.required' => 'Выберите абонемент.',
            'user_lesson_package_id.exists' => 'Абонемент не найден.',
            'team_id.required' => 'Выберите группу.',
            'team_id.exists' => 'Группа не найдена.',
            'occurrence_date.required' => 'Укажите дату занятия.',
            'occurrence_date.date_format' => 'Некорректный формат даты занятия.',
            'lesson_occurrence_status_id.required' => 'Выберите статус.',
            'lesson_occurrence_status_id.exists' => 'Выбранный статус не найден или неактивен.',
            'trainer_profile_id.exists' => 'Выбранный тренер не найден.',
        ];
    }
}
