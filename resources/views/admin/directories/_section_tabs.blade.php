<ul class="nav nav-tabs mb-3" id="directoriesSectionTabs" role="tablist">
    @can('groups.view')
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? '') === 'groups' ? 'active' : '' }}"
               href="{{ route('admin.team.index') }}"
               role="tab">Группы</a>
        </li>
    @endcan

    @can('locations.view')
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? '') === 'objects' ? 'active' : '' }}"
               href="{{ route('admin.locations.index') }}"
               role="tab">Объекты</a>
        </li>
    @endcan

    @can('districts.view')
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? '') === 'districts' ? 'active' : '' }}"
               href="{{ route('admin.districts.index') }}"
               role="tab">Районы</a>
        </li>
    @endcan

    @can('sport_types.view')
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? '') === 'sport-types' ? 'active' : '' }}"
               href="{{ route('admin.sport-types.index') }}"
               role="tab">Виды спорта</a>
        </li>
    @endcan

    @can('legal_entities.view')
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? '') === 'legal-entities' ? 'active' : '' }}"
               href="{{ route('admin.legal-entities.index') }}"
               role="tab">Юр. лица</a>
        </li>
    @endcan
</ul>
