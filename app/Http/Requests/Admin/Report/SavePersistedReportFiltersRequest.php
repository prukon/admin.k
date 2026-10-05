<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Report;

use App\Models\Location;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Reports\PersistedReportFilters;
use App\Support\PartnerAdminUserOptions;
use App\Support\Reports\ReportFilterCatalog;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

abstract class SavePersistedReportFiltersRequest extends FormRequest
{
    abstract protected function filterProfile(): string;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        if ($this->boolean('reset')) {
            $this->merge(['reset' => true]);

            return;
        }

        $this->merge(['reset' => false]);

        foreach ($this->listKeys() as $key) {
            if (! $this->exists($key)) {
                continue;
            }
            $value = $this->input($key);
            if (! is_array($value)) {
                $this->merge([
                    $key => ($value === null || $value === '') ? [] : [$value],
                ]);
            }
        }
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

        $rules = [
            'reset' => ['sometimes', 'boolean'],
            'filter_user_id' => ['nullable', 'integer', 'min:1', $this->userIdRule()],
            'status' => ['nullable', Rule::in(['active', 'inactive'])],
        ];

        if ($this->filterProfile() === PersistedReportFilters::DEBTS) {
            $rules['debt_month'] = ['nullable', 'date_format:Y-m'];
        } else {
            $rules['payment_month'] = ['nullable', 'date_format:Y-m'];
            $rules['operation_date_from'] = ['nullable', 'date_format:Y-m-d'];
            $rules['operation_date_to'] = ['nullable', 'date_format:Y-m-d', $this->dateToRule()];
            $rules['payment_provider'] = ['nullable', Rule::in(['tbank', 'robokassa'])];
        }

        if ($this->usesTeamList()) {
            $rules['filter_team_id'] = ['nullable', 'array'];
            $rules['filter_team_id.*'] = [$this->teamIdRule()];
        } else {
            $rules['filter_team_id'] = ['nullable', $this->teamIdRule()];
        }

        if ($this->user()?->can('trainers.view')) {
            if ($this->usesTeamList()) {
                $rules['filter_trainer_profile_id'] = ['nullable', 'array'];
                $rules['filter_trainer_profile_id.*'] = [$this->trainerIdRule()];
            } else {
                $rules['filter_trainer_profile_id'] = ['nullable', $this->trainerIdRule()];
            }
        }

        if ($this->user()?->can('locations.view')) {
            if ($this->usesTeamList()) {
                $rules['filter_location_id'] = ['nullable', 'array'];
                $rules['filter_location_id.*'] = [$this->locationIdRule()];
                $rules['filter_admin_user_id'] = ['nullable', 'array'];
                $rules['filter_admin_user_id.*'] = [$this->adminUserIdRule()];
            } else {
                $rules['filter_location_id'] = ['nullable', $this->locationIdRule()];
                $rules['filter_admin_user_id'] = ['nullable', $this->adminUserIdRule()];
            }
        }

        if ($this->storesGroupMode()) {
            $rules['mode'] = ['required', 'string', Rule::in(['operation', 'subscription'])];
        }

