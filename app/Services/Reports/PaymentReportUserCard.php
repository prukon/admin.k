<?php

declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\User;
use App\Services\Chat\ChatService;
use App\Services\Pricing\UserPercentDiscount;
use Illuminate\Support\Collection;

/**
 * Карточка ученика в отчёте «Платежи».
 * Базовые поля совпадают с карточкой контакта в чате, плюс email, дата рождения и скидка.
 */
final class PaymentReportUserCard
{
    public function __construct(
        private readonly ChatService $chat,
    ) {
    }

    /**
     * @return array{
     *     id: int,
     *     avatar: string,
     *     full_name: string,
     *     phone: string,
     *     email: string,
     *     birthday: string,
     *     parent_full_name: string,
     *     parent_phone: string,
     *     parent_email: string,
     *     discount_percent: int|null,
     *     discount_comment: string,
     *     family_siblings: list<string>,
     *     has_family_account: bool,
     *     has_multiple_teams: bool,
     *     is_online: bool,
     *     last_seen_at: string|null,
     *     last_seen_label: string,
     *     team_title: string,
     *     partner_name: string
     * }
     */
    public function payload(User $user): array
    {
        $card = $this->chat->userCard($user);
        $user->loadMissing('parentProfile');

        $percent = UserPercentDiscount::percent($user);
        $hasDiscount = $percent >= 1;

        $card['email'] = trim((string) ($user->email ?? ''));
        $card['parent_email'] = trim((string) ($user->parentProfile?->email ?? ''));
        $card['birthday'] = $user->birthday ? $user->birthday->format('d.m.Y') : '';
        $card['discount_percent'] = $hasDiscount ? $percent : null;
        $card['discount_comment'] = $hasDiscount ? (string) (UserPercentDiscount::comment($user) ?? '') : '';

        $siblings = $this->familySiblingNames($user);
        $card['family_siblings'] = $siblings;
        $card['has_family_account'] = $siblings !== [];
        $card['has_multiple_teams'] = $this->teamCount($user) > 1;

        return $card;
    }

    /**
     * Активные братья и сёстры: тот же партнёр и parent_id, роль user, без самого ученика.
     * Отключённые и удалённые в список не входят — как переключатель семейного кабинета.
     *
     * @return list<string>
     */
    private function familySiblingNames(User $user): array
    {
        $parentId = (int) ($user->parent_id ?? 0);
        $partnerId = (int) ($user->partner_id ?? 0);
        if ($parentId < 1 || $partnerId < 1) {
            return [];
        }

        /** @var Collection<int, User> $siblings */
        $siblings = User::query()
            ->where('partner_id', $partnerId)
            ->where('parent_id', $parentId)
            ->where('is_enabled', true)
            ->whereKeyNot($user->id)
            ->whereHas('role', static fn ($q) => $q->where('name', 'user'))
            ->orderBy('lastname')
            ->orderBy('name')
            ->get();

        return $siblings
            ->map(function (User $sibling): string {
                $name = trim((string) $sibling->full_name);
                if ($name === '') {
                    $name = trim((string) ($sibling->name ?? ''));
                }

                return $name;
            })
            ->filter(static fn (string $name): bool => $name !== '')
            ->values()
            ->all();
    }

    private function teamCount(User $user): int
    {
        $partnerId = (int) ($user->partner_id ?? 0);
        if ($partnerId < 1) {
            return 0;
        }

        return $user->teams()
            ->where('teams.partner_id', $partnerId)
            ->wherePivot('partner_id', $partnerId)
            ->count();
    }
}
