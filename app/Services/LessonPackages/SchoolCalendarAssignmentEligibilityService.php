<?php

declare(strict_types=1);

namespace App\Services\LessonPackages;

use App\Models\LessonPackage;
use App\Models\User;
use App\Models\UserLessonPackage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Единые условия отбора назначений для календаря школы: списки в модалках и проверка «есть ли кому привязать».
 */
final class SchoolCalendarAssignmentEligibilityService
{
    /**
     * Участник партнёра и активен (для выбора в календаре).
     */
    public function userBelongsToPartnerAndEnabled(int $partnerId, int $userId): bool
    {
        if ($userId < 1) {
            return false;
        }

        return User::query()
            ->where('partner_id', $partnerId)
            ->whereKey($userId)
            ->where('is_enabled', 1)
            ->exists();
    }

    /**
     * Условия на строку user_lesson_packages для гибкого абонемента в календаре школы.
     *
     * @param  Builder<UserLessonPackage>|Relation  $query
     * @return Builder<UserLessonPackage>|Relation
     */
    public function constrainFlexibleAssignable(Builder|Relation $query, int $partnerId): Builder|Relation
    {
        return $query
            ->whereHas('lessonPackage', fn ($lq) => $lq->where('partner_id', $partnerId)->where('schedule_type', 'flexible'))
            ->where('lessons_total', '>', 0)
            ->whereRaw(
                '(SELECT COUNT(*) FROM user_team_schedule_slots WHERE user_team_schedule_slots.user_lesson_package_id = user_lesson_packages.id) < user_lesson_packages.lessons_total'
            );
    }

    /**
     * @param  Builder<UserLessonPackage>|Relation  $query
     * @return Builder<UserLessonPackage>|Relation
     */
    public function constrainFixedAssignable(Builder|Relation $query, int $partnerId): Builder|Relation
    {
        return $query
            ->whereHas('lessonPackage', fn ($lq) => $lq->where('partner_id', $partnerId)->where('schedule_type', 'fixed'))
            ->whereNull('starts_at')
            ->whereNull('ends_at')
            ->where('lessons_total', '>', 0);
    }

    /**
     * Разовое занятие: ещё можно записать в календарь (нет строки user_team_schedule_slots по этому ULP).
     *
     * @param  Builder<UserLessonPackage>|Relation  $query
     * @return Builder<UserLessonPackage>|Relation
     */
    public function constrainSingleLessonAssignable(Builder|Relation $query, int $partnerId): Builder|Relation
    {
        return $query
            ->whereHas('lessonPackage', fn ($lq) => $lq->where('partner_id', $partnerId)->where('schedule_type', 'no_schedule'))
            ->where('lessons_total', '>', 0)
            ->whereRaw(
                '(SELECT COUNT(*) FROM user_team_schedule_slots WHERE user_team_schedule_slots.user_lesson_package_id = user_lesson_packages.id) < user_lesson_packages.lessons_total'
            );
    }

    /**
     * Активный ученик партнёра с доступом к назначению в этом запросе.
     *
     * @param  Builder<UserLessonPackage>|Relation  $query
     * @return Builder<UserLessonPackage>|Relation
     */
    public function constrainEnabledPartnerUser(Builder|Relation $query, int $partnerId): Builder|Relation
    {
        return $query->whereHas('user', fn ($q) => $q->where('partner_id', $partnerId)->where('is_enabled', 1));
    }

    public function flexibleAssignmentsQuery(int $partnerId): Builder
    {
        $q = UserLessonPackage::query()
            ->with(['lessonPackage:id,name,schedule_type']);

        $this->constrainEnabledPartnerUser($q, $partnerId);

        return $this->constrainFlexibleAssignable($q, $partnerId)->orderByDesc('id');
    }

    public function fixedAssignmentsQuery(int $partnerId): Builder
    {
        $q = UserLessonPackage::query()
            ->with(['lessonPackage:id,name,schedule_type']);

        $this->constrainEnabledPartnerUser($q, $partnerId);

        return $this->constrainFixedAssignable($q, $partnerId)->orderByDesc('id');
    }

    public function singleLessonAssignmentsQuery(int $partnerId): Builder
    {
        $q = UserLessonPackage::query()
            ->with(['lessonPackage:id,name,schedule_type']);

        $this->constrainEnabledPartnerUser($q, $partnerId);

        return $this->constrainSingleLessonAssignable($q, $partnerId)->orderByDesc('id');
    }

    public function hasAnyFlexible(int $partnerId): bool
    {
        return $this->flexibleAssignmentsQuery($partnerId)->exists();
    }

    public function hasAnyFixed(int $partnerId): bool
    {
        return $this->fixedAssignmentsQuery($partnerId)->exists();
    }

    public function hasAnySingleLesson(int $partnerId): bool
    {
        return $this->singleLessonAssignmentsQuery($partnerId)->exists();
    }

    /**
     * Активные шаблоны абонементов «разовое занятие» партнёра.
     *
     * @return \Illuminate\Database\Eloquent\Builder<LessonPackage>
     */
    public function singleLessonTemplatesQuery(int $partnerId): \Illuminate\Database\Eloquent\Builder
    {
        return LessonPackage::query()
            ->where('partner_id', $partnerId)
            ->where('schedule_type', 'no_schedule')
            ->where('is_active', true)
            ->where('lessons_count', '>', 0)
            ->orderBy('name');
    }

    public function hasAnySingleLessonTemplate(int $partnerId): bool
    {
        return $this->singleLessonTemplatesQuery($partnerId)->exists();
    }

    public function formatSingleLessonAssignmentLabel(UserLessonPackage $ulp): string
    {
        $name = $ulp->lessonPackage?->name ?? 'Разовое занятие';

        return $name.' №'.(int) $ulp->id.' — осталось '.(int) $ulp->lessons_remaining;
    }

    public function formatFlexibleAssignmentLabel(UserLessonPackage $ulp): string
    {
        $name = $ulp->lessonPackage?->name ?? 'Абонемент';

        return $name.' №'.(int) $ulp->id.' — осталось '.(int) $ulp->lessons_remaining;
    }
}
