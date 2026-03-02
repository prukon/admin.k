@extends('layouts.admin2')
@section('content')
    <div class="main-content">
        <h4 class="pt-3 pb-3  text-start">Установка цен 1</h4>
        <div class="">
            <ul class="nav nav-tabs" id="myTab" role="tablist">
                {{-- Установка цен по месяцам --}}
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $activeTab == 'monthly' ? 'active' : '' }}" href="/admin/setting-prices/monthly"
                        role="tab">По месяцам</a>
                </li>

                {{-- Платежи по ученикам --}}
                <li class="nav-item" role="presentation">
                    <a class="nav-link {{ $activeTab == 'users' ? 'active' : '' }}" href="/admin/setting-prices/users"
                        role="tab">По ученикам</a>
                </li>
            </ul>


            <div class="tab-content">
                @if ($activeTab === 'monthly')
                    @include('admin.SettingPrices.monthly', [
                        'teamPrices' => $teamPrices,
                        'allTeams' => $allTeams,
                        'monthString' => $monthString,
                    ])
                @elseif($activeTab === 'users')
                    @include('admin.SettingPrices.users', [
                        'teamPrices' => $teamPrices,
                        'allTeams' => $allTeams,
                        'monthString' => $monthString,
                    ])
                @endif
            </div>
        </div>
    </div> 
    <!-- Модальное окно логов -->
    @include('includes.logModal')
@endsection
