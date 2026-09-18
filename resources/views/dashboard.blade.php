@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="p-8">
        <div class="mx-auto max-w-4xl rounded-lg bg-white p-6 shadow">
            <h1 class="text-2xl font-semibold">Dashboard</h1>

            <p class="mt-4">
                Signed in as <strong>{{ auth()->user()->name }}</strong>
                <span class="rounded bg-gray-200 px-2 py-1 text-sm">{{ auth()->user()->roleLabel() }}</span>
            </p>

            @if (auth()->user()->isRequester())
                {{-- Requesting Staff entry points into the Legal Submission Portal.
                     Explicit requester check, not @unless (canAccessRegister()):
                     requesters also get Register access (LP1 Amendment 2), so a
                     register-based condition would wrongly hide these buttons.
                     Links only; authorization stays in the routes and policy. --}}
                <p class="mt-4 text-sm text-gray-600">
                    Your account is set up for submitting agreement requests to Legal.
                </p>

                <div class="mt-6 flex gap-3">
                    <a href="{{ route('submissions.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                        Create Submission
                    </a>
                    <a href="{{ route('submissions.index') }}" class="rounded-md bg-gray-800 px-4 py-2 text-white hover:bg-gray-900">
                        My Submissions
                    </a>
                </div>
            @endif

            <div class="mt-6 flex gap-3">
                @if (auth()->user()->canAccessRegister())
                    <a href="{{ route('agreements.index') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                        Open register
                    </a>
                @endif

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
