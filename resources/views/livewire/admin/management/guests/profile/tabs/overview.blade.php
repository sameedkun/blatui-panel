@php
    /** @var \App\Models\User $record */
    $general = [
        ['label' => 'Name', 'value' => $record->name],
        ['label' => 'Email', 'value' => $record->email],
        ['label' => 'Status', 'value' => $record->banned_at ? 'Banned' : 'Active'],
        ['label' => 'External ID', 'value' => $record->external_id, 'mono' => true],
        ['label' => 'Guest ID', 'value' => (string) $record->id, 'mono' => true],
    ];

    $dates = [
        ['label' => 'Registration date', 'value' => $record->registration_date],
        ['label' => 'Last login', 'value' => $record->last_login, 'diff' => true, 'fallback' => 'Never'],
        ['label' => 'Created at', 'value' => $record->created_at],
    ];

    $bool = fn (bool $v): string => $v ? 'Yes' : 'No';
    $status = [
        ['label' => 'Banned', 'value' => $bool($record->isBanned())],
    ];
@endphp

@php
    $section = function (string $heading, array $rows) {
        return compact('heading', 'rows');
    };
    $sections = [$section('General', $general), $section('Dates', $dates), $section('Status', $status)];
@endphp

<div class="grid gap-6 lg:grid-cols-3">
    @foreach ($sections as $s)
        <x-ui.card>
            <p class="mb-4 text-sm font-medium">{{ $s['heading'] }}</p>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-1">
                @foreach ($s['rows'] as $row)
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ $row['label'] }}</dt>
                        <dd class="mt-0.5 text-sm {{ ! empty($row['mono']) ? 'font-mono text-xs break-all' : '' }}">
                            @if ($row['value'] instanceof \Carbon\CarbonInterface)
                                <x-ui.local-time :value="$row['value']" :show-diff="$row['diff'] ?? false" />
                            @elseif ($row['value'] === null)
                                {{ $row['fallback'] ?? '—' }}
                            @else
                                {{ $row['value'] }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>
    @endforeach
</div>