        if ($this->filterProfile() === PersistedReportFilters::PAYMENTS) {
            $rules['user_name'] = ['nullable', 'string', 'max:255'];
            $rules['team_title'] = ['nullable', 'string', 'max:255'];
            $rules['payment_source'] = ['nullable', Rule::in(['gateway', 'manual'])];
            $rules['payment_method'] = ['nullable', Rule::in(['card', 'sbp_qr', 'tpay'])];
            $rules['email_newsletter'] = ['nullable', Rule::in(['0', '1', 0, 1])];
            $rules['payment_refund_status'] = ['nullable', Rule::in(['no_refund', 'refunded', 'refund_pending'])];

            if ($this->user()?->can('reports.additional.value.view')) {
                foreach ([
                    'bank_commission_acquiring_min',
                    'bank_commission_acquiring_max',
                    'bank_commission_payout_min',
                    'bank_commission_payout_max',
                ] as $key) {
                    $rules[$key] = ['nullable', 'numeric', 'min:0', 'max:99999999', 'regex:/^\d+(\.\d{1,2})?$/'];
                }
                $rules['bank_commission_acquiring_max'][] = $this->moneyCeilingRule('bank_commission_acquiring_min', 'Комиссия оплаты «до» не меньше «от».');
                $rules['bank_commission_payout_max'][] = $this->moneyCeilingRule('bank_commission_payout_min', 'Комиссия выплаты «до» не меньше «от».');
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reset' => 'Сброс',
            'filter_user_id' => 'Ученик',
            'filter_team_id' => 'Группа',
            'filter_team_id.*' => 'Группа',
            'filter_trainer_profile_id' => 'Тренер',
            'filter_trainer_profile_id.*' => 'Тренер',
            'filter_location_id' => 'Объект',
            'filter_location_id.*' => 'Объект',
            'filter_admin_user_id' => 'Админ',
            'filter_admin_user_id.*' => 'Админ',
            'user_name' => 'Ученик',
            'team_title' => 'Группа',
            'payment_month' => 'Оплаченный месяц',
            'debt_month' => 'Месяц задолженности',
            'operation_date_from' => 'Дата платежа с',
            'operation_date_to' => 'Дата платежа по',
            'payment_provider' => 'Провайдер',
            'payment_source' => 'Источник оплаты',
            'payment_method' => 'Способ оплаты',
            'email_newsletter' => 'Email рассылка',
            'payment_refund_status' => 'Статус платежа',
            'bank_commission_acquiring_min' => 'Комиссия оплаты от',
            'bank_commission_acquiring_max' => 'Комиссия оплаты до',
            'bank_commission_payout_min' => 'Комиссия выплаты от',
            'bank_commission_payout_max' => 'Комиссия выплаты до',
            'status' => 'Активность ученика',
            'mode' => 'Группировка',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reset.required' => 'Не удалось сбросить фильтры.',
            'reset.boolean' => 'Не удалось сбросить фильтры.',
            'filter_user_id.integer' => 'Выберите ученика из списка.',
            'filter_user_id.min' => 'Выберите ученика из списка.',
            'filter_team_id.array' => 'Выберите группу из списка.',
            'filter_trainer_profile_id.array' => 'Выберите тренера из списка.',
            'filter_location_id.array' => 'Выберите объект из списка.',
            'filter_admin_user_id.array' => 'Выберите администратора из списка.',
            'user_name.max' => 'Имя ученика не длиннее 255 символов.',
            'team_title.max' => 'Название группы не длиннее 255 символов.',
            'payment_month.date_format' => 'Укажите оплаченный месяц в формате ГГГГ-ММ.',
            'debt_month.date_format' => 'Укажите месяц задолженности в формате ГГГГ-ММ.',
            'operation_date_from.date_format' => 'Укажите дату платежа «с» в формате ГГГГ-ММ-ДД.',
            'operation_date_to.date_format' => 'Укажите дату платежа «по» в формате ГГГГ-ММ-ДД.',
            'payment_provider.in' => 'Выберите провайдера: T-Bank или Robokassa.',
            'payment_source.in' => 'Выберите источник оплаты: все, платёжная система или ручная оплата.',
            'payment_method.in' => 'Выберите способ оплаты: карта, QR (СБП) или T-Pay.',
            'email_newsletter.in' => 'Выберите значение фильтра «Email рассылка».',
            'payment_refund_status.in' => 'Выберите статус платежа из списка.',
            'status.in' => 'Выберите активность ученика: все, только активные или только неактивные.',
            'mode.required' => 'Выберите группировку: по месяцу абонемента или по дате платежа.',
            'mode.string' => 'Выберите группировку: по месяцу абонемента или по дате платежа.',
            'mode.in' => 'Выберите группировку: по месяцу абонемента или по дате платежа.',
            'bank_commission_acquiring_min.numeric' => 'Комиссия оплаты «от» должна быть числом.',
            'bank_commission_acquiring_min.min' => 'Комиссия оплаты «от» не меньше 0.',
            'bank_commission_acquiring_min.max' => 'Комиссия оплаты «от» слишком большая.',
            'bank_commission_acquiring_min.regex' => 'Комиссия оплаты «от»: не больше двух знаков после запятой.',
            'bank_commission_acquiring_max.numeric' => 'Комиссия оплаты «до» должна быть числом.',
            'bank_commission_acquiring_max.min' => 'Комиссия оплаты «до» не меньше 0.',
            'bank_commission_acquiring_max.max' => 'Комиссия оплаты «до» слишком большая.',
            'bank_commission_acquiring_max.regex' => 'Комиссия оплаты «до»: не больше двух знаков после запятой.',
            'bank_commission_payout_min.numeric' => 'Комиссия выплаты «от» должна быть числом.',
            'bank_commission_payout_min.min' => 'Комиссия выплаты «от» не меньше 0.',
            'bank_commission_payout_min.max' => 'Комиссия выплаты «от» слишком большая.',
            'bank_commission_payout_min.regex' => 'Комиссия выплаты «от»: не больше двух знаков после запятой.',
            'bank_commission_payout_max.numeric' => 'Комиссия выплаты «до» должна быть числом.',
            'bank_commission_payout_max.min' => 'Комиссия выплаты «до» не меньше 0.',
            'bank_commission_payout_max.max' => 'Комиссия выплаты «до» слишком большая.',
            'bank_commission_payout_max.regex' => 'Комиссия выплаты «до»: не больше двух знаков после запятой.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return app(PersistedReportFilters::class)->normalizeValidated(
            $this->validated(),
            $this->filterProfile(),
            $this->user()
        );
    }

