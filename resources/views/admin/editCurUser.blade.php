@extends('layouts.admin2')
@section('content')
    <script>
        // Передача данных текущего пользователя из Blade в JavaScript
        let currentUserName = "{{ auth()->user()->name }}";
        let currentUserRole = "{{ auth()->user()->role }}";
    </script>
    </div>

    <div class="container-fluid main-content" xmlns="http://www.w3.org/1999/html">
        <h4 class="pt-3 pb-3  text-start">Учетная запись</h4>
        <div class="container-fluid">

            <div class="row justify-content-md-center">
                <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">

                        <a class="nav-link {{ $activeTab == 'user' ? 'active' : '' }}"
                           href="/admin/account-settings/users/{{ $user->id }}/edit"
                           role="tab"> Администратор
                        </a>
                    </li>

                    <!-- Вкладки для всех партнёров пользователя -->
                    @foreach ($partners as $partner)
                        <li class="nav-item" role="presentation">
                            <a class="nav-link {{ $activeTab == 'partner' ? 'active' : '' }}"
                               href="/admin/account-settings/partner/{{ $partner->id }}/edit"
                               role="tab"> Организация
                                {{--                              {{ $partner->title }}--}}
                            </a>
                        </li>
                    @endforeach

                </ul>

                {{--ВКЛАДКА ЮЗЕР--}}
                <div class="tab-content" id="myTabContent">
                    <div class="tab-pane fade {{ $activeTab == 'user' ? 'show active' : '' }}" role="tabpanel">
                        <div class="container-fluid">
                            <script src="{{ asset('js/dashboard-ajax.js') }}"></script>
                            <div class="col-md-12 main-content user-data text-start">
                                {{--<h4 class="pt-3 pb-3">Редактирование администратора</h4>--}}
                                <div class="row">
                                    {{--Аватар--}}
                                    <div class="col-12 col-lg-3 d-flex flex-column align-items-center">

                                        <div class="avatar_wrapper d-flex align-items-center justify-content-center">
                                            <img id='confirm-img'
                                                 @if ($user->image_crop)
                                                 src="{{ asset('storage/avatars/' . $user->image_crop) }}"
                                                 alt="{{ $user->image_crop }}"
                                                 @else
                                                 src="/img/default.png" alt="Аватар по умолчанию"
                                                    @endif
                                            >
                                        </div>
                                        <div class='container-form'>
                                            <input id='selectedFile' class="disp-none" type='file'
                                                   accept=".png, .jpg, .jpeg, .svg">
                                            <button id="upload-photo" class="btn-primary btn">Выбрать фото...</button>
                                        </div>
                                    </div>
                                    {{--Данные пользователя--}}
                                    <div class="col-12 col-lg-6 user-data-wrap mb-3">
                                        <form action="{{ route('admin.cur.user.update', $user->id)}}" method="post">
                                            {{-- Токен (система защиты) необходим при использовании любого роута кроме get. --}}
                                            @csrf
                                            @method('patch')

                                            {{-- Поле "Имя" --}}
                                            <div class="mb-3">
                                                <label for="name" class="form-label">Имя*</label>
                                                <input type="text" name="name" class="form-control" id="name"
                                                       value="{{ old('name', $user->name) }}">
                                                @error('name')
                                                <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            {{-- Поле "Дата рождения" --}}
                                            <div class="mb-3">
                                                <label for="birthday" class="form-label">Дата рождения</label>
                                                {{--                        <input type="date" name="birthday" class="form-control" id="birthday" value="{{ old('birthday', $user->birthday) }}">--}}
                                                <input type="date" name="birthday" class="form-control" id="birthday"
                                                       value="{{ old('birthday', $user->birthday) }}"
                                                       max="{{ date('Y-m-d') }}">

                                                @error('birthday')
                                                <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            {{-- Поле "Группа" --}}
                                            <div class="mb-3">
                                                <label for="team" class="form-label">Группа</label>
                                                <select class="form-control" id="team" name="team_id">
                                                    <option value="" {{ old('team_id', $user->team_id) == null ? 'selected' : '' }}>
                                                        Без группы
                                                    </option>
                                                    @foreach($allTeams as $team)
                                                        <option
                                                                {{ old('team_id', $user->team_id) == $team->id ? 'selected' : '' }}
                                                                value="{{ $team->id }}">{{ $team->title }}</option>
                                                    @endforeach
                                                </select>
                                                @error('team_id')
                                                <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            {{-- Поле "Email" --}}
                                            <div class="mb-3">
                                                <label for="email" class="form-label">Адрес электронной почты*</label>
                                                <input name="email" type="email" class="form-control" id="email"
                                                       placeholder="name@example.com"
                                                       value="{{ old('email', $user->email) }}">
                                                @error('email')
                                                <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            {{-- Блок изменения пароля --}}
                                            <div class="buttons-wrap change-pass-wrap" id="change-pass-wrap"
                                                 style="display: none;">
                                                <div class="d-flex align-items-center mt-3">
                                                    <div class="position-relative">
                                                        <input type="password" id="new-password" class="form-control"
                                                               placeholder="Новый пароль">
                                                        <span toggle="#new-password"
                                                              class="fa fa-fw fa-eye field-icon toggle-password"></span>
                                                    </div>
                                                    <button type="button" id="apply-password-btn"
                                                            class="btn btn-primary ml-2">Применить
                                                    </button>
                                                    <button type="button" id="cancel-change-password-btn"
                                                            class="btn btn-danger ml-2">Отмена
                                                    </button>
                                                </div>
                                                <div id="error-message" class="text-danger mt-2" style="display:none;">
                                                    Пароль должен быть не
                                                    менее 8 символов
                                                </div>
                                            </div>

                                            <hr class="mt-3">

                                            {{-- Кнопки "Обновить" и "Изменить пароль" --}}
                                            <div class="button-group buttons-wrap mt-3">
                                                <button type="submit" class="btn btn-primary update-btn">Обновить
                                                </button>
                                                <button type="button" id="change-password-btn"
                                                        class="btn btn-danger ml-2">Изменить пароль
                                                </button>
                                            </div>

                                        </form>
                                    </div>
                                </div>

                                <div id="password-change-message" class="text-success ml-3" style="display:none;">Пароль
                                    изменен
                                </div>

                            </div>

                            {{--Модалка аватарки--}}
                            <div class="modal fade" id="uploadPhotoModal" tabindex="-1" role="dialog"
                                 aria-labelledby="uploadPhotoModalLabel"
                                 aria-hidden="true">
                                <div class="modal-dialog" role="document">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="uploadPhotoModalLabel">Загрузка аватарки</h5>
                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                <span aria-hidden="true">&times;</span>
                                            </button>
                                        </div>
                                        <div class="modal-body">

                                            <form id="uploadImageForm" enctype="multipart/form-data">
                                            @csrf
                                            <!-- Выбор файла -->
                                                <input class="mb-3" type="file" id="upload" accept="image/*">

                                                <!-- Контейнер для Croppie -->
                                                <div id="upload-demo" style="width:300px;"></div>

                                                <!-- Скрытое поле для сохранения имени пользователя -->
                                                <input type="hidden" id="selectedUserName" name="userName" value="">

                                                <!-- Скрытое поле для обрезанного изображения -->
                                                <input type="hidden" id="croppedImage" name="croppedImage">

                                                <!-- Кнопка для сохранения изображения -->
                                                <button type="button" id="saveImageBtn" class="btn btn-primary">
                                                    Загрузить
                                                </button>
                                            </form>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{--ВКЛАДКА ОРГАНИЗАЦИЯ--}}
                <div class="tab-content" id="myTabContent">
                    <div class="tab-pane fade {{ $activeTab == 'partner' ? 'show active' : '' }}" role="tabpanel">
                        <div class="container-fluid ">

                            {{--<form action="{{ route('admin.cur.partner.update', $partner->id) }}"  method="POST">--}}
                                {{--@csrf--}}
                                {{--@method('PATCH')--}}
                                {{--<div class="row text-start">--}}
                                    {{-- Основные данные организации --}}
                                    {{--<div class="col-12 col-lg-5 user-data-wrap mb-3">--}}
                                        {{--<h4>Основная информация</h4>--}}
                                        {{-- Тип бизнеса --}}
                                        {{--<div class="mb-3 ">--}}
                                            {{--<label for="business_type" class="form-label ">Тип бизнеса*</label>--}}
                                            {{--<select name="business_type" id="business_type"--}}
                                                    {{--class="form-control">--}}
                                                {{--<option value="company"--}}
                                                        {{--{{ old('business_type', $partner->business_type) === 'company' ? 'selected' : '' }}>--}}
                                                    {{--ООО--}}
                                                {{--</option>--}}
                                                {{--<option value="individual_entrepreneur"--}}
                                                        {{--{{ old('business_type', $partner->business_type) === 'individual_entrepreneur' ? 'selected' : '' }}>--}}
                                                    {{--ИП--}}
                                                {{--</option>--}}
                                                {{--<option value="non_commercial_organization"--}}
                                                        {{--{{ old('business_type', $partner->business_type) === 'non_commercial_organization' ? 'selected' : '' }}>--}}
                                                    {{--НКО--}}
                                                {{--</option>--}}
                                                {{--<option value="physical_person"--}}
                                                        {{--{{ old('business_type', $partner->business_type) === 'physical_person' ? 'selected' : '' }}>--}}
                                                    {{--Физическое лицо--}}
                                                {{--</option>--}}
                                            {{--</select>--}}
                                            {{--@error('business_type')--}}
                                            {{--<p class="text-danger">{{ $message }}</p>--}}
                                            {{--@enderror--}}
                                        {{--</div>--}}

                                        {{-- Наименование (или ФИО для физ.лица) --}}
                                        {{--<div class="mb-3">--}}
                                            {{--<label for="title" class="form-label"--}}
                                                   {{--id="label-title">Наименование*</label>--}}
                                            {{--<input type="text" class="form-control" id="title" name="title"--}}
                                                   {{--value="{{ old('title', $partner->title) }}">--}}
                                            {{--@error('title')--}}
                                            {{--<p class="text-danger">{{ $message }}</p>--}}
                                            {{--@enderror--}}
                                        {{--</div>--}}

                                        {{-- ИНН --}}
                                        {{--<div class="mb-3" id="tax_id_wrapper">--}}
                                            {{--<label for="tax_id" class="form-label">ИНН</label>--}}
                                            {{--<input type="text" name="tax_id" class="form-control" id="tax_id"--}}
                                                   {{--value="{{ old('tax_id', $partner->tax_id) }}">--}}
                                            {{--@error('tax_id')--}}
                                            {{--<p class="text-danger">{{ $message }}</p>--}}
                                            {{--@enderror--}}
                                        {{--</div>--}}

                                        {{-- Регистрационный номер (ОГРН / ОГРНИП) --}}
                                        {{--<div class="mb-3" id="registration_number_wrapper">--}}
                                            {{--<label for="registration_number" class="form-label"--}}
                                                   {{--id="label-registration_number">ОГРН (ОГРНИП)</label>--}}
                                            {{--<input type="text" name="registration_number" class="form-control"--}}
                                                   {{--id="registration_number"--}}
                                                   {{--value="{{ old('registration_number', $partner->registration_number) }}">--}}
                                            {{--@error('registration_number')--}}
                                            {{--<p class="text-danger">{{ $message }}</p>--}}
                                            {{--@enderror--}}
                                        {{--</div>--}}

                                        {{-- Адрес --}}
                                        {{--<div class="mb-3">--}}
                                            {{--<label for="address" class="form-label">Почтовый адрес</label>--}}
                                            {{--<input type="text" name="address" class="form-control" id="address"--}}
                                                   {{--value="{{ old('address', $partner->address) }}">--}}
                                            {{--@error('address')--}}
                                            {{--<p class="text-danger">{{ $message }}</p>--}}
                                            {{--@enderror--}}
                                        {{--</div>--}}

                                        {{-- Телефон --}}
                                        {{--<div class="mb-3">--}}
                                            {{--<label for="phone" class="form-label">Телефон</label>--}}
                                            {{--<input type="text" name="phone" class="form-control" id="phone"--}}
                                                   {{--value="{{ old('phone', $partner->phone) }}">--}}
                                            {{--@error('phone')--}}
                                            {{--<p class="text-danger">{{ $message }}</p>--}}
                                            {{--@enderror--}}
                                        {{--</div>--}}

                                        {{-- Email --}}
                                        {{--<div class="mb-3">--}}
                                            {{--<label for="email-partner" class="form-label">E-mail--}}
                                                {{--партнёра*</label>--}}
                                            {{--<input type="email" name="email" class="form-control"--}}
                                                   {{--id="email-partner"--}}
                                                   {{--value="{{ old('email', $partner->email) }}">--}}
                                            {{--@error('email')--}}
                                            {{--<p class="text-danger">{{ $message }}</p>--}}
                                            {{--@enderror--}}
                                        {{--</div>--}}

                                        {{-- Сайт --}}
                                        {{--<div class="mb-3">--}}
                                            {{--<label for="website" class="form-label">Сайт</label>--}}
                                            {{--<input type="text" name="website" class="form-control" id="website"--}}
                                                   {{--value="{{ old('website', $partner->website) }}">--}}
                                            {{--@error('website')--}}
                                            {{--<p class="text-danger">{{ $message }}</p>--}}
                                            {{--@enderror--}}
                                        {{--</div>--}}
                                    {{--</div>--}}

                                    {{-- реквизиты --}}
                                    {{--<div class="col-12 col-lg-5 user-data-wrap mb-3">--}}
                                       {{--<h4>Реквизиты</h4>--}}
                                        {{--<div id="bankFields">--}}
                                            {{--<div class="mb-3">--}}
                                                {{--<label for="bank_name" class="form-label">Наименование--}}
                                                    {{--банка</label>--}}
                                                {{--<input type="text" class="form-control" id="bank_name"--}}
                                                       {{--name="bank_name"--}}
                                                       {{--value="{{ old('bank_name', $partner->bank_name) }}">--}}
                                                {{--@error('bank_name')--}}
                                                {{--<p class="text-danger">{{ $message }}</p>--}}
                                                {{--@enderror--}}
                                            {{--</div>--}}

                                            {{--<div class="mb-3">--}}
                                                {{--<label for="bank_bik" class="form-label">БИК</label>--}}
                                                {{--<input type="text" class="form-control" id="bank_bik"--}}
                                                       {{--name="bank_bik"--}}
                                                       {{--value="{{ old('bank_bik', $partner->bank_bik) }}">--}}
                                                {{--@error('bank_bik')--}}
                                                {{--<p class="text-danger">{{ $message }}</p>--}}
                                                {{--@enderror--}}
                                            {{--</div>--}}

                                            {{--<div class="mb-3">--}}
                                                {{--<label for="bank_account" class="form-label">Расчетный--}}
                                                    {{--счет</label>--}}
                                                {{--<input type="text" class="form-control" id="bank_account"--}}
                                                       {{--name="bank_account"--}}
                                                       {{--value="{{ old('bank_account', $partner->bank_account) }}">--}}
                                                {{--@error('bank_account')--}}
                                                {{--<p class="text-danger">{{ $message }}</p>--}}
                                                {{--@enderror--}}
                                            {{--</div>--}}
                                        {{--</div>--}}
                                    {{--</div>--}}

                                    {{-- Кнопка отправки формы --}}
                                    {{--<button type="submit" class="btn btn-primary width-170 mb-3">Обновить данные</button>--}}
                                {{--</div>--}}
                            {{--</form>--}}

                            <form action="{{ route('admin.cur.partner.update', $partner->id) }}" method="POST">
                                @csrf
                                @method('PATCH')
                                <div class="row text-start">
                                    {{-- Основные данные организации --}}
                                    <div class="col-12 col-lg-5 user-data-wrap mb-3">
                                        <h4>Основная информация</h4>
                                        {{-- Тип бизнеса --}}
                                        <div class="mb-3">
                                            <label for="business_type" class="form-label">Тип бизнеса*</label>
                                            {{--<select name="business_type" id="business_type" class="form-control">--}}
                                                {{--<option value="company"--}}
                                                        {{--{{ old('business_type', $partner->business_type) === 'company' ? 'selected' : '' }}>--}}
                                                    {{--ООО--}}
                                                {{--</option>--}}
                                                {{--<option value="individual_entrepreneur"--}}
                                                        {{--{{ old('business_type', $partner->business_type) === 'individual_entrepreneur' ? 'selected' : '' }}>--}}
                                                    {{--ИП--}}
                                                {{--</option>--}}
                                                {{-- Если в FormRequest разрешено только 2 варианта, то остальные можно не выводить --}}
                                            {{--</select>--}}



                                            <select name="business_type" id="business_type"
                                            class="form-control">
                                            <option value="company"
                                            {{ old('business_type', $partner->business_type) === 'company' ? 'selected' : '' }}>
                                            ООО
                                            </option>
                                            <option value="individual_entrepreneur"
                                            {{ old('business_type', $partner->business_type) === 'individual_entrepreneur' ? 'selected' : '' }}>
                                            ИП
                                            </option>
                                            <option value="non_commercial_organization"
                                            {{ old('business_type', $partner->business_type) === 'non_commercial_organization' ? 'selected' : '' }}>
                                            НКО
                                            </option>
                                            <option value="physical_person"
                                            {{ old('business_type', $partner->business_type) === 'physical_person' ? 'selected' : '' }}>
                                            Физическое лицо
                                            </option>
                                            </select>




                                            @error('business_type')
                                            <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- Наименование (или ФИО) --}}
                                        <div class="mb-3">
                                            <label for="title" class="form-label">Наименование*</label>
                                            <input type="text"
                                                   class="form-control"
                                                   id="title"
                                                   name="title"
                                                   value="{{ old('title', $partner->title) }}">
                                            @error('title')
                                            <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- ИНН --}}
                                        <div class="mb-3 " id ='tax_id_wrapper'>
                                            <label for="tax_id" class="form-label">ИНН</label>
                                            <input type="text"
                                                   class="form-control"
                                                   id="tax_id"
                                                   name="tax_id"
                                                   value="{{ old('tax_id', $partner->tax_id) }}">
                                            @error('tax_id')
                                            <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- КПП --}}
                                        <div class="mb-3 " id ='kpp_wrapper'>
                                            <label for="kpp" class="form-label">КПП</label>
                                            <input type="text"
                                                   class="form-control"
                                                   id="kpp"
                                                   name="kpp"
                                                   value="{{ old('kpp', $partner->kpp) }}">
                                            @error('kpp')
                                            <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>



                                        {{-- Регистрационный номер (ОГРН / ОГРНИП) --}}
                                        <div class="mb-3" id="registration_number_wrapper">
                                            <label for="registration_number" class="form-label" id="label-registration_number">ОГРН (ОГРНИП)</label>
                                            <input type="text"
                                                   class="form-control"
                                                   id="registration_number"
                                                   name="registration_number"
                                                   value="{{ old('registration_number', $partner->registration_number) }}">
                                            @error('registration_number')
                                            <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- Адрес --}}
                                        <div class="mb-3">
                                            <label for="address" class="form-label">Почтовый адрес</label>
                                            <input type="text"
                                                   class="form-control"
                                                   id="address"
                                                   name="address"
                                                   value="{{ old('address', $partner->address) }}">
                                            @error('address')
                                            <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- Телефон --}}
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Телефон</label>
                                            <input type="text"
                                                   class="form-control"
                                                   id="phone"
                                                   name="phone"
                                                   value="{{ old('phone', $partner->phone) }}">
                                            @error('phone')
                                            <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- E-mail партнёра --}}
                                        <div class="mb-3">
                                            <label for="email-partner" class="form-label">E-mail партнёра*</label>
                                            <input type="email"
                                                   class="form-control"
                                                   id="email-partner"
                                                   name="email"
                                                   value="{{ old('email', $partner->email) }}">
                                            @error('email')
                                            <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>

                                        {{-- Сайт --}}
                                        <div class="mb-3">
                                            <label for="website" class="form-label">Сайт</label>
                                            <input type="text"
                                                   class="form-control"
                                                   id="website"
                                                   name="website"
                                                   value="{{ old('website', $partner->website) }}">
                                            @error('website')
                                            <p class="text-danger">{{ $message }}</p>
                                            @enderror
                                        </div>
                                    </div>

                                    {{-- Реквизиты --}}
                                    <div class="col-12 col-lg-5 user-data-wrap mb-3">
                                        <h4 id = "requisites">Реквизиты</h4>
                                        <div id="bankFields">
                                            <div class="mb-3">
                                                <label for="bank_name" class="form-label">Наименование банка</label>
                                                <input type="text"
                                                       class="form-control"
                                                       id="bank_name"
                                                       name="bank_name"
                                                       value="{{ old('bank_name', $partner->bank_name) }}">
                                                @error('bank_name')
                                                <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="mb-3">
                                                <label for="bank_bik" class="form-label">БИК</label>
                                                <input type="text"
                                                       class="form-control"
                                                       id="bank_bik"
                                                       name="bank_bik"
                                                       value="{{ old('bank_bik', $partner->bank_bik) }}">
                                                @error('bank_bik')
                                                <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>

                                            <div class="mb-3">
                                                <label for="bank_account" class="form-label">Расчетный счет</label>
                                                <input type="text"
                                                       class="form-control"
                                                       id="bank_account"
                                                       name="bank_account"
                                                       value="{{ old('bank_account', $partner->bank_account) }}">
                                                @error('bank_account')
                                                <p class="text-danger">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Кнопка отправки формы --}}
                                    <button type="submit" class="btn btn-primary width-170 mb-3">Обновить данные</button>
                                </div>
                            </form>

                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>



