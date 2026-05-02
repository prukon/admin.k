<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\UserLessonPackage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class UpdateUserLessonPackageAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null || ! $user->can('lessonPackages.view')) {
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
    }

    public function rules(): array
    {
        $rules = [
            'fee_amount' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
        ];

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

            $feeIncoming = round((float) $this->input('fee_amount'), 2);
            $feeCurrent = round((float) $assignment->fee_amount, 2);
            $feeChanging = abs($feeIncoming - $feeCurrent) > 0.00001;

            if ($assignment->effective_is_paid && $feeChanging) {
                $allowsViaUnpaidTransition = $this->user()?->can('lessonPackages.manualPaid.manage')
                    && $this->input('payment_status') === 'unpaid'
                    && $assignment->effective_is_paid;

                if (! $allowsViaUnpaidTransition) {
                    $v->errors()->add('fee_amount', 'Нельзя менять сумму у оплаченного абонемента.');
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
