{{--
    Full-detail dialog for one activity-log row — shared by the Activity Logs
    viewer and the per-record profile Activity tab, so "view in detail" is
    identical everywhere rather than reimplemented per screen.

    Expects:
        $activity      ?Activity   (with 'causer', 'subject' eager loaded)
        $dialogId      string      unique <x-ui.dialog> id on the page
        $closeMethod   string      Livewire method to call when the dialog closes
        $showScopeLink bool        show the "Show all activity for this record" link (default true)
--}}
@php
    use App\Support\ActivityPresenter;
    use Illuminate\Support\Str;

    $showScopeLink ??= true;

    $actionBadge = function (?string $event): array {
        return match ($event) {
            'banned', 'deleted', 'force_deleted', 'purged', 'failed' => ['variant' => 'destructive', 'class' => ''],
            'created' => ['variant' => 'default', 'class' => 'border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400'],
            'restored', 'unbanned' => ['variant' => 'default', 'class' => 'border-0 bg-sky-500/15 text-sky-700 dark:text-sky-400'],
            'login', 'password_reset' => ['variant' => 'secondary', 'class' => ''],
            default => ['variant' => 'outline', 'class' => ''],
        };
    };

    $formatValue = function ($value): string {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_null($value)) {
            return '—';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    };
@endphp

<x-ui.dialog :id="$dialogId" :open="$activity !== null"
    x-init="$watch('open', value => { if (! value) $wire.{{ $closeMethod }}() })">
    <x-ui.dialog-content class="sm:max-w-2xl">
        @if ($activity)
            @php
                $sProps = collect($activity->properties ?? []);
                $attributes = (array) $sProps->get('attributes', []);
                $old = (array) $sProps->get('old', []);
                $rest = ActivityPresenter::orderProperties($sProps->except(['module', 'context', 'attributes', 'old']));

                $subject = $activity->subject;
                $subjectUrl = null;
                if ($subject instanceof \App\Models\User) {
                    $subjectUrl = $subject->isStaff()
                        ? route('admin.staff.edit', $subject)
                        : route('admin.users.edit', $subject);
                }
                $sBadge = $actionBadge($activity->event);
            @endphp

            <x-ui.dialog-header>
                <x-ui.dialog-title class="flex items-center gap-2">
                    <x-ui.badge :variant="$sBadge['variant']" class="{{ $sBadge['class'] }}">
                        {{ Str::headline($activity->event ?? '—') }}
                    </x-ui.badge>
                    <span class="text-muted-foreground">{{ Str::headline($sProps['module'] ?? '') }}</span>
                </x-ui.dialog-title>
                <x-ui.dialog-description>
                    {{ $activity->created_at?->toDayDateTimeString() }}
                    ({{ $activity->created_at?->diffForHumans() }})
                </x-ui.dialog-description>
            </x-ui.dialog-header>

            <div class="max-h-[60vh] space-y-4 overflow-y-auto">

                {{-- Facts grid --}}
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">Causer</dt>
                        <dd class="font-medium">
                            @if ($activity->causer)
                                {{ $activity->causer->name }}
                                <span class="block text-xs font-normal text-muted-foreground">{{ $activity->causer->email }}</span>
                            @else
                                System
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Subject</dt>
                        <dd class="font-medium">
                            @if ($activity->subject_type)
                                @if ($subjectUrl)
                                    <a href="{{ $subjectUrl }}" class="text-primary underline hover:no-underline">
                                        {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}
                                    </a>
                                @else
                                    <span class="font-mono text-xs">
                                        {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}
                                    </span>
                                    <span class="block text-xs font-normal text-muted-foreground">no longer exists</span>
                                @endif
                            @else
                                <span class="text-muted-foreground">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Category</dt>
                        <dd><x-ui.badge variant="secondary">{{ Str::headline($activity->log_name ?? '—') }}</x-ui.badge></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">Context</dt>
                        <dd><x-ui.badge variant="outline">{{ Str::headline($sProps['context'] ?? '—') }}</x-ui.badge></dd>
                    </div>
                </dl>

                {{-- Before → after diff --}}
                @if (! empty($attributes))
                    <div>
                        <p class="mb-1.5 text-xs font-semibold text-muted-foreground">Changes</p>
                        <div class="overflow-hidden rounded-md border border-border">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-border bg-muted/40 text-xs text-muted-foreground">
                                        <th class="px-3 py-2 text-left font-medium">Field</th>
                                        <th class="px-3 py-2 text-left font-medium">Before</th>
                                        <th class="px-3 py-2 text-left font-medium">After</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach ($attributes as $field => $newValue)
                                        <tr>
                                            <td class="px-3 py-2 font-medium">{{ Str::headline($field) }}</td>
                                            <td class="px-3 py-2 text-muted-foreground line-through decoration-destructive/40">
                                                {{ $formatValue($old[$field] ?? null) }}
                                            </td>
                                            <td class="px-3 py-2 text-emerald-700 dark:text-emerald-400">
                                                {{ $formatValue($newValue) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Remaining properties --}}
                @if ($rest->isNotEmpty())
                    <div>
                        <p class="mb-1.5 text-xs font-semibold text-muted-foreground">Properties</p>
                        <dl class="space-y-2 rounded-md border border-border bg-muted/20 p-3 text-sm">
                            @foreach ($rest as $key => $value)
                                <div class="flex gap-3">
                                    <dt class="w-32 shrink-0 text-xs text-muted-foreground">{{ Str::headline($key) }}</dt>
                                    <dd class="min-w-0 flex-1 break-words font-mono text-xs">
                                        @if (is_array($value) && (isset($value['attributes']) || isset($value['old'])))
                                            {{ $formatValue($value['old'] ?? null) }}
                                            <span class="text-muted-foreground">→</span>
                                            {{ $formatValue($value['attributes'] ?? null) }}
                                        @else
                                            {{ $formatValue($value) }}
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                {{-- Scope to this subject (viewer-only — the profile tab is already scoped) --}}
                @if ($showScopeLink && $activity->subject_type)
                    <div class="border-t border-border pt-3">
                        <button type="button"
                            @click="open = false"
                            wire:click="scopeToActivitySubject({{ $activity->id }})"
                            class="inline-flex items-center gap-1.5 text-xs text-primary underline hover:no-underline">
                            <x-lucide-filter class="size-3.5" />
                            Show all activity for this record
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </x-ui.dialog-content>
</x-ui.dialog>
