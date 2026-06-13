<ul class="nav nav-tabs mb-3" id="directoriesSectionTabs" role="tablist">
    @can('districts.view')
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? '') === 'districts' ? 'active' : '' }}"
               href="{{ route('admin.districts.index') }}"
               role="tab">Районы</a>
        </li>
    @endcan

    @can('locations.view')
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? '') === 'objects' ? 'active' : '' }}"
               href="{{ route('admin.locations.index') }}"
               role="tab">Объекты</a>
        </li>
    @endcan
</ul>
