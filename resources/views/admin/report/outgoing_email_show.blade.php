@extends('layouts.admin2')

@section('content')
<div class="container-fluid py-3">
    @include('admin.report.partials.outgoing_email_show_content', [
        'log' => $log,
        'inModal' => false,
    ])
</div>
@endsection
