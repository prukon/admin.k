<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\User;
use App\Models\UserTableSetting;
use Illuminate\Http\Request;

/**
 * Фильтры отчётов на пользователя: user_table_settings.filters.
 * Колонки остаются в columns, «Показать N» — в page_length.
 */
final class PersistedReportFilters
{
    public const PAYMENTS = 'reports_payments';

    public const MONTHLY = 'reports_payments_monthly';

    public const LTV = 'reports_ltv';

    public const LTV_TEAMS = 'reports_ltv_teams';

    public const LTV_LOCATIONS = 'reports_ltv_locations';

    public const DEBTS = 'reports_debts';

    /**
     * Подставить сохранённые фильтры в запрос страницы.
     * Query-строка с фильтрами перезаписывает запись в БД.
     */
    public function hydrate(Request $request, string $tableKey): void
    {
        $user = $request->user();
        $userId = (int) ($user?->id ?? 0);
        if ($userId < 1 || ! $this->known($tableKey)) {
            return;
        }

        if ($this->queryCarries($request, $tableKey)) {
            $filters = $this->visibleFor($user, $tableKey, $this->fromQuery($request, $tableKey));
            $this->store($userId, $tableKey, $filters);
            $this->writeQuery($request, $filters);

            return;
        }

        $saved = $this->load($userId, $tableKey);
        if ($saved === null) {
            return;
        }

        $this->writeQuery($request, $this->visibleFor($user, $tableKey, $saved));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function store(int $userId, string $tableKey, array $filters): void
    {
        UserTableSetting::query()->updateOrCreate(
            [
                'user_id' => $userId,
                'table_key' => $tableKey,
            ],
            [
                'filters' => $filters,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(string $tableKey): array
    {
        $filters = [];
        foreach ($this->fields($tableKey) as $key => $kind) {
            $filters[$key] = $kind === 'list' ? [] : ($key === 'status' ? 'active' : '');
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalizeValidated(array $input, string $tableKey, ?User $user): array
    {
        $filters = $this->defaults($tableKey);
        foreach ($this->fields($tableKey) as $key => $kind) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $filters[$key] = $this->sanitizeValue($key, $kind, $input[$key]);
        }

        return $this->visibleFor($user, $tableKey, $filters);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function load(int $userId, string $tableKey): ?array
    {
        $row = UserTableSetting::query()
            ->where('user_id', $userId)
            ->where('table_key', $tableKey)
            ->first();

        $raw = $row?->filters;
        if (! is_array($raw)) {
            return null;
        }

        return $this->sanitizeMap($raw, $tableKey);
    }

    /**
     * @return array<string, mixed>
     */
    private function fromQuery(Request $request, string $tableKey): array
    {
        $query = $request->query->all();
        $filters = $this->defaults($tableKey);
        foreach ($this->fields($tableKey) as $key => $kind) {
            if (! array_key_exists($key, $query)) {
                continue;
            }
            $filters[$key] = $this->sanitizeValue($key, $kind, $query[$key]);
        }

        if (($filters['operation_date_from'] ?? '') !== ''
            && ($filters['operation_date_to'] ?? '') !== ''
            && $filters['operation_date_to'] < $filters['operation_date_from']) {
            $filters['operation_date_to'] = '';
        }

        return $filters;
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function sanitizeMap(array $raw, string $tableKey): array
    {
        $filters = $this->defaults($tableKey);
        foreach ($this->fields($tableKey) as $key => $kind) {
            if (! array_key_exists($key, $raw)) {
                continue;
            }
            $filters[$key] = $this->sanitizeValue($key, $kind, $raw[$key]);
        }

        return $filters;
    }

    private function sanitizeValue(string $key, string $kind, mixed $raw): mixed
    {
        if ($kind === 'list') {
            return $this->stringList($raw, $key === 'filter_location_id' || $key === 'filter_admin_user_id');
        }

        if ($key === 'status') {
            $status = $this->plainString($raw);

            return in_array($status, ['active', 'inactive', ''], true) ? $status : 'active';
        }

        if (in_array($key, ['payment_month', 'debt_month'], true)) {
            return $this->month($raw);
        }

        if (in_array($key, ['operation_date_from', 'operation_date_to'], true)) {
            return $this->date($raw);
        }

        if ($key === 'payment_provider') {
            return $this->enum($raw, ['tbank', 'robokassa']);
        }

        if ($key === 'payment_source') {
            return $this->enum($raw, ['gateway', 'manual']);
        }

        if ($key === 'payment_method') {
            return $this->enum($raw, ['card', 'sbp_qr', 'tpay']);
        }

        if ($key === 'email_newsletter') {
            return $this->enum($raw, ['0', '1']);
        }

        if ($key === 'payment_refund_status') {
            return $this->enum($raw, ['no_refund', 'refunded', 'refund_pending']);
        }

        if (str_starts_with($key, 'bank_commission_')) {
            return $this->money($raw);
        }

        if ($key === 'filter_location_id' || $key === 'filter_admin_user_id') {
            $list = $this->stringList($raw, true);

            return $list[0] ?? '';
        }

        if (in_array($key, ['filter_user_id', 'filter_team_id', 'filter_trainer_profile_id'], true)) {
            $list = $this->stringList($raw, false);

            return $list[0] ?? '';
        }

        $text = $this->plainString($raw);
        if (mb_strlen($text) > 255) {
            $text = mb_substr($text, 0, 255);
        }

        return $text;
    }

    /**
     * Скаляр для GET. Массив в поле-строке (например user_name[]=) не приводится к строке:
     * иначе страница отчёта падает, вместо того чтобы отбросить кривое значение.
     */
    private function plainString(mixed $raw): string
    {
        if (is_array($raw) || is_object($raw)) {
            return '';
        }

        return trim((string) ($raw ?? ''));
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $raw, bool $allowNone): array
    {
        $items = is_array($raw) ? $raw : (($raw === null || $raw === '') ? [] : [$raw]);
        $out = [];
        foreach ($items as $item) {
            if (is_array($item) || is_object($item)) {
                continue;
            }
            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }
            if ($allowNone && $value === 'none') {
                $out[] = 'none';

                continue;
            }
            if (ctype_digit($value) && (int) $value > 0) {
                $out[] = $value;
            }
        }

        return array_values(array_unique($out));
    }

    private function month(mixed $raw): string
    {
        $value = $this->plainString($raw);
        if (! preg_match('/^\d{4}-\d{2}$/', $value)) {
            return '';
        }
        $month = (int) substr($value, 5, 2);

        return ($month >= 1 && $month <= 12) ? $value : '';
    }

    private function date(mixed $raw): string
    {
        $value = $this->plainString($raw);
        $parsed = \DateTime::createFromFormat('!Y-m-d', $value);
        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            return '';
        }

        return $value;
    }

    /**
     * @param  list<string>  $allowed
     */
    private function enum(mixed $raw, array $allowed): string
    {
        $value = $this->plainString($raw);

        return in_array($value, $allowed, true) ? $value : '';
    }

    private function money(mixed $raw): string
    {
        $value = $this->plainString($raw);
        if ($value === '' || ! preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            return '';
        }
        if ((float) $value > 99999999) {
            return '';
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function writeQuery(Request $request, array $filters): void
    {
        foreach ($filters as $key => $value) {
            $request->query->set($key, $value);
        }
    }

    private function queryCarries(Request $request, string $tableKey): bool
    {
        $keys = array_keys($this->fields($tableKey));
        foreach (['user_name', 'team_title'] as $legacy) {
            if (! in_array($legacy, $keys, true)) {
                $keys[] = $legacy;
            }
        }

        foreach ($keys as $key) {
            if ($request->query->has($key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function visibleFor(?User $user, string $tableKey, array $filters): array
    {
        if (! $user?->can('trainers.view') && array_key_exists('filter_trainer_profile_id', $filters)) {
            $filters['filter_trainer_profile_id'] = $this->fields($tableKey)['filter_trainer_profile_id'] === 'list' ? [] : '';
        }
        if (! $user?->can('locations.view') && array_key_exists('filter_location_id', $filters)) {
            $filters['filter_location_id'] = $this->fields($tableKey)['filter_location_id'] === 'list' ? [] : '';
        }
        if (! $user?->can('locations.view') && array_key_exists('filter_admin_user_id', $filters)) {
            $filters['filter_admin_user_id'] = $this->fields($tableKey)['filter_admin_user_id'] === 'list' ? [] : '';
        }
        if ($tableKey === self::PAYMENTS && ! $user?->can('reports.additional.value.view')) {
            foreach ([
                'bank_commission_acquiring_min',
                'bank_commission_acquiring_max',
                'bank_commission_payout_min',
                'bank_commission_payout_max',
            ] as $key) {
                $filters[$key] = '';
            }
        }

        return $filters;
    }

    private function known(string $tableKey): bool
    {
        return $this->fields($tableKey) !== [];
    }

    /**
     * @return array<string, string> key => list|scalar
     */
    private function fields(string $tableKey): array
    {
        $sharedDates = [
            'payment_month' => 'scalar',
            'operation_date_from' => 'scalar',
            'operation_date_to' => 'scalar',
            'payment_provider' => 'scalar',
            'status' => 'scalar',
        ];

        return match ($tableKey) {
            self::PAYMENTS => [
                'filter_user_id' => 'scalar',
                'filter_team_id' => 'list',
                'filter_trainer_profile_id' => 'list',
                'filter_location_id' => 'list',
                'filter_admin_user_id' => 'list',
                'user_name' => 'scalar',
                'team_title' => 'scalar',
                'payment_month' => 'scalar',
                'operation_date_from' => 'scalar',
                'operation_date_to' => 'scalar',
                'payment_provider' => 'scalar',
                'payment_source' => 'scalar',
                'payment_method' => 'scalar',
                'email_newsletter' => 'scalar',
                'payment_refund_status' => 'scalar',
                'bank_commission_acquiring_min' => 'scalar',
                'bank_commission_acquiring_max' => 'scalar',
                'bank_commission_payout_min' => 'scalar',
                'bank_commission_payout_max' => 'scalar',
                'status' => 'scalar',
            ],
            self::MONTHLY => [
                'filter_user_id' => 'scalar',
                'filter_team_id' => 'scalar',
                'filter_trainer_profile_id' => 'scalar',
                'filter_location_id' => 'scalar',
                'filter_admin_user_id' => 'scalar',
            ] + $sharedDates,
            self::LTV, self::LTV_TEAMS, self::LTV_LOCATIONS => [
                'filter_user_id' => 'scalar',
                'filter_team_id' => 'list',
                'filter_trainer_profile_id' => 'list',
                'filter_location_id' => 'list',
                'filter_admin_user_id' => 'list',
            ] + $sharedDates,
            self::DEBTS => [
                'filter_user_id' => 'scalar',
                'filter_team_id' => 'list',
                'filter_trainer_profile_id' => 'list',
                'filter_location_id' => 'list',
                'filter_admin_user_id' => 'list',
                'debt_month' => 'scalar',
                'status' => 'scalar',
            ],
            default => [],
        };
    }
}
