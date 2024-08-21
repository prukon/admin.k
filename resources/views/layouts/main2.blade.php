<link rel="icon" href=" {{ asset('img/favicon.png') }} " type="image/png">

{{--Bootstrap--}}
@vite(['resources/js/app.js', 'resources/sass/app.scss'])

{{--Styles--}}
<link rel="stylesheet" href="{{ asset('css/fcistok.css') }}">
<link rel="stylesheet" href="{{ asset('css/style.css') }}">
<link rel="stylesheet" href="{{ asset('css/media-style.css') }}">
<link rel="stylesheet" href="{{ asset('css/calendar.css') }}">

{{--scripts--}}
<script src="{{ asset('js/main.js') }}"></script>

{{--JQuery--}}
<script src="{{ asset('js/jquery/jquery-3.7.1.min.js') }}"></script>

{{--JQuery-UI--}}
<script src="{{ asset('js/jquery/jquery-ui.min.js') }}"></script>

{{--Fontawesome--}}
<script src="{{ asset('js/fontawesome/fontawesome.js') }}"></script>

{{--Select2--}}
<link rel="stylesheet" href="{{ asset('css/select2/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/select2/select2-bootstrap-5-theme.min.css') }}">
<script src="{{ asset('js/select2/select2.full.min.js') }}"></script>

{{--Datapicker--}}
<link rel="stylesheet" href="{{ asset('css/datapicker/datepicker.material.css') }}">
<link rel="stylesheet" href="{{ asset('css/datapicker/datepicker.minimal.css') }}">
<link rel="stylesheet" href="{{ asset('css/datapicker/themes-jquery-ui.css') }}">
<script src="{{ asset('js/datapicker/datepicker.js') }}"></script>

{{--Cropie--}}
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.css">
<link rel="stylesheet" href="{{ asset('css/croppie.css') }}">
<script src="https://cdnjs.cloudflare.com/ajax/libs/croppie/2.6.5/croppie.min.js"></script>

