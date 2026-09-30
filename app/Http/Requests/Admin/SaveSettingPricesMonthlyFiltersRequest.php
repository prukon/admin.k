<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\LessonPackage;
use App\Models\Location;
use App\Support\PartnerAdminUserOptions;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveSettingPricesMonthlyFiltersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->boolean('reset')) {
            $this->merge([
                'reset' => true,
            ]);

            return;
        }

        $merge = ['reset' => false];
        foreach ([
            'team_title',
            'user_name',
            'team_package',
            'user_package',
            'location_id',
            'admin_user_id',
            'team_price',
            'user_paid',
            'user_membership',
        ] as $key) {
            $merge[$key] = trim((string) $this->input($key, ''));
        }

        foreach (['team_price', 'user_paid', 'user_membership', 'team_package', 'user_package', 'location_id', 'admin_user_id'] as $key) {
            if ($merge[$key] === '') {
                $merge[$key] = null;
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        if ($this->boolean('reset')) {
            return [
                'reset' => ['required', 'boolean'],
            ];
        }

        return [
            'reset' => ['sometimes', 'boolean'],
            'team_title' => ['nullable', 'string', 'max:255'],
            'user_name' => ['nullable', 'string', 'max:255'],
            'team_package' => ['nullable', 'string', 'max:20', $this->packageRule()],
            'user_package' => ['nullable', 'string', 'max:20', $this->packageRule()],
            'team_price' => ['nullable', Rule::in(['set', 'unset'])],
            'user_paid' => ['nullable', Rule::in(['paid', 'unpaid'])],
            'user_membership' => ['nullable', Rule::in(['current', 'former'])],
            'location_id' => ['nullable', 'string', 'max:20', $this->locationRule()],
            'admin_user_id' => ['nullable', 'string', 'max:20', $this->adminRule()],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'team_title' => 'Название группы',
            'team_package' => 'Абонемент группы',
            'team_price' => 'Цена группы',
            'user_name' => 'Ученик',
            'user_paid' => 'Оплата',
            'user_membership' => 'Состав',
            'user_package' => 'Абонемент ученика',
            'location_id' => 'Объект',
            'admin_user_id' => 'Администратор объекта',
            'reset' => 'Сброс',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'team_title.max' => 'Название группы не длиннее 255 символов.',
            'user_name.max' => 'Имя ученика не длиннее 255 символов.',
            'team_package.max' => 'Выберите абонемент из списка.',
            'user_package.max' => 'Выберите абонемент из списка.',
            'team_price.in' => 'Выберите цену: задана или не задана.',
            'user_paid.in' => 'Выберите оплату: оплачено или не оплачено.',
            'user_membership.in' => 'Выберите состав: в группе или не в группе.',
            'location_id.max' => 'Выберите объект из списка.',
            'admin_user_id.max' => 'Выберите администратора из списка.',
        ];
    }

    /**
     * @return array{
     *     team_title: string,
     *     team_package: string,
     *     team_price: string,
     *     user_name: string,
     *     user_paid: string,
     *     user_membership: string,
     *     user_package: string,
     *     location_id: string,
     *     admin_user_id: string
     * }
     */
    public function filters(): array
    {
        return [
            'team_title' => trim((string) $this->input('team_title', '')),
            'team_package' => $this->storedChoice('team_package'),
            'team_price' => $this->storedChoice('team_price'),
            'user_name' => trim((string) $this->input('user_name', '')),
            'user_paid' => $this->storedChoice('user_paid'),
            'user_membership' => $this->storedChoice('user_membership'),
            'user_package' => $this->storedChoice('user_package'),
            'location_id' => $this->storedChoice('location_id'),
            'admin_user_id' => $this->storedChoice('admin_user_id'),
        ];
    }

    private function storedChoice(string $key): string
    {
        $value = $this->input($key);

        return $value === null ? '' : trim((string) $value);
    }

    private function packageRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '' || $value === 'none') {
                return;
            }

            if (! ctype_digit((string) $value)) {
                $fail('Выберите абонемент из списка.');

                return;
            }

            $partnerId = (int) (app('current_partner')->id ?? 0);
            $exists = LessonPackage::query()
                ->whereKey((int) $value)
                ->where('partner_id', $partnerId)
                ->exists();

            if (! $exists) {
                $fail('Выберите абонемент из списка.');
            }
        };
    }

    private function locationRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '' || $value === 'none') {
                return;
            }

            if (! ctype_digit((string) $value)) {
                $fail('Выберите объект из списка.');

                return;
            }

            $partnerId = (int) (app('current_partner')->id ?? 0);
            $exists = Location::query()
                ->whereKey((int) $value)
                ->where('partner_id', $partnerId)
                ->where('is_enabled', true)
                ->exists();

            if (! $exists) {
                $fail('Выберите объект из списка.');
            }
        };
    }

    private function adminRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '' || $value === 'none') {
                return;
            }

            if (! ctype_digit((string) $value)) {
                $fail('Выберите администратора из списка.');

                return;
            }

            $partnerId = (int) (app('current_partner')->id ?? 0);
            $allowed = PartnerAdminUserOptions::forPartner($partnerId)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            if (! in_array((int) $value, $allowed, true)) {
                $fail('Выберите администратора из списка.');
            }
        };
    }
}
