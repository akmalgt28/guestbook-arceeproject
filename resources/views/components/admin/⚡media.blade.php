<?php

use App\Models\Event;
use App\Models\MediaFile;
use App\Services\GoogleDriveService;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public string $eventFilter = '';
    public bool $eventContext = false;
    public function mount(?string $eventFilter = null, bool $eventContext = false): void
    {
        $this->eventContext = $eventContext;

        if ($eventFilter !== null && $eventFilter !== '') {
            $this->eventFilter = $eventFilter;
            $this->uploadEventId = $eventFilter;
            $this->eventContext = true;
        }
    }

    public string $uploadEventId = '';
    public bool $uploadOpen = false;
    public $mediaFile;

    public function with(): array
    {
        abort_unless(auth()->user()?->can('media.view'), 403);
        return [
            'events' => $this->accessibleEvents()->orderBy('event_date')->pluck('name', 'id'),
            'mediaFiles' => MediaFile::query()->whereIn('event_id', $this->accessibleEvents()->select('id'))->when($this->eventFilter !== '', fn (Builder $query): Builder => $query->where('event_id', $this->eventFilter))->with('event')->latest()->get(),
        ];
    }

    public function upload(): void
    {
        abort_unless(auth()->user()?->can('media.upload'), 403);
        $data = $this->validate(['uploadEventId' => ['required', 'integer'], 'mediaFile' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:20480']]);
        abort_unless($this->accessibleEvents()->whereKey($data['uploadEventId'])->exists(), 403);

        try {
            $uploaded = app(GoogleDriveService::class)->upload($this->mediaFile->getRealPath(), $this->mediaFile->getClientOriginalName());
            MediaFile::query()->create(['event_id' => $data['uploadEventId'], 'uploaded_by' => auth()->id(), 'drive_file_id' => $uploaded['id'], 'folder_id' => data_get($uploaded, 'parents.0'), 'name' => $uploaded['name'] ?? $this->mediaFile->getClientOriginalName(), 'mime_type' => $uploaded['mimeType'] ?? $this->mediaFile->getMimeType(), 'url' => $uploaded['webViewLink'] ?? null, 'metadata' => $uploaded]);
            $this->uploadOpen = false; $this->reset(['mediaFile', 'uploadEventId']); session()->flash('success', 'Media berhasil diunggah ke Google Drive.');
        } catch (\Throwable $exception) {
            $this->addError('mediaFile', $exception->getMessage());
        }
    }

    public function delete(int $mediaId): void
    {
        abort_unless(auth()->user()?->can('media.delete'), 403);
        $media = MediaFile::query()->whereIn('event_id', $this->accessibleEvents()->select('id'))->findOrFail($mediaId);
        try { app(GoogleDriveService::class)->delete($media->drive_file_id); } catch (\Throwable $exception) { $this->addError('mediaFile', $exception->getMessage()); return; }
        $media->delete(); session()->flash('success', 'Media berhasil dihapus.');
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user(); return Event::query()->when(! $user->hasRole('Super Admin'), fn (Builder $query): Builder => $query->where(function (Builder $query) use ($user): void { $query->where('created_by', $user->getKey())->orWhereHas('admins', fn (Builder $query): Builder => $query->whereKey($user->getKey())); }));
    }
};
?>

    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-4"><div><h1 class="text-3xl font-semibold text-brand-950">Media</h1></div>@if (auth()->user()->can('media.upload'))<a href="{{ ($eventContext ? route('admin.events.media.upload', $eventFilter) : route('admin.media.upload')) }}" class="rounded-xl bg-brand-950 px-5 py-3 text-sm font-semibold text-white">Upload media</a>@endif</div>
        @if (session('success'))<div class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">{{ session('success') }}</div>@endif
        @error('mediaFile')<div class="rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $message }}</div>@enderror
        <div class="rounded-3xl border border-slate-200 bg-white p-5">@if ($eventContext)<div class="flex h-[50px] items-center justify-between rounded-xl border border-slate-300 bg-slate-100 px-4 py-3 text-slate-700"><span>{{ $events->get($eventFilter, 'Event aktif') }}</span><i data-lucide="lock" class="size-4 text-slate-500"></i></div>@else<select wire:model.live="eventFilter" class="w-full rounded-xl border-slate-300 px-4 py-3"><option value="">Semua event</option>@foreach ($events as $id => $eventName)<option value="{{ $id }}">{{ $eventName }}</option>@endforeach</select>@endif</div>
        <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">@forelse ($mediaFiles as $media)<article class="overflow-hidden rounded-3xl border border-slate-200 bg-white">@if (str_starts_with($media->mime_type, 'image/') && $media->url)<img src="{{ $media->url }}" alt="{{ $media->name }}" class="h-48 w-full object-cover">@else<div class="grid h-48 place-items-center bg-slate-100 text-sm font-semibold text-slate-500">{{ strtoupper($media->mime_type) }}</div>@endif<div class="p-5"><h2 class="truncate font-semibold">{{ $media->name }}</h2><p class="mt-1 text-sm text-slate-500">{{ $media->event->name }}</p>@if (auth()->user()->can('media.delete'))<button wire:click="delete({{ $media->id }})" wire:confirm="Hapus media ini?" class="mt-4 rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700">Hapus</button>@endif</div></article>@empty<div class="rounded-3xl border border-dashed border-slate-300 bg-white p-12 text-center text-slate-500 sm:col-span-2 lg:col-span-3">Belum ada media.</div>@endforelse</div>
    </div>