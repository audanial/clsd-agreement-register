@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="p-8">
        <div class="mx-auto max-w-4xl rounded-lg bg-white p-6 shadow">
            <h1 class="text-2xl font-semibold">Dashboard</h1>

            <p class="mt-4">
                Signed in as <strong>{{ auth()->user()->name }}</strong>
                <span class="rounded bg-gray-200 px-2 py-1 text-sm">{{ auth()->user()->role }}</span>
            </p>

            <div class="mt-6 flex gap-3">
                <a href="{{ route('agreements.index') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                    Open register
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md bg-gray-800 px-4 py-2 text-white hover:bg-gray-900">
                        Log out
                    </button>
                </form>
            </div>
        </div>
    </div>
@endsection
