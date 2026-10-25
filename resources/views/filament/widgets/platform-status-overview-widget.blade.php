<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                <div class="flex items-center gap-2">
                    <x-heroicon-m-signal class="w-5 h-5 text-primary-500" />
                    <span class="font-medium text-gray-900 dark:text-gray-100">Sosyal Medya Platformları ve API Sağlık Durumu</span>
                </div>
                <span class="text-xs font-normal text-gray-500 dark:text-gray-400">Canlı Bağlantı Durumu</span>
            </div>
        </x-slot>

        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-3 mt-2">
            @foreach($platforms as $p)
                <div class="platform-card rounded-xl border border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-800 p-3 shadow-xs hover:border-primary-500/50 transition">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-semibold text-sm text-gray-900 dark:text-gray-100">{{ $p['name'] }}</span>
                        @if($p['status_color'] === 'success')
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                        @elseif($p['status_color'] === 'warning')
                            <span class="inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                        @else
                            <span class="inline-flex rounded-full h-2.5 w-2.5 bg-gray-400 dark:bg-gray-500"></span>
                        @endif
                    </div>

                    <div class="text-xs mb-2">
                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium 
                            {{ $p['status_color'] === 'success' ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400 dark:border dark:border-emerald-800/50' : '' }}
                            {{ $p['status_color'] === 'warning' ? 'bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400 dark:border dark:border-amber-800/50' : '' }}
                            {{ $p['status_color'] === 'gray' ? 'bg-gray-100 text-gray-600 dark:bg-gray-700/60 dark:text-gray-300 dark:border dark:border-gray-600/50' : '' }}
                        ">
                            {{ $p['status_label'] }}
                        </span>
                    </div>

                    <div class="pt-2 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between text-[11px] text-gray-500 dark:text-gray-400">
                        <span>Bugün: <strong class="text-gray-800 dark:text-gray-200">{{ $p['today_posts'] }}</strong>/{{ $p['daily_limit'] }}</span>
                        <a href="{{ url('/admin/social-accounts') }}" class="text-primary-600 dark:text-primary-400 hover:underline font-medium">Yönet</a>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
