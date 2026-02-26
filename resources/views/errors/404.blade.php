@extends('layouts.landingPage')

@section('title', 'Game Over — 404')

@section('content')
    <div class="container text-center mt-5">
        <img src="https://i.imgur.com/lKJiT77.png"
             alt="Game Over"
             class="img-fluid mb-4"
             style="max-width: 300px;">

        <h1 class="display-4" style="font-family: 'Press Start 2P', cursive;">404</h1>
        <p class="lead" style="font-family: 'Press Start 2P', cursive;">Страница не найдена.</p>
        <p class="mb-4">Кажется, ты зашёл не туда.</p>

        <div class="d-flex justify-content-center flex-wrap">

            @auth
                <a href="{{ route('dashboard') }}" class="btn btn-warning btn-lg px-4">
                    Вернуться в личный кабинет
                </a>
            @else
                <a href="{{ url('/') }}" class="btn btn-warning btn-lg px-4">
                    Вернуться на главную
                </a>
            @endauth

        </div>
    </div>
@endsection

@push('styles')
    <!-- Google font для ретро-игрового стиля -->
    <link href="https://fonts.googleapis.com/css2?family=Press+Start+2P&display=swap" rel="stylesheet">
@endpush