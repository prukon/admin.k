<?php

declare(strict_types=1);

namespace App\Services\SchoolLeads;

use App\Enums\SchoolLeadParentMatchReason;
use App\Models\ParentProfile;

final readonly class SchoolLeadParentMatchResult
{
    public function __construct(
        public ParentProfile $parent,
        public SchoolLeadParentMatchReason $reason,
        public int $count,
    ) {
    }

    /**
     * @return array{
     *     parent_id: int,
     *     parent_match_reason: string,
     *     parent_match_count: int,
     *     parent_match_confirmed: null
     * }
     */
    public function toLeadAttributes(): array
    {
        return [
            'parent_id'               => (int) $this->parent->id,
            'parent_match_reason'     => $this->reason->value,
            'parent_match_count'      => $this->count,
            'parent_match_confirmed'  => null,
        ];
    }
}
