<?php

declare(strict_types=1);

namespace App\Services\SchoolLeads;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Поверхностное сопоставление ребёнка из лида с уже существующим учеником
 * (клиентом) того же партнёра — по фамилии и имени. Группа (секция) в
 * сопоставлении не участвует: у лида и у найденного ученика она может
 * отличаться, это ожидаемый сценарий (ученик записывается во вторую секцию).
 */
final class SchoolLeadChildMatcher
{
    public function match(int $partnerId, ?string $lastname, ?string $firstname): ?User
    {
        if ($partnerId <= 0) {
            return null;
        }

        $lastnameNorm = $this->normalizeName($lastname);
        $firstnameNorm = $this->normalizeName($firstname);

        if ($lastnameNorm === null || $firstnameNorm === null) {
            return null;
        }

        return $this->baseQuery($partnerId)
            ->whereRaw('LOWER(TRIM(lastname)) = ?', [$lastnameNorm])
            ->whereRaw('LOWER(TRIM(name)) = ?', [$firstnameNorm])
            ->orderBy('id')
            ->first();
    }

    /**
     * Пакетный поиск для списка лидов (например, страницы таблицы), чтобы не
     * делать по отдельному запросу на каждую строку.
     *
     * @param  Collection<int, array{lastname: ?string, firstname: ?string}>  $pairs
     * @return Collection<string, User> ключ — "lastname|firstname" в нижнем регистре
     */
    public function matchMany(int $partnerId, Collection $pairs): Collection
    {
        $normalizedPairs = $pairs
            ->map(function (array $pair) {
                return [
                    'lastname'  => $this->normalizeName($pair['lastname'] ?? null),
                    'firstname' => $this->normalizeName($pair['firstname'] ?? null),
                ];
            })
            ->filter(fn (array $pair) => $pair['lastname'] !== null && $pair['firstname'] !== null)
            ->unique(fn (array $pair) => $pair['lastname'] . '|' . $pair['firstname'])
            ->values();

        if ($partnerId <= 0 || $normalizedPairs->isEmpty()) {
            return collect();
        }

        $candidates = $this->baseQuery($partnerId)
            ->with('teams')
            ->where(function ($query) use ($normalizedPairs) {
                foreach ($normalizedPairs as $pair) {
                    $query->orWhere(function ($nested) use ($pair) {
                        $nested
                            ->whereRaw('LOWER(TRIM(lastname)) = ?', [$pair['lastname']])
                            ->whereRaw('LOWER(TRIM(name)) = ?', [$pair['firstname']]);
                    });
                }
            })
            ->orderBy('id')
            ->get();

        $result = collect();

        foreach ($normalizedPairs as $pair) {
            $key = $pair['lastname'] . '|' . $pair['firstname'];
            /** @var User|null $match */
            $match = $candidates->first(function (User $user) use ($pair) {
                return $this->normalizeName($user->lastname) === $pair['lastname']
                    && $this->normalizeName($user->name) === $pair['firstname'];
            });

            if ($match !== null) {
                $result->put($key, $match);
            }
        }

        return $result;
    }

    /**
     * Ключ для сопоставления результата matchMany() с исходной парой имени.
     * Возвращает null, если фамилия или имя пусты после нормализации.
     */
    public function normalizeKey(?string $lastname, ?string $firstname): ?string
    {
        $lastnameNorm = $this->normalizeName($lastname);
        $firstnameNorm = $this->normalizeName($firstname);

        if ($lastnameNorm === null || $firstnameNorm === null) {
            return null;
        }

        return $lastnameNorm . '|' . $firstnameNorm;
    }

    private function baseQuery(int $partnerId)
    {
        return User::query()
            ->where('partner_id', $partnerId)
            ->whereNotNull('lastname')
            ->whereNotNull('name')
            ->whereHas('role', function ($query) {
                $query->where('name', 'user')->where('is_sistem', true);
            });
    }

    private function normalizeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $trimmed = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name) ?? ''));

        return $trimmed !== '' ? $trimmed : null;
    }
}
