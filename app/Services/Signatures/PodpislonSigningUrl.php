<?php

declare(strict_types=1);

namespace App\Services\Signatures;

use App\Models\Contract;

/**
 * Ссылка на подписание из API Подпислона ({@see https://podpislon.ru/sign/pack/...}),
 * та же, что в SMS клиенту. В БД пишется только URL этого вида.
 */
final class PodpislonSigningUrl
{
    public const PATTERN = '#^https://podpislon\.ru/sign/pack/[0-9]+/[0-9a-f]+/?$#i';

    public static function normalize(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }

        if (! preg_match(self::PATTERN, $url)) {
            return null;
        }

        return rtrim($url, '/');
    }

    /**
     * @param  array<string, mixed>|null  $doc
     */
    public static function fromDocument(?array $doc): ?string
    {
        if ($doc === null) {
            return null;
        }

        foreach (self::candidateStrings($doc) as $candidate) {
            $normalized = self::normalize($candidate);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        $raw = $doc['raw'] ?? null;
        if (! is_array($raw)) {
            return null;
        }

        if (array_is_list($raw)) {
            foreach ($raw as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $found = self::fromDocument($item);
                if ($found !== null) {
                    return $found;
                }
            }

            return null;
        }

        return self::fromDocument($raw);
    }

    /**
     * @param  list<array<string, mixed>|string>  $links
     */
    public static function fromLinks(array $links): ?string
    {
        foreach ($links as $item) {
            $url = is_array($item) ? ($item['link'] ?? null) : $item;
            $normalized = self::normalize(is_string($url) ? $url : null);
            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * Сохраняет валидный URL. Пустой и чужой хост не пишутся; уже то же значение — без UPDATE.
     *
     * @param  array<string, mixed>|null  $doc
     * @param  list<array<string, mixed>|string>  $fallbackLinks
     */
    public static function capture(Contract $contract, ?array $doc = null, array $fallbackLinks = []): bool
    {
        if (self::persist($contract, self::fromDocument($doc))) {
            return true;
        }

        if (filled($contract->provider_signing_url)) {
            return false;
        }

        return self::persist($contract, self::fromLinks($fallbackLinks));
    }

    public static function persist(Contract $contract, ?string $url): bool
    {
        $normalized = self::normalize($url);
        if ($normalized === null) {
            return false;
        }

        if ($contract->provider_signing_url === $normalized) {
            return false;
        }

        $contract->provider_signing_url = $normalized;
        $contract->save();

        return true;
    }

    /**
     * @param  array<string, mixed>  $doc
     * @return list<string>
     */
    private static function candidateStrings(array $doc): array
    {
        $out = [];

        foreach (($doc['contacts'] ?? []) as $contact) {
            if (is_array($contact) && isset($contact['link']) && is_string($contact['link'])) {
                $out[] = $contact['link'];
            }
        }

        if (isset($doc['contact']['link']) && is_string($doc['contact']['link'])) {
            $out[] = $doc['contact']['link'];
        }

        foreach (($doc['links'] ?? []) as $link) {
            if (is_string($link)) {
                $out[] = $link;
            }
        }

        $result = $doc['result'] ?? null;
        if (is_array($result)) {
            foreach (($result['links'] ?? []) as $link) {
                if (is_string($link)) {
                    $out[] = $link;
                }
            }
        }

        return $out;
    }
}
