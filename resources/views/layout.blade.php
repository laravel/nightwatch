<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Meta Information -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <link rel="shortcut icon" href="{{ asset('/vendor/laravel-package/favicon.ico') }}" />

    <meta name="robots" content="noindex, nofollow">

    <title>LaravelPackage{{ config('app.name') ? ' - ' . config('app.name') : '' }}</title>

    <!-- Scripts -->
    @vite('resources/js/app.js', 'vendor/laravel-package/build')
</head>
<body>

<div id="app">
    <h1>Laravel Package{{ config('app.name') ? ' - ' . config('app.name') : '' }}</h1>
</div>

<!-- Global LaravelPackage Object -->
<script>
    window.LaravelPackage = @json($scriptVariables);
</script>

</body>
</html>
