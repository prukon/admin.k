@extends('layouts.app')

@section('content')


    @include('partner-offer-multirasschety')

    <!-- Footer -->
    <footer class="bg-dark text-white text-center py-4">
        <div class="container">
            <p class="mb-1">Все права защищены. 2024 - 2025 <кружок class="online"></кружок> &copy;</p>
            <div>
                <a href="oferta" class="text-white text-decoration-none mx-2">Оферта</a>
                <a href="{{ route('privacy.policy') }}"  class="text-white text-decoration-none mx-2">Политика конфиденциальности</a>
            </div>
        </div>
    </footer>

@endsection
