<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\Partner;
use App\Models\PartnerSocialLink;
use App\Models\SocialNetwork;
use App\Models\Team;
use App\Models\User;
use App\Support\RuPhone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Вид спорта, объект группы и контакты партнёра для плейсхолдеров DOCX.
 * Пустые источники дают пустую строку и не блокируют договор.
 */
class ContractContextPlaceholderService
{
    public const SPORT_TYPE_NAME = 'sport_type_name';

    public const LOCATION_ADDRESS = 'location_address';

    public const LOCATION_ADMIN_EMAILS = 'location_admin_emails';

    public const LOCATION_ADMIN_PHONES = 'location_admin_phones';

    public const PARTNER_EMAIL = 'partner_email';

    public const PARTNER_PHONE = 'partner_phone';

    public const SOCIAL_KEY_PREFIX = 'partner_social_';

    /**
     * @return list<string>
     */
    public static function staticKeys(): array
    {
        return [
            self::SPORT_TYPE_NAME,
            self::LOCATION_ADDRESS,
            self::LOCATION_ADMIN_EMAILS,
            self::LOCATION_ADMIN_PHONES,
            self::PARTNER_EMAIL,
            self::PARTNER_PHONE,
        ];
    }

    public static function socialPlaceholderKey(string $code): ?string
    {
        $code = strtolower(trim($code));
        if ($code === '' || preg_match('/^[a-z][a-z0-9_]*$/', $code) !== 1) {
            return null;
        }

        return self::SOCIAL_KEY_PREFIX.$code;
    }

    public static function isSocialPlaceholderKey(string $key): bool
    {
        return str_starts_with($key, self::SOCIAL_KEY_PREFIX)
            && self::socialPlaceholderKey(substr($key, strlen(self::SOCIAL_KEY_PREFIX))) === $key;
    }

    /**
     * Глобально включённые сети — те же, что в модалке «Социальные сети».
     *
     * @return list<array{key: string, title: string, code: string}>
     */
    public function enabledSocialPresets(): array
    {
        $presets = [];
        foreach ($this->catalogNetworks() as $network) {
            if (!$network->is_enabled) {
                continue;
            }

            $key = self::socialPlaceholderKey((string) $network->code);
            if ($key === null) {
                continue;
            }

            $presets[] = [
                'key'   => $key,
                'title' => trim((string) $network->title) !== '' ? trim((string) $network->title) : (string) $network->code,
                'code'  => (string) $network->code,
            ];
        }

        return $presets;
    }

    /**
     * @return array<string, string>
     */
    public function valuesForContract(Contract $contract): array
    {
        $values = $this->emptyValues();

        $partnerId = (int) ($contract->school_id ?? 0);
        if ($partnerId <= 0) {
            return $values;
        }

        $partner = Partner::query()->whereKey($partnerId)->first(['id', 'email', 'phone']);
        if ($partner !== null) {
            $values[self::PARTNER_EMAIL] = trim((string) ($partner->email ?? ''));
            $values[self::PARTNER_PHONE] = $this->formatPhone((string) ($partner->phone ?? ''));
            $this->fillSocialLinks($values, $partnerId);
        }

        $teamId = (int) ($contract->group_id ?? 0);
        if ($teamId <= 0) {
            return $values;
        }

        $team = Team::query()
            ->whereKey($teamId)
            ->where('partner_id', $partnerId)
            ->whereNull('deleted_at')
            ->with([
                'sportType:id,name',
                'location:id,address',
            ])
            ->first();

        if ($team === null) {
            return $values;
        }

        $values[self::SPORT_TYPE_NAME] = trim((string) ($team->sportType?->name ?? ''));
        $values[self::LOCATION_ADDRESS] = trim((string) ($team->location?->address ?? ''));
        $this->fillLocationAdmins($values, $team, $partnerId);

        return $values;
    }

    /**
     * @return array<string, string>
     */
    private function emptyValues(): array
    {
        $values = [];
        foreach (self::staticKeys() as $key) {
            $values[$key] = '';
        }

        foreach ($this->catalogNetworks() as $network) {
            $key = self::socialPlaceholderKey((string) $network->code);
            if ($key !== null) {
                $values[$key] = '';
            }
        }

        return $values;
    }

    /**
     * @param array<string, string> $values
     */
    private function fillSocialLinks(array &$values, int $partnerId): void
    {
        $links = PartnerSocialLink::query()
            ->where('partner_id', $partnerId)
            ->where('is_enabled', true)
            ->with('socialNetwork:id,code,is_enabled')
            ->get();

        foreach ($links as $link) {
            $network = $link->socialNetwork;
            if ($network === null || !$network->is_enabled) {
                continue;
            }

            $key = self::socialPlaceholderKey((string) $network->code);
            if ($key === null || !array_key_exists($key, $values)) {
                continue;
            }

            $url = trim((string) ($link->url ?? ''));
            if ($url === '') {
                continue;
            }

            $values[$key] = $url;
        }
    }

    /**
     * @param array<string, string> $values
     */
    private function fillLocationAdmins(array &$values, Team $team, int $partnerId): void
    {
        $locationId = (int) ($team->location_id ?? 0);
        if ($locationId <= 0) {
            return;
        }

        $admins = User::query()
            ->select(['users.id', 'users.email', 'users.phone'])
            ->join('location_admin_user', 'location_admin_user.user_id', '=', 'users.id')
            ->where('location_admin_user.location_id', $locationId)
            ->where('location_admin_user.partner_id', $partnerId)
            ->whereNull('users.deleted_at')
            ->orderBy('users.lastname')
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->get();

        $values[self::LOCATION_ADMIN_EMAILS] = $this->joinUniqueEmails($admins);
        $values[self::LOCATION_ADMIN_PHONES] = $this->joinUniquePhones($admins);
    }

    /**
     * @param Collection<int, User> $admins
     */
    private function joinUniqueEmails(Collection $admins): string
    {
        $emails = [];
        $seen = [];
        foreach ($admins as $admin) {
            $email = trim((string) ($admin->email ?? ''));
            if ($email === '') {
                continue;
            }

            $fold = mb_strtolower($email, 'UTF-8');
            if (isset($seen[$fold])) {
                continue;
            }

            $seen[$fold] = true;
            $emails[] = $email;
        }

        return implode(', ', $emails);
    }

    /**
     * @param Collection<int, User> $admins
     */
    private function joinUniquePhones(Collection $admins): string
    {
        $phones = [];
        $seen = [];
        foreach ($admins as $admin) {
            $raw = trim((string) ($admin->phone ?? ''));
            if ($raw === '') {
                continue;
            }

            $digits = RuPhone::normalizeDigits($raw) ?? $raw;
            if (isset($seen[$digits])) {
                continue;
            }

            $seen[$digits] = true;
            $phones[] = $this->formatPhone($raw);
        }

        return implode(', ', $phones);
    }

    private function formatPhone(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }

        return RuPhone::formatForInput($raw);
    }

    /**
     * @return Collection<int, SocialNetwork>
     */
    private function catalogNetworks(): Collection
    {
        try {
            if (!Schema::hasTable('social_networks')) {
                return collect();
            }

            return SocialNetwork::query()
                ->orderBy('sort')
                ->orderBy('id')
                ->get(['id', 'code', 'title', 'is_enabled', 'sort']);
        } catch (\Throwable) {
            return collect();
        }
    }
}
