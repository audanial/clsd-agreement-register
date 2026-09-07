<?php

use App\Models\Agreement;
use App\Models\Campus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $campus = '';

    #[Url]
    public string $type = '';

    #[Url]
    public string $documentStatus = '';

    #[Url]
    public string $projectStatus = '';

    #[Url]
    public string $year = '';

    #[Url]
    public bool $showArchived = false;

    public function updating($name): void
    {
        if (in_array($name, ['search', 'campus', 'type', 'documentStatus', 'projectStatus', 'year', 'showArchived'], true)) {
            $this->resetPage();
        }
    }

    #[Computed]
    public function agreements()
    {
        return Agreement::query()
            ->with(['partner', 'campus'])
            ->when(! $this->showArchived, fn ($q) => $q->notArchived())
            ->when($this->showArchived, fn ($q) => $q->archived())
            ->when($this->search, function ($q) {
                $term = '%'.mb_strtolower($this->search).'%';
                $q->where(function ($sub) use ($term) {
                    $sub->whereRaw('LOWER(title) like ?', [$term])
                        ->orWhereHas('partner', fn ($p) => $p->whereRaw('LOWER(name) like ?', [$term]));
                });
            })
            ->when($this->campus, fn ($q) => $q->where('campus_id', $this->campus))
            ->when($this->type, fn ($q) => $q->where('type', $this->type))
            ->when($this->documentStatus, fn ($q) => $q->where('document_status', $this->documentStatus))
            ->when($this->projectStatus, fn ($q) => $q->where('project_status', $this->projectStatus))
            ->when($this->year, fn ($q) => $q->whereYear('agreement_date', $this->year))
            ->latest('agreement_date')
            ->paginate(15);
    }

    #[Computed]
    public function campuses()
    {
        return Campus::active()->orderBy('sort_order')->get();
    }

    #[Computed]
    public function types(): array
    {
        return ['LOI', 'NDA', 'MOA', 'MOU', 'SEA', 'MOC', 'ADDENDUM'];
    }

    #[Computed]
    public function documentStatuses(): array
    {
        $statuses = [
            'pending' => 'Pending',
            'awaiting_partner' => 'Awaiting Partner',
            'signed' => 'Signed',
        ];

        if (! auth()->user()->canSeePending()) {
            unset($statuses['pending']);
        }

        return $statuses;
    }

    #[Computed]
    public function projectStatuses(): array
    {
        return [
            'not_started' => 'Not Started',
            'ongoing' => 'Ongoing',
            'stalled' => 'Stalled',
            'completed' => 'Completed',
        ];
    }

    #[Computed]
    public function availableYears()
    {
        $yearExpression = match (DB::getDriverName()) {
            'sqlite' => "strftime('%Y', agreement_date)",
            default => 'YEAR(agreement_date)',
        };

        // Aliased as available_year so it is not shadowed by the Agreement::year() accessor.
        return Agreement::query()
            ->selectRaw("DISTINCT {$yearExpression} as available_year")
            ->whereNotNull('agreement_date')
            ->orderByDesc('available_year')
            ->pluck('available_year')
            ->map(fn ($year) => (int) $year)
            ->values();
    }
};
?>

<div class="mx-auto max-w-7xl">
    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-semibold">Agreement Register</h1>
        @if (auth()->user()->canWrite())
            <a href="{{ route('agreements.create') }}" class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                Create agreement
            </a>
        @endif
    </div>

    <div class="mb-6 rounded-lg bg-white p-4 shadow">
        <div class="grid gap-4 md:grid-cols-3 lg:grid-cols-7">
            <div>
                <label class="block text-sm font-medium">Search</label>
                <input wire:model.live="search" type="text" placeholder="Title or partner..." class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
            </div>

            <div>
                <label class="block text-sm font-medium">Campus</label>
                <select wire:model.live="campus" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All</option>
                    @foreach ($this->campuses as $c)
                        <option value="{{ $c->id }}">{{ $c->code }} — {{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium">Type</label>
                <select wire:model.live="type" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All</option>
                    @foreach ($this->types as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium">Document status</label>
                <select wire:model.live="documentStatus" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All</option>
                    @foreach ($this->documentStatuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium">Project status</label>
                <select wire:model.live="projectStatus" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All</option>
                    @foreach ($this->projectStatuses as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium">Year</label>
                <select wire:model.live="year" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm">
                    <option value="">All</option>
                    @foreach ($this->availableYears as $y)
                        <option value="{{ $y }}">{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-end">
                <label class="flex items-center gap-2 text-sm">
                    <input wire:model.live="showArchived" type="checkbox" class="rounded border-gray-300">
                    Show archived
                </label>
            </div>
        </div>
    </div>

    <div class="rounded-lg bg-white shadow">
        <table class="min-w-full divide-y divide-gray-400">
            <thead class="bg-[#293D7A]">
                <tr class="divide-x divide-gray-400">
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">Title</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">Partner</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">Duration</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">Scope</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">Document Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">Project Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">PIC</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white">Campus</th>
                    <th class="px-4 py-3 text-left text-xs font-medium uppercase text-white"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-400">
                @forelse ($this->agreements as $agreement)
                    <tr class="divide-x divide-gray-400">
                        <td class="px-4 py-3 text-sm font-medium">{{ $agreement->title }}</td>
                        <td class="px-4 py-3 text-sm">{{ $agreement->partner?->name }}</td>
                        <td class="px-4 py-3 text-sm">
                            <x-agreement-duration :agreement="$agreement" />
                        </td>
                        <td class="px-4 py-3 text-sm" title="{{ $agreement->scope ?? '' }}">{{ $agreement->scope ? Str::limit($agreement->scope, 80) : '—' }}</td>
                        <td class="px-4 py-3 text-sm">
                            <x-badges.document-status :status="$agreement->document_status" />
                        </td>
                        <td class="px-4 py-3 text-sm">
                            <div class="flex items-center gap-2">
                                <x-badges.project-status :status="$agreement->project_status" />
                                @if ($agreement->hasStaleProjectStatus())
                                    <x-badges.stale :since="$agreement->project_status_updated_at" />
                                @endif
                            </div>
                        </td>
                        <td class="px-4 py-3 text-sm">{{ $agreement->pic_name ?? '—' }}</td>
                        <td class="px-4 py-3 text-sm">{{ $agreement->campus?->code }}</td>
                        <td class="px-4 py-3 text-sm">
                            <a href="{{ route('agreements.show', $agreement) }}" class="text-indigo-600 hover:text-indigo-900">View</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-6 text-center text-sm text-gray-500">No agreements found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="px-4 py-3">
            {{ $this->agreements->links() }}
        </div>
    </div>
</div>
