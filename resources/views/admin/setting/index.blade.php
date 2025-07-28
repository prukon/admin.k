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
                    @can('manage-roles')
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
                    @can('setting-payment-systems')
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
                </ul>
                <div class="tab-content">
                @if($activeTab === 'setting')
                    <!-- Контент вкладки пользователей -->
                    @include('admin.setting.setting',
                    ['textForUsers' => $textForUsers,
                    'partnerId' => $partnerId,
                    'isRegistrationActive' => $isRegistrationActive,
                    ])
                @elseif($activeTab === 'rule')
                    <!-- Контент вкладки организаций -->
                    @include('admin.setting.rule',
                    ['roles' => $roles,
                    'permissions' => $permissions,
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
                    @endif
                </div>
            </div>
    </div>
    <!-- Модальное окно логов -->
    @include('includes.logModal')
    <!-- Модальное окно подтверждения удаления -->
    @include('includes.modal.confirmDeleteModal')
    <!-- Модальное окно успешного обновления данных -->
    @include('includes.modal.successModal')
    <!-- Модальное окно ошибки -->
    @include('includes.modal.errorModal')
@endsection
