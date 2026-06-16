<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Services\Users\UsersSectionTabsResolver;

trait RendersUsersSectionTabs
{
    /**
     * @return array{activeTab: string, usersSectionTabs: list<array{id: string, label: string, route: string, route_params: array<string, string>}>}
     */
    protected function usersSectionViewData(string $activeTab): array
    {
        return [
            'activeTab'         => $activeTab,
            'usersSectionTabs'  => app(UsersSectionTabsResolver::class)->resolve(),
        ];
    }
}
