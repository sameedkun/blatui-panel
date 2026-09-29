@php
    $user = auth()->user();
    $canCreate = $user->can('dashboard.reports.create');
    $canManage = $user->can('dashboard.reports.manage');
    $tabs = [
        'generated' => ['label' => __('dashboard.reports.tabs.generated'), 'icon' => 'files'],
        'scheduled' => ['label' => __('dashboard.reports.tabs.scheduled'), 'icon' => 'calendar-clock'],
        'library' => ['label' => __('dashboard.reports.tabs.library'), 'icon' => 'library'],
    ];
@endphp

<div class="flex flex-col gap-6">

    <x-admin.dashboard.header :description="__('dashboard.reports.subtitle')"
        :breadcrumbs="[['label' => __('navigation.home'), 'url' => route('admin.dashboard')], ['label' => __('dashboard.reports.title')]]">
        {{ __('dashboard.reports.title') }}

        @if ($canCreate || $canManage)
            <x-slot:actions>
                @if ($canManage && count($definitions))
                    <x-ui.button variant="outline" size="sm" wire:click="openSchedule">
                        <x-lucide-calendar-plus class="size-4" />
                        {{ __('dashboard.reports.actions.schedule') }}
                    </x-ui.button>
                @endif
                @if ($canCreate && count($definitions))
                    <x-ui.button size="sm" wire:click="openGenerate">
                        <x-lucide-plus class="size-4" />
                        {{ __('dashboard.reports.actions.generate') }}
                    </x-ui.button>
                @endif
            </x-slot:actions>
        @endif
    </x-admin.dashboard.header>

    {{-- Stats --}}
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-admin.stat-card :label="__('dashboard.reports.stats.total')" :value="$stats['total']" icon="files" />
        <x-admin.stat-card :label="__('dashboard.reports.stats.in_flight')" :value="$stats['in_flight']" icon="loader" />
        <x-admin.stat-card :label="__('dashboard.reports.stats.schedules')" :value="$stats['schedules']" icon="calendar-clock" />
        <x-admin.stat-card :label="__('dashboard.reports.stats.storage')" :value="\Illuminate\Support\Number::fileSize($stats['storage'], precision: 1)" icon="hard-drive" />
    </div>

    <x-admin.show-tabs :tabs="$tabs" :active="$tab">
        @include('livewire.admin.dashboard.reports.'.$tab)
    </x-admin.show-tabs>

    @if ($canCreate)
        @include('livewire.admin.dashboard.reports.generate-dialog')
    @endif

    @if ($canManage)
        @include('livewire.admin.dashboard.reports.schedule-dialog')

        <x-admin.confirm-dialog id="delete-schedule" :title="__('dashboard.reports.dialogs.delete_schedule_title')"
            confirm="$wire.deleteSchedule()" cancel="$wire.set('deletingScheduleId', null)"
            :confirm-label="__('common.delete')" variant="destructive">
            {{ __('dashboard.reports.dialogs.delete_schedule_body') }}
        </x-admin.confirm-dialog>
    @endif

    @can('dashboard.reports.delete')
        <x-admin.confirm-dialog id="delete-report" :title="__('dashboard.reports.dialogs.delete_report_title')"
            confirm="$wire.delete()" cancel="$wire.set('deletingReportId', null)"
            :confirm-label="__('common.delete')" variant="destructive">
            {{ __('dashboard.reports.dialogs.delete_report_body') }}
        </x-admin.confirm-dialog>
    @endcan
</div>
