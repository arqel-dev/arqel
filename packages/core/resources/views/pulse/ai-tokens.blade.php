<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 1" :class="$class ?? ''" wire:poll.30s="">
    <x-pulse::card-header
        name="Arqel AI Tokens"
        title="AI tokens consumed today across all providers."
    />

    <x-pulse::scroll :expand="false">
        <div class="grid grid-cols-2 gap-4 p-4">
            <div>
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Tokens (today)</div>
                <div class="text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100">
                    {{ number_format($tokens) }}
                </div>
            </div>
            <div>
                <div class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">Cost (USD)</div>
                <div class="text-2xl font-bold tabular-nums text-gray-900 dark:text-gray-100">
                    ${{ number_format($cost, 4) }}
                </div>
            </div>
        </div>
    </x-pulse::scroll>
</x-pulse::card>
