<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\RuPhone;
use Illuminate\Foundation\Http\FormRequest;

final class SendUserLessonPackagePaySmsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && $user->can('lessonPackages.view')
            && $user->can('setPrices.packageAssignments.view');
    }

    protected function prepareForValidation(): void
    {
        if (! $this->exists('phone')) {
            return;
        }

        $raw = $this->input('phone');
        if (! is_string($raw)) {
            $this->merge(['phone' => null]);

            return;
        }

        $trimmed = trim($raw);
        $this->merge([
            'phone' => $trimmed === '' ? null : $trimmed,
        ]);
    }

    public function rules(): array
    {
        return [
            'phone' => [
                'nullable',
                'string',
                'max:32',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    $digits = RuPhone::normalizeDigits((string) $value);
                    if ($digits === null || strlen($digits) !== 11 || ! str_starts_with($digits, '7')) {
                        $fail('Укажите корректный номер телефона.');
                    }
                },
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'phone' => 'телефон',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.string' => 'Поле «:attribute» должно быть строкой.',
            'phone.max' => 'Поле «:attribute» не должно превышать :max символов.',
        ];
    }

    public function normalizedPhoneDigits(): ?string
    {
        $raw = $this->input('phone');
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        $digits = RuPhone::normalizeDigits($raw);
        if ($digits === null || strlen($digits) !== 11 || ! str_starts_with($digits, '7')) {
            return null;
        }

        return $digits;
    }
}
