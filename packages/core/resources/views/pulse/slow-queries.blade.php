<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 1" :class="$class ?? ''">
    <x-pulse::card-header
        name="Arqel Slow Queries"
        title="Slow queries originating from Arqel resource controllers."
    />

    <x-pulse::scroll :expand="false">
        <div class="p-4 text-sm text-gray-500 dark:text-gray-400">
            Configure the Pulse Slow Queries recorder to see Arqel-specific queries.
            <span class="block mt-2 text-xs">
                TODO: scoped instrumentation in a follow-up to LCLOUD-003.
            </span>
        </div>
    </x-pulse::scroll>
</x-pulse::card>
