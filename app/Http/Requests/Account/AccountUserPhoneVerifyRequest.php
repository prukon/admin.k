<?php

declare(strict_types=1);

namespace App\Http\Requests\Account;

use App\Models\User;
use App\Support\RuPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

abstract class AccountUserPhoneVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        $allowed = $actor instanceof User
            && $target instanceof User
            && Gate::forUser($actor)->allows('verify-phone', $target);

        if (! $allowed) {
            Log::warning(static::class.': denied', [
                'actor_id' => $actor instanceof User ? $actor->id : null,
                'target_id' => $target instanceof User ? $target->id : null,
            ]);
        }

        return $allowed;
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

        $digits = RuPhone::normalizeDigits($raw);
        if ($digits !== null && strlen($digits) === 11 && str_starts_with($digits, '7')) {
            $this->merge(['phone' => $digits]);

            return;
        }

        $this->merge(['phone' => trim($raw)]);
    }

    /**
     * @return array<string, mixed>
     */
    public function phoneRules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^7\d{10}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function phoneAttributes(): array
    {
        return [
            'phone' => 'Телефон',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function phoneMessages(): array
    {
        return [
            'phone.required' => 'Укажите номер телефона.',
            'phone.string' => 'Поле «:attribute» должно быть строкой.',
            'phone.regex' => 'Некорректный номер. Формат 79XXXXXXXXX.',
        ];
    }

    public function phoneDigits(): string
    {
        return (string) $this->validated('phone');
    }
}
