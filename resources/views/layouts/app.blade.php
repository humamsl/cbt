<!DOCTYPE html>
<html lang="id" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') &middot; {{ $AppCfg['app_name'] }}</title>
    @if($AppCfg['favicon'])
        <link rel="icon" href="{{ Storage::url($AppCfg['favicon']) }}">
    @endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        :root {
            --brand: {{ $AppCfg['theme_color'] ?? '#1f47f5' }};
        }
    </style>
    @stack('head')
</head>
<body class="h-full">
<div class="min-h-full flex" x-data="{ sidebarOpen: false }">

    @include('partials.sidebar')

    <div class="flex-1 flex flex-col min-w-0 md:pl-64">
        @include('partials.header')

        <main class="flex-1 px-3 sm:px-4 md:px-8 py-4 md:py-6">
            @if (session('success'))
                <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition
                     class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 flex items-center justify-between shadow-soft">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                        {{ session('success') }}
                    </div>
                    <button @click="show=false" class="text-emerald-700 hover:text-emerald-900">×</button>
                </div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 shadow-soft">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>

        <footer class="px-4 md:px-8 py-5 text-xs text-ink-500 border-t border-slate-100 bg-white">
            {{ $AppCfg['footer_text'] ?? ('© '.date('Y').' '.$AppCfg['app_name'].' — '.$AppCfg['app_tagline']) }}
        </footer>
    </div>
</div>
@stack('scripts')
</body>
</html>
