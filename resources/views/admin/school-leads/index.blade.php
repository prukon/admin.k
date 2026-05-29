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

            @can('schoolLeadLanding.view')
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ ($activeTab ?? '') === 'landing' ? 'active' : '' }}"
                       href="{{ route('admin.school-leads.landing') }}"
                       role="tab">Страница заявки</a>
                </li>
            @endcan
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
            @elseif(($activeTab ?? '') === 'landing')
                @include('admin.school-leads.tabs.landing')
            @elseif(($activeTab ?? '') === 'widget')
                @include('admin.school-leads.tabs.widget')
            @endif
        </div>
    </div>
@endsection