    /**
     * @return list<string>
     */
    private function listKeys(): array
    {
        if (! $this->usesTeamList()) {
            return [];
        }

        return [
            'filter_team_id',
            'filter_trainer_profile_id',
            'filter_location_id',
            'filter_admin_user_id',
        ];
    }

    private function usesTeamList(): bool
    {
        return $this->filterProfile() !== PersistedReportFilters::MONTHLY;
    }

    private function storesGroupMode(): bool
    {
        return in_array($this->filterProfile(), [
            PersistedReportFilters::MONTHLY,
            PersistedReportFilters::LTV_TEAMS,
            PersistedReportFilters::LTV_LOCATIONS,
            PersistedReportFilters::LTV_ADMINS,
        ], true);
    }

    private function partnerId(): int
    {
        $partner = app()->bound('current_partner') ? app('current_partner') : null;

        return (int) ($partner->id ?? 0);
    }

    private function userIdRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }

            $exists = User::query()
                ->whereKey((int) $value)
                ->where('partner_id', $this->partnerId())
                ->exists();

            if (! $exists) {
                $fail('Выберите ученика из списка.');
            }
        };
    }

    private function teamIdRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }
            if (! ctype_digit((string) $value)) {
                $fail('Выберите группу из списка.');

                return;
            }

            $allowed = ReportFilterCatalog::activeTeams($this->partnerId(), $this->user())
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            if (! in_array((int) $value, $allowed, true)) {
                $fail('Выберите группу из списка.');
            }
        };
    }

    private function trainerIdRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '') {
                return;
            }
            if (! ctype_digit((string) $value)) {
                $fail('Выберите тренера из списка.');

                return;
            }

            $exists = TrainerProfile::query()
                ->whereKey((int) $value)
                ->where('partner_id', $this->partnerId())
                ->where('is_enabled', true)
                ->whereHas('user', fn ($q) => $q->where('is_enabled', true))
                ->exists();

            if (! $exists) {
                $fail('Выберите тренера из списка.');
            }
        };
    }

    private function locationIdRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '' || $value === 'none') {
                return;
            }
            if (! ctype_digit((string) $value)) {
                $fail('Выберите объект из списка.');

                return;
            }

            $exists = Location::query()
                ->whereKey((int) $value)
                ->where('partner_id', $this->partnerId())
                ->where('is_enabled', true)
                ->exists();

            if (! $exists) {
                $fail('Выберите объект из списка.');
            }
        };
    }

    private function adminUserIdRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($value === null || $value === '' || $value === 'none') {
                return;
            }
            if (! ctype_digit((string) $value)) {
                $fail('Выберите администратора из списка.');

                return;
            }

            $allowed = PartnerAdminUserOptions::forPartner($this->partnerId())
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();

            if (! in_array((int) $value, $allowed, true)) {
                $fail('Выберите администратора из списка.');
            }
        };
    }

    private function dateToRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $from = $this->input('operation_date_from');
            if ($value === null || $value === '' || $from === null || $from === '') {
                return;
            }
            if ((string) $value < (string) $from) {
                $fail('Дата платежа «по» не раньше даты «с».');
            }
        };
    }

    private function moneyCeilingRule(string $minKey, string $message): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($minKey, $message): void {
            $min = $this->input($minKey);
            if ($value === null || $value === '' || $min === null || $min === '' || ! is_numeric($min) || ! is_numeric($value)) {
                return;
            }
            if ((float) $value < (float) $min) {
                $fail($message);
            }
        };
    }
}
