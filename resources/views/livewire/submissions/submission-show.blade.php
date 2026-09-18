<?php

use App\Models\Submission;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Submission $submission;

    /**
     * Re-authorization on Livewire update requests. On the initial page load
     * this hook runs before mount(), when the property is not yet set — there
     * mount() authorizes against the route-bound, database-loaded model.
     * Afterwards the property is hydrated from the snapshot on every update
     * request, so this hook re-checks the CURRENT session user against it:
     * an inactive or different requester cannot reuse an existing snapshot.
     */
    public function boot(): void
    {
        if (! isset($this->submission)) {
            return;
        }

        Gate::authorize('view', $this->submission);
    }

    public function mount(Submission $submission): void
    {
        Gate::authorize('view', $submission);

        $this->submission = $submission;
    }

    /**
     * Read-only detail. LP1 exposes no mutation actions: authorization lives
     * in the policy, and the only history is the initial audit event.
     */
    #[Computed]
    public function activities()
    {
        return $this->submission->activities()->with('user')->orderBy('created_at')->get();
    }
};
?>

<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ $submission->title }}</h1>
        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">
            {{ ucfirst($submission->status) }}
        </span>
    </div>

    <div class="rounded-lg bg-white p-6 shadow">
        <dl class="grid gap-4 md:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500">Submission No.</dt>
                <dd class="mt-1 text-sm">{{ '#'.$submission->id }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Campus / Department</dt>
                <dd class="mt-1 text-sm"><strong class="font-bold">{{ $submission->campus?->code }}</strong> — {{ $submission->campus?->name }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Partner / organisation</dt>
                <dd class="mt-1 text-sm">{{ $submission->partner_name }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Agreement type</dt>
                <dd class="mt-1 text-sm">{{ $submission->agreement_type ?? 'Not sure' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Submitted</dt>
                <dd class="mt-1 text-sm"><x-datetime :value="$submission->submitted_at" /></dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Status</dt>
                <dd class="mt-1 text-sm">
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800">
                        {{ ucfirst($submission->status) }}
                    </span>
                </dd>
            </div>

            @if (auth()->user()->isLegalStaff())
                <div>
                    <dt class="text-sm font-medium text-gray-500">Requested by</dt>
                    <dd class="mt-1 text-sm">{{ $submission->requester?->name }}</dd>
                </div>

                <div>
                    <dt class="text-sm font-medium text-gray-500">Created</dt>
                    <dd class="mt-1 text-sm"><x-datetime :value="$submission->created_at" /></dd>
                </div>
            @endif
        </dl>

        <div class="mt-6">
            <dt class="text-sm font-medium text-gray-500">Purpose / scope</dt>
            <dd class="mt-1 whitespace-pre-wrap text-sm">{{ $submission->purpose }}</dd>
        </div>

        <div class="mt-6 rounded-md border-l-4 border-[#7B2231] bg-[#FBF2F3] px-4 py-3 text-sm text-[#5C2B2E]">
            <span class="font-semibold">Private &amp; Confidential</span> — for UniKL Legal handling only. This submission is locked and cannot be edited.
        </div>
    </div>

    <div class="rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-lg font-medium">Activity</h2>

        @forelse ($this->activities as $activity)
            <div class="border-b border-gray-100 py-3 last:border-0">
                <p class="text-sm">
                    <span class="font-medium">{{ $activity->description }}</span>
                    <span class="text-gray-500">— {{ $activity->user?->name ?? 'System' }}</span>
                </p>
                <p class="mt-1 text-xs text-gray-400"><x-datetime :value="$activity->created_at" /></p>
            </div>
        @empty
            <p class="text-sm text-gray-500">No activity yet.</p>
        @endforelse
    </div>
</div>
