{{--
    Subscription-related slice of the owning user's audit trail. Subscription
    events are logged with the User as subject (see SubscriptionService), never
    the Subscription row itself, so there is no per-row forSubject() scope to
    read off here — this shows every subscription_* event for this user rather
    than only the ones concerning this exact historical row.

    Expects: $record, $activities (paginated Activity, ->with('causer'))
--}}
@php
    use App\Models\User;
    use App\Support\ActivityPresenter;
@endphp

<x-ui.card>
    <div class="mb-4 flex items-center justify-between">
        <div>
            <p class="text-sm font-medium">{{ __('subscriptions.activity.title') }}</p>
            <p class="text-xs text-muted-foreground">{{ __('subscriptions.activity.description') }}</p>
        </div>
        @can('activity_logs.view')
            @if ($record->user)
                <a href="{{ route('admin.activity-logs.index', ['subjectType' => User::class, 'subjectId' => $record->user_id]) }}" class="text-xs text-primary underline hover:no-underline">
                    {{ __('subscriptions.actions.view_user_history') }}
                </a>
            @endif
        @endcan
    </div>

    <div class="divide-y divide-border">
        @forelse ($activities as $activity)
            @php $item = ActivityPresenter::present($activity, $record->user); @endphp
            <div wire:key="subscription-activity-{{ $activity->id }}" class="flex gap-3 py-4 first:pt-0 last:pb-0">
                <span class="flex size-8 shrink-0 items-center justify-center rounded-full {{ $item['colorClass'] }}">
                    <x-dynamic-component :component="'lucide-' . $item['icon']" class="size-4" />
                </span>

                <div class="flex min-w-0 flex-1 flex-col gap-1.5">
                    <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-0.5">
                        <p class="text-sm font-medium">{{ $item['title'] }}</p>
                        <x-ui.local-time :value="$item['timestamp']" format="smart" class="shrink-0 text-xs text-muted-foreground" />
                    </div>

                    @if ($item['rows'])
                        <dl class="flex flex-col gap-1">
                            @foreach ($item['rows'] as $row)
                                <div class="flex items-start gap-2 text-sm">
                                    <dt class="w-24 shrink-0 text-muted-foreground">{{ $row['label'] }}</dt>
                                    <dd class="min-w-0 flex-1 break-words">
                                        @if (is_array($row['value']))
                                            <div class="flex flex-wrap gap-1">
                                                @foreach ($row['value'] as $chip)
                                                    <x-ui.badge variant="outline" class="font-normal">{{ $chip }}</x-ui.badge>
                                                @endforeach
                                            </div>
                                        @else
                                            {{ $row['value'] }}
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if ($activity->causer)
                        <p class="text-xs text-muted-foreground">{{ __('subscriptions.activity.by', ['name' => $activity->causer->name]) }}</p>
                    @endif
                </div>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center gap-2 py-12 text-center">
                <x-lucide-clipboard-list class="size-8 text-muted-foreground/30" />
                <p class="text-sm text-muted-foreground">{{ __('subscriptions.activity.none') }}</p>
            </div>
        @endforelse
    </div>

    @if ($activities->hasPages())
        <div class="mt-4 border-t border-border pt-4">
            {{ $activities->links('livewire.admin.partials.pagination') }}
        </div>
    @endif
</x-ui.card>
