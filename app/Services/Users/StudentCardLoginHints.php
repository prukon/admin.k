<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Enums\AuditEvent;
use App\Models\MyLog;
use App\Models\User;
use App\Services\Geo\IpCountry;
use App\Services\Geo\IpCountryResolver;
use Jenssegers\Agent\Agent;

/**
 * Флаги и устройство для шапки карточки ученика: последний успешный вход и последняя активность, если страна другая.
 */
final class StudentCardLoginHints
{
    public function __construct(
        private readonly IpCountryResolver $countries,
    ) {}

    /**
     * @return array{
     *     device: ?string,
     *     device_label: string,
     *     flags: list<array{code: string, label: string}>
     * }
     */
    public function forStudent(User $student): array
    {
        $login = $this->latestLogin($student);
        $loginParsed = $login === null ? [] : $this->parseLoginDescription((string) $login->description);
        $activity = $this->parseUserAgent((string) ($student->last_activity_user_agent ?? ''));

        $loginCountry = $this->countries->resolve($loginParsed['ip'] ?? null);
        $activityIp = trim((string) ($student->last_activity_ip ?? ''));
        $loginIp = trim((string) ($loginParsed['ip'] ?? ''));
        $activityCountry = $activityIp !== '' && $activityIp !== $loginIp
            ? $this->countries->resolve($activityIp)
            : null;

        $flags = [];
        if ($loginCountry !== null) {
            $flags[] = $this->flag($loginCountry, (string) ($loginParsed['browser'] ?? ''));
        }

        if ($activityCountry !== null && ($loginCountry === null || strcasecmp($activityCountry->code, $loginCountry->code) !== 0)) {
            $flags[] = $this->flag($activityCountry, (string) ($activity['browser'] ?? ''));
        }

        $device = $loginParsed['device'] ?? $activity['device'] ?? null;

        return [
            'device' => $device,
            'device_label' => $this->deviceLabel($device),
            'flags' => $flags,
        ];
    }

    /**
     * @return array{ip: ?string, browser: ?string, device: ?string}
     */
    public function parseLoginDescription(string $description): array
    {
        $ip = null;
        if (preg_match('/IP:\s*([0-9a-fA-F:.]+)/', $description, $match) === 1) {
            $ip = $match[1];
        }

        $browser = null;
        if (preg_match('/Браузер:\s*([^,\n]+)/u', $description, $match) === 1) {
            $browser = trim($match[1]);
            if ($browser === '' || $browser === '0') {
                $browser = null;
            }
        }

        $desktop = $this->yesNo($description, 'ПК');
        $mobile = $this->yesNo($description, 'Моб\.\s*устройство');
        $tablet = $this->yesNo($description, 'Планшет');

        $device = null;
        if ($tablet === true) {
            $device = 'tablet';
        } elseif ($mobile === true) {
            $device = 'mobile';
        } elseif ($desktop === true) {
            $device = 'desktop';
        }

        return [
            'ip' => $ip,
            'browser' => $browser,
            'device' => $device,
        ];
    }

    private function latestLogin(User $student): ?MyLog
    {
        return MyLog::query()
            ->where('event', AuditEvent::AuthLogin->value)
            ->where(function ($query) use ($student) {
                $query->where('author_id', $student->id)
                    ->orWhere('user_id', $student->id);
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{browser: ?string, device: ?string}
     */
    private function parseUserAgent(string $userAgent): array
    {
        $userAgent = trim($userAgent);
        if ($userAgent === '') {
            return ['browser' => null, 'device' => null];
        }

        $agent = new Agent();
        $agent->setUserAgent($userAgent);
        $browserName = trim((string) $agent->browser());
        $browserVersion = $browserName !== '' ? trim((string) $agent->version($browserName)) : '';
        $browser = trim($browserName.' '.$browserVersion);

        $device = null;
        if ($agent->isTablet()) {
            $device = 'tablet';
        } elseif ($agent->isMobile()) {
            $device = 'mobile';
        } elseif ($agent->isDesktop()) {
            $device = 'desktop';
        }

        return [
            'browser' => $browser !== '' ? $browser : null,
            'device' => $device,
        ];
    }

    /**
     * @return array{code: string, label: string}
     */
    private function flag(IpCountry $country, string $browser): array
    {
        $browser = trim($browser);
        $label = $browser !== '' ? $country->name.', '.$browser : $country->name;

        return [
            'code' => strtolower($country->code),
            'label' => $label,
        ];
    }

    private function deviceLabel(?string $device): string
    {
        return match ($device) {
            'mobile' => 'Телефон',
            'tablet' => 'Планшет',
            'desktop' => 'Компьютер',
            default => '',
        };
    }

    private function yesNo(string $description, string $labelPattern): ?bool
    {
        if (preg_match('/'.$labelPattern.':\s*(Да|Нет)/u', $description, $match) !== 1) {
            return null;
        }

        return $match[1] === 'Да';
    }
}
