<?php

declare(strict_types=1);

namespace App\Services\Schedule\TrainerSalary;

use App\Models\TrainerSalaryPeriod;
use App\Services\PartnerContext;
use App\Services\Schedule\TrainerSalary\Schemes\Classic\ClassicTrainerSalaryScheme;
use Illuminate\Support\Facades\DB;

final class TrainerSalarySchemeResolver
{
    /** @var array<string, int>|null */
    private ?array $permissionIdsByName = null;

    public function __construct(
        private readonly TrainerSalarySchemeRegistry $registry,
        private readonly PartnerContext $partnerContext,
    ) {
    }

    public function hasActiveScheme(?int $partnerId = null): bool
    {
        return $this->activeScheme($partnerId) !== null;
    }

    public function activeScheme(?int $partnerId = null): ?TrainerSalaryScheme
    {
        $partnerId ??= $this->partnerContext->partnerId();
        if ($partnerId === null || $partnerId <= 0) {
            return null;
        }

        $grantedIds = $this->grantedSchemePermissionIds($partnerId);
        if ($grantedIds === []) {
            return null;
        }

        foreach ($this->registry->all() as $scheme) {
            $permissionId = $this->permissionId($scheme->permissionName());
            if ($permissionId !== null && in_array($permissionId, $grantedIds, true)) {
                return $scheme;
            }
        }

        return null;
    }

    public function requireActiveScheme(?int $partnerId = null): TrainerSalaryScheme
    {
        $scheme = $this->activeScheme($partnerId) ?? $this->superadminFallbackScheme();
        if ($scheme === null) {
            abort(403);
        }

        return $scheme;
    }

    public function schemeForPeriod(TrainerSalaryPeriod $period): TrainerSalaryScheme
    {
        $code = trim((string) ($period->scheme_code ?? ''));
        if ($code === '') {
            $code = ClassicTrainerSalaryScheme::CODE;
        }

        try {
            return $this->registry->get($code);
        } catch (\InvalidArgumentException) {
            return $this->registry->get(ClassicTrainerSalaryScheme::CODE);
        }
    }

    public function schemeForPartnerPeriod(int $partnerId, ?int $year, ?int $month): ?TrainerSalaryScheme
    {
        if ($year !== null && $month !== null && $year > 0 && $month >= 1 && $month <= 12) {
            $period = TrainerSalaryPeriod::query()
                ->where('partner_id', $partnerId)
                ->where('year', $year)
                ->where('month', $month)
                ->first();

            if ($period !== null && $this->periodHasSnapshots($period)) {
                return $this->schemeForPeriod($period);
            }
        }

        return $this->activeScheme($partnerId) ?? $this->superadminFallbackScheme();
    }

    /**
     * Superadmin обходит права схем так же, как Gate::before — остальные abilities.
     * Если школе схема ещё не выдана, для его работы берём первую схему каталога (classic).
     */
    private function superadminFallbackScheme(): ?TrainerSalaryScheme
    {
        if (! $this->partnerContext->isSuperAdmin()) {
            return null;
        }

        $schemes = $this->registry->all();

        return $schemes[0] ?? null;
    }

    private function periodHasSnapshots(TrainerSalaryPeriod $period): bool
    {
        return $period->snapshots()->exists();
    }

    /**
     * @return list<int>
     */
    private function grantedSchemePermissionIds(int $partnerId): array
    {
        $permissionIds = array_values(array_filter($this->permissionIdsByName()));
        if ($permissionIds === []) {
            return [];
        }

        return DB::table('permission_role')
            ->where('partner_id', $partnerId)
            ->whereIn('permission_id', $permissionIds)
            ->pluck('permission_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function permissionId(string $name): ?int
    {
        $id = $this->permissionIdsByName()[$name] ?? null;

        return $id !== null && $id > 0 ? $id : null;
    }

    /**
     * @return array<string, int>
     */
    private function permissionIdsByName(): array
    {
        if ($this->permissionIdsByName !== null) {
            return $this->permissionIdsByName;
        }

        $names = $this->registry->permissionNames();
        if ($names === []) {
            $this->permissionIdsByName = [];

            return $this->permissionIdsByName;
        }

        $this->permissionIdsByName = DB::table('permissions')
            ->whereIn('name', $names)
            ->pluck('id', 'name')
            ->map(fn ($id) => (int) $id)
            ->all();

        return $this->permissionIdsByName;
    }
}
