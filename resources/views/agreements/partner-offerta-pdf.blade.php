<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Партнёрская оферта — kidscrm.online</title>
    <style>
        @page { margin: 14mm; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #111;
        }
        h1 { font-size: 16pt; margin: 0 0 12px; }
        h2 { font-size: 13pt; margin: 14px 0 8px; }
        p { margin: 0 0 8px; }
        ul { margin: 0 0 8px; padding-left: 18px; }
        .container { padding: 0; }
        .table { width: 100%; border-collapse: collapse; margin: 8px 0 12px; }
        .table th, .table td { border: 1px solid #333; padding: 4px 6px; font-size: 9.5pt; }
        .table th { background: #f3f4f6; text-align: left; }
        a { color: #111; text-decoration: none; }
    </style>
</head>
<body>
    @include('agreements.partner-offerta', ['hidePdfDownload' => true])
</body>
</html>
