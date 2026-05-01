<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 1" :class="$class ?? ''" wire:poll.5s="">
    <x-pulse::card-header
        name="Arqel Resources"
        title="Total Resource classes registered in this panel."
    >
        <x-slot:icon>
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5M3.75 17.25h16.5" />
            </svg>
        </x-slot:icon>
    </x-pulse::card-header>

    <x-pulse::scroll :expand="false">
        <div class="text-3xl font-bold tabular-nums text-gray-900 dark:text-gray-100 p-4">
            {{ number_format($count) }}
        </div>
        <div class="px-4 pb-4 text-xs text-gray-500 dark:text-gray-400">
            Resource{{ $count === 1 ? '' : 's' }} registered
        </div>
    </x-pulse::scroll>
</x-pulse::card>
