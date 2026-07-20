<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-bind:class="$store.theme.isDark ? 'dark' : ''">

<head>
    @include('partials.admin.head', [
        'title' => $title ?? '',
        'description' => $description ?? null,
    ])
</head>

<body class="min-h-svh bg-background font-sans antialiased">

    <x-ui.sidebar-provider :defaultOpen="true">

        @include('partials.admin.sidebar')

        <x-ui.sidebar-inset>

            <header class="flex h-16 shrink-0 items-center gap-2 border-b border-border px-4">
                <x-ui.sidebar-trigger class="-ml-1" />
                <x-ui.separator orientation="vertical" class="mr-2 h-4" />

                @hasSection('breadcrumb')
                    @yield('breadcrumb')
                @else
                    <x-ui.breadcrumb>
                        <x-ui.breadcrumb-list>
                            <x-ui.breadcrumb-item>
                                <x-ui.breadcrumb-page>@yield('title', config('app.name'))</x-ui.breadcrumb-page>
                            </x-ui.breadcrumb-item>
                        </x-ui.breadcrumb-list>
                    </x-ui.breadcrumb>
                @endif

                <div class="ml-auto flex items-center gap-1">
                    {{-- Dark mode toggle --}}
                    <x-ui.button variant="ghost" size="icon" @click="$store.theme.toggle()"
                        aria-label="Toggle dark mode">
                        <x-lucide-sun class="size-4 block dark:hidden" aria-hidden="true" />
                        <x-lucide-moon class="size-4 hidden dark:block" aria-hidden="true" />
                    </x-ui.button>

                    {{-- Settings --}}
                    @can('settings.view')
                        <a href="{{ route('admin.settings.general') }}" aria-label="Settings" wire:navigate>
                            <x-ui.button variant="ghost" size="icon" aria-label="Settings">
                                <x-lucide-settings class="size-4" aria-hidden="true" />
                            </x-ui.button>
                        </a>
                    @endcan

                    {{-- User avatar (desktop shortcut to the account page) --}}
                    <a href="{{ route('admin.account') }}" aria-label="My account" wire:navigate>
                        <x-ui.avatar class="size-8 cursor-pointer rounded-full">
                            @if (auth()->user()->avatarUrl())
                                <x-ui.avatar-image src="{{ auth()->user()->avatarUrl() }}" alt="{{ auth()->user()->name }}" />
                            @else
                                <x-ui.avatar-fallback class="text-xs">
                                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                                </x-ui.avatar-fallback>
                            @endif
                        </x-ui.avatar>
                    </a>
                </div>
            </header>

            <div class="flex flex-1 flex-col gap-4 p-4 pt-4">
                {{ $slot }}
            </div>

        </x-ui.sidebar-inset>

    </x-ui.sidebar-provider>

    <x-ui.sonner position="bottom-right" />

    @include('partials.admin.scripts')

    {{-- Flash toast (fires after Livewire redirect) --}}
    @if (session('toast'))
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: @json(session('toast'))
                }));
            });
        </script>
    @endif

</body>

</html>
