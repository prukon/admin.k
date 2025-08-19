<h2>Новая заявка с лендинга</h2>

<p><strong>Имя:</strong> {{ $submission->name }}</p>
<p><strong>Телефон:</strong> {{ $submission->phone }}</p>

@if($submission->email)
    <p><strong>Email:</strong> {{ $submission->email }}</p>
@endif

@if($submission->message)
    <p><strong>Сообщение:</strong></p>
    <p>{{ $submission->message }}</p>
@endif
