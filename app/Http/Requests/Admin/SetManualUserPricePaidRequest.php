<?php

namespace App\Http\Requests\Admin;

use App\Models\UserPrice;
use App\Support\LessonPackagePostpayPermission;
use App\Support\LessonPackageTypePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SetManualUserPricePaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $payload = $this->all();
        if ($payload === [] && $this->getContent() !== '') {
            $decoded = json_decode($this->getContent(), true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if (array_key_exists('lesson_package_id', $payload)) {
            $pkg = $payload['lesson_package_id'];
            if ($pkg === '' || $pkg === false) {
                $payload['lesson_package_id'] = null;
            }
        }

        if (array_key_exists('price', $payload) && $payload['price'] === '') {
            unset($payload['price']);
        }

        $this->replace($payload);
    }

    public function rules(): array
    {
        $partnerId = (int) (app('current_partner')->id ?? 0);

        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'team_id' => ['required', 'integer', 'min:1'],
            'selectedDate' => ['required', 'string', 'max:255'],
            'mode' => ['required', Rule::in(['paid', 'unpaid'])],
            'comment' => ['required', 'string', 'min:3', 'max:5000'],
            'lesson_package_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('lesson_packages', 'id')->where(
                    fn ($q) => $q->where('partner_id', $partnerId)
                ),
            ],
            'price' => ['nullable', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            if (! array_key_exists('lesson_package_id', $this->all())) {
                return;
            }

            $packageId = $this->input('lesson_package_id');
            if ($packageId === null || $packageId === '') {
                return;
            }

            $userId = (int) $this->input('user_id');
            $teamId = (int) $this->input('team_id');
            $monthDate = LessonPackagePostpayPermission::monthStringToDate((string) $this->input('selectedDate', ''));

            $previous = null;
            if ($userId > 0 && $teamId > 0 && $monthDate !== '') {
                $existing = UserPrice::query()
                    ->where('user_id', $userId)
                    ->where('team_id', $teamId)
                    ->whereDate('new_month', $monthDate)
                    ->value('lesson_package_id');
                $previous = $existing !== null ? (int) $existing : null;
            }

            LessonPackageTypePermission::rejectUnauthorizedPackageId(
                $v,
                $this->user(),
                (int) $packageId,
                'lesson_package_id',
                $previous,
            );
        });
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'ученик',
            'team_id' => 'группа',
            'selectedDate' => 'месяц',
            'mode' => 'статус оплаты',
            'comment' => 'комментарий',
            'lesson_package_id' => 'абонемент',
            'price' => 'цена',
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Не указан ученик.',
            'user_id.integer' => 'Некорректный ученик.',
            'user_id.min' => 'Некорректный ученик.',
            'team_id.required' => 'Выберите группу для изменения статуса оплаты.',
            'team_id.integer' => 'Некорректная группа.',
            'team_id.min' => 'Некорректная группа.',
            'selectedDate.required' => 'Укажите месяц.',
            'mode.required' => 'Укажите статус оплаты.',
            'mode.in' => 'Некорректный режим ручной отметки оплаты.',
            'comment.required' => 'Укажите комментарий к ручному изменению.',
            'comment.min' => 'Комментарий должен содержать не менее :min символов.',
            'comment.max' => 'Комментарий слишком длинный.',
            'lesson_package_id.integer' => 'Некорректный абонемент.',
            'lesson_package_id.min' => 'Некорректный абонемент.',
            'lesson_package_id.exists' => 'Выбранный абонемент не найден или недоступен.',
            'price.numeric' => 'Цена должна быть числом.',
            'price.min' => 'Цена не может быть отрицательной.',
            'price.max' => 'Цена слишком большая.',
        ];
    }
}
