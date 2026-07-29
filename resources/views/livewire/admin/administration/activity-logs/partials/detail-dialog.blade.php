{{--
    Full-detail dialog for one activity-log row — shared by the Activity Logs
    viewer and the per-record profile Activity tab, so "view in detail" is
    identical everywhere rather than reimplemented per screen.

    Expects:
        $activity      ?Activity   (with 'causer', 'subject' eager loaded)
        $dialogId      string      unique <x-ui.dialog> id on the page
        $closeMethod   string      Livewire method to call when the dialog closes
        $showScopeLink bool        show the "Show all activity for this record" link (default true)
        $currentRecord ?Model      the record this page is already showing, if any — its own
                                    subject link is shown as plain text instead of a self-link
--}}
@php
    use App\Support\ActivityPresenter;

    $showScopeLink ??= true;
    $currentRecord ??= null;

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
            return $value ? __('activity_logs.values.yes') : __('activity_logs.values.no');
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
                $subjectUrl = ActivityPresenter::subjectUrl($subject);
                $isCurrentRecord = $subject && $currentRecord
                    && $subject::class === $currentRecord::class
                    && $subject->getKey() === $currentRecord->getKey();
                $sBadge = $actionBadge($activity->event);
            @endphp

            <x-ui.dialog-header>
                <x-ui.dialog-title class="flex items-center gap-2">
                    <x-ui.badge :variant="$sBadge['variant']" class="{{ $sBadge['class'] }}">
                        {{ ActivityPresenter::actionLabel($activity->event) }}
                    </x-ui.badge>
                    <span class="text-muted-foreground">{{ ActivityPresenter::moduleLabel($sProps['module'] ?? null) }}</span>
                </x-ui.dialog-title>
                <x-ui.dialog-description>
                    <x-ui.local-time :value="$activity->created_at" show-diff="true" />
                </x-ui.dialog-description>
            </x-ui.dialog-header>

            <div class="max-h-[60vh] space-y-4 overflow-y-auto">

                {{-- Facts grid --}}
                <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ __('activity_logs.detail.causer') }}</dt>
                        <dd class="font-medium">
                            @if ($activity->causer)
                                {{ $activity->causer->name }}
                                <span class="block text-xs font-normal text-muted-foreground">{{ $activity->causer->email }}</span>
                            @else
                                {{ __('activity_logs.values.system') }}
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ __('activity_logs.detail.subject') }}</dt>
                        <dd class="font-medium">
                            @if ($activity->subject_type)
                                @if ($isCurrentRecord)
                                    <span class="inline-flex flex-wrap items-center gap-1.5 text-muted-foreground">
                                        {{ ActivityPresenter::subjectTypeLabel($activity->subject_type) }} #{{ $activity->subject_id }}
                                        <x-ui.badge variant="outline" class="gap-1 text-[10px] font-normal">
                                            <x-lucide-eye class="size-2.5" />
                                            {{ __('activity_logs.detail.viewing') }}
                                        </x-ui.badge>
                                    </span>
                                @elseif ($subjectUrl)
                                    <a href="{{ $subjectUrl }}" class="text-primary underline hover:no-underline">
                                        {{ ActivityPresenter::subjectTypeLabel($activity->subject_type) }} #{{ $activity->subject_id }}
                                    </a>
                                @elseif ($subject)
                                    {{-- Record exists but there's no page to link to for it (or the viewer lacks the permission). --}}
                                    <span class="font-mono text-xs">
                                        {{ ActivityPresenter::subjectTypeLabel($activity->subject_type) }} #{{ $activity->subject_id }}
                                    </span>
                                @else
                                    <span class="font-mono text-xs">
                                        {{ ActivityPresenter::subjectTypeLabel($activity->subject_type) }} #{{ $activity->subject_id }}
                                    </span>
                                    <span class="block text-xs font-normal text-muted-foreground">{{ __('activity_logs.detail.no_longer_exists') }}</span>
                                @endif
                            @else
                                <span class="text-muted-foreground">—</span>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ __('activity_logs.detail.category') }}</dt>
                        <dd><x-ui.badge variant="secondary">{{ ActivityPresenter::categoryLabel($activity->log_name) }}</x-ui.badge></dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">{{ __('activity_logs.detail.context') }}</dt>
                        <dd><x-ui.badge variant="outline">{{ ActivityPresenter::contextLabel($sProps['context'] ?? null) }}</x-ui.badge></dd>
                    </div>
                </dl>

                {{-- Before → after diff --}}
                @if (! empty($attributes))
                    <div>
                        <p class="mb-1.5 text-xs font-semibold text-muted-foreground">{{ __('activity_logs.detail.changes') }}</p>
                        <div class="overflow-hidden rounded-md border border-border">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-border bg-muted/40 text-xs text-muted-foreground">
                                        <th class="px-3 py-2 text-left font-medium">{{ __('activity_logs.detail.field') }}</th>
                                        <th class="px-3 py-2 text-left font-medium">{{ __('activity_logs.detail.before') }}</th>
                                        <th class="px-3 py-2 text-left font-medium">{{ __('activity_logs.detail.after') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach ($attributes as $field => $newValue)
                                        <tr>
                                            <td class="px-3 py-2 font-medium">{{ ActivityPresenter::fieldLabel($field) }}</td>
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
                        <p class="mb-1.5 text-xs font-semibold text-muted-foreground">{{ __('activity_logs.detail.properties') }}</p>
                        <dl class="space-y-2 rounded-md border border-border bg-muted/20 p-3 text-sm">
                            @foreach ($rest as $key => $value)
                                <div class="flex gap-3">
                                    <dt class="w-32 shrink-0 text-xs text-muted-foreground">{{ ActivityPresenter::fieldLabel($key) }}</dt>
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
                            {{ __('activity_logs.actions.show_all_for_record') }}
                        </button>
                    </div>
                @endif
            </div>
        @endif
    </x-ui.dialog-content>
</x-ui.dialog>
