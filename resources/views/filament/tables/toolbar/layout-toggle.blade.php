@php
    $isGrid = $this->isGridLayout();
@endphp

<div class="flex items-center">
    <button
        type="button"
        wire:click="changeLayoutView"
        title="{{ $isGrid ? 'Tablo Görünümüne Geç' : 'Izgara Görünümüne Geç' }}"
        class="fi-icon-btn relative flex items-center justify-center rounded-lg outline-none transition duration-75 focus-visible:ring-2 disabled:pointer-events-none disabled:opacity-70 h-9 w-9 {{ $isGrid ? 'bg-indigo-600/20 text-indigo-400 border border-indigo-500/40 hover:bg-indigo-600/30' : 'text-gray-400 hover:text-gray-200 hover:bg-white/5 border border-slate-700/60' }}"
    >
        @if($isGrid)
            <x-filament::icon
                icon="heroicon-o-bars-3-bottom-left"
                class="h-5 w-5"
            />
        @else
            <x-filament::icon
                icon="heroicon-o-squares-2x2"
                class="h-5 w-5"
            />
        @endif
    </button>
</div>
