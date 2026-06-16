<ul class="nav nav-tabs" id="usersSectionTabs" role="tablist">
    @foreach($usersSectionTabs ?? [] as $tab)
        <li class="nav-item" role="presentation">
            <a class="nav-link {{ ($activeTab ?? '') === $tab['id'] ? 'active' : '' }}"
               href="{{ route($tab['route'], $tab['route_params'] ?? []) }}"
               role="tab">{{ $tab['label'] }}</a>
        </li>
    @endforeach
</ul>
