@extends('layouts.admin2')

@section('title', 'Доступ запрещён')

@section('content')
    <div class="container text-center pt-5">
        <h1>403 — Доступ запрещён</h1>
        <p>У вас нет прав для просмотра этой страницы.</p>
        <a href="/" class="btn btn-primary mt-3">На главную</a>
    </div>
@endsection