@endsection









@section('scripts')


    <script>

        const uploadUrl = "{{ route('profile.user.uploadAvatar') }}";
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            //Добавление имени пользователя в скрытое поле формы для формы отправки аватарки
            function appendUserNametoForm(name) {
                if (currentUserRole === "admin") {
                    if (name) {
                        $('#selectedUserName').val(name);
                    } else {
                        // берем имя юзера из селекта
                        $('#selectedUserName').val($('#single-select-user').val());
                    }
                } else {
                    // берем имя пользователя авторизованного юзера
                    $('#selectedUserName').val(currentUserName);
                }
            }

            // Клик по ИЗМЕНИТЬ ПАРОЛЬ
            function changePasswordBtn() {
                document.getElementById('change-password-btn').addEventListener('click', function () {
                    console.log(1);
                    document.getElementById('change-password-btn').style.display = 'none';
                    document.getElementById('change-pass-wrap').style.display = 'inline-block';
                });
            }

            // Клик по ПРИМЕНИТЬ ПАРОЛЬ
            function applyPasswordBtn() {
                document.getElementById('apply-password-btn').addEventListener('click', function () {
                    var userId = '{{ $user->id }}';
                    var newPassword = document.getElementById('new-password').value;
                    var token = '{{ csrf_token() }}';
                    var errorMessage = document.getElementById('error-message');

                    // Проверка длины пароля
                    if (newPassword.length < 8) {
                        errorMessage.style.display = 'block'; // Показываем сообщение об ошибке
                        return; // Прерываем выполнение, если пароль слишком короткий
                    } else {
                        errorMessage.style.display = 'none'; // Скрываем сообщение об ошибке
                    }

                    fetch(`/user/${userId}/update-password`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': token,
                        },
                        body: JSON.stringify({password: newPassword}),
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                document.getElementById('change-password-btn').style.display = 'inline-block';
                                document.querySelector('#change-pass-wrap').style.display = 'none';

                                function showPasswordChangeMessage() {
                                    const message = document.getElementById('password-change-message');
                                    message.style.display = 'block'; // Показываем сообщение
                                    setTimeout(() => {
                                        message.classList.add('fade-out'); // Начинаем плавное исчезновение
                                    }, 2000); // Через 2 секунды

                                    setTimeout(() => {
                                        message.style.display = 'none'; // Полностью скрываем через 3 секунды
                                        message.classList.remove('fade-out'); // Удаляем класс, чтобы можно было показать сообщение снова
                                    }, 3000);
                                }

                                // Пример вызова функции
                                showPasswordChangeMessage();
                            }
                        });
                });
            }

            // Клик по ОТМЕНА
            function cancelChangePasswordBtn() {
                document.getElementById('cancel-change-password-btn').addEventListener('click', function () {
                    document.getElementById('change-password-btn').style.display = 'inline-block';
                    document.getElementById('change-pass-wrap').style.display = 'none';
                    document.getElementById('error-message').style.display = 'none';
                });
            }

            // AJAX Вызов модалки
            function showModal() {
                document.getElementById('upload-photo').addEventListener('click', function () {
                    $('#uploadPhotoModal').modal('show');
                });

                // Инициализация Croppie для аватарки
                $uploadCrop = $('#upload-demo').croppie({
                    viewport: {width: 200, height: 250, type: 'square'},
                    boundary: {width: 300, height: 300},
                    showZoomer: true
                });

                // Получаем текущий URL аватарки
                var currentAvatarUrl = $('#confirm-img').attr('src');
                console.log('Текущий URL аватарки:', currentAvatarUrl);

                $uploadCrop.croppie('bind', {
                    url: '/img/white.jpg'
                });

                // Если аватарка не является изображением по умолчанию, загружаем её в Croppie
                if (currentAvatarUrl && currentAvatarUrl !== '/img/default.png') {
                    $uploadCrop.croppie('bind', {
                        url: currentAvatarUrl
                    }).then(function () {
                        console.log('Текущая аватарка успешно загружена в Croppie.');
                    }).catch(function (error) {
                        console.error('Ошибка загрузки текущей аватарки в Croppie:', error);
                    });
                }

                // При выборе файла изображение загружается в Croppie
                $('#upload').on('change', function () {

                    var reader = new FileReader();
                    reader.onload = function (e) {
                        $uploadCrop.croppie('bind', {
                            url: e.target.result
                        }).then(function () {
                            // Croppie готов к использованию
                        });
                    }
                    reader.readAsDataURL(this.files[0]);
                });

                // Сохранение обрезанного изображения и отправка через AJAX
                $('#saveImageBtn').on('click', function () {
                    $uploadCrop.croppie('result', {
                        type: 'base64',
                        size: 'viewport'
                    }).then(function (resp) {
                        // Заполняем скрытое поле base64 изображением
                        $('#croppedImage').val(resp);

                        let userName = $('#selectedUserName').val();

                        // Создаем FormData для отправки
                        var formData = new FormData();
                        formData.append('_token', $('input[name="_token"]').val()); // Добавляем CSRF-токен
                        formData.append('croppedImage', $('#croppedImage').val()); // Добавляем обрезанное изображение
                        formData.append('userName', userName); // Добавляем имя пользователя

                        // Отправка данных через AJAX
                        $.ajax({
                            url: uploadUrl, // URL маршрута
                            type: 'POST', // Метод POST
                            data: formData, // Данные формы
                            contentType: false,
                            processData: false,
                            success: function (response) {
                                if (response.success) {
                                    // Обновляем изображение на странице
                                    $('#confirm-img').attr('src', response.image_url);
                                    console.log('Изображение успешно загружено!');
                                } else {
                                    alert('Ошибка загрузки изображения');
                                }
                                location.reload();
                            },
                            error: function (xhr, status, error) {
                                console.error('Ошибка:', error);
                                alert('Ошибка на сервере');
                            }
                        });
                    });
                });
            }

            // Скрытие модалки
            function hideModal() {
                // Закрытие модального окна при клике на крестик
                $('#uploadPhotoModal .close').on('click', function () {
                    $('#uploadPhotoModal').modal('hide');
                });
            }

            // Функция для показа/скрытия пароля с помощью иконки глаза
            function showPassword() {
                const togglePassword = document.querySelector('.toggle-password');
                const passwordInput = document.getElementById('new-password');

                togglePassword.addEventListener('click', function () {
                    const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                    passwordInput.setAttribute('type', type);

                    // Меняем иконку глаза
                    this.classList.toggle('fa-eye');
                    this.classList.toggle('fa-eye-slash');
                });
            }

