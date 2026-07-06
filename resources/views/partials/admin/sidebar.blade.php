<x-ui.sidebar collapsible="icon">

    {{-- Brand / Team switcher --}}
    <x-ui.sidebar-header>
        <div class="flex items-center gap-2 py-1" x-bind:class="open ? 'px-2' : ''">
            <div
                class="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-lg">
                <x-lucide-command class="size-4" />
            </div>
            <div class="grid flex-1 text-left text-sm leading-tight" x-bind:class="open ? '' : 'hidden'">
                <span class="truncate font-semibold">{{ config('app.name') }}</span>
                <span class="truncate text-xs text-sidebar-foreground/60">Admin</span>
            </div>
        </div>
    </x-ui.sidebar-header>

    {{-- Navigation --}}
    <x-ui.sidebar-content>

        {{-- General --}}
        <x-ui.sidebar-group>
            <x-ui.sidebar-menu-item>
                <x-ui.sidebar-menu-button href="{{ route('admin.dashboard') }}" :isActive="request()->routeIs('admin.dashboard')">
                    <x-lucide-layout-dashboard />
                    <span>Dashboard</span>
                </x-ui.sidebar-menu-button>
            </x-ui.sidebar-menu-item>

            <x-ui.sidebar-group-label>Management</x-ui.sidebar-group-label>

            <x-ui.sidebar-menu>

                <x-ui.sidebar-menu-item>
                    <x-ui.sidebar-menu-button href="{{ route('admin.users.index') }}" :isActive="request()->routeIs('admin.users.*')">
                        <x-lucide-users />
                        <span>Users</span>
                    </x-ui.sidebar-menu-button>
                </x-ui.sidebar-menu-item>

                <x-ui.sidebar-menu-item>
                    <x-ui.sidebar-menu-button href="{{ route('admin.guests.index') }}" :isActive="request()->routeIs('admin.guests.*')">
                        <x-lucide-user />
                        <span>Guests</span>
                    </x-ui.sidebar-menu-button>
                </x-ui.sidebar-menu-item>

            </x-ui.sidebar-menu>
        </x-ui.sidebar-group>

    </x-ui.sidebar-content>

    {{-- User footer --}}
    <x-ui.sidebar-footer>
        <x-ui.sidebar-menu>
            <x-ui.sidebar-menu-item>
                <x-ui.dropdown-menu>
                    <x-ui.dropdown-menu-trigger>
                        <x-ui.sidebar-menu-button size="lg"
                            class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                            <x-ui.avatar class="size-8 rounded-lg">
                                @if(auth()->user()->avatar)
                                    <x-ui.avatar-image src="{{ auth()->user()->avatar ?? '' }}"
                                        alt="{{ auth()->user()->name ?? 'User' }}" />
                                @endif
                                <x-ui.avatar-fallback class="rounded-lg">
                                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                                </x-ui.avatar-fallback>
                            </x-ui.avatar>
                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-semibold">{{ auth()->user()->name ?? 'Guest' }}</span>
                                <span
                                    class="truncate text-xs text-sidebar-foreground/60">{{ auth()->user()->email ?? '' }}</span>
                            </div>
                            <x-lucide-ellipsis-vertical class="ml-auto size-4" />
                        </x-ui.sidebar-menu-button>
                    </x-ui.dropdown-menu-trigger>
                    <x-ui.dropdown-menu-content class="w-56" align="end" side="top" :sideOffset="4">
                        <x-ui.dropdown-menu-label class="p-0 font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <x-ui.avatar class="size-8 rounded-lg">
                                    @if(auth()->user()->avatar)
                                        <x-ui.avatar-image src="{{ auth()->user()->avatar ?? '' }}"
                                            alt="{{ auth()->user()->name ?? 'User' }}" />
                                    @endif
                                    <x-ui.avatar-fallback class="rounded-lg">
                                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                                    </x-ui.avatar-fallback>
                                </x-ui.avatar>
                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name ?? 'Guest' }}</span>
                                    <span
                                        class="truncate text-xs text-muted-foreground">{{ auth()->user()->email ?? '' }}</span>
                                </div>
                            </div>
                        </x-ui.dropdown-menu-label>
                        <x-ui.dropdown-menu-separator />
                        <x-ui.dropdown-menu-item
                            href="{{ Route::has('settings.profile') ? route('settings.profile') : '#' }}">
                            <x-lucide-user class="size-4" />
                            Profile
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item
                            href="{{ Route::has('settings.account') ? route('settings.account') : '#' }}">
                            <x-lucide-settings class="size-4" />
                            Settings
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-separator />
                        <x-ui.dropdown-menu-item
                            href="{{ route('logout') }}"
                            class="text-destructive focus:text-destructive">
                            <x-lucide-log-out class="size-4" />
                            Log out
                        </x-ui.dropdown-menu-item>
                    </x-ui.dropdown-menu-content>
                </x-ui.dropdown-menu>
            </x-ui.sidebar-menu-item>
        </x-ui.sidebar-menu>
    </x-ui.sidebar-footer>

    <x-ui.sidebar-rail />

</x-ui.sidebar>
