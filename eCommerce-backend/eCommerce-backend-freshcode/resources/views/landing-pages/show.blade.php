@if (preg_match('/<\/head\s*>/i', $document->html ?? ''))
{!! preg_replace_callback('/<\/head\s*>/i', fn () => '<style>'.$document->css.'</style></head>', $document->html, 1) !!}
@else
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->title }}</title>
    <style>{!! $document->css !!}</style>
</head>
<body>
{!! $document->html !!}
</body>
</html>
@endif
