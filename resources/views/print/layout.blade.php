<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    <style>
        @page { size: A4 portrait; margin: 12mm 10mm; }
        * { box-sizing: border-box; }
        body { font-family: "Times New Roman", Times, serif; font-size: 12pt; color: #000; background: #fff; margin: 0; padding: 16px; }
        .toolbar { font-family: system-ui, sans-serif; font-size: 14px; margin-bottom: 16px; display: flex; gap: 8px; }
        .toolbar button { padding: 6px 14px; cursor: pointer; }
        .approve { margin-left: auto; width: 62mm; text-align: left; line-height: 1.5; }
        h1 { font-size: 14pt; text-align: center; margin: 10px 0 12px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 3px 5px; vertical-align: top; }
        th { font-weight: bold; text-align: center; }
        .num { width: 8mm; text-align: center; }
        .cancelled { text-decoration: line-through; }
        .changed { font-style: italic; font-weight: bold; }
        .small { font-size: 9.5pt; }
        .note { font-size: 10pt; margin-top: 8px; }
        .empty { text-align: center; padding: 24px; font-style: italic; }
        @media print { .toolbar { display: none; } body { padding: 0; } }
    </style>
</head>
<body>
    <div class="toolbar">
        <button type="button" onclick="window.print()">Друкувати</button>
        <span style="align-self: center; opacity: .7">Для PDF оберіть «Зберегти як PDF» у вікні друку.</span>
    </div>
    @yield('content')
</body>
</html>
