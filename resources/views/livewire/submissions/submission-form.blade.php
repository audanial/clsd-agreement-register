<?php

use App\Actions\CreateSubmission;
use App\Models\Campus;
use App\Models\Submission;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $title = '';

    public ?int $campus_id = null;

    public string $partner_name = '';

    public string $agreement_type = '';

    public string $purpose = '';

    /**
     * Requester-only form. Authorized on every request so a deactivated
     * requester, or a legal/admin session replaying a snapshot, cannot submit.
     */
    public function boot(): void
    {
        Gate::authorize('create', Submission::class);
    }

    /**
     * Delegate everything trusted to the creation boundary: it derives the
     * owner from the session, validates every field, and writes the submission
     * plus its audit event atomically. Livewire maps its ValidationException
     * onto the field errors below — no rules are duplicated here.
     */
    public function save(): void
    {
        $submission = app(CreateSubmission::class)([
            'title' => $this->title,
            'campus_id' => $this->campus_id,
            'partner_name' => $this->partner_name,
            // The form's "Not sure" blank option arrives as '' and is stored
            // as null, exactly like the trusted action's nullable rule.
            'agreement_type' => $this->agreement_type === '' ? null : $this->agreement_type,
            'purpose' => $this->purpose,
        ]);

        session()->flash('status', 'Submission sent to Legal.');

        $this->redirectRoute('submissions.show', $submission, navigate: true);
    }

    /**
     * Only active, non-TBD campuses — mirroring the trusted boundary's
     * exists + is_active + code <> 'TBD' constraint, since the dropdown is a
     * convenience, never the validation.
     */
    #[Computed]
    public function campuses()
    {
        return Campus::active()
            ->where('code', '!=', 'TBD')
            ->orderBy('sort_order')
            ->get();
    }
};
?>

<div class="mx-auto max-w-4xl">
    <h1 class="mb-6 text-2xl font-semibold">Create Submission</h1>

    <form wire:submit="save" class="space-y-6 rounded-lg bg-white p-6 shadow">
        <div class="rounded-md border-l-4 border-[#7B2231] bg-[#FBF2F3] px-4 py-3 text-sm text-[#5C2B2E]">
            <span class="font-semibold">Private &amp; Confidential</span> — this request is visible only to you and UniKL Legal staff.
        </div>

        <div>
            <label class="block text-sm font-medium">Title</label>
            <input wire:model="title" type="text" placeholder="A short, descriptive title for your request" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-6 md:grid-cols-2">
            <div>
                <label class="block text-sm font-medium">Campus / Department</label>
                <select wire:model="campus_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Select campus / department</option>
                    @foreach ($this->campuses as $campus)
                        <option value="{{ $campus->id }}">{{ $campus->code }} — {{ $campus->name }}</option>
                    @endforeach
                </select>
                @error('campus_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Agreement type</label>
                <select wire:model="agreement_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    <option value="">Not sure</option>
                    @foreach (Submission::AGREEMENT_TYPES as $type)
                        <option value="{{ $type }}">{{ $type }}</option>
                    @endforeach
                </select>
                @error('agreement_type') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium">Partner / organisation</label>
            <input wire:model="partner_name" type="text" placeholder="Legal name of the partner organisation" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
            @error('partner_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium">Purpose / scope</label>
            <textarea wire:model="purpose" rows="6" placeholder="What the agreement covers and why it is needed" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm"></textarea>
            @error('purpose') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('submissions.index') }}" class="text-sm text-gray-600 hover:text-gray-900">Cancel</a>
            <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                Submit to Legal
            </button>
        </div>
    </form>
</div>
