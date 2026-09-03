<?php

use App\Actions\RecordAgreementActivity;
use App\Models\Agreement;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public Agreement $agreement;

    public string $document_status = '';
    public string $project_status = '';

    public function mount(Agreement $agreement): void
    {
        $this->agreement = $agreement;
        $this->document_status = $agreement->document_status;
        $this->project_status = $agreement->project_status;
    }

    public function updateStatus(): void
    {
        if (! auth()->user()->canWrite()) {
            abort(403);
        }

        $this->validate([
            'document_status' => ['required', 'in:pending,awaiting_partner,signed'],
            'project_status' => ['required', 'in:not_started,ongoing,stalled,completed'],
        ]);

        $record = app(RecordAgreementActivity::class);

        foreach (['document_status', 'project_status'] as $field) {
            if ($this->agreement->$field !== $this->$field) {
                $from = $this->agreement->$field;
                $to = $this->$field;

                $this->agreement->$field = $to;

                if ($field === 'project_status') {
                    $this->agreement->project_status_updated_at = now();
                }

                $record($this->agreement, 'status_changed', ucfirst(str_replace('_', ' ', $field)).' changed from '.($from ?? 'null').' to '.($to ?? 'null'), [
                    'field' => $field,
                    'from' => $from,
                    'to' => $to,
                ]);
            }
        }

        $this->agreement->save();

        session()->flash('status', 'Status updated.');
    }

    #[Computed]
    public function activities()
    {
        return $this->agreement->activities()->with('user')->latest()->get();
    }
};
?>

<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold">{{ $agreement->title }}</h1>
        @if (auth()->user()->canWrite())
            <a href="{{ route('agreements.edit', $agreement) }}" class="rounded-md bg-gray-800 px-4 py-2 text-white hover:bg-gray-900">Edit</a>
        @endif
    </div>

    <div class="rounded-lg bg-white p-6 shadow">
        <dl class="grid gap-4 md:grid-cols-2">
            <div>
                <dt class="text-sm font-medium text-gray-500">Type</dt>
                <dd class="mt-1 text-sm">{{ $agreement->type }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Campus</dt>
                <dd class="mt-1 text-sm">{{ $agreement->campus?->code }} — {{ $agreement->campus?->name }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Partner</dt>
                <dd class="mt-1 text-sm">{{ $agreement->partner?->name }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">PIC</dt>
                <dd class="mt-1 text-sm">{{ $agreement->pic_name ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Sector</dt>
                <dd class="mt-1 text-sm">{{ $agreement->sector ? ucfirst($agreement->sector) : '—' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Year</dt>
                <dd class="mt-1 text-sm">{{ $agreement->year ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Agreement date</dt>
                <dd class="mt-1 text-sm">{{ $agreement->agreement_date?->format('d M Y') ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Effective date</dt>
                <dd class="mt-1 text-sm">{{ $agreement->effective_date?->format('d M Y') ?? '—' }}</dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Expiry</dt>
                <dd class="mt-1 text-sm">
                    @if ($agreement->isIndefinite())
                        Indefinite
                    @else
                        {{ $agreement->expiry_date->format('d M Y') }}
                        @if ($agreement->isExpired())
                            <span class="ml-2 text-red-600">(expired)</span>
                        @endif
                    @endif
                </dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Document status</dt>
                <dd class="mt-1 text-sm">
                    <x-badges.document-status :status="$agreement->document_status" />
                </dd>
            </div>

            <div>
                <dt class="text-sm font-medium text-gray-500">Project status</dt>
                <dd class="mt-1 text-sm">
                    <div class="flex items-center gap-2">
                        <x-badges.project-status :status="$agreement->project_status" />
                        @if ($agreement->hasStaleProjectStatus())
                            <x-badges.stale :since="$agreement->project_status_updated_at" />
                        @endif
                    </div>
                </dd>
            </div>
        </dl>

        @if ($agreement->scope)
            <div class="mt-6">
                <dt class="text-sm font-medium text-gray-500">Scope</dt>
                <dd class="mt-1 whitespace-pre-wrap text-sm">{{ $agreement->scope }}</dd>
            </div>
        @endif

        @if ($agreement->notes)
            <div class="mt-6">
                <dt class="text-sm font-medium text-gray-500">Notes</dt>
                <dd class="mt-1 whitespace-pre-wrap text-sm">{{ $agreement->notes }}</dd>
            </div>
        @endif
    </div>

    @if (auth()->user()->canWrite())
        <div class="rounded-lg bg-white p-6 shadow">
            <h2 class="mb-4 text-lg font-medium">Change status</h2>
            <form wire:submit="updateStatus" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="block text-sm font-medium">Document status</label>
                    <select wire:model="document_status" class="mt-1 block w-48 rounded-md border-gray-300 shadow-sm">
                        <option value="pending">Pending</option>
                        <option value="awaiting_partner">Awaiting Partner</option>
                        <option value="signed">Signed</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium">Project status</label>
                    <select wire:model="project_status" class="mt-1 block w-48 rounded-md border-gray-300 shadow-sm">
                        <option value="not_started">Not Started</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="stalled">Stalled</option>
                        <option value="completed">Completed</option>
                    </select>
                </div>

                <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                    Update status
                </button>
            </form>
        </div>
    @endif

    <div class="rounded-lg bg-white p-6 shadow">
        <h2 class="mb-4 text-lg font-medium">Activity</h2>

        @forelse ($this->activities as $activity)
            <div class="border-b border-gray-100 py-3 last:border-0">
                <p class="text-sm">
                    <span class="font-medium">{{ $activity->description }}</span>
                    <span class="text-gray-500">— {{ $activity->user?->name ?? 'System' }}</span>
                </p>
                <p class="mt-1 text-xs text-gray-400">{{ $activity->created_at->diffForHumans() }}</p>
            </div>
        @empty
            <p class="text-sm text-gray-500">No activity yet.</p>
        @endforelse
    </div>
</div>
