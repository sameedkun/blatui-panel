<x-ui.sidebar collapsible="icon">

    {{-- Brand / Team switcher --}}
    <x-ui.sidebar-header>
        <x-ui.sidebar-menu>
            <x-ui.sidebar-menu-item>
                <x-ui.dropdown-menu>
                    <x-ui.dropdown-menu-trigger>
                        <x-ui.sidebar-menu-button size="lg" class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                            <div class="bg-sidebar-primary text-sidebar-primary-foreground flex aspect-square size-8 items-center justify-center rounded-lg">
                                <x-lucide-command class="size-4" />
                            </div>
                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-semibold">{{ config('app.name') }}</span>
                                <span class="truncate text-xs text-sidebar-foreground/60">Admin</span>
                            </div>
                            <x-lucide-chevrons-up-down class="ml-auto size-4" />
                        </x-ui.sidebar-menu-button>
                    </x-ui.dropdown-menu-trigger>
                    <x-ui.dropdown-menu-content class="w-56" align="start" side="bottom" :sideOffset="4">
                        <x-ui.dropdown-menu-label class="text-xs text-muted-foreground">Workspace</x-ui.dropdown-menu-label>
                        <x-ui.dropdown-menu-item href="{{ route('dashboard') }}">
                            <x-lucide-layout-dashboard class="size-4" />
                            Dashboard
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-separator />
                        <x-ui.dropdown-menu-item href="#">
                            <x-lucide-settings-2 class="size-4" />
                            Settings
                        </x-ui.dropdown-menu-item>
                    </x-ui.dropdown-menu-content>
                </x-ui.dropdown-menu>
            </x-ui.sidebar-menu-item>
        </x-ui.sidebar-menu>
    </x-ui.sidebar-header>

    {{-- Navigation --}}
    <x-ui.sidebar-content>

        {{-- General --}}
        <x-ui.sidebar-group>
            <x-ui.sidebar-group-label>General</x-ui.sidebar-group-label>
            <x-ui.sidebar-menu>

                <x-ui.sidebar-menu-item>
                    <x-ui.sidebar-menu-button href="{{ route('dashboard') }}" :isActive="request()->routeIs('dashboard')">
                        <x-lucide-layout-dashboard />
                        <span>Dashboard</span>
                    </x-ui.sidebar-menu-button>
                </x-ui.sidebar-menu-item>

                <x-ui.sidebar-menu-item>
                    <x-ui.sidebar-menu-button href="{{ route('tasks.index') }}" :isActive="request()->routeIs('tasks.*')">
                        <x-lucide-check-square />
                        <span>Tasks</span>
                    </x-ui.sidebar-menu-button>
                </x-ui.sidebar-menu-item>

                <x-ui.sidebar-menu-item>
                    <x-ui.sidebar-menu-button href="{{ route('apps.index') }}" :isActive="request()->routeIs('apps.*')">
                        <x-lucide-layout-grid />
                        <span>Apps</span>
                    </x-ui.sidebar-menu-button>
                </x-ui.sidebar-menu-item>

                <x-ui.sidebar-menu-item>
                    <x-ui.sidebar-menu-button href="{{ route('chats.index') }}" :isActive="request()->routeIs('chats.*')">
                        <x-lucide-message-square />
                        <span>Chats</span>
                    </x-ui.sidebar-menu-button>
                    <x-ui.sidebar-menu-badge>3</x-ui.sidebar-menu-badge>
                </x-ui.sidebar-menu-item>

                <x-ui.sidebar-menu-item>
                    <x-ui.sidebar-menu-button href="{{ route('users.index') }}" :isActive="request()->routeIs('users.*')">
                        <x-lucide-users />
                        <span>Users</span>
                    </x-ui.sidebar-menu-button>
                </x-ui.sidebar-menu-item>

            </x-ui.sidebar-menu>
        </x-ui.sidebar-group>

        {{-- Pages --}}
        <x-ui.sidebar-group>
            <x-ui.sidebar-separator />
            <x-ui.sidebar-group-label>Pages</x-ui.sidebar-group-label>
            <x-ui.sidebar-menu>

                {{-- Auth (collapsible) --}}
                <x-ui.sidebar-menu-item x-data="{ open: {{ request()->routeIs('auth.*') ? 'true' : 'false' }} }">
                    <x-ui.sidebar-menu-button @click="open = !open" :isActive="request()->routeIs('auth.*')">
                        <x-lucide-lock />
                        <span>Auth</span>
                        <x-lucide-chevron-right class="ml-auto transition-transform duration-200" ::class="open ? 'rotate-90' : ''" />
                    </x-ui.sidebar-menu-button>
                    <x-ui.sidebar-menu-sub x-show="open" x-collapse>
                        <x-ui.sidebar-menu-sub-item>
                            <x-ui.sidebar-menu-sub-button href="{{ route('login') }}" :isActive="request()->routeIs('login')">Sign In</x-ui.sidebar-menu-sub-button>
                        </x-ui.sidebar-menu-sub-item>
                        <x-ui.sidebar-menu-sub-item>
                            <x-ui.sidebar-menu-sub-button href="{{ route('register') }}" :isActive="request()->routeIs('register')">Sign Up</x-ui.sidebar-menu-sub-button>
                        </x-ui.sidebar-menu-sub-item>
                        <x-ui.sidebar-menu-sub-item>
                            <x-ui.sidebar-menu-sub-button href="{{ route('password.request') }}" :isActive="request()->routeIs('password.*')">Forgot Password</x-ui.sidebar-menu-sub-button>
                        </x-ui.sidebar-menu-sub-item>
                        <x-ui.sidebar-menu-sub-item>
                            <x-ui.sidebar-menu-sub-button href="{{ route('verification.notice') }}" :isActive="request()->routeIs('verification.*')">OTP</x-ui.sidebar-menu-sub-button>
                        </x-ui.sidebar-menu-sub-item>
                    </x-ui.sidebar-menu-sub>
                </x-ui.sidebar-menu-item>

                {{-- Errors (collapsible) --}}
                <x-ui.sidebar-menu-item x-data="{ open: {{ request()->routeIs('errors.*') ? 'true' : 'false' }} }">
                    <x-ui.sidebar-menu-button @click="open = !open" :isActive="request()->routeIs('errors.*')">
                        <x-lucide-triangle-alert />
                        <span>Errors</span>
                        <x-lucide-chevron-right class="ml-auto transition-transform duration-200" ::class="open ? 'rotate-90' : ''" />
                    </x-ui.sidebar-menu-button>
                    <x-ui.sidebar-menu-sub x-show="open" x-collapse>
                        <x-ui.sidebar-menu-sub-item>
                            <x-ui.sidebar-menu-sub-button href="{{ route('errors.404') }}" :isActive="request()->routeIs('errors.404')">Not Found</x-ui.sidebar-menu-sub-button>
                        </x-ui.sidebar-menu-sub-item>
                        <x-ui.sidebar-menu-sub-item>
                            <x-ui.sidebar-menu-sub-button href="{{ route('errors.500') }}" :isActive="request()->routeIs('errors.500')">Server Error</x-ui.sidebar-menu-sub-button>
                        </x-ui.sidebar-menu-sub-item>
                        <x-ui.sidebar-menu-sub-item>
                            <x-ui.sidebar-menu-sub-button href="{{ route('errors.503') }}" :isActive="request()->routeIs('errors.503')">Maintenance</x-ui.sidebar-menu-sub-button>
                        </x-ui.sidebar-menu-sub-item>
                        <x-ui.sidebar-menu-sub-item>
                            <x-ui.sidebar-menu-sub-button href="{{ route('errors.401') }}" :isActive="request()->routeIs('errors.401')">Unauthorised</x-ui.sidebar-menu-sub-button>
                        </x-ui.sidebar-menu-sub-item>
                    </x-ui.sidebar-menu-sub>
                </x-ui.sidebar-menu-item>

            </x-ui.sidebar-menu>
        </x-ui.sidebar-group>

        {{-- Other --}}
        <x-ui.sidebar-group>
            <x-ui.sidebar-separator />
            <x-ui.sidebar-group-label>Other</x-ui.sidebar-group-label>
            <x-ui.sidebar-menu>

                {{-- Settings (collapsible) --}}
                <x-ui.sidebar-menu-item x-data="{ open: {{ request()->routeIs('settings.*') ? 'true' : 'false' }} }">
                    <x-ui.sidebar-menu-button @click="open = !open" :isActive="request()->routeIs('settings.*')">
                        <x-lucide-settings />
                        <span>Settings</span>
                        <x-lucide-chevron-right class="ml-auto transition-transform duration-200" ::class="open ? 'rotate-90' : ''" />
                    </x-ui.sidebar-menu-button>
                    <x-ui.sidebar-menu-sub x-show="open" x-collapse>
                        <x-ui.sidebar-menu-sub-item>
                            <x-ui.sidebar-menu-sub-button href="{{ route('settings.profile') }}" :isActive="request()->routeIs('settings.profile')">Profile</x-ui.sidebar-menu-sub-button>
                        </x-ui.sidebar-menu-sub-item>
                        <x-ui.sidebar-menu-sub-item>
                            <x-ui.sidebar-menu-sub-button href="{{ route('settings.account') }}" :isActive="request()->routeIs('settings.account')">Account</x-ui.sidebar-menu-sub-button>
                        </x-ui.sidebar-menu-sub-item>
                        <x-ui.sidebar-menu-sub-item>
                            <x-ui.sidebar-menu-sub-button href="{{ route('settings.appearance') }}" :isActive="request()->routeIs('settings.appearance')">Appearance</x-ui.sidebar-menu-sub-button>
                        </x-ui.sidebar-menu-sub-item>
                        <x-ui.sidebar-menu-sub-item>
                            <x-ui.sidebar-menu-sub-button href="{{ route('settings.notifications') }}" :isActive="request()->routeIs('settings.notifications')">Notifications</x-ui.sidebar-menu-sub-button>
                        </x-ui.sidebar-menu-sub-item>
                    </x-ui.sidebar-menu-sub>
                </x-ui.sidebar-menu-item>

                <x-ui.sidebar-menu-item>
                    <x-ui.sidebar-menu-button href="{{ route('help') }}" :isActive="request()->routeIs('help')">
                        <x-lucide-circle-help />
                        <span>Help Center</span>
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
                        <x-ui.sidebar-menu-button size="lg" class="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground">
                            <x-ui.avatar class="size-8 rounded-lg">
                                <x-ui.avatar-image src="{{ auth()->user()->avatar ?? '' }}" alt="{{ auth()->user()->name ?? 'User' }}" />
                                <x-ui.avatar-fallback class="rounded-lg">
                                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                                </x-ui.avatar-fallback>
                            </x-ui.avatar>
                            <div class="grid flex-1 text-left text-sm leading-tight">
                                <span class="truncate font-semibold">{{ auth()->user()->name ?? 'Guest' }}</span>
                                <span class="truncate text-xs text-sidebar-foreground/60">{{ auth()->user()->email ?? '' }}</span>
                            </div>
                            <x-lucide-ellipsis-vertical class="ml-auto size-4" />
                        </x-ui.sidebar-menu-button>
                    </x-ui.dropdown-menu-trigger>
                    <x-ui.dropdown-menu-content class="w-56" align="end" side="top" :sideOffset="4">
                        <x-ui.dropdown-menu-label class="p-0 font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <x-ui.avatar class="size-8 rounded-lg">
                                    <x-ui.avatar-image src="{{ auth()->user()->avatar ?? '' }}" alt="{{ auth()->user()->name ?? 'User' }}" />
                                    <x-ui.avatar-fallback class="rounded-lg">
                                        {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 2)) }}
                                    </x-ui.avatar-fallback>
                                </x-ui.avatar>
                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name ?? 'Guest' }}</span>
                                    <span class="truncate text-xs text-muted-foreground">{{ auth()->user()->email ?? '' }}</span>
                                </div>
                            </div>
                        </x-ui.dropdown-menu-label>
                        <x-ui.dropdown-menu-separator />
                        <x-ui.dropdown-menu-item href="{{ route('settings.profile') }}">
                            <x-lucide-user class="size-4" />
                            Profile
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item href="{{ route('settings.account') }}">
                            <x-lucide-settings class="size-4" />
                            Settings
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-separator />
                        <x-ui.dropdown-menu-item
                            href="{{ route('logout') }}"
                            onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                            class="text-destructive focus:text-destructive"
                        >
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
