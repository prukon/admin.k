@extends('layouts.admin2')
@section('content')
    <div class="main-content text-start">

        <h4 class="pt-3">Партнеры</h4>
        <hr>
        <div class="buttons d-flex flex-row align-items-center mb-3">
            <button id="new-team" type="button" class="btn btn-primary mr-2 new-team width-170"
                    data-bs-toggle="modal" data-bs-target="#createPartnerModal">
                Добавить партнера
            </button>
            <button type="button" class="btn btn-primary width-170" id="logs" data-bs-toggle="modal"
                    data-bs-target="#historyModal">История изменений
            </button>
        </div>
        <hr>
        @php
            $counter = 1;
        @endphp

        @foreach($allPartners as $partner)
            <div class="team">
                <a href="javascript:void(0);" class="edit-partner-link" data-id="{{ $partner->id }}"
                   style="{{ $partner->is_enabled == 0 ? 'color: red;' : '' }}">
                    {{ $counter }}. {{$partner->title}}
                </a>
            </div>
            @php
                $counter++;
            @endphp
        @endforeach

        <div class="mt-3">
            {{ $allPartners->withQueryString()->links() }}
        </div>
    </div>
    <!-- Модальное окно создания команды -->
{{--    @include('includes.modal.createPartner')--}}

    <!-- Модальное окно редактирования команды -->
    @include('includes.modal.editPartner')

    <!-- Модальное окно логов -->
    @include('includes.logModal')

@endsection

@section('scripts')
    <!-- Модальное окно логов -->
    <script>
        $(document).ready(function () {
            showLogModal("{{ route('logs.data.partner') }}");
{{--            showLogModal("{{ route('logs.data.team') }}");--}}

        })
    </script>
@endsection