<?php

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractTemplate;
use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Привязка шаблона абонемента к договору: каталог, снимок, плейсхолдеры.
 */
class ContractLessonPackageBinder
{
    public const PERMISSION = 'contracts.lessonPackage.bind';

    public const ASSIGNED_SUFFIX = ' (установлен)';

    public const KEY_NAME = 'package_name';

    public const KEY_PRICE = 'package_price';

    public const KEY_LESSONS_COUNT = 'package_lessons_count';

    public const KEY_LESSONS_PER_WEEK = 'package_lessons_per_week';

    public const KEY_DURATION_MINUTES = 'package_lesson_duration_minutes';

    public const KEY_LESSON_PRICE = 'package_lesson_price';

    /**
     * @return list<string>
     */
    public static function placeholderKeys(): array
    {
        return [
            self::KEY_NAME,
            self::KEY_PRICE,
            self::KEY_LESSONS_COUNT,
            self::KEY_LESSONS_PER_WEEK,
            self::KEY_DURATION_MINUTES,
            self::KEY_LESSON_PRICE,
        ];
    }

    public function canBind(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user?->can(self::PERMISSION) ?? false;
    }

    public function latestAssignedPackageId(int $partnerId, int $userId): ?int
    {
        if ($partnerId <= 0 || $userId <= 0) {
            return null;
        }

        $id = UserLessonPackage::query()
            ->join('lesson_packages', 'lesson_packages.id', '=', 'user_lesson_packages.lesson_package_id')
            ->where('user_lesson_packages.user_id', $userId)
            ->where('lesson_packages.partner_id', $partnerId)
            ->orderByDesc('user_lesson_packages.created_at')
            ->orderByDesc('user_lesson_packages.id')
            ->value('user_lesson_packages.lesson_package_id');

        return $id !== null ? (int) $id : null;
    }

    /**
     * @return array{packages: list<array{id: int, name: string, is_assigned: bool}>, selected_id: int|null}
     */
    public function optionsForStudent(int $partnerId, ?int $userId): array
    {
        $assignedId = $userId ? $this->latestAssignedPackageId($partnerId, $userId) : null;

        $packages = LessonPackage::query()
            ->where('partner_id', $partnerId)
            ->where(function ($query) use ($assignedId) {
                $query->where('is_active', true);
                if ($assignedId) {
                    $query->orWhere('id', $assignedId);
                }
            })
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name']);

        $items = [];
        foreach ($packages as $package) {
            $isAssigned = $assignedId !== null && (int) $package->id === $assignedId;
            $items[] = [
                'id'          => (int) $package->id,
                'name'        => (string) $package->name,
                'is_assigned' => $isAssigned,
            ];
        }

        usort($items, static function (array $left, array $right): int {
            if ($left['is_assigned'] !== $right['is_assigned']) {
                return $left['is_assigned'] ? -1 : 1;
            }

            $byName = strcasecmp($left['name'], $right['name']);

            return $byName !== 0 ? $byName : ($left['id'] <=> $right['id']);
        });

        return [
            'packages'    => $items,
            'selected_id' => $assignedId,
        ];
    }

    /**
     * @param array{name?: string, is_assigned?: bool} $item
     */
    public function optionLabel(array $item): string
    {
        $name = trim((string) ($item['name'] ?? ''));
        if ($name === '') {
            return '';
        }

        return !empty($item['is_assigned']) ? $name.self::ASSIGNED_SUFFIX : $name;
    }

    public function resolveSelectablePackage(int $partnerId, int $packageId, ?int $userId = null): ?LessonPackage
    {
        if ($partnerId <= 0 || $packageId <= 0) {
            return null;
        }

        $assignedId = $userId ? $this->latestAssignedPackageId($partnerId, $userId) : null;

        return LessonPackage::query()
            ->where('partner_id', $partnerId)
            ->whereKey($packageId)
            ->where(function ($query) use ($assignedId) {
                $query->where('is_active', true);
                if ($assignedId) {
                    $query->orWhere('id', $assignedId);
                }
            })
            ->first();
    }

    /**
     * @return array{
     *     name: string,
     *     price_cents: int,
     *     lessons_count: int|null,
     *     lessons_per_week: int|null,
     *     lesson_duration_minutes: int|null,
     *     lesson_price_cents: int|null
     * }
     */
    public function snapshot(LessonPackage $package): array
    {
        return [
            'name'                      => (string) $package->name,
            'price_cents'               => (int) $package->price_cents,
            'lessons_count'             => $this->nullableInt($package->lessons_count),
            'lessons_per_week'          => $this->nullableInt($package->lessons_per_week),
            'lesson_duration_minutes'   => $this->nullableInt($package->lesson_duration_minutes),
            'lesson_price_cents'        => $this->nullableInt($package->lesson_price_cents),
        ];
    }

    /**
     * @param array<string, mixed>|null $snapshot
     * @return array<string, string>
     */
    public function placeholderValues(?array $snapshot): array
    {
        $empty = [
            self::KEY_NAME               => '',
            self::KEY_PRICE              => '',
            self::KEY_LESSONS_COUNT      => '',
            self::KEY_LESSONS_PER_WEEK   => '',
            self::KEY_DURATION_MINUTES   => '',
            self::KEY_LESSON_PRICE       => '',
        ];

        if (!is_array($snapshot) || $snapshot === []) {
            return $empty;
        }

        $lessonPriceCents = $this->nullableInt($snapshot['lesson_price_cents'] ?? null);

        return [
            self::KEY_NAME              => trim((string) ($snapshot['name'] ?? '')),
            self::KEY_PRICE             => $this->formatMoney((int) ($snapshot['price_cents'] ?? 0)),
            self::KEY_LESSONS_COUNT     => $this->formatInt($snapshot['lessons_count'] ?? null),
            self::KEY_LESSONS_PER_WEEK  => $this->formatInt($snapshot['lessons_per_week'] ?? null),
            self::KEY_DURATION_MINUTES  => $this->formatInt($snapshot['lesson_duration_minutes'] ?? null),
            self::KEY_LESSON_PRICE      => $lessonPriceCents === null ? '' : $this->formatMoney($lessonPriceCents),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function placeholderValuesForContract(Contract $contract): array
    {
        $snapshot = $contract->package_snapshot;

        return $this->placeholderValues(is_array($snapshot) ? $snapshot : null);
    }

    public function snapshotName(?array $snapshot): ?string
    {
        $name = trim((string) ($snapshot['name'] ?? ''));

        return $name !== '' ? $name : null;
    }

    public function templateRequiresPackage(ContractTemplate $template): bool
    {
        $schema = $template->currentVersion?->fields_schema;

        return is_array($schema) && ContractTemplateVariablePresets::schemaUsesPackageFields($schema);
    }

    /**
     * @return Collection<int, ContractTemplate>
     */
    public function activeTemplatesForPartner(int $partnerId): Collection
    {
        $templates = ContractTemplate::query()
            ->forPartner($partnerId)
            ->active()
            ->whereNotNull('current_version_id')
            ->with('currentVersion')
            ->orderBy('title')
            ->get(['id', 'title', 'current_version_id']);

        $templates->each(function (ContractTemplate $template): void {
            $template->setAttribute('requires_lesson_package', $this->templateRequiresPackage($template));
        });

        return $templates;
    }

    private function formatMoney(int $cents): string
    {
        return Money::formatRub($cents).' руб.';
    }

    private function formatInt(mixed $value): string
    {
        $int = $this->nullableInt($value);

        return $int === null ? '' : (string) $int;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }
}
