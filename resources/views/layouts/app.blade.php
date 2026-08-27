<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', $title ?? config('app.name', 'Laravel'))</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-gray-50 text-gray-900 antialiased">
    @auth
        <nav class="bg-gray-800 text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3">
                <a href="{{ route('dashboard') }}" class="font-semibold">CLSD Agreement Register</a>
                <div class="flex items-center gap-4 text-sm">
                    <a href="{{ route('agreements.index') }}" class="hover:text-gray-300">Register</a>
                    <a href="{{ route('dashboard') }}" class="hover:text-gray-300">Dashboard</a>
                    @if (auth()->user()->canManageUsers())
                        <a href="{{ route('users.index') }}" class="hover:text-gray-300">Users</a>
                    @endif
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="hover:text-gray-300">Log out</button>
                    </form>
                </div>
            </div>
        </nav>
    @endauth

    <main class="p-6">
        @if (session('status'))
            <div class="mx-auto mb-6 max-w-7xl rounded-md bg-green-100 px-4 py-3 text-green-800">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
        {{ $slot ?? '' }}
    </main>

    @livewireScripts
</body>
</html>
