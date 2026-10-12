<?php

use App\Models\Event;
use App\Models\InvitationTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public string $search = '';
    public bool $formOpen = false;
    public ?int $editingId = null;
    public string $name = '';
    public string $slug = '';
    public string $description = '';
    public bool $isActive = true;
    public string $primaryColor = '#1F2937';
    public string $secondaryColor = '#F3F4F6';
    public string $fontFamily = 'Inter';
    public array $sections = ['hero', 'event_info', 'location', 'rsvp', 'guestbook'];
    public string $eventId = '';

    public function with(): array
    {
        abort_unless(auth()->user()?->can('templates.view'), 403);
        return [
            'templates' => InvitationTemplate::query()->when($this->search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query): void { $query->where('name', 'ilike', "%{$this->search}%")->orWhere('slug', 'ilike', "%{$this->search}%"); }))->withCount('events')->latest()->get(),
            'events' => $this->accessibleEvents()->orderBy('event_date')->pluck('name', 'id'),
        ];
    }

    public function openCreate(): void
    {
        abort_unless(auth()->user()?->can('templates.create'), 403);
        $this->resetForm(); $this->formOpen = true;
    }

    public function openEdit(int $templateId): void
    {
        abort_unless(auth()->user()?->can('templates.update'), 403);
        $template = InvitationTemplate::query()->findOrFail($templateId);
        $this->editingId = $template->id; $this->name = $template->name; $this->slug = $template->slug; $this->description = $template->description ?? ''; $this->isActive = $template->is_active;
        $this->primaryColor = data_get($template->schema, 'primary_color', '#1F2937'); $this->secondaryColor = data_get($template->schema, 'secondary_color', '#F3F4F6'); $this->fontFamily = data_get($template->schema, 'font_family', 'Inter'); $this->sections = data_get($template->schema, 'sections', $this->sections); $this->eventId = (string) ($template->events->first()?->id ?? ''); $this->formOpen = true;
    }

    public function save(): void
    {
        abort_unless(auth()->user()?->can($this->editingId ? 'templates.update' : 'templates.create'), 403);
        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'], 'slug' => ['required', 'alpha_dash', 'max:255', Rule::unique('invitation_templates', 'slug')->ignore($this->editingId)], 'description' => ['nullable', 'string', 'max:5000'], 'isActive' => ['boolean'],
            'primaryColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'secondaryColor' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'fontFamily' => ['required', Rule::in(['Inter', 'Poppins', 'Playfair Display', 'Cormorant Garamond', 'Merriweather'])], 'sections' => ['required', 'array'], 'sections.*' => ['in:hero,event_info,location,gallery,rsvp,guestbook,contact'], 'eventId' => ['nullable', 'integer'],
        ]);
        $template = InvitationTemplate::query()->updateOrCreate(['id' => $this->editingId], ['name' => $data['name'], 'slug' => $data['slug'], 'description' => $data['description'] ?: null, 'is_active' => $data['isActive'], 'schema' => ['primary_color' => $data['primaryColor'], 'secondary_color' => $data['secondaryColor'], 'font_family' => $data['fontFamily'], 'sections' => $data['sections']]]);
        if (filled($data['eventId'])) { abort_unless($this->accessibleEvents()->whereKey($data['eventId'])->exists(), 403); $template->events()->syncWithoutDetaching([$data['eventId']]); }
        $this->formOpen = false; $this->resetForm(); session()->flash('success', 'Template berhasil disimpan.');
    }

    public function toggleActive(int $templateId): void
    {
        abort_unless(auth()->user()?->can('templates.update'), 403);
        $template = InvitationTemplate::query()->findOrFail($templateId); $template->update(['is_active' => ! $template->is_active]);
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user(); return Event::query()->when(! $user->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void { $query->where('created_by', $user->getKey())->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey())); }));
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'slug', 'description', 'eventId']); $this->isActive = true; $this->primaryColor = '#1F2937'; $this->secondaryColor = '#F3F4F6'; $this->fontFamily = 'Inter'; $this->sections = ['hero', 'event_info', 'location', 'rsvp', 'guestbook']; $this->resetValidation();
    }
};
?>

    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-3xl font-semibold text-brand-950">Invitation templates</h1></div>@if (auth()->user()->can('templates.create'))<a href="{{ route('admin.invitation-templates.create') }}" class="rounded-xl bg-brand-950 px-5 py-3 text-sm font-semibold text-white">Tambah template</a>@endif</div>
        @if (session('success'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        <div class="rounded-3xl border border-slate-200 bg-white p-5"><input wire:model.live.debounce.300ms="search" class="w-full rounded-xl border-slate-300 px-4 py-3" placeholder="Cari nama atau slug template..."></div>
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">@forelse ($templates as $template)<article class="overflow-hidden rounded-3xl border border-slate-200 bg-white"><div class="h-28 p-5" style="background: linear-gradient(135deg, {{ data_get($template->schema, 'primary_color', '#1F2937') }}, {{ data_get($template->schema, 'secondary_color', '#F3F4F6') }});"><span class="rounded-full bg-white/90 px-3 py-1 text-xs font-semibold text-slate-700">{{ $template->is_active ? 'Aktif' : 'Nonaktif' }}</span></div><div class="p-6"><h2 class="text-xl font-semibold">{{ $template->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $template->slug }} · {{ $template->events_count }} event</p><p class="mt-4 line-clamp-2 text-sm text-slate-600">{{ $template->description ?: 'Belum ada deskripsi.' }}</p><div class="mt-5 flex gap-2">@if (auth()->user()->can('templates.update'))<a href="{{ route('admin.invitation-templates.edit', $template) }}" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold">Edit</a><button wire:click="toggleActive({{ $template->id }})" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold">{{ $template->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>@endif</div></div></article>@empty<div class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500 md:col-span-2 xl:col-span-3">Belum ada template.</div>@endforelse</div>
    </div>