{{--
    Deliberately empty.

    This tab is reserved for infrastructure this panel will manage later — VPN node fleets,
    inference workers, regional capacity, egress usage. It exists now so the dashboard's
    navigation does not change shape the day those land: only this view gets replaced.

    Nothing about the running Laravel app belongs here. Queue depth, failed jobs and the
    scheduler heartbeat live on the Overview tab, because they describe this app rather
    than the estate it will eventually manage.
--}}
<div>
    <x-ui.empty class="py-20">
        <x-ui.empty-header>
            <x-ui.empty-media variant="icon">
                <x-lucide-server-cog class="size-6" />
            </x-ui.empty-media>
            <x-ui.empty-title>{{ __('dashboard.infrastructure.empty_title') }}</x-ui.empty-title>
            <x-ui.empty-description>
                {{ __('dashboard.infrastructure.empty_description') }}
            </x-ui.empty-description>
        </x-ui.empty-header>
    </x-ui.empty>
</div>
