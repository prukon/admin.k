<?php

declare(strict_types=1);

namespace App\Services\SchoolLeads;

use App\Enums\SchoolLeadParentMatchReason;
use App\Models\ParentProfile;
use App\Support\RuPhone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class SchoolLeadParentMatcher
{
    /**
     * Приоритет: email → телефон (10 цифр без кода страны) → ФИ.
     * При нескольких кандидатах на сработавшем шаге берём первого по id ASC.
     */
    public function match(
        int $partnerId,
        ?string $email,
        ?string $phone,
        ?string $lastname,
        ?string $firstname,
    ): ?SchoolLeadParentMatchResult {
        if ($partnerId <= 0) {
            return null;
        }

        $emailMatch = $this->matchByEmail($partnerId, $email);
        if ($emailMatch !== null) {
            return $emailMatch;
        }

        $phoneMatch = $this->matchByPhone($partnerId, $phone);
        if ($phoneMatch !== null) {
            return $phoneMatch;
        }

        return $this->matchByName($partnerId, $lastname, $firstname);
    }

    private function matchByEmail(int $partnerId, ?string $email): ?SchoolLeadParentMatchResult
    {
        $normalized = $this->normalizeEmail($email);
        if ($normalized === null) {
            return null;
        }

        $matches = $this->baseQuery($partnerId)
            ->whereNotNull('email')
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->orderBy('id')
            ->get();

        return $this->resultFromMatches($matches, SchoolLeadParentMatchReason::Email);
    }

    private function matchByPhone(int $partnerId, ?string $phone): ?SchoolLeadParentMatchResult
    {
        $digits10 = RuPhone::nationalDigits10($phone);
        if ($digits10 === null) {
            return null;
        }

        $candidates = $this->baseQuery($partnerId)
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->where('phone', 'like', '%' . $digits10 . '%')
            ->orderBy('id')
            ->get();

        $matches = $candidates->filter(function (ParentProfile $parent) use ($digits10): bool {
            return RuPhone::nationalDigits10($parent->phone) === $digits10;
        })->values();

        return $this->resultFromMatches($matches, SchoolLeadParentMatchReason::Phone);
    }

    private function matchByName(
        int $partnerId,
        ?string $lastname,
        ?string $firstname,
    ): ?SchoolLeadParentMatchResult {
        $lastnameNorm = $this->normalizeName($lastname);
        $firstnameNorm = $this->normalizeName($firstname);

        if ($lastnameNorm === null || $firstnameNorm === null) {
            return null;
        }

        $matches = $this->baseQuery($partnerId)
            ->whereNotNull('lastname')
            ->whereNotNull('firstname')
            ->whereRaw('LOWER(TRIM(lastname)) = ?', [$lastnameNorm])
            ->whereRaw('LOWER(TRIM(firstname)) = ?', [$firstnameNorm])
            ->orderBy('id')
            ->get();

        return $this->resultFromMatches($matches, SchoolLeadParentMatchReason::Name);
    }

    /**
     * @param  Collection<int, ParentProfile>  $matches
     */
    private function resultFromMatches(
        Collection $matches,
        SchoolLeadParentMatchReason $reason,
    ): ?SchoolLeadParentMatchResult {
        if ($matches->isEmpty()) {
            return null;
        }

        /** @var ParentProfile $first */
        $first = $matches->first();

        return new SchoolLeadParentMatchResult(
            parent: $first,
            reason: $reason,
            count: $matches->count(),
        );
    }

    /**
     * @return Builder<ParentProfile>
     */
    private function baseQuery(int $partnerId): Builder
    {
        return ParentProfile::query()
            ->where('partner_id', $partnerId);
    }

    private function normalizeEmail(?string $email): ?string
    {
        if ($email === null) {
            return null;
        }

        $trimmed = mb_strtolower(trim($email));

        return $trimmed !== '' ? $trimmed : null;
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
