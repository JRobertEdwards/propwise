<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Propwise') — Propwise</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-gray-50 text-gray-900 antialiased">
    <header class="bg-gray-950 border-b border-white/10">
        <div class="max-w-5xl mx-auto px-4 sm:px-6 py-4 flex items-center gap-3">
            <span class="text-white font-semibold text-lg tracking-tight">Propwise</span>
            <span class="text-gray-500 text-sm hidden sm:inline">UK property sales explorer</span>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-4 sm:px-6 py-8">
        @yield('content')
    </main>
</body>
</html>
