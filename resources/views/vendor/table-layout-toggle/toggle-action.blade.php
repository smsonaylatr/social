@php
    $isGrid = $this->isGridLayout();
    $icon = $isGrid ? $listIcon : $gridIcon;
    $tooltip = $isGrid ? 'Tablo / Liste Görünümüne Geç' : 'Izgara (Grid) Görünümüne Geç';
@endphp

<div class="flex items-center">
    <x-filament::icon-button
        :icon="$icon"
        color="gray"
        size="md"
        class="h-9 w-9 text-slate-400 hover:text-white hover:bg-slate-800 transition-colors"
        :tooltip="$tooltip"
        wire:click="changeLayoutView"
    />
</div>
