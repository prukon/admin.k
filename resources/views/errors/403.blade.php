@extends(auth()->check() ? 'layouts.admin2' : 'layouts.app')

@section('title', 'Доступ запрещён')

@push('meta')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <div class="container text-center pt-5">
        <h1>403 — Доступ запрещён</h1>
        <p>У вас нет прав для просмотра этой страницы.</p>
        @auth
            <a href="/" class="btn btn-primary mt-3">На главную</a>
        @else
            <a href="{{ route('login') }}" class="btn btn-primary mt-3">Войти</a>
        @endauth
    </div>
@endsection
