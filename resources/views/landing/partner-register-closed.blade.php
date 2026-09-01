@extends('layouts.landingPage')

@section('title', 'Регистрация временно закрыта — kidscrm.online')

@push('head')
    <meta name="robots" content="noindex, nofollow">
@endpush

@section('content')
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-lg-6">
                <h1 class="h3 fw-bold mb-2">Регистрация временно закрыта</h1>
                <p class="text-muted mb-4">
                    Оставьте заявку на демо или напишите в Telegram.
                </p>

                <div class="d-flex flex-wrap gap-2">
                    <button type="button"
                            class="btn btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#createOrder">
                        Записаться на демо
                    </button>
                    <a class="btn btn-outline-primary"
                       href="https://t.me/prukon"
                       target="_blank"
                       rel="noopener noreferrer">
                        Написать в Telegram
                    </a>
                </div>

                <p class="small text-muted mt-4 mb-0">
                    Уже есть аккаунт?
                    <a href="{{ route('login') }}">Войти</a>
                </p>
            </div>
        </div>
    </div>
@endsection
