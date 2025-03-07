<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Сброс пароля | kidslink.ru</title>
</head>
<body>
<p>Здравствуйте!</p>
<p>Вы запросили сброс пароля для своей учетной записи. Нажмите на кнопку ниже, чтобы сбросить пароль:</p>
<a href="{{ $url }}"
   style="display: inline-block; padding: 10px 20px; color: white; background-color: blue; text-decoration: none; border-radius: 5px;">
    Сбросить пароль
</a>
<p>Если вы не запрашивали сброс пароля, просто проигнорируйте это письмо.</p>
<p>С уважением,<br>Команда {{ config('app.name') }}</p>
</body>
</html>
