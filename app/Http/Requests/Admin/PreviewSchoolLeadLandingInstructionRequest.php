<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Support\RuPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PreviewSchoolLeadLandingInstructionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->can('schoolLeadLanding.view');
    }

    protected function prepareForValidation(): void
    {
        $omitPhone = $this->boolean('omit_phone');

        $raw = $this->input('phone');
        if (! is_string($raw)) {
            $this->merge([
                'omit_phone' => $omitPhone,
                'phone' => null,
            ]);

            return;
        }

        $trimmed = trim($raw);
        $this->merge([
            'omit_phone' => $omitPhone,
            'phone' => $trimmed === '' ? null : $trimmed,
        ]);
    }

    public function rules(): array
    {
        return [
            'omit_phone' => ['required', 'boolean'],
            'phone' => [
                Rule::requiredIf(fn () => ! $this->boolean('omit_phone')),
                'nullable',
                'string',
                'max:32',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($this->boolean('omit_phone')) {
                        return;
                    }
                    if ($value === null || $value === '') {
                        return;
                    }
                    if (! is_string($value)) {
                        $fail('Укажите корректный номер телефона.');

                        return;
                    }

                    $digits = RuPhone::normalizeDigits($value);
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
            'omit_phone' => 'не указывать номер телефона',
            'phone' => 'номер телефона',
        ];
    }

    public function messages(): array
    {
        return [
            'omit_phone.required' => 'Укажите, нужно ли показывать номер телефона в инструкции.',
            'omit_phone.boolean' => 'Поле «:attribute» должно быть да или нет.',
            'phone.required' => 'Укажите номер телефона.',
            'phone.required_if' => 'Укажите номер телефона.',
            'phone.string' => 'Поле «:attribute» должно быть строкой.',
            'phone.max' => 'Поле «:attribute» не должно превышать :max символов.',
        ];
    }

    public function omitPhone(): bool
    {
        return $this->boolean('omit_phone');
    }

    public function normalizedPhoneDigits(): ?string
    {
        if ($this->omitPhone()) {
            return null;
        }

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
