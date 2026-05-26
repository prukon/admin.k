@extends('layouts.admin2')

@section('content')
    <div class="main-content text-start">
        <h4 class="pt-3 pb-3 text-start">Партнеры</h4>

        <ul class="nav nav-tabs" id="partnersSectionTabs" role="tablist">
            @can('partner.view')
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ ($activeTab ?? 'partners') === 'partners' ? 'active' : '' }}"
                       href="{{ route('admin.partner.index') }}"
                       role="tab">Партнеры</a>
                </li>
            @endcan

            @can('partnerLeads.view')
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ ($activeTab ?? '') === 'leads' ? 'active' : '' }}"
                       href="{{ route('admin.partner-leads') }}"
                       role="tab">Лиды</a>
                </li>
            @endcan

            @can('tbank.payouts.manage')
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ ($activeTab ?? '') === 'payouts' ? 'active' : '' }}"
                       href="{{ route('admin.tinkoff.payouts.index') }}"
                       role="tab">Выплаты T‑Bank</a>
                </li>
            @endcan
        </ul>

        <div class="tab-content">
            @if (($activeTab ?? 'partners') === 'partners')
                @include('admin.partners.tabs.partners')
            @elseif (($activeTab ?? '') === 'leads')
                @include('admin.partners.tabs.leads')
            @elseif (($activeTab ?? '') === 'payouts')
                @include('admin.partners.tabs.payouts')
            @endif
        </div>
    </div>
@endsection
