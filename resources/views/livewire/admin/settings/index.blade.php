@component('layouts.admin.app', ['title' => $title ?? __('settings.title')])
    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-8">
        <x-admin.page-header :title="__('settings.title')" :description="__('settings.subtitle')"
            :breadcrumbs="[['label' => __('settings.breadcrumbs.home'), 'url' => route('admin.dashboard')], ['label' => __('settings.breadcrumbs.settings')]]" />

        <div class="flex flex-col gap-6 lg:flex-row lg:gap-10">
            <aside class="shrink-0 lg:w-1/5">
                <nav class="mb-4 flex h-auto w-full shrink-0 flex-row justify-start gap-1 overflow-x-auto border-b border-border bg-transparent p-0 pb-2 text-muted-foreground lg:mb-0 lg:flex-col lg:items-stretch lg:border-b-0 lg:pb-0">
                    @can('settings.general.view')
                        <a href="{{ route('admin.settings.general') }}" wire:navigate
                            class="inline-flex cursor-pointer items-center justify-start gap-2 whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs('admin.settings.general') ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground' }}">
                            <x-lucide-settings class="size-4" />
                            {{ __('settings.tabs.general') }}
                        </a>
                    @endcan

                    @can('settings.mail.view')
                        <a href="{{ route('admin.settings.mail') }}" wire:navigate
                            class="inline-flex cursor-pointer items-center justify-start gap-2 whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs('admin.settings.mail') ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground' }}">
                            <x-lucide-mail class="size-4" />
                            {{ __('settings.tabs.mail') }}
                        </a>
                    @endcan

                    @can('settings.policies.view')
                        <a href="{{ route('admin.settings.policies') }}" wire:navigate
                            class="inline-flex cursor-pointer items-center justify-start gap-2 whitespace-nowrap rounded-md px-3 py-2 text-sm font-medium transition-colors {{ request()->routeIs('admin.settings.policies') ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground' }}">
                            <x-lucide-scroll-text class="size-4" />
                            {{ __('settings.tabs.policies') }}
                        </a>
                    @endcan
                </nav>
            </aside>

            <div class="flex-1 space-y-6 lg:max-w-4xl">
                {{ $slot }}
            </div>
        </div>
    </div>
@endcomponent
