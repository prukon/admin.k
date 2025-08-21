@extends('layouts.admin2')
@section('content')
    <script>
        // Передача данных текущего пользователя из Blade в JavaScript
        let currentUserName = "{{ auth()->user()->name }}";
        let currentUserRole = "{{ auth()->user()->role }}";
    </script>


    <div class="main-content text-start">
        <h4 class="pt-3 pb-3">Учетная запись</h4>
        {{--<div class="container-fluid">--}}

            <div class="">
                <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <a class="nav-link {{ $activeTab == 'user' ? 'active' : '' }}"
                           href="/account-settings/users/{{ $user->id }}/edit"
                           role="tab"> {{auth()->user()->role->label}}
                        </a>
                    </li>
                    <!-- Вкладки для всех партнёров пользователя -->
                    @can('account-partner-view')
                        @foreach ($partners as $partner)
                            <li class="nav-item" role="presentation">
                                <a class="nav-link {{ $activeTab == 'partner' ? 'active' : '' }}"
                                   href="/account-settings/partner/{{ $partner->id }}/edit"
                                   role="tab"> Организация
                                </a>
                            </li>
                        @endforeach
                    @endcan
                </ul>


                <div class="tab-content">
                @if($activeTab === 'user')
                    <!-- Контент вкладки пользователей -->
                    @include('account.users',
                    ['user' => $user,
                    'partners' => $partners,
                    'allTeams' => $allTeams,
                    'fields' => $fields,
                    'userFieldValues' => $userFieldValues,
                    'editableFields' => $editableFields,
                    ])
                @elseif($activeTab === 'partner')
                    <!-- Контент вкладки организаций -->
                        @include('account.organizations',
                        ['user' => $user,
                        'partners' => $partners,
                        'allTeams' => $allTeams,
                        ])
                    @endif
                </div>
            {{--</div>--}}

        </div>
    </div>

    <!-- Модальное окно подтверждения удаления -->
{{--    @include('includes.modal.confirmDeleteModal')--}}

    <!-- Модальное окно успешного обновления данных -->
{{--    @include('includes.modal.successModal')--}}

    <!-- Модальное окно ошибки -->
{{--    @include('includes.modal.errorModal')--}}

@endsection
