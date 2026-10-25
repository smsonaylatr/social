<div class="flex items-center gap-2">
    <button
        type="button"
        wire:click="deploy"
        wire:loading.attr="disabled"
        title="Sunucuyu GitHub ile Güncelle (git pull && migrate)"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-emerald-600/20 text-emerald-400 border border-emerald-500/30 hover:bg-emerald-600/30 hover:border-emerald-500/50 transition-all duration-150 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed cursor-pointer"
    >
        <span wire:loading.remove wire:target="deploy" class="flex items-center gap-1.5">
            <svg class="w-4 h-4 text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            <span>Yenile & Güncelle</span>
        </span>

        <span wire:loading wire:target="deploy" class="flex items-center gap-1.5">
            <svg class="animate-spin w-4 h-4 text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span>Güncelleniyor...</span>
        </span>
    </button>
</div>
