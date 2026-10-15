<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $boot['locale']) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }}</title>
    @viteReactRefresh
    @vite($entry, $buildDirectory)
</head>
<body>
    <div id="panneau"></div>
    <script type="application/json" id="panneau-boot">@json($boot)</script>
</body>
</html>
