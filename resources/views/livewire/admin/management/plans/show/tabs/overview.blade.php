@php
    use Illuminate\Support\Str;
    /** @var \App\Models\Plan $record */

    $general = [
        ['label' => 'Plan Name', 'value' => $record->name],
        ['label' => 'Permalink Slug', 'value' => '/' . $record->slug, 'mono' => true],
        ['label' => 'Description', 'value' => $record->description],
        ['label' => 'Status', 'value' => $record->is_active ? 'Active' : 'Inactive', 'badge' => true, 'active' => $record->is_active],
        ['label' => 'Best Deal Recommended', 'value' => $record->is_best_deal ? 'Yes' : 'No'],
        ['label' => 'Sort Order Position', 'value' => (string) $record->sort_order],
        ['label' => 'Database Record ID', 'value' => (string) $record->id, 'mono' => true],
    ];
@endphp

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    {{-- Main Specifications Column --}}
    <div class="space-y-6 lg:col-span-2">

        {{-- Specifications Card --}}
        <x-ui.card>
            <x-ui.card-header class="border-b border-border/50 pb-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                        <x-lucide-package class="size-4.5" />
                    </div>
                    <div>
                        <x-ui.card-title class="text-base">Plan Specifications</x-ui.card-title>
                        <x-ui.card-description>Primary configuration and display metadata for this plan.</x-ui.card-description>
                    </div>
                </div>
            </x-ui.card-header>

            <x-ui.card-content class="pt-6">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-5 sm:grid-cols-2">
                    @foreach ($general as $row)
                        <div class="{{ $row['label'] === 'Description' ? 'sm:col-span-2' : '' }} rounded-lg border border-border/60 bg-muted/20 p-3.5">
                            <dt class="text-xs font-medium text-muted-foreground">{{ $row['label'] }}</dt>
                            <dd class="mt-1 text-sm font-medium text-foreground">
                                @if (isset($row['badge']))
                                    @if ($row['active'])
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                            <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium text-muted-foreground border border-border">
                                            Inactive
                                        </span>
                                    @endif
                                @elseif (!empty($row['mono']))
                                    <span class="font-mono text-xs text-foreground/90 bg-muted/80 px-2 py-0.5 rounded border border-border/50 select-all">
                                        {{ $row['value'] }}
                                    </span>
                                @else
                                    {{ $row['value'] !== null && $row['value'] !== '' ? $row['value'] : '—' }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </x-ui.card-content>
        </x-ui.card>

        {{-- Audit Timestamps --}}
        <x-ui.card>
            <x-ui.card-header class="border-b border-border/50 pb-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                        <x-lucide-clock class="size-4.5" />
                    </div>
                    <div>
                        <x-ui.card-title class="text-base">System Audit Trail</x-ui.card-title>
                        <x-ui.card-description>Creation and last mutation timestamps.</x-ui.card-description>
                    </div>
                </div>
            </x-ui.card-header>

            <x-ui.card-content class="grid grid-cols-1 gap-4 sm:grid-cols-2 pt-6">
                <div class="rounded-lg border border-border/60 bg-muted/20 p-3.5">
                    <dt class="text-xs font-medium text-muted-foreground">Created Date & Time</dt>
                    <dd class="mt-1 text-sm font-semibold text-foreground">
                        <x-ui.local-time :value="$record->created_at" format="MMM D, YYYY h:mm A" />
                    </dd>
                </div>
                <div class="rounded-lg border border-border/60 bg-muted/20 p-3.5">
                    <dt class="text-xs font-medium text-muted-foreground">Last Updated Date & Time</dt>
                    <dd class="mt-1 text-sm font-semibold text-foreground">
                        <x-ui.local-time :value="$record->updated_at" format="MMM D, YYYY h:mm A" />
                    </dd>
                </div>
            </x-ui.card-content>
        </x-ui.card>

    </div>

    {{-- Features Column --}}
    <div class="space-y-6 lg:col-span-1">

        <x-ui.card>
            <x-ui.card-header class="border-b border-border/50 pb-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                        <x-lucide-sparkles class="size-4.5" />
                    </div>
                    <div>
                        <x-ui.card-title class="text-base">Entitlements & Features</x-ui.card-title>
                        <x-ui.card-description>Flags and limits granted to users.</x-ui.card-description>
                    </div>
                </div>
            </x-ui.card-header>

            <x-ui.card-content class="pt-6">
                @if (count(config('panel.features', [])))
                    <div class="space-y-3">
                        @foreach (config('panel.features', []) as $key => $feature)
                            @php $value = $record->feature($key); @endphp
                            <div class="flex items-center justify-between rounded-lg border border-border/60 bg-card p-3 shadow-2xs">
                                <span class="text-xs font-medium text-foreground">
                                    {{ $feature['name'] ?? Str::headline($key) }}
                                </span>
                                <div>
                                    @if (($feature['type'] ?? 'string') === 'boolean')
                                        @if ($value)
                                            <x-ui.badge variant="default" class="border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 font-medium text-xs">
                                                Enabled
                                            </x-ui.badge>
                                        @else
                                            <x-ui.badge variant="secondary" class="text-xs">
                                                Disabled
                                            </x-ui.badge>
                                        @endif
                                    @else
                                        <span class="font-mono text-xs font-semibold text-primary bg-primary/10 px-2 py-0.5 rounded">
                                            {{ $value ?? '—' }}
                                        </span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-muted-foreground">No features configured.</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>

    </div>

</div>
