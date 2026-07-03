<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <title>Доступ в личный кабинет | kidscrm.online</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; line-height: 1.6; color: #111; margin: 0; padding: 24px;">
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

<p>Здравствуйте!</p>

<p>
    Для <strong>{{ $student->full_name ?: trim($student->name . ' ' . $student->lastname) }}</strong>
    создан личный кабинет в &quot;{{ $partnerTitle }}&quot; на сервисе kidscrm.online.
</p>

<p>Ниже данные для входа:</p>

<table cellpadding="0" cellspacing="0" style="margin: 16px 0; border-collapse: collapse;">
    <tr>
        <td style="padding: 6px 16px 6px 0; color: #555;">Email для входа</td>
        <td style="padding: 6px 0;"><strong>{{ $student->email }}</strong></td>
    </tr>
    <tr>
        <td style="padding: 6px 16px 6px 0; color: #555;">Пароль</td>
        <td style="padding: 6px 0;"><strong>{{ $plainPassword }}</strong></td>
    </tr>
</table>

<p>
    <a href="{{ $loginUrl }}"
       style="display: inline-block; padding: 12px 24px; color: #fff; background-color: #2563eb; text-decoration: none; border-radius: 8px;">
        Войти в личный кабинет
    </a>
</p>

<p style="color: #555; font-size: 14px;">
    После первого входа рекомендуем сменить пароль в настройках профиля.
    Если вы не ожидали это письмо, свяжитесь с администрацией &quot;{{ $partnerTitle }}&quot;.
</p>

<p>
    С уважением,<br>
    Команда <a href="{{ config('app.url') }}" style="color: #2563eb; text-decoration: none;">kidscrm.online</a>
</p>
</body>
</html>
