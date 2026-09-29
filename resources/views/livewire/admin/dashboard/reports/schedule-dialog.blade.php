{{-- Create / edit a recurring report schedule. --}}
@php
    $weekdays = collect(range(0, 6))->mapWithKeys(fn (int $day): array => [$day => now()->startOfWeek(\Carbon\CarbonInterface::SUNDAY)->addDays($day)->translatedFormat('l')]);
@endphp

<x-ui.dialog id="schedule-report">
    <x-ui.dialog-content class="sm:max-w-lg">
        <x-ui.dialog-header>
            <x-ui.dialog-title>{{ $editingScheduleId ? __('dashboard.reports.dialogs.edit_schedule_title') : __('dashboard.reports.dialogs.schedule_title') }}</x-ui.dialog-title>
            <x-ui.dialog-description>{{ __('dashboard.reports.dialogs.schedule_description') }}</x-ui.dialog-description>
        </x-ui.dialog-header>

        <div class="flex max-h-[65vh] flex-col gap-5 overflow-y-auto px-0.5">
            <x-ui.field>
                <x-ui.field-label required>{{ __('dashboard.reports.fields.name') }}</x-ui.field-label>
                <x-ui.input wire:model="scheduleName" :placeholder="__('dashboard.reports.placeholders.schedule_name')" maxlength="120" />
                @error('scheduleName')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label required>{{ __('dashboard.reports.fields.report') }}</x-ui.field-label>
                <select wire:model.live="scheduleReport" class="blat-select h-9 w-full">
                    @foreach ($definitions as $key => $definition)
                        <option value="{{ $key }}" @selected($scheduleReport === $key)>{{ $definition->label() }}</option>
                    @endforeach
                </select>
                @error('scheduleReport')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>

            @if ($scheduleDefinition && count($scheduleDefinition->filters()))
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($scheduleDefinition->filters() as $filter)
                        <x-ui.field wire:key="schedule-filter-{{ $scheduleReport }}-{{ $filter->key }}">
                            <x-ui.field-label>{{ $filter->label }}</x-ui.field-label>
                            <select wire:model="scheduleFilters.{{ $filter->key }}" class="blat-select h-9 w-full">
                                <option value="">{{ __('dashboard.reports.filters.all') }}</option>
                                @foreach ($filter->options as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </x-ui.field>
                    @endforeach
                </div>
            @endif

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <x-ui.field>
                    <x-ui.field-label required>{{ __('dashboard.reports.fields.frequency') }}</x-ui.field-label>
                    <select wire:model.live="scheduleFrequency" class="blat-select h-9 w-full">
                        @foreach ($frequencies as $frequency)
                            <option value="{{ $frequency->value }}">{{ $frequency->label() }}</option>
                        @endforeach
                    </select>
                </x-ui.field>

                @if ($scheduleFrequency === \App\Enum\ReportFrequency::Weekly->value)
                    <x-ui.field>
                        <x-ui.field-label>{{ __('dashboard.reports.fields.day_of_week') }}</x-ui.field-label>
                        <select wire:model="scheduleDayOfWeek" class="blat-select h-9 w-full">
                            @foreach ($weekdays as $day => $label)
                                <option value="{{ $day }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                @elseif ($scheduleFrequency === \App\Enum\ReportFrequency::Monthly->value)
                    <x-ui.field>
                        <x-ui.field-label>{{ __('dashboard.reports.fields.day_of_month') }}</x-ui.field-label>
                        <select wire:model="scheduleDayOfMonth" class="blat-select h-9 w-full">
                            @foreach (range(1, 28) as $day)
                                <option value="{{ $day }}">{{ $day }}</option>
                            @endforeach
                        </select>
                    </x-ui.field>
                @endif

                <x-ui.field>
                    <x-ui.field-label>{{ __('dashboard.reports.fields.hour') }}</x-ui.field-label>
                    <select wire:model="scheduleHour" class="blat-select h-9 w-full">
                        @foreach (range(0, 23) as $hour)
                            <option value="{{ $hour }}">{{ sprintf('%02d:00', $hour) }}</option>
                        @endforeach
                    </select>
                </x-ui.field>
            </div>
            <p class="-mt-3 text-xs text-muted-foreground">
                {{ __('dashboard.reports.frequency_hints.'.$scheduleFrequency) }}
                {{ __('dashboard.reports.timezone_hint', ['timezone' => config('app.timezone')]) }}
            </p>

            <x-ui.field>
                <x-ui.field-label required>{{ __('dashboard.reports.fields.format') }}</x-ui.field-label>
                @include('livewire.admin.dashboard.reports.format-picker', ['model' => 'scheduleFormat', 'selected' => $scheduleFormat])
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label required>{{ __('dashboard.reports.fields.recipients') }}</x-ui.field-label>
                <x-ui.textarea wire:model="scheduleRecipients" rows="2" :placeholder="__('dashboard.reports.placeholders.recipients')" />
                <x-ui.field-description>{{ __('dashboard.reports.recipients_hint', ['max' => config('dashboard.reports.max_recipients')]) }}</x-ui.field-description>
                @error('scheduleRecipients')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>
        </div>

        <x-ui.dialog-footer>
            <x-ui.button variant="outline" @click="open = false">{{ __('common.cancel') }}</x-ui.button>
            <x-ui.button wire:click="saveSchedule" wire:loading.attr="disabled" wire:target="saveSchedule">
                <x-lucide-loader-circle class="size-4 animate-spin" wire:loading wire:target="saveSchedule" />
                {{ $editingScheduleId ? __('common.save') : __('dashboard.reports.actions.create_schedule') }}
            </x-ui.button>
        </x-ui.dialog-footer>
    </x-ui.dialog-content>
</x-ui.dialog>
