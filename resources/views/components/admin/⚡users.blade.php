<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

new class extends Component
{
    public string $search = '';

    public function with(): array
    {
        abort_unless(auth()->user()?->can('users.view'), 403);
        return ['users' => User::query()->when($this->search !== '', fn (Builder $query): Builder => $query->where(fn (Builder $query): Builder => $query->where('name', 'ilike', "%{$this->search}%")->orWhere('email', 'ilike', "%{$this->search}%")))->with('roles')->orderBy('name')->get()];
    }

    public function deactivate(int $userId): void
    {
        abort_unless(auth()->user()?->can('users.delete'), 403);
        abort_if($userId === auth()->id(), 422, 'Akun yang sedang dipakai tidak dapat dinonaktifkan.');
        User::query()->findOrFail($userId)->delete();
        session()->flash('success', 'User berhasil dinonaktifkan.');
    }
};
?>
<div class="space-y-8">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-3xl font-semibold text-brand-950">Users</h1></div>@if(auth()->user()->can('users.create'))<a href="{{ route('admin.users.create') }}" class="rounded-xl bg-brand-950 px-5 py-3 text-sm font-semibold text-white">Tambah user</a>@endif</div>
    @if(session('success'))<div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
    <div class="rounded-3xl border border-slate-200 bg-white p-5"><input wire:model.live.debounce.300ms="search" class="w-full px-4 py-3" placeholder="Cari nama atau email user..."></div>
    <div class="grid gap-4">@forelse($users as $user)<article class="rounded-3xl border border-slate-200 bg-white p-6"><div class="flex flex-wrap items-start justify-between gap-4"><div class="flex items-center gap-4"><div class="grid size-12 place-items-center rounded-2xl bg-brand-100 text-lg font-bold text-brand-700">{{ str($user->name)->substr(0, 1)->upper() }}</div><div><h2 class="text-xl font-semibold text-brand-950">{{ $user->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $user->email }}</p></div></div><div class="flex flex-wrap gap-2">@if(auth()->user()->can('users.update'))<a href="{{ route('admin.users.edit', $user) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold">Edit</a>@endif @if(auth()->user()->can('users.delete') && $user->id !== auth()->id())<button wire:click="deactivate({{ $user->id }})" wire:confirm="Nonaktifkan user ini?" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700">Nonaktifkan</button>@endif</div></div><div class="mt-5 flex flex-wrap gap-2">@forelse($user->roles as $role)<span class="rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">{{ $role->name }}</span>@empty<span class="text-sm text-slate-500">Belum ada role</span>@endforelse</div></article>@empty<div class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500">Belum ada user.</div>@endforelse</div>
</div>