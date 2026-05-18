<h2>Новая заявка с лендинга</h2>

<p><strong>Имя:</strong> {{ $partnerLead->name }}</p>
<p><strong>Телефон:</strong> {{ $partnerLead->phone }}</p>

@if($partnerLead->email)
    <p><strong>Email:</strong> {{ $partnerLead->email }}</p>
@endif

@if($partnerLead->message)
    <p><strong>Сообщение:</strong></p>
    <p>{{ $partnerLead->message }}</p>
@endif
