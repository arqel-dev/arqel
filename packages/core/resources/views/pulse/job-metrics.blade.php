<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 1" :class="$class ?? ''" wire:poll.10s="">
    <x-pulse::card-header
        name="Arqel Job Metrics"
        title="Pending and failed jobs scoped to Arqel\\* classes."
    />

    <x-pulse::scroll :expand="false">
        <div class="grid grid-cols-2 gap-4 p-4">
            <div>
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Pending</div>
                <div class="text-2xl font-bold tabular-nums text-blue-600 dark:text-blue-400">
                    {{ number_format($pending) }}
                </div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Failed</div>
                <div class="text-2xl font-bold tabular-nums {{ $failed > 0 ? 'text-red-600 dark:text-red-400' : 'text-gray-900 dark:text-gray-100' }}">
                    {{ number_format($failed) }}
                </div>
            </div>
        </div>
    </x-pulse::scroll>
</x-pulse::card>
