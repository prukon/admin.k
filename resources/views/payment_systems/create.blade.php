@extends('layouts.app')

@section('content')
    <div class="container">
        <h1>Добавить платёжную систему</h1>

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="m-0">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form action="{{ route('payment-systems.store') }}" method="POST">
            @csrf

            <div class="mb-3">
                <label for="partner_id" class="form-label">Партнёр (необязательно)</label>
                <select name="partner_id" id="partner_id" class="form-select">
                    <option value="">Без партнёра</option>
                    @foreach($partners as $partner)
                        <option value="{{ $partner->id }}"
                                {{ old('partner_id') == $partner->id ? 'selected' : '' }}>
                            {{ $partner->name }}
                            {{-- Или какое-то поле partner->title, partner->company_name и т.п. --}}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="mb-3">
                <label for="name" class="form-label">Название системы</label>
                <input type="text" name="name" id="name" class="form-control"
                       value="{{ old('name') }}" required>
            </div>

            <div class="mb-3">
                <label for="merchant_login" class="form-label">Merchant Login</label>
                <input type="text" name="merchant_login" id="merchant_login"
                       class="form-control" value="{{ old('merchant_login') }}">
            </div>

            <div class="mb-3">
                <label for="password1" class="form-label">Пароль 1</label>
                <input type="text" name="password1" id="password1"
                       class="form-control" value="{{ old('password1') }}">
            </div>

            <div class="mb-3">
                <label for="password2" class="form-label">Пароль 2</label>
                <input type="text" name="password2" id="password2"
                       class="form-control" value="{{ old('password2') }}">
            </div>

            <div class="mb-3 form-check">
                <input type="checkbox" name="test_mode" id="test_mode" class="form-check-input" value="1"
                        {{ old('test_mode') ? 'checked' : '' }}>
                <label class="form-check-label" for="test_mode">Test Mode</label>
            </div>

            <button type="submit" class="btn btn-success">Сохранить</button>
        </form>
    </div>
@endsection
