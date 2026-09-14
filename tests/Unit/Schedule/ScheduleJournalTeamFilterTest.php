<?php

declare(strict_types=1);

namespace Tests\Unit\Schedule;

use App\Support\Schedule\ScheduleJournalTeamFilter;
use PHPUnit\Framework\TestCase;

final class ScheduleJournalTeamFilterTest extends TestCase
{
    public function test_empty_tokens_mean_all_groups(): void
    {
        $filter = ScheduleJournalTeamFilter::fromTokens([]);

        $this->assertTrue($filter->isAll());
        $this->assertFalse($filter->isNoneOnly());
        $this->assertFalse($filter->isSingleTeam());
        $this->assertSame('all', $filter->legacyScalar());
        $this->assertSame([], $filter->tokens());
        $this->assertTrue($filter->usesAllGroupsPresentation());
    }

    public function test_legacy_scalar_and_none(): void
    {
        $none = ScheduleJournalTeamFilter::fromMixed('none');
        $this->assertTrue($none->isNoneOnly());
        $this->assertSame('none', $none->legacyScalar());
        $this->assertSame(['none'], $none->tokens());

        $one = ScheduleJournalTeamFilter::fromMixed('12');
        $this->assertTrue($one->isSingleTeam());
        $this->assertSame(12, $one->singleTeamId());
        $this->assertSame('12', $one->legacyScalar());
        $this->assertFalse($one->usesAllGroupsPresentation());
    }

    public function test_multiple_ids_are_or_and_all_like_presentation(): void
    {
        $filter = ScheduleJournalTeamFilter::fromTokens(['3', 'none', '3', '5']);

        $this->assertFalse($filter->isAll());
        $this->assertTrue($filter->includeNone);
        $this->assertSame([3, 5], $filter->teamIds);
        $this->assertSame(['none', '3', '5'], $filter->tokens());
        $this->assertSame('all', $filter->legacyScalar());
        $this->assertTrue($filter->usesAllGroupsPresentation());
    }

    public function test_index_validated_prefers_team_ids(): void
    {
        $fromIds = ScheduleJournalTeamFilter::fromIndexValidated([
            'team' => '9',
            'team_ids' => ['2', '4'],
        ]);
        $this->assertSame([2, 4], $fromIds->teamIds);

        $fromLegacy = ScheduleJournalTeamFilter::fromIndexValidated(['team' => '9']);
        $this->assertSame([9], $fromLegacy->teamIds);
    }

    public function test_context_team_id_prefers_single_then_intersection(): void
    {
        $single = ScheduleJournalTeamFilter::fromTokens(['10']);
        $this->assertSame(10, $single->contextTeamId([7, 10]));

        $multi = ScheduleJournalTeamFilter::fromTokens(['4', '8']);
        $this->assertSame(8, $multi->contextTeamId([8, 1]));
        $this->assertSame(1, $multi->contextTeamId([1, 2]));
        $this->assertNull($multi->contextTeamId([]));
    }
}
