{{-- resources/views/auth/two-factor-phone.blade.php --}}
@extends('layouts.app')

@section('content')
    <div class="container" style="max-width:480px">
        <h3 class="mb-3">Укажите телефон для 2FA</h3>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('two-factor.phone.save') }}">
            @csrf
            <div class="mb-3">
                <label for="phone" class="form-label">Телефон</label>
                @include('includes.fields.phone-input', [
                    'name' => 'phone',
                    'id' => 'phone',
                    'value' => old('phone'),
                    'required' => true,
                ])
                <small class="text-muted">Формат: 79XXXXXXXXX (для sms.ru)</small>
            </div>
            <button type="submit" class="btn btn-primary">Сохранить и получить код</button>
        </form>
    </div>
@endsection
