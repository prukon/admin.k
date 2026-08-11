<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Уведомление об оплате | kidscrm.online</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; line-height: 1.6; color: #111; margin: 0; padding: 24px; font-size: 14px;">
@if (file_exists(public_path('img/logo.png')))
<p style="margin: 0 0 24px;">
    <a href="{{ config('app.url') }}" style="text-decoration: none;">
        <img src="{{ url('img/logo.png') }}"
             alt="kidscrm.online"
             width="140"
             style="display: block; max-width: 140px; height: auto; border: 0;">
    </a>
</p>
@endif

<div style="margin: 0 0 24px;">
{!! $bodyHtml !!}
</div>

<p style="margin: 0;">
    С уважением,<br>
    Команда <a href="{{ config('app.url') }}" style="color: #2563eb; text-decoration: none;">kidscrm.online</a>
</p>
</body>
</html>
