@php
    $floatingPositionClasses = [
        'top-left' => '-left-5 top-8',
        'top-right' => '-right-5 top-10',
        'middle-left' => '-left-6 top-1/2 -translate-y-1/2',
        'middle-right' => '-right-6 top-1/2 -translate-y-1/2',
        'bottom-left' => '-left-5 bottom-20',
        'bottom-right' => '-right-5 bottom-24',
    ];
    $floatingSizeClasses = ['sm' => 'size-14', 'md' => 'size-20', 'lg' => 'size-28'];
@endphp
@foreach ($items ?? [] as $floatingImage)
    @if (filled($floatingImage['url'] ?? null))
        <img src="{{ $floatingImage['url'] }}" alt="" aria-hidden="true" class="invitation-floating-image {{ $floatingPositionClasses[$floatingImage['position'] ?? 'top-right'] ?? $floatingPositionClasses['top-right'] }} {{ $floatingSizeClasses[$floatingImage['size'] ?? 'md'] ?? $floatingSizeClasses['md'] }}" style="opacity: {{ ((int) ($floatingImage['opacity'] ?? 100)) / 100 }}" loading="lazy">
    @endif
@endforeach
