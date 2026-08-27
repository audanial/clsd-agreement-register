<?php

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $role = '';

    public string $password = '';

    #[Computed]
    public function users()
    {
        return User::orderBy('name')->get();
    }

    public function create(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'role' => ['required', 'in:admin,legal,viewer'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role' => $validated['role'],
            'password' => $validated['password'],
            'is_active' => true,
        ]);

        $this->reset(['name', 'email', 'role', 'password']);
    }

    public function toggle(int $userId): void
    {
        if ($userId === auth()->id()) {
            return;
        }

        $user = User::findOrFail($userId);
        $user->is_active = ! $user->is_active;
        $user->save();
    }
};
?>

<div class="mx-auto max-w-4xl space-y-6">
    @if (! auth()->user()->canManageUsers())
        <p class="text-gray-700">You do not have permission to manage users.</p>
    @else
        <h1 class="text-2xl font-semibold">User management</h1>

        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="mb-4 text-lg font-medium">Create user</h2>

            <form wire:submit="create" class="grid gap-4 md:grid-cols-2">
                <div>
                    <label for="user-name" class="block text-sm font-medium">Name</label>
                    <input id="user-name" wire:model="name" type="text" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="user-email" class="block text-sm font-medium">Email</label>
                    <input id="user-email" wire:model="email" type="email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="user-role" class="block text-sm font-medium">Role</label>
                    <select id="user-role" wire:model="role" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Select role</option>
                        <option value="admin">Admin</option>
                        <option value="legal">Legal</option>
                        <option value="viewer">Viewer</option>
                    </select>
                    @error('role') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="user-password" class="block text-sm font-medium">Initial password</label>
                    <input id="user-password" wire:model="password" type="password" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                    @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div class="md:col-span-2">
                    <button type="submit" class="rounded-md bg-indigo-600 px-4 py-2 text-white hover:bg-indigo-700">
                        Create user
                    </button>
                </div>
            </form>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="mb-4 text-lg font-medium">Users</h2>

            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-500">Name</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-500">Email</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-500">Role</th>
                        <th class="px-4 py-2 text-left text-sm font-medium text-gray-500">Status</th>
                        <th class="px-4 py-2 text-right text-sm font-medium text-gray-500"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($this->users as $user)
                        <tr>
                            <td class="px-4 py-3 text-sm">{{ $user->name }}</td>
                            <td class="px-4 py-3 text-sm">{{ $user->email }}</td>
                            <td class="px-4 py-3 text-sm">{{ $user->role }}</td>
                            <td class="px-4 py-3 text-sm">{{ $user->is_active ? 'Active' : 'Inactive' }}</td>
                            <td class="px-4 py-3 text-right text-sm">
                                @if ($user->id === auth()->id())
                                    <span class="text-gray-500">Cannot deactivate yourself</span>
                                @else
                                    <button
                                        wire:click="toggle({{ $user->id }})"
                                        type="button"
                                        class="rounded-md px-3 py-1 text-sm {{ $user->is_active ? 'bg-red-100 text-red-800 hover:bg-red-200' : 'bg-green-100 text-green-800 hover:bg-green-200' }}"
                                    >
                                        {{ $user->is_active ? 'Deactivate' : 'Activate' }}
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif
</div>
