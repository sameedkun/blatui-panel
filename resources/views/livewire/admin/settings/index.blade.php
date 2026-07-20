@component('layouts.admin.app', ['title' => 'Settings'])
    <div class="mx-auto flex w-full max-w-[1280px] flex-col gap-8">

        <x-admin.page-header title="Settings" description="Manage your application settings, mail, and policies."
            :breadcrumbs="[['label' => 'Home', 'url' => route('admin.dashboard')], ['label' => 'Settings']]" />

        <div class="flex flex-col gap-6 lg:flex-row lg:gap-10">
            
            {{-- Navigation sidebar --}}
            <aside class="lg:w-1/5 shrink-0">
                <nav class="flex flex-row overflow-x-auto pb-2 lg:pb-0 lg:flex-col lg:items-stretch gap-1 shrink-0 bg-transparent text-muted-foreground w-full justify-start h-auto p-0 border-b lg:border-b-0 border-border mb-4 lg:mb-0">
                    
                    {{-- General --}}
                    @can('settings.view')
                        <a href="{{ route('admin.settings.general') }}" wire:navigate
                            class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors justify-start cursor-pointer whitespace-nowrap {{ request()->routeIs('admin.settings.general') ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground' }}">
                            <x-lucide-settings class="size-4" />
                            General
                        </a>
                    @endcan

                    {{-- Mail --}}
                    @can('settings.mail.view')
                        <a href="{{ route('admin.settings.mail') }}" wire:navigate
                            class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors justify-start cursor-pointer whitespace-nowrap {{ request()->routeIs('admin.settings.mail') ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground' }}">
                            <x-lucide-mail class="size-4" />
                            Mail
                        </a>
                    @endcan

                    {{-- Policies --}}
                    @can('settings.policies.view')
                        <a href="{{ route('admin.settings.policies') }}" wire:navigate
                            class="inline-flex items-center gap-2 rounded-md px-3 py-2 text-sm font-medium transition-colors justify-start cursor-pointer whitespace-nowrap {{ request()->routeIs('admin.settings.policies') ? 'bg-muted text-foreground' : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground' }}">
                            <x-lucide-scroll-text class="size-4" />
                            Policies
                        </a>
                    @endcan

                </nav>
            </aside>

            {{-- Content area --}}
            <div class="flex-1 lg:max-w-4xl space-y-6">
                {{ $slot }}
            </div>
            
        </div>

    </div>
@endcomponent
