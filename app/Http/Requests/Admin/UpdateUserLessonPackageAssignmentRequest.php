<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\UserLessonPackage;
use App\Models\UserTeamScheduleSlot;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateUserLessonPackageAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null
            || ! $user->can('lessonPackages.view')
            || ! $user->can('setPrices.packageAssignments.view')
        ) {
            return false;
        }

        $assignment = $this->assignmentFromRoute();
        if (! $assignment instanceof UserLessonPackage) {
            return false;
        }

        $partnerId = (int) (app('current_partner')->id ?? 0);

        return $assignment->user !== null
            && (int) $assignment->user->partner_id === $partnerId;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('payment_status') && $this->input('payment_status') === '') {
            $this->merge(['payment_status' => null]);
        }

        if ($this->has('ends_at') && $this->input('ends_at') === '') {
            $this->merge(['ends_at' => null]);
        }
    }

    public function rules(): array
    {
        $assignment = $this->assignmentFromRoute();
        $periodSet = $assignment instanceof UserLessonPackage
            && $assignment->starts_at !== null
            && $assignment->ends_at !== null;

        $rules = [
            'fee_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
        ];

        if ($periodSet) {
            $rules['ends_at'] = ['required', 'date', 'date_format:Y-m-d'];
        } else {
            $rules['ends_at'] = ['prohibited'];
        }

        if ($this->user()?->can('lessonPackages.manualPaid.manage')) {
            $rules['payment_status'] = ['nullable', Rule::in(['paid', 'unpaid'])];
            $rules['payment_comment'] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'fee_amount' => 'стоимость для ученика',
            'ends_at' => 'дата окончания',
            'payment_status' => 'статус оплаты',
            'payment_comment' => 'комментарий к изменению статуса',
        ];
    }

    public function messages(): array
    {
        return [
            'fee_amount.required' => 'Укажите стоимость.',
            'fee_amount.numeric' => 'Стоимость должна быть числом.',
            'fee_amount.min' => 'Стоимость не может быть отрицательной.',
            'fee_amount.max' => 'Слишком большая сумма.',
            'ends_at.required' => 'Укажите дату окончания.',
            'ends_at.date' => 'Укажите корректную дату окончания.',
            'ends_at.date_format' => 'Укажите корректную дату окончания.',
            'ends_at.prohibited' => 'Период ещё не задан — дату окончания можно менять после привязки к календарю.',
            'payment_status.in' => 'Выберите корректный статус оплаты.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $assignment = $this->assignmentFromRoute();
            if (! $assignment instanceof UserLessonPackage) {
                return;
            }

            if ($this->user()?->can('lessonPackages.manualPaid.manage')) {
                $status = $this->input('payment_status');
                if ($status !== null && $status !== '') {
                    $desiredPaid = $status === 'paid';
                    if ($desiredPaid !== $assignment->effective_is_paid) {
                        $comment = trim((string) $this->input('payment_comment', ''));
                        if (mb_strlen($comment) < 3) {
                            $v->errors()->add(
                                'payment_comment',
                                'Укажите комментарий к изменению статуса оплаты (минимум 3 символа).'
                            );
                        }
                    }
                }
            } elseif ($this->has('payment_status') || $this->has('payment_comment')) {
                $v->errors()->add('payment_status', 'Недостаточно прав для изменения статуса оплаты.');
            }

            $feeIncomingCents = Money::toCents($this->input('fee_amount'));
            $feeCurrentCents = (int) ($assignment->fee_amount_cents ?? 0);
            $feeChanging = $feeIncomingCents !== $feeCurrentCents;

            if ($assignment->effective_is_paid && $feeChanging) {
                $allowsViaUnpaidTransition = $this->user()?->can('lessonPackages.manualPaid.manage')
                    && $this->input('payment_status') === 'unpaid'
                    && $assignment->effective_is_paid;

                if (! $allowsViaUnpaidTransition) {
                    $v->errors()->add('fee_amount', 'Нельзя менять сумму у оплаченного абонемента.');
                }
            }

            $periodSet = $assignment->starts_at !== null && $assignment->ends_at !== null;
            if (! $periodSet || $v->errors()->has('ends_at')) {
                return;
            }

            $endsRaw = $this->input('ends_at');
            if (! is_string($endsRaw) || $endsRaw === '') {
                return;
            }

            try {
                $newEnd = CarbonImmutable::createFromFormat('Y-m-d', $endsRaw)->startOfDay();
            } catch (\Throwable) {
                return;
            }

            $start = CarbonImmutable::parse($assignment->starts_at->format('Y-m-d'))->startOfDay();
            if ($newEnd->lt($start)) {
                $v->errors()->add('ends_at', 'Дата окончания не может быть раньше даты начала.');

                return;
            }

            $lastLessonRaw = UserTeamScheduleSlot::query()
                ->where('user_lesson_package_id', (int) $assignment->id)
                ->max('starts_at');

            if ($lastLessonRaw !== null && $lastLessonRaw !== '') {
                $lastLesson = CarbonImmutable::parse((string) $lastLessonRaw)->startOfDay();
                if ($newEnd->lt($lastLesson)) {
                    $v->errors()->add(
                        'ends_at',
                        'Дата окончания не может быть раньше последнего занятия в календаре ('.$lastLesson->format('d.m.Y').').'
                    );
                }
            }
        });
    }

    private function assignmentFromRoute(): ?UserLessonPackage
    {
        $route = $this->route('assignment');
        if ($route instanceof UserLessonPackage) {
            return $route->loadMissing('user:id,name,lastname,partner_id');
        }

        return null;
    }
}
