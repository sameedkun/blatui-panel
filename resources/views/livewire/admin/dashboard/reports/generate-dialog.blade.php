{{--
    Generate-report dialog. Raw <select>s rather than x-ui.select: that component
    teleports its panel and dies under Livewire's morph (.ai/rules/views.md).
    Filter options come from the chosen definition, so switching report resets them.
--}}
<x-ui.dialog id="generate-report">
    <x-ui.dialog-content class="sm:max-w-lg">
        <x-ui.dialog-header>
            <x-ui.dialog-title>{{ __('dashboard.reports.dialogs.generate_title') }}</x-ui.dialog-title>
            <x-ui.dialog-description>{{ __('dashboard.reports.dialogs.generate_description') }}</x-ui.dialog-description>
        </x-ui.dialog-header>

        <div class="flex flex-col gap-5">
            <x-ui.field>
                <x-ui.field-label required>{{ __('dashboard.reports.fields.report') }}</x-ui.field-label>
                <select wire:model.live="reportKey" class="blat-select h-9 w-full">
                    @foreach ($definitions as $key => $definition)
                        <option value="{{ $key }}" @selected($reportKey === $key)>{{ $definition->label() }}</option>
                    @endforeach
                </select>
                @if ($generateDefinition)
                    <x-ui.field-description>{{ $generateDefinition->description() }}</x-ui.field-description>
                @endif
                @error('reportKey')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label required>{{ __('dashboard.reports.fields.date_range') }}</x-ui.field-label>
                <div class="flex flex-wrap gap-1.5">
                    @foreach (\App\Livewire\Admin\Dashboard\Reports::PRESETS as $preset)
                        <button type="button" wire:click="applyPreset('{{ $preset }}')"
                            class="rounded-full border border-border px-2.5 py-0.5 text-xs text-muted-foreground transition-colors hover:border-foreground/30 hover:text-foreground">
                            {{ __('dashboard.reports.presets.'.$preset) }}
                        </button>
                    @endforeach
                </div>
                <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                    <x-ui.input type="date" wire:model="from" max="{{ now()->toDateString() }}" aria-label="{{ __('dashboard.reports.fields.from') }}" />
                    <x-lucide-arrow-right class="size-4 text-muted-foreground" />
                    <x-ui.input type="date" wire:model="to" max="{{ now()->toDateString() }}" aria-label="{{ __('dashboard.reports.fields.to') }}" />
                </div>
                @error('from')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
                @error('to')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>

            @if ($generateDefinition && count($generateDefinition->filters()))
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($generateDefinition->filters() as $filter)
                        <x-ui.field wire:key="generate-filter-{{ $reportKey }}-{{ $filter->key }}">
                            <x-ui.field-label>{{ $filter->label }}</x-ui.field-label>
                            <select wire:model="filters.{{ $filter->key }}" class="blat-select h-9 w-full">
                                <option value="">{{ __('dashboard.reports.filters.all') }}</option>
                                @foreach ($filter->options as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    @endforeach
                </div>
            @endif

            <x-ui.field>
                <x-ui.field-label required>{{ __('dashboard.reports.fields.format') }}</x-ui.field-label>
                @include('livewire.admin.dashboard.reports.format-picker', ['model' => 'format', 'selected' => $format])
                @error('format')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>
        </div>

        <x-ui.dialog-footer>
            <x-ui.button variant="outline" @click="open = false">{{ __('common.cancel') }}</x-ui.button>
            <x-ui.button wire:click="generate" wire:loading.attr="disabled" wire:target="generate">
                <x-lucide-loader-circle class="size-4 animate-spin" wire:loading wire:target="generate" />
                {{ __('dashboard.reports.actions.generate_submit') }}
            </x-ui.button>
        </x-ui.dialog-footer>
    </x-ui.dialog-content>
</x-ui.dialog>
