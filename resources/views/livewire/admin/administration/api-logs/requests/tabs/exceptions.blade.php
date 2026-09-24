<div class="flex flex-col gap-6">
    @foreach ($exceptions as $exception)
        @php $appFrames = collect($exception->trace ?? [])->where('app', true)->count(); @endphp

        <x-ui.card wire:key="exception-{{ $exception->id }}" x-data="{ vendor: false }">
            <x-ui.card-header>
                <x-ui.card-title class="break-all font-mono text-sm text-rose-700 dark:text-rose-400">{{ $exception->class }}</x-ui.card-title>
                <x-ui.card-description class="break-words text-sm text-foreground">{{ $exception->message }}</x-ui.card-description>
                @if ($exception->file)
                    <p class="font-mono text-xs text-muted-foreground">{{ $exception->file }}:{{ $exception->line }}</p>
                @endif
            </x-ui.card-header>
            <x-ui.card-content class="flex flex-col gap-5">

                @if ($exception->previous)
                    <div>
                        <p class="mb-1.5 text-xs font-semibold text-muted-foreground">{{ __('api_logs.exception.previous') }}</p>
                        @foreach ($exception->previous as $index => $previous)
                            <div wire:key="previous-{{ $exception->id }}-{{ $index }}" class="mb-1.5 rounded-md border border-border p-2">
                                <p class="font-mono text-xs">{{ $previous['class'] }}</p>
                                <p class="text-xs">{{ $previous['message'] }}</p>
                                <p class="font-mono text-[11px] text-muted-foreground">{{ $previous['file'] }}:{{ $previous['line'] }}</p>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div>
                    <div class="mb-1.5 flex items-center justify-between">
                        <p class="text-xs font-semibold text-muted-foreground">{{ __('api_logs.exception.trace') }}</p>
                        @if ($appFrames < count($exception->trace ?? []))
                            <button type="button" @click="vendor = ! vendor" class="text-xs text-primary hover:underline">
                                <span x-show="! vendor">{{ __('api_logs.actions.show_vendor_frames') }}</span>
                                <span x-show="vendor" x-cloak>{{ __('api_logs.actions.hide_vendor_frames') }}</span>
                            </button>
                        @endif
                    </div>
                    <ol class="divide-y divide-border overflow-hidden rounded-md border border-border font-mono text-xs">
                        @foreach ($exception->trace ?? [] as $index => $frame)
                            <li wire:key="frame-{{ $exception->id }}-{{ $index }}"
                                @if (! $frame['app']) x-show="vendor" x-cloak @endif
                                @class(['px-3 py-1.5', 'bg-primary/5' => $frame['app'], 'text-muted-foreground' => ! $frame['app']])>
                                <span class="block break-all">{{ $frame['call'] ?: '—' }}</span>
                                @if ($frame['file'])
                                    <span class="block break-all text-[11px] text-muted-foreground">{{ $frame['file'] }}:{{ $frame['line'] }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ol>
                </div>

                <div>
                    <p class="mb-1.5 text-xs font-semibold text-muted-foreground">{{ __('api_logs.exception.occurrences') }}</p>
                    @forelse ($occurrences[$exception->fingerprint] ?? [] as $other)
                        <a wire:key="occurrence-{{ $exception->id }}-{{ $other->request_id }}" href="{{ route('admin.api-logs.requests.show', $other->request_id) }}" wire:navigate
                            class="flex items-center gap-3 rounded px-2 py-1 text-xs hover:bg-muted/40">
                            <span class="font-mono text-primary">{{ $other->request_id }}</span>
                            <span class="ml-auto text-muted-foreground"><x-ui.local-time :value="$other->created_at" show-diff="true" /></span>
                        </a>
                    @empty
                        <p class="text-xs text-muted-foreground">{{ __('api_logs.exception.occurrences_empty', ['days' => config('api_logs.retention.exception_days')]) }}</p>
                    @endforelse
                    <p class="mt-2 font-mono text-[11px] text-muted-foreground">{{ __('api_logs.exception.fingerprint') }}: {{ $exception->fingerprint }}</p>
                </div>

            </x-ui.card-content>
        </x-ui.card>
    @endforeach
</div>
