<?php

namespace App\Support;

use Illuminate\Contracts\Auth\Authenticatable;

final class DirectoriesMenu
{
    /** @var list<array{permission: string, label: string, route: string}> */
    private const ENTRIES = [
        ['permission' => 'groups.view', 'label' => 'Группы', 'route' => 'admin.team.index'],
        ['permission' => 'locations.view', 'label' => 'Объекты', 'route' => 'admin.locations.index'],
        ['permission' => 'districts.view', 'label' => 'Районы', 'route' => 'admin.districts.index'],
        ['permission' => 'sport_types.view', 'label' => 'Виды спорта', 'route' => 'admin.sport-types.index'],
        ['permission' => 'legal_entities.view', 'label' => 'Юр. лица', 'route' => 'admin.legal-entities.index'],
    ];

    /**
     * @return array{url: string, label: string}|null
     */
    public static function forUser(?Authenticatable $user): ?array
    {
        if ($user === null) {
            return null;
        }

        if (! $user->can('directories.view')) {
            return null;
        }

        $available = array_values(array_filter(
            self::ENTRIES,
            static fn (array $entry): bool => $user->can($entry['permission'])
        ));

        if ($available === []) {
            return null;
        }

        $primary = $available[0];

        return [
            'url'   => route($primary['route']),
            'label' => count($available) === 1 ? $primary['label'] : 'Справочники',
        ];
    }
}
