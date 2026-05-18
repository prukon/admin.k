<h2>Новая заявка с сайта</h2>

<p><strong>Организация:</strong> {{ $partnerTitle }}</p>
<p><strong>Имя:</strong> {{ $schoolLead->name }}</p>
<p><strong>Телефон:</strong> {{ $schoolLead->phone }}</p>

@if($schoolLead->utm_source || $schoolLead->utm_campaign)
    <p><strong>UTM:</strong>
        @if($schoolLead->utm_source) source: {{ $schoolLead->utm_source }} @endif
        @if($schoolLead->utm_medium) medium: {{ $schoolLead->utm_medium }} @endif
        @if($schoolLead->utm_campaign) campaign: {{ $schoolLead->utm_campaign }} @endif
    </p>
@endif

@if($schoolLead->page_url)
    <p><strong>Страница:</strong> {{ $schoolLead->page_url }}</p>
@endif

<p>
    <a href="{{ url('/admin/school-leads') }}">Открыть заявки в CRM</a>
</p>
