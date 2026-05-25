<ul class="nav nav-tabs" id="contractsSectionTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ ($activeTab ?? 'contracts') === 'contracts' ? 'active' : '' }}"
           href="{{ route('contracts.index') }}"
           role="tab">Договоры</a>
    </li>
    <li class="nav-item" role="presentation">
        <a class="nav-link {{ ($activeTab ?? '') === 'templates' ? 'active' : '' }}"
           href="{{ route('contract-templates.index') }}"
           role="tab">Шаблоны</a>
    </li>
</ul>