//Изменение полей формы органзиациии в зависимости от еее типа
            function toggleFields() {
                const businessType = $('#business_type').val();

                // При выборе "physical_person" скрываем поля tax_id и registration_number,
                // меняем заголовок поля title на "ФИО"
                if (businessType === 'physical_person') {
                    $('#tax_id_wrapper').hide();
                    $('#kpp_wrapper').hide();
                    $('#registration_number_wrapper').hide();
                    $('#requisites').hide();

                } else {
                    // Для остальных типов показываем поля tax_id и registration_number,
                    // и меняем заголовок поля title на "Наименование"
                    $('#tax_id_wrapper').show();
                    $('#kpp_wrapper').show();
                    $('#registration_number_wrapper').show();
                    $('#requisites').show();


                    console.log(businessType);
                    console.log( $('#kpp_wrapper'));

                    // Меняем заголовок поля регистрации
                    if (businessType === 'company' || businessType === 'non_commercial_organization') {
                        $('#label-registration_number').text('ОГРН');
                        $('#kpp_wrapper').show();

                    } else if (businessType === 'individual_entrepreneur') {
                        $('#label-registration_number').text('ОГРНИП');
                        $('#kpp_wrapper').hide();
                    }
                }

                // Поля банка (показываем только для company, non_commercial_organization, individual_entrepreneur)
                if (businessType === 'physical_person') {
                    $('#bankFields').hide();

                } else {
                    $('#bankFields').show();

                }
            }

            // По изменению селекта — снова вызываем
            $('#business_type').change(function () {
                toggleFields();
            });

            // Вызов функций при загрузке страницы
            appendUserNametoForm("{{ $user->name }}");
            changePasswordBtn();
            applyPasswordBtn();
            cancelChangePasswordBtn();
            showModal();
            hideModal();
            showPassword();
            toggleFields();
        });

    </script>
@endsection
