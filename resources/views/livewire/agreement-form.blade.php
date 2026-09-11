<?php

use App\Actions\RecordAgreementActivity;
use App\Models\Agreement;
use App\Models\Campus;
use App\Models\Country;
use App\Models\Partner;
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

    public string $newPartnerCountry = 'local';

    public ?int $campus_id = null;

    public string $picMode = 'existing';

    public ?string $pic_name = null;

    public string $sector = '';

    public ?string $agreement_date = null;

    public ?string $expiry_date = null;

    public string $document_status = 'pending';

    public string $project_status = 'not_started';

    public string $scope = '';

    public string $notes = '';

    public array $dropdownOpen = [];

    public function mount(?Agreement $agreement = null): void
    {
        $this->agreement = $agreement;

        if ($agreement) {
            $this->title = $agreement->title;
            $this->type = $agreement->type;
            $this->partner_id = $agreement->partner_id;
            $this->campus_id = $agreement->campus_id;
            $this->pic_name = $agreement->pic_name;
            $this->sector = $agreement->sector ?? '';
            $this->agreement_date = $agreement->agreement_date?->format('Y-m-d');
            $this->expiry_date = $agreement->expiry_date?->format('Y-m-d');
            $this->document_status = $agreement->document_status;
            $this->project_status = $agreement->project_status;
            $this->scope = $agreement->scope ?? '';
            $this->notes = $agreement->notes ?? '';
        }
    }

    public function toggleDropdown(string $name): void
    {
        $this->dropdownOpen[$name] = ! ($this->dropdownOpen[$name] ?? false);
    }

    public function closeDropdown(string $name): void
    {
        $this->dropdownOpen[$name] = false;
    }

    public function selectDropdown(string $name, mixed $value): void
    {
        $this->{$name} = $value;
        $this->dropdownOpen[$name] = false;
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
            'pic_name' => $validated['pic_name'] ?: null,
            'sector' => $validated['sector'] ?: null,
            'agreement_date' => $validated['agreement_date'] ?: null,
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
            'type' => ['required', 'in:LOI,NDA,MOA,MOU,SEA,ADDENDUM'],
            'partner_id' => ['nullable', 'required_if:partnerMode,existing', 'exists:partners,id'],
            'newPartnerName' => ['nullable', 'required_if:partnerMode,new', 'string', 'max:255'],
            'newPartnerShortName' => ['nullable', 'string', 'max:100'],
            'newPartnerCountry' => ['required', 'in:local,international'],
            'campus_id' => ['required', 'exists:campuses,id'],
            'picMode' => ['required', 'in:existing,new'],
            'pic_name' => ['nullable', 'required_if:picMode,new', 'string', 'max:255'],
            'sector' => ['nullable', 'in:academic,industri'],
            'agreement_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'document_status' => ['required', 'in:pending,awaiting_partner,signed'],
            'project_status' => ['required', 'in:not_started,ongoing,stalled,completed'],
            'scope' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    protected function resolvePartnerId(): ?int
    {
        if ($this->partnerMode === 'new' && filled($this->newPartnerName)) {
            $countryId = match ($this->newPartnerCountry) {
                'local' => Country::where('is_domestic', true)->first()?->id,
                'international' => Country::where('name', 'International')->first()?->id,
                default => null,
            };

            $partner = Partner::create([
                'name' => $this->newPartnerName,
                'short_name' => $this->newPartnerShortName ?: null,
                'country_id' => $countryId,
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
    public function existingPics()
    {
        return Agreement::query()
            ->whereNotNull('pic_name')
            ->distinct()
            ->orderBy('pic_name')
            ->pluck('pic_name');
    }

    #[Computed]
    public function partners()
    {
        return Partner::orderBy('name')->get();
    }

    #[Computed]
    public function campusOptions(): array
    {
        return $this->campuses->map(fn (Campus $campus) => [
            'value' => $campus->id,
            'label' => '<strong class="font-bold">'.e($campus->code).'</strong> — '.e($campus->name),
        ])->all();
    }

    #[Computed]
    public function partnerOptions(): array
    {
        return $this->partners->map(fn (Partner $partner) => [
            'value' => $partner->id,
            'label' => e($partner->name),
        ])->all();
    }

    #[Computed]
    public function picOptions(): array
    {
        return $this->existingPics
            ->map(fn (string $name) => ['value' => $name, 'label' => e($name)])
            ->prepend(['value' => '', 'label' => 'No PIC'])
            ->all();
    }

    #[Computed]
    public function countries()
    {
        return Country::orderBy('name')->get();
    }

    #[Computed]
    public function dateWarning(): ?string
    {
        if (! $this->agreement_date || ! $this->expiry_date) {
            return null;
        }

        return $this->expiry_date < $this->agreement_date
            ? 'Expiry date is earlier than the date signed. Save anyway if that matches the document.'
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
                    @foreach (['LOI', 'NDA', 'MOA', 'MOU', 'SEA', 'ADDENDUM'] as $t)
                        <option value="{{ $t }}">{{ $t }}</option>
                    @endforeach
                </select>
                @error('type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Campus / Department</label>
                <x-dropdown-select
                    name="campus_id"
                    placeholder="Select campus / department"
                    :options="$this->campusOptions"
                    :value="$campus_id"
                    :is-open="$dropdownOpen['campus_id'] ?? false"
                />
                @error('campus_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium">Partner</label>
            <div class="mt-2 flex gap-4 text-sm">
                <label class="flex items-center gap-2">
                    <input wire:model.live="partnerMode" type="radio" value="existing" class="rounded border-gray-300">
                    Existing partner
                </label>
                <label class="flex items-center gap-2">
                    <input wire:model.live="partnerMode" type="radio" value="new" class="rounded border-gray-300">
                    New partner
                </label>
            </div>

            @if ($partnerMode === 'existing')
                <x-dropdown-select
                    name="partner_id"
                    placeholder="Select partner"
                    :options="$this->partnerOptions"
                    :value="$partner_id"
                    :is-open="$dropdownOpen['partner_id'] ?? false"
                />
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
                        <div class="flex items-center gap-4 text-sm">
                            <label class="flex items-center gap-2">
                                <input wire:model.live="newPartnerCountry" type="radio" value="local" class="rounded border-gray-300">
                                Local
                            </label>
                            <label class="flex items-center gap-2">
                                <input wire:model.live="newPartnerCountry" type="radio" value="international" class="rounded border-gray-300">
                                International
                            </label>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">PIC</label>
                <div class="mt-2 flex gap-4 text-sm">
                    <label class="flex items-center gap-2">
                        <input wire:model.live="picMode" type="radio" value="existing" class="rounded border-gray-300">
                        Existing PIC
                    </label>
                    <label class="flex items-center gap-2">
                        <input wire:model.live="picMode" type="radio" value="new" class="rounded border-gray-300">
                        New PIC
                    </label>
                </div>

                @if ($picMode === 'existing')
                    <x-dropdown-select
                        name="pic_name"
                        placeholder="No PIC"
                        :options="$this->picOptions"
                        :value="$pic_name"
                        :is-open="$dropdownOpen['pic_name'] ?? false"
                    />
                @else
                    <input wire:model="pic_name" type="text" placeholder="Full name, e.g. Ahmad bin Osman" class="mt-3 block w-full rounded-md border-gray-300 shadow-sm">
                @endif
                @error('pic_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Sector</label>
                <select wire:model="sector" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Select sector</option>
                    <option value="academic">Academic</option>
                    <option value="industri">Industry</option>
                </select>
                @error('sector') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">Date Signed (DD/MM/YYYY)</label>
                <input wire:model="agreement_date" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                @error('agreement_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-sm text-gray-500">
                    You selected: <x-date :value="$agreement_date" />
                </p>
            </div>

            <div>
                <label class="block text-sm font-medium">Expiry date (DD/MM/YYYY)</label>
                <input wire:model="expiry_date" type="date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                @error('expiry_date') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                <p class="mt-1 text-sm text-gray-500">
                    You selected: <x-date :value="$expiry_date" />
                </p>
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
