@extends('layouts.admin2')

@section('content')
    <div class="main-content">
        <h4 class="pt-3 pb-3 text-start">Заявки с сайта</h4>

        <ul class="nav nav-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link {{ ($activeTab ?? 'leads') === 'leads' ? 'active' : '' }}"
                   href="{{ route('admin.school-leads') }}"
                   role="tab">Заявки</a>
            </li>

            @can('schoolWidget.view')
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ ($activeTab ?? '') === 'widget' ? 'active' : '' }}"
                       href="{{ route('admin.school-leads.widget') }}"
                       role="tab">Виджет для сайта</a>
                </li>
            @endcan
        </ul>

        <div class="tab-content">
            @if (($activeTab ?? 'leads') === 'leads')
                @include('admin.school-leads.tabs.leads')
            @elseif(($activeTab ?? '') === 'widget')
                @include('admin.school-leads.tabs.widget')
            @endif
        </div>
    </div>
@endsection

