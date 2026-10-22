<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-m-heart class="w-5 h-5 text-rose-500" />
                <span class="font-medium text-gray-900 dark:text-gray-100">Sistem Sağlık ve Altyapı Durumu</span>
            </div>
        </x-slot>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-3 mt-2">
            @foreach($services as $svc)
                <div class="system-health-card p-3 rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800 shadow-xs">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-xs font-semibold text-gray-800 dark:text-gray-200">{{ $svc['name'] }}</span>
                        @if($svc['is_healthy'])
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950/70 dark:text-emerald-400 dark:border dark:border-emerald-800/50">
                                {{ $svc['status'] }}
                            </span>
                        @else
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950/70 dark:text-amber-400 dark:border dark:border-amber-800/50">
                                {{ $svc['status'] }}
                            </span>
                        @endif
                    </div>
                    <p class="text-[11px] text-gray-500 dark:text-gray-400 truncate">{{ $svc['detail'] }}</p>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
