@extends('layouts.landingPage')

@section('title', 'kidscrm.online — Управление спортом онлайн')
@section('meta_robots', 'noindex,follow')
@section('meta_description', 'Публичная оферта kidscrm.online: условия использования сервиса и порядок оплаты услуг.')
   
@section('content')
    <!-- Hero -->
    <section class="bg-light py-5">

        {{--оферта для  родителей--}}
        @include('agreements.public-offerta')

    </section>
@endsection
