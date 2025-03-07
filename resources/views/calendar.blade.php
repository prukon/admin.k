<!-- resources/views/calendar.blade.php -->

@extends('layouts.app')

@section('content')
    <div id="calendar"></div>
@endsection

@push('styles')
    <!-- Подключение FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/main.min.css" rel="stylesheet">
@endpush

@push('scripts')
    <!-- Подключение FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.8/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/daygrid@6.1.8/main.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let calendarEl = document.getElementById('calendar');

            if (calendarEl) {
                let calendar = new FullCalendar.Calendar(calendarEl, {
                    plugins: [ 'dayGrid' ],
                    initialView: 'dayGridMonth',
                    events: @json($events) // Переданные события
                });

                calendar.render();
            } else {
                console.error('Элемент с id="calendar" не найден.');
            }
        });
    </script>
@endpush
