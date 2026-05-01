<x-pulse::card :cols="$cols ?? 'full'" :rows="$rows ?? 1" :class="$class ?? ''" wire:poll.30s="">
    <x-pulse::card-header
        name="Arqel Top Actions"
        title="Top 10 Arqel actions by execution count over the last 24h."
    />

    <x-pulse::scroll :expand="false">
        @if (count($rows) === 0)
            <div class="p-4 text-sm text-gray-500 dark:text-gray-400">
                No action executions recorded in the last 24h.
            </div>
        @else
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left">
                        <th class="px-4 py-2">Action</th>
                        <th class="px-4 py-2 text-right">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2 font-mono text-xs">{{ $row['action'] }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ number_format($row['count']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-pulse::scroll>
</x-pulse::card>
