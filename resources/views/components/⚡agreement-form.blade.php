<?php

use App\Actions\RecordAgreementActivity;
use App\Models\Agreement;
use App\Models\Campus;
use App\Models\Country;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?Agreement $agreement = null;

    public string $title = '';

    public string $type = '';

    public string $partnerMode = 'existing';

    public ?int $partner_id = null;

    public string $newPartnerName = '';

    public string $newPartnerShortName = '';

    public ?int $newPartnerCountryId = null;

    public ?int $campus_id = null;

    public ?int $pic_user_id = null;

    public string $sector = '';

    public ?string $agreement_date = null;

    public ?string $effective_date = null;

    public ?string $expiry_date = null;

    public string $document_status = 'pending';

    public string $project_status = 'not_started';

    public string $scope = '';

    public string $notes = '';

    public function mount(?Agreement $agreement = null): void
    {
        $this->agreement = $agreement;

        if ($agreement) {
            $this->title = $agreement->title;
            $this->type = $agreement->type;
            $this->partner_id = $agreement->partner_id;
            $this->campus_id = $agreement->campus_id;
            $this->pic_user_id = $agreement->pic_user_id;
            $this->sector = $agreement->sector ?? '';
            $this->agreement_date = $agreement->agreement_date?->format('Y-m-d');
            $this->effective_date = $agreement->effective_date?->format('Y-m-d');
            $this->expiry_date = $agreement->expiry_date?->format('Y-m-d');
            $this->document_status = $agreement->document_status;
            $this->project_status = $agreement->project_status;
            $this->scope = $agreement->scope ?? '';
            $this->notes = $agreement->notes ?? '';
        }
    }

    public function save(): void
    {
        $validated = $this->validate($this->rules());

        $partnerId = $this->resolvePartnerId();

        $agreement = $this->agreement ?? new Agreement;
        $isNew = ! $agreement->exists;

        $originalStatus = $isNew ? null : [
            'document_status' => $agreement->document_status,
            'project_status' => $agreement->project_status,
        ];

        $agreement->fill([
            'title' => $validated['title'],
            'type' => $validated['type'],
            'partner_id' => $partnerId,
            'campus_id' => $validated['campus_id'],
            'pic_user_id' => $validated['pic_user_id'],
            'sector' => $validated['sector'] ?: null,
            'agreement_date' => $validated['agreement_date'] ?: null,
            'effective_date' => $validated['effective_date'] ?: null,
            'expiry_date' => $validated['expiry_date'] ?: null,
            'document_status' => $validated['document_status'],
            'project_status' => $validated['project_status'],
            'scope' => $validated['scope'] ?: null,
            'notes' => $validated['notes'] ?: null,
        ]);

        if ($agreement->isDirty('project_status')) {
            $agreement->project_status_updated_at = now();
        }

        $agreement->save();

        $this->recordActivities($agreement, $isNew, $originalStatus);

        session()->flash('status', $isNew ? 'Agreement created.' : 'Agreement updated.');

        $this->redirectRoute('agreements.show', $agreement, navigate: true);
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:LOI,NDA,MOA,MOU,SEA,MOC,ADDENDUM'],
            'partner_id' => ['nullable', 'required_if:partnerMode,existing', 'exists:partners,id'],
            'newPartnerName' => ['nullable', 'required_if:partnerMode,new', 'string', 'max:255'],
            'newPartnerShortName' => ['nullable', 'string', 'max:100'],
            'newPartnerCountryId' => ['nullable', 'exists:countries,id'],
            'campus_id' => ['required', 'exists:campuses,id'],
            'pic_user_id' => ['nullable', 'exists:users,id'],
            'sector' => ['nullable', 'in:academic,industri'],
            'agreement_date' => ['nullable', 'date'],
            'effective_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'document_status' => ['required', 'in:pending,awaiting_partner,signed'],
            'project_status' => ['required', 'in:not_started,ongoing,stalled,completed'],
            'scope' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function resolvePartnerId(): int
    {
        if ($this->partnerMode === 'new' && filled($this->newPartnerName)) {
            $partner = Partner::create([
                'name' => $this->newPartnerName,
                'short_name' => $this->newPartnerShortName ?: null,
                'country_id' => $this->newPartnerCountryId,
            ]);

            return $partner->id;
        }

        return $this->partner_id;
    }

    protected function recordActivities(Agreement $agreement, bool $isNew, ?array $originalStatus = null): void
    {
        $record = app(RecordAgreementActivity::class);

        if ($isNew) {
            $record($agreement, 'created', 'Agreement created');

            return;
        }

        foreach (['document_status', 'project_status'] as $field) {
            $from = $originalStatus[$field] ?? null;
            $to = $agreement->$field;

            if ($from !== $to) {
                $record($agreement, 'status_changed', ucfirst(str_replace('_', ' ', $field)).' changed from '.($from ?? 'null').' to '.($to ?? 'null'), [
                    'field' => $field,
                    'from' => $from,
                    'to' => $to,
                ]);
            }
        }
    }

    #[Computed]
    public function campuses()
    {
        return Campus::active()->orderBy('sort_order')->get();
    }

    #[Computed]
    public function users()
    {
        return User::query()
            ->where(function ($query) {
                $query->where('is_active', true)
                    ->orWhere('id', $this->pic_user_id);
            })
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function partners()
    {
        return Partner::orderBy('name')->get();
    }

    #[Computed]
    public function countries()
    {
        return Country::orderBy('name')->get();
    }

    #[Computed]
    public function dateWarning(): ?string
    {
        if (! $this->effective_date || ! $this->expiry_date) {
            return null;
        }

        return $this->expiry_date < $this->effective_date
            ? 'Expiry date is earlier than the effective date. Save anyway if that matches the document.'
            : null;
    }

    #[Computed]
    public function similarPartners()
    {
        if (mb_strlen($this->newPartnerName) < 3) {
            return collect();
        }

        return Partner::whereRaw('LOWER(name) like ?', ['%'.mb_strtolower($this->newPartnerName).'%'])
            ->orderBy('name')
            ->limit(5)
            ->get();
    }
};
?>

<div class="mx-auto max-w-4xl">
    <h1 class="mb-6 text-2xl font-semibold">{{ $agreement ? 'Edit agreement' : 'Create agreement' }}</h1>

    <form wire:submit="save" class="space-y-6 rounded-lg bg-white p-6 shadow">
        <div>
            <label class="block text-sm font-medium">Title</label>
            <input wire:model="title" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">Type</label>
                <select wire:model="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Select type</option>
                    @foreach (['LOI', 'NDA', 'MOA', 'MOU', 'SEA', 'MOC', 'ADDENDUM'] as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
                @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Campus</label>
                <select wire:model="campus_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Select campus</option>
                    @foreach ($this->campuses as $campus)
                        <option value="{{ $campus->id }}">{{ $campus->code }} — {{ $campus->name }}</option>
                    @endforeach
                </select>
                @error('campus_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium">Partner</label>
            <div class="mt-2 flex gap-4 text-sm">
                <label class="flex items-center gap-2">
                    <input wire:model="partnerMode" type="radio" value="existing" class="rounded border-gray-300">
                    Existing partner
                </label>
                <label class="flex items-center gap-2">
                    <input wire:model="partnerMode" type="radio" value="new" class="rounded border-gray-300">
                    New partner
                </label>
            </div>

            @if ($partnerMode === 'existing')
                <select wire:model="partner_id" class="mt-3 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Select partner</option>
                    @foreach ($this->partners as $partner)
                        <option value="{{ $partner->id }}">{{ $partner->name }}</option>
                    @endforeach
                </select>
                @error('partner_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            @else
                <div class="mt-3 space-y-3">
                    <input wire:model.live="newPartnerName" type="text" placeholder="Partner legal name" class="block w-full rounded-md border-gray-300 shadow-sm">
                    @error('newPartnerName') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror

                    @if ($this->similarPartners->isNotEmpty())
                        <div class="rounded-md bg-amber-50 p-3 text-sm text-amber-800">
                            <p class="font-medium">Similar partners already exist:</p>
                            <ul class="mt-1 list-inside list-disc">
                                @foreach ($this->similarPartners as $p)
                                    <li>{{ $p->name }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="grid gap-3 md:grid-cols-2">
                        <input wire:model="newPartnerShortName" type="text" placeholder="Short name (optional)" class="block w-full rounded-md border-gray-300 shadow-sm">
                        <select wire:model="newPartnerCountryId" class="block w-full rounded-md border-gray-300 shadow-sm">
                            <option value="">Country (optional)</option>
                            @foreach ($this->countries as $country)
                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            @endif
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">PIC</label>
                <select wire:model="pic_user_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">No PIC</option>
                    @foreach ($this->users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }}</option>
                    @endforeach
                </select>
                @error('pic_user_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Sector</label>
                <select wire:model="sector" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Select sector</option>
                    <option value="academic">Academic</option>
                    <option value="industri">Industri</option>
                </select>
                @error('sector') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-3">
            <div>
                <label class="block text-sm font-medium">Agreement date</label>
                <input wire:model="agreement_date" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                @error('agreement_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Effective date</label>
                <input wire:model="effective_date" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                @error('effective_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Expiry date</label>
                <input wire:model="expiry_date" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                @error('expiry_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @if ($this->dateWarning)
                    <p class="mt-1 text-sm text-amber-700">{{ $this->dateWarning }}</p>
                @endif
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">Document status</label>
                <select wire:model="document_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="pending">Pending</option>
                    <option value="awaiting_partner">Awaiting Partner</option>
                    <option value="signed">Signed</option>
                </select>
                @error('document_status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Project status</label>
                <select wire:model="project_status" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="not_started">Not Started</option>
                    <option value="ongoing">Ongoing</option>
                    <option value="stalled">Stalled</option>
                    <option value="completed">Completed</option>
                </select>
                @error('project_status') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium">Scope</label>
            <textarea wire:model="scope" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
            @error('scope') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Notes</label>
            <textarea wire:model="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
            @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('agreements.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                {{ $agreement ? 'Update agreement' : 'Create agreement' }}
            </button>
        </div>
    </form>
</div>
