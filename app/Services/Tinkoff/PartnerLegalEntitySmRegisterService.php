<?php

namespace App\Services\Tinkoff;

use App\Enums\PartnerLegalEntityBusinessType;
use App\Models\Partner;
use App\Models\PartnerLegalEntity;
use Illuminate\Support\Facades\Log;

final class PartnerLegalEntitySmRegisterService
{
    public function __construct(
        private readonly SmRegisterClient $sm,
    ) {
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{shopCode: string|null, status: string|null, raw: mixed}
     */
    public function register(PartnerLegalEntity $entity, Partner $partner, array $validated): array
    {
        $payload = $this->buildRegisterPayload($entity, $partner, $validated);

        Log::channel('tinkoff')->info('[sm-register][legal_entity] payload ' . json_encode($payload, JSON_UNESCAPED_UNICODE));

        $response = $this->sm->register($payload);

        $shopCode = data_get($response, 'shopCode') ?? data_get($response, 'code') ?? data_get($response, 'id');
        $status = data_get($response, 'status') ?? 'REGISTERED';

        $legalName = trim((string) $validated['organization_name']);
        $bd = $entity->sms_name ?: $this->makeDescriptor($legalName);
        $smsToSave = $entity->sms_name ?: $bd;

        [$ceoFirst, $ceoLast, $ceoMiddle, $ceoPhone] = $this->resolveCeo($entity, $validated, $partner, $legalName);

        $entity->fill($this->persistFieldsFromValidated($validated, $legalName, $ceoFirst, $ceoLast, $ceoMiddle, $ceoPhone))
            ->fill([
                'tinkoff_shop_code' => $shopCode,
                'sm_register_status' => $status,
                'registered_at' => $entity->registered_at ?? now(),
                'sms_name' => $smsToSave,
                'bank_details_version' => (int) ($entity->bank_details_version ?? 0) + 1,
                'bank_details_last_updated_at' => now(),
            ])
            ->save();

        return [
            'shopCode' => is_string($shopCode) ? $shopCode : null,
            'status' => is_string($status) ? $status : null,
            'raw' => $response,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function patch(PartnerLegalEntity $entity, Partner $partner, array $validated): array
    {
        $shopCode = trim((string) ($entity->tinkoff_shop_code ?? ''));
        if ($shopCode === '') {
            throw new \RuntimeException('Сначала зарегистрируйте юр. лицо в sm-register (нет ShopCode).');
        }

        $payload = $this->buildPatchPayload($entity, $partner, $validated);

        Log::channel('tinkoff')->info('[sm-register][legal_entity][patch] shopCode=' . $shopCode . ' payload=' . json_encode($payload, JSON_UNESCAPED_UNICODE));

        $response = $this->sm->patch($shopCode, $payload);

        $legalName = trim((string) $validated['organization_name']);
        [$ceoFirst, $ceoLast, $ceoMiddle, $ceoPhone] = $this->resolveCeo($entity, $validated, $partner, $legalName);

        $entity->fill($this->persistFieldsFromValidated($validated, $legalName, $ceoFirst, $ceoLast, $ceoMiddle, $ceoPhone))
            ->fill([
                'bank_details_version' => (int) ($entity->bank_details_version ?? 0) + 1,
                'bank_details_last_updated_at' => now(),
            ])
            ->save();

        return is_array($response) ? $response : ['raw' => $response];
    }

    /**
     * @return array{status: string|null, raw: mixed}
     */
    public function refreshStatus(PartnerLegalEntity $entity): array
    {
        $shopCode = trim((string) ($entity->tinkoff_shop_code ?? ''));
        if ($shopCode === '') {
            throw new \RuntimeException('Нет ShopCode.');
        }

        $res = $this->sm->getStatus($shopCode);
        $entity->sm_register_status = data_get($res, 'status') ?? $entity->sm_register_status;
        $entity->save();

        return [
            'status' => $entity->sm_register_status,
            'raw' => $res,
        ];
    }

    /**
     * @return array{changed: array<string, array{from: mixed, to: mixed}>, raw: mixed}
     */
    public function pullFromRemote(PartnerLegalEntity $entity): array
    {
        $shopCode = trim((string) ($entity->tinkoff_shop_code ?? ''));
        if ($shopCode === '') {
            throw new \RuntimeException('Нет ShopCode.');
        }

        $remote = $this->sm->getStatus($shopCode);

        Log::channel('tinkoff')->info('[sm-register][legal_entity][pull] shopCode=' . $shopCode);

        $addr = data_get($remote, 'addresses.0', []);
        $bank = data_get($remote, 'bankAccount', []);
        $phones = data_get($remote, 'phones', []);
        $phone = data_get($phones, '0.phone');
        $details = (string) data_get($bank, 'details', '');

        $smsName = $entity->sms_name ?: (string) data_get($remote, 'billingDescriptor');

        $toWrite = [
            'organization_name' => (string) data_get($remote, 'fullName', $entity->organization_name),
            'tax_id' => (string) data_get($remote, 'inn', $entity->tax_id),
            'kpp' => (string) data_get($remote, 'kpp', $entity->kpp),
            'registration_number' => (string) data_get($remote, 'ogrn', $entity->registration_number),
            'city' => (string) data_get($addr, 'city', $entity->city),
            'zip' => (string) data_get($addr, 'zip', $entity->zip),
            'address' => (string) data_get($addr, 'street', $entity->address),
            'bank_name' => (string) data_get($bank, 'bankName', $entity->bank_name),
            'bank_bik' => (string) data_get($bank, 'bik', $entity->bank_bik),
            'bank_account' => (string) data_get($bank, 'account', $entity->bank_account),
            'sm_details_template' => $details !== '' ? $details : $entity->sm_details_template,
            'sm_register_status' => (string) data_get($remote, 'status', $entity->sm_register_status),
            'sms_name' => $smsName ?: $entity->sms_name,
        ];

        if ($phone) {
            $ceo = is_array($entity->ceo) ? $entity->ceo : [];
            $ceo['phone'] = $phone;
            $toWrite['ceo'] = $ceo;
        }

        $before = $entity->only(array_keys($toWrite));

        $entity->fill($toWrite);

        $dirty = $entity->getDirty();
        if ($dirty !== []) {
            $entity->bank_details_version = (int) ($entity->bank_details_version ?? 0) + 1;
            $entity->bank_details_last_updated_at = now();
        }
        $entity->save();

        $after = $entity->fresh()->only(array_keys($toWrite));
        $changed = [];
        foreach ($after as $key => $value) {
            if (($before[$key] ?? null) !== $value) {
                $changed[$key] = ['from' => $before[$key] ?? null, 'to' => $value];
            }
        }

        return ['changed' => $changed, 'raw' => $remote];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildRegisterPayload(PartnerLegalEntity $entity, Partner $partner, array $validated): array
    {
        $legalName = trim((string) $validated['organization_name']);
        $bd = $entity->sms_name ?: $this->makeDescriptor($legalName);
        $businessType = PartnerLegalEntityBusinessType::from((string) $validated['business_type']);

        $phone = $this->normalizePhone($validated['phone'] ?? $partner->phone);
        $city = $this->normalizeCity((string) $validated['city']);
        $street = $this->sanitizeStreet((string) $validated['address'], $city);
        $kpp = $this->resolveKpp($businessType, $validated['kpp'] ?? $entity->kpp);
        $ogrn = $this->resolveOgrn($validated['registration_number'] ?? '');
        $siteUrl = $validated['website'] ?? $partner->website ?? config('app.url');

        [$ceoFirst, $ceoLast, $ceoMiddle, $ceoPhone] = $this->resolveCeo($entity, $validated, $partner, $legalName);

        $payload = [
            'billingDescriptor' => $bd,
            'fullName' => $legalName,
            'name' => $legalName,
            'inn' => (string) $validated['tax_id'],
            'kpp' => (string) $kpp,
            'ogrn' => $ogrn,
            'addresses' => [[
                'type' => 'legal',
                'zip' => (string) $validated['zip'],
                'country' => 'RUS',
                'city' => $city,
                'street' => $street,
            ]],
            'phones' => $phone ? [[
                'type' => 'common',
                'phone' => $phone,
                'description' => 'Контакт',
            ]] : [],
            'email' => (string) $validated['email'],
            'siteUrl' => $siteUrl,
            'bankAccount' => [
                'account' => (string) $validated['bank_account'],
                'bankName' => (string) $validated['bank_name'],
                'bik' => (string) $validated['bank_bik'],
                'details' => (string) $validated['sm_details_template'],
            ],
            'ceo' => [
                'firstName' => $ceoFirst,
                'lastName' => $ceoLast,
                'middleName' => $ceoMiddle,
                'phone' => $ceoPhone,
                'country' => 'RUS',
            ],
        ];

        return $this->cleanPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildPatchPayload(PartnerLegalEntity $entity, Partner $partner, array $validated): array
    {
        $legalName = trim((string) $validated['organization_name']);
        $businessType = PartnerLegalEntityBusinessType::from((string) $validated['business_type']);

        $phone = $this->normalizePhone($validated['phone'] ?? $partner->phone);
        $city = $this->normalizeCity((string) $validated['city']);
        $street = $this->sanitizeStreet((string) $validated['address'], $city);
        $kpp = $this->resolveKpp($businessType, $validated['kpp'] ?? $entity->kpp);
        $ogrn = $this->resolveOgrn($validated['registration_number'] ?? '');
        $siteUrl = $validated['website'] ?? $partner->website ?? config('app.url');

        $payload = [
            'fullName' => $legalName,
            'name' => $legalName,
            'inn' => (string) $validated['tax_id'],
            'kpp' => (string) $kpp,
            'ogrn' => $ogrn,
            'addresses' => [[
                'type' => 'legal',
                'zip' => (string) $validated['zip'],
                'country' => 'RUS',
                'city' => $city,
                'street' => $street,
            ]],
            'phones' => $phone ? [[
                'type' => 'common',
                'phone' => $phone,
                'description' => 'Контакт',
            ]] : [],
            'email' => (string) $validated['email'],
            'siteUrl' => $siteUrl,
            'bankAccount' => [
                'account' => (string) $validated['bank_account'],
                'bankName' => (string) $validated['bank_name'],
                'bik' => (string) $validated['bank_bik'],
                'details' => (string) $validated['sm_details_template'],
            ],
        ];

        return $this->cleanPayload($payload);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function persistFieldsFromValidated(
        array $validated,
        string $legalName,
        string $ceoFirst,
        string $ceoLast,
        ?string $ceoMiddle,
        string $ceoPhone,
    ): array {
        $businessType = PartnerLegalEntityBusinessType::from((string) $validated['business_type']);
        $city = $this->normalizeCity((string) $validated['city']);

        return [
            'business_type' => $businessType->value,
            'title' => (string) $validated['title'],
            'organization_name' => $legalName,
            'tax_id' => (string) $validated['tax_id'],
            'registration_number' => (string) $validated['registration_number'],
            'kpp' => $this->resolveKpp($businessType, $validated['kpp'] ?? null),
            'city' => $city,
            'zip' => (string) $validated['zip'],
            'address' => (string) $validated['address'],
            'bank_name' => (string) $validated['bank_name'],
            'bank_bik' => (string) $validated['bank_bik'],
            'bank_account' => (string) $validated['bank_account'],
            'sm_details_template' => (string) $validated['sm_details_template'],
            'ceo' => [
                'firstName' => $ceoFirst,
                'lastName' => $ceoLast,
                'middleName' => $ceoMiddle,
                'phone' => $ceoPhone,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: string, 1: string, 2: ?string, 3: string}
     */
    private function resolveCeo(
        PartnerLegalEntity $entity,
        array $validated,
        Partner $partner,
        string $legalName,
    ): array {
        $existingCeo = is_array($entity->ceo) ? $entity->ceo : null;
        $normalizePhone = fn (?string $raw) => $this->normalizePhone($raw) ?: '+70000000000';

        if ($existingCeo && ! empty($existingCeo['firstName']) && ! empty($existingCeo['lastName'])) {
            return [
                (string) $existingCeo['firstName'],
                (string) $existingCeo['lastName'],
                isset($existingCeo['middleName']) ? (string) $existingCeo['middleName'] : null,
                $normalizePhone($existingCeo['phone'] ?? ($validated['phone'] ?? $partner->phone)),
            ];
        }

        [$ceoFirst, $ceoLast, $ceoMiddle] = $this->extractCeoFromTitle($legalName);

        return [
            $ceoFirst,
            $ceoLast,
            $ceoMiddle,
            $normalizePhone($validated['phone'] ?? $partner->phone),
        ];
    }

    private function resolveKpp(PartnerLegalEntityBusinessType $type, mixed $kpp): string
    {
        if (! $type->requiresKpp()) {
            return '000000000';
        }

        $kpp = trim((string) ($kpp ?? ''));

        return $kpp !== '' ? $kpp : '000000000';
    }

    private function resolveOgrn(mixed $registrationNumber): ?int
    {
        $ogrnDigits = preg_replace('/\D+/', '', (string) $registrationNumber);

        return $ogrnDigits !== '' ? (int) $ogrnDigits : null;
    }

    private function normalizePhone(?string $raw): ?string
    {
        if (! $raw) {
            return null;
        }
        $d = preg_replace('/\D+/', '', $raw);
        if (! $d) {
            return null;
        }
        if (strlen($d) === 11 && ($d[0] === '7' || $d[0] === '8')) {
            $d = '7' . substr($d, 1);
        } elseif (strlen($d) === 10) {
            $d = '7' . $d;
        }

        return '+' . $d;
    }

    private function normalizeCity(string $city): string
    {
        return preg_match('/^(\s*spb|\s*спб)$/iu', $city) ? 'Санкт-Петербург' : $city;
    }

    private function makeDescriptor(string $src): string
    {
        $map = ['А' => 'A', 'Б' => 'B', 'В' => 'V', 'Г' => 'G', 'Д' => 'D', 'Е' => 'E', 'Ё' => 'E', 'Ж' => 'ZH', 'З' => 'Z', 'И' => 'I', 'Й' => 'Y', 'К' => 'K', 'Л' => 'L', 'М' => 'M', 'Н' => 'N', 'О' => 'O', 'П' => 'P', 'Р' => 'R', 'С' => 'S', 'Т' => 'T', 'У' => 'U', 'Ф' => 'F', 'Х' => 'H', 'Ц' => 'C', 'Ч' => 'CH', 'Ш' => 'SH', 'Щ' => 'SCH', 'Ы' => 'Y', 'Э' => 'E', 'Ю' => 'YU', 'Я' => 'YA', 'Ь' => '', 'Ъ' => ''];
        $map += array_change_key_case($map, CASE_LOWER);
        $s = strtoupper(strtr($src, $map));
        $s = preg_replace('/[^A-Z0-9 ._-]+/', '', $s) ?? '';
        $s = trim(preg_replace('/\s+/', ' ', $s) ?? '');
        if ($s === '') {
            $s = 'KRUZHOK';
        }
        if (strlen($s) > 14) {
            $s = substr($s, 0, 14);
        }

        return $s;
    }

    /**
     * @return array{0: string, 1: string, 2: ?string}
     */
    private function extractCeoFromTitle(string $title): array
    {
        $t = trim(preg_replace('/^ИП\s+/ui', '', $title));
        $parts = preg_split('/\s+/u', $t, -1, PREG_SPLIT_NO_EMPTY);
        $last = $parts[0] ?? 'Иванов';
        $first = $parts[1] ?? 'Иван';
        $middle = $parts[2] ?? null;

        return [$first, $last, $middle];
    }

    private function sanitizeStreet(string $raw, string $city): string
    {
        $s = preg_replace('/\b(г\.?|город)\b[\s\.]*санкт[\s\-]*петербург\b/iu', '', $raw);
        $s = preg_replace('/\bсанкт[\s\-]*петербург\b/iu', '', $s);
        $s = preg_replace('/\b(спб|с\-пб)\b/iu', '', $s);
        $s = preg_replace('/\b(пр[\.\-]?\s*т|просп\.?|пр\-т)\b/iu', 'проспект', $s);
        $s = preg_replace('/корп\.?\s*\/\s*ст\.?/iu', 'к.', $s);
        $s = preg_replace('/корп\.?/iu', 'к.', $s);
        $s = preg_replace('/стр\.?/iu', 'стр.', $s);
        $s = preg_replace('/кв\.?\s*\/\s*оф\.?/iu', 'оф.', $s);
        $s = preg_replace('/кв\.?/iu', 'кв.', $s);
        $s = preg_replace('/оф\.?/iu', 'оф.', $s);
        $s = preg_replace('/[^0-9A-Za-zА-Яа-яЁё\s\.,]/u', '', $s);
        $s = preg_replace('/\s*,\s*/u', ', ', $s);
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = trim($s, ' ,');
        if ($s === '') {
            $s = trim(preg_replace('/' . preg_quote($city, '/') . '/iu', '', $raw));
        }

        return $s;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cleanPayload(array $payload): array
    {
        $clean = function ($v) use (&$clean) {
            if (is_array($v)) {
                $o = [];
                foreach ($v as $k => $x) {
                    $cx = $clean($x);
                    if ($cx !== null && $cx !== '') {
                        $o[$k] = $cx;
                    }
                }

                return $o;
            }

            return $v;
        };

        return $clean($payload);
    }
}
