<?php

use App\Models\User;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Spatie\Permission\Models\Role;

new class extends Component
{
    public ?int $userId = null;
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $role = '';

    public function mount(?int $userId = null): void
    {
        $this->userId = $userId;
        abort_unless(auth()->user()?->can($userId ? 'users.update' : 'users.create'), 403);
        if ($userId) {
            $user = User::query()->with('roles')->findOrFail($userId);
            $this->name = $user->name;
            $this->email = $user->email;
            $this->role = (string) ($user->roles->first()?->name ?? '');
        }
    }

    public function with(): array
    {
        return ['roles' => Role::query()->orderBy('name')->pluck('name')];
    }

    public function save(): void
    {
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->userId)],
            'password' => [$this->userId ? 'nullable' : 'required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::exists('roles', 'name')],
        ]);
        $user = $this->userId ? User::query()->findOrFail($this->userId) : new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        if (filled($data['password'] ?? null)) {
            $user->password = $data['password'];
        }
        $user->save();
        $user->syncRoles([$data['role']]);
        session()->flash('success', 'User berhasil disimpan.');
        $this->redirectRoute('admin.users');
    }
};
?>
<div class="mx-auto max-w-3xl space-y-8"><div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-3xl font-semibold text-brand-950">{{ $userId ? 'Edit user' : 'Tambah user' }}</h1></div><a href="{{ route('admin.users') }}" class="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700">Kembali</a></div><form wire:submit="save" class="grid gap-5 rounded-3xl border border-slate-200 bg-white p-6 sm:grid-cols-2 sm:p-8"><div class="sm:col-span-2"><label class="text-sm font-semibold text-slate-700">Nama</label><input wire:model="name" class="mt-2 w-full px-4 py-3">@error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div><div><label class="text-sm font-semibold text-slate-700">Email</label><input wire:model="email" type="email" class="mt-2 w-full px-4 py-3">@error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div><div><label class="text-sm font-semibold text-slate-700">Role</label><select wire:model="role" class="mt-2 w-full px-4 py-3"><option value="" disabled>Pilih role</option>@foreach($roles as $roleName)<option value="{{ $roleName }}">{{ $roleName }}</option>@endforeach</select></div><div><label class="text-sm font-semibold text-slate-700">Password {{ $userId ? '(kosongkan jika tidak berubah)' : '' }}</label><input wire:model="password" type="password" class="mt-2 w-full px-4 py-3"></div><div><label class="text-sm font-semibold text-slate-700">Konfirmasi password</label><input wire:model="password_confirmation" type="password" class="mt-2 w-full px-4 py-3"></div><div class="flex justify-end gap-3 sm:col-span-2"><a href="{{ route('admin.users') }}" class="rounded-xl border border-slate-300 px-5 py-3 font-semibold text-slate-700">Batal</a><button class="rounded-xl bg-brand-950 px-5 py-3 font-semibold text-white">Simpan user</button></div></form></div>