@php
    $requireJquery = $requireJquery ?? false;
@endphp

@if($requireJquery)
    @once('phone-inputmask-jquery')
        <script src="{{ asset('js/jquery/jquery-3.7.1.min.js') }}"></script>
    @endonce
@endif

@include('includes.scripts.phone-inputmask-lib')
@include('includes.scripts.phone-inputmask-init')
