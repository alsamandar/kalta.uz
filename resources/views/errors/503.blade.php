<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('Back soon') }} · Kalta.uz</title>
    <style>
        body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:#fffaf3;color:#1b1510;font:16px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif;text-align:center}
        .c{font-size:clamp(4rem,16vw,7rem);font-weight:800;letter-spacing:-.06em;line-height:1;color:#ff7500}
        h1{margin:.2em 0 .3em;font-size:1.8rem}p{max-width:42ch;margin:0 auto 1.4em;color:#4a4036}
        a{display:inline-block;padding:14px 26px;border-radius:14px;background:#ff7500;color:#1b1510;font-weight:700;text-decoration:none}
    </style>
</head>
<body>
    <main>
        <div class="c">503</div>
        <h1>{{ __('Back soon') }}</h1>
        <p>{{ __('Kalta.uz is being updated. Please try again in a few minutes.') }}</p>
        <a href="/">{{ __('Back to the home page') }}</a>
    </main>
</body>
</html>
