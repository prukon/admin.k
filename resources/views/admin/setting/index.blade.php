@extends('layouts.admin2')
@section('content')
    <div class=" main-content">
        <h4 class="pt-3 pb-3  text-start">Настройки</h4>
            {{--<div class="justify-content-md-center">--}}
            <div class="">
                <!-- Вкладки -->
                <ul class="nav nav-tabs" id="paymentTabs" role="tablist">

                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab == 'setting' ? 'active' : '' }}"
                           href="{{ route('admin.setting.setting') }}"
                           id="setting-tab"
                           role="tab"
                           aria-controls="setting"
                           aria-selected="{{ $activeTab == 'setting' ? 'true' : 'false' }}">
                            Общие
                        </a>
                    </li>


                    @can('settings-roles-view')
                    <li class="nav-item">
                        <a class="nav-link {{ $activeTab == 'rule' ? 'active' : '' }}"
                           href="{{ route('admin.setting.rule') }}"
                           id="rule-tab"
                           role="tab"
                           aria-controls="rule"
                           aria-selected="{{ $activeTab == 'rule' ? 'true' : 'false' }}">
                            Права и роли
                        </a>
                    </li>
                    @endcan
                    @can('settings-paymentSystems-view')
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab == 'paymentSystem' ? 'active' : '' }}"
                               href="{{ route('admin.setting.paymentSystem') }}"
                               id="paymentSystem-tab"
                               role="tab"
                               aria-controls="paymentSystem"
                               aria-selected="{{ $activeTab == 'paymentSystem' ? 'true' : 'false' }}">
                                Платежные системы
                            </a>
                        </li>
                    @endcan

                    @can('settings.commission')
                        <li class="nav-item">
                            <a class="nav-link {{ $activeTab == 'tbankCommissions' ? 'active' : '' }}"
                               href="{{ route('admin.setting.tbankCommissions') }}"
                               id="tbankCommissions-tab"
                               role="tab"
                               aria-controls="tbankCommissions"
                               aria-selected="{{ $activeTab == 'tbankCommissions' ? 'true' : 'false' }}">
                                Комиссии Т-Банк
                            </a>
                        </li>
                    @endcan
                </ul>
                <div class="tab-content">
                @if($activeTab === 'setting')
                    <!-- Контент вкладки пользователей -->
                    @include('admin.setting.setting',
                    ['textForUsers' => $textForUsers,
                    'partnerId' => $partnerId,
                    'isRegistrationActive' => $isRegistrationActive,
                    'force2faAdmins' => $force2faAdmins,

                    ])
                @elseif($activeTab === 'rule')
                    <!-- Контент вкладки прав -->
                    @include('admin.setting.rule',
                    ['roles' => $roles,
                    'permissions' => $permissions,
                    'groups'      => $groups,   // ← добавили
                    ])
                @elseif($activeTab === 'paymentSystem')
                    <!-- Контент вкладки организаций -->
                        @include('admin.setting.paymentSystem',
                        ['paymentSystems' => $paymentSystems,
                        'partners' => $partners,
                        'curUser' => $curUser,
                        'robokassa' => $robokassa,
                        'tbank' => $tbank,
                        ])
                    @elseif($activeTab === 'tbankCommissions')
                        @include('admin.setting.tbankCommissions', [
                            'mode' => $mode ?? 'list',
                            'rules' => $rules ?? null,
                            'partners' => $partners ?? null,
                            'rule' => $rule ?? null,
                        ])
                    @endif
                </div>
            </div>
    </div>
    <!-- Модальное окно логов -->
    @include('includes.logModal')
@endsection
