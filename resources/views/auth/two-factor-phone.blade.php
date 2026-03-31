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
                <input type="tel" class="form-control" id="phone" name="phone"
                       value="{{ old('phone') }}" placeholder="+7 999 111 22 33" required>
                <small class="text-muted">Формат: 79XXXXXXXXX (для sms.ru)</small>
            </div>
            <button type="submit" class="btn btn-primary">Сохранить и получить код</button>
        </form>
    </div>
@endsection
