<?php

use App\Models\Submission;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    /**
     * Authorization runs on every request — the initial page load and each
     * Livewire update — so a deactivated, role-changed, or different user
     * cannot reuse an existing component snapshot. Route middleware is
     * defence in depth, never the control.
     */
    public function boot(): void
    {
        Gate::authorize('viewAny', Submission::class);
    }

    /**
     * Role-aware queue. Requesting Staff are constrained at the database-query
     * level — before pagination or counting — so another requester's rows are
     * never fetched and hidden afterwards. Legal and Admin see every submission.
     */
    #[Computed]
    public function submissions()
    {
        $query = Submission::query()->with('campus');

        if (auth()->user()->isLegalStaff()) {
            $query->with('requester');
        } else {
            $query->where('created_by', auth()->id());
        }

        return $query->latest('submitted_at')->paginate(15);
    }

    #[Computed]
    public function heading(): string
    {
        return auth()->user()->isLegalStaff()
            ? 'Submission Queue'
            : 'My Submissions';
    }
};
?>

<div class="mx-auto max-w-7xl">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ $this->heading }}</h1>

        @if (auth()->user()->isRequester())
            <a href="{{ route('submissions.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                New Submission
            </a>
        @endif
    </div>

    <div class="rounded-lg bg-white shadow">
        <table class="min-w-full divide-y divide-gray-400 border-l border-r border-gray-400">
            <thead class="bg-[#1F3A5F]">
                <tr class="divide-x divide-gray-400">
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">No.</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">Campus / Department</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">Submitted</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">Status</th>
                    @if (auth()->user()->isLegalStaff())
                        <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">Requested by</th>
                    @endif
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-400">
                @forelse ($this->submissions as $submission)
                    <tr class="divide-x divide-gray-400">
                        <td class="px-4 py-3 text-sm font-medium">{{ '#'.$submission->id }}</td>
                        <td class="px-4 py-3 text-sm font-medium">{{ $submission->title }}</td>
                        <td class="px-4 py-3 text-sm"><strong class="font-bold">{{ $submission->campus?->code }}</strong> — {{ $submission->campus?->name }}</td>
                        <td class="px-4 py-3 text-sm">{{ $submission->agreement_type ?? 'Not sure' }}</td>
                        <td class="px-4 py-3 text-sm"><x-datetime :value="$submission->submitted_at" /></td>
                        <td class="px-4 py-3 text-sm">
                            <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">
                                {{ ucfirst($submission->status) }}
                            </span>
                        </td>
                        @if (auth()->user()->isLegalStaff())
                            <td class="px-4 py-3 text-sm">{{ $submission->requester?->name }}</td>
                        @endif
                        <td class="px-4 py-3 text-sm">
                            <a href="{{ route('submissions.show', $submission) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ auth()->user()->isLegalStaff() ? 8 : 7 }}" class="px-4 py-6 text-center text-sm text-gray-500">
                            @if (auth()->user()->isLegalStaff())
                                No submissions yet. Requests from staff appear here once submitted.
                            @else
                                No submissions yet. Use “New Submission” to send your first request to Legal.
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-4 py-3">
            {{ $this->submissions->links() }}
        </div>
    </div>
</div>
