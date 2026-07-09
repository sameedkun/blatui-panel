@php
    use Illuminate\Support\Str;
    /** @var \App\Models\User $record */

    $general = [
        ['label' => 'Name', 'value' => $record->name],
        ['label' => 'Email', 'value' => $record->email],
        ['label' => 'Type', 'value' => Str::headline($record->type->value)],
        ['label' => 'Status', 'value' => $record->banned_at ? 'Banned' : 'Active'],
        ['label' => 'External ID', 'value' => $record->external_id, 'mono' => true],
        ['label' => 'User ID', 'value' => (string) $record->id, 'mono' => true],
    ];

    $dates = [
        ['label' => 'Registration date', 'value' => $record->registration_date?->format('M d, Y H:i') ?? '—'],
        ['label' => 'Last login', 'value' => $record->last_login?->diffForHumans() ?? 'Never'],
        ['label' => 'Password changed', 'value' => $record->password_changed_at?->format('M d, Y H:i') ?? 'Never'],
        ['label' => 'Email verified at', 'value' => $record->email_verified_at?->format('M d, Y H:i') ?? 'Not verified'],
        ['label' => 'Created at', 'value' => $record->created_at?->format('M d, Y H:i') ?? '—'],
    ];

    $bool = fn (bool $v): string => $v ? 'Yes' : 'No';
    $status = [
        ['label' => 'Verified', 'value' => $bool($record->hasVerifiedEmail())],
        ['label' => 'Banned', 'value' => $bool($record->isBanned())],
        ['label' => 'Pending deletion', 'value' => $bool($record->isPendingDeletion())],
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
                            {{ $row['value'] }}
                        </dd>
                    </div>
                @endforeach
            </dl>
        </x-ui.card>
    @endforeach
</div>
