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
            @can('dashboard.view')
                <x-ui.sidebar-menu-item>
                    <x-ui.sidebar-menu-button href="{{ route('admin.dashboard') }}" :isActive="request()->routeIs('admin.dashboard')">
                        <x-lucide-layout-dashboard />
                        <span>Dashboard</span>
                    </x-ui.sidebar-menu-button>
                </x-ui.sidebar-menu-item>
            @endcan

            @canany(['users.view', 'guests.view', 'plans.view', 'subscriptions.view'])
                <x-ui.sidebar-group-label>Management</x-ui.sidebar-group-label>

                <x-ui.sidebar-menu>

                    @can('users.view')
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button href="{{ route('admin.users.index') }}" :isActive="request()->routeIs('admin.users.*')">
                                <x-lucide-users />
                                <span>Users</span>
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    @endcan

                    @can('guests.view')
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button href="{{ route('admin.guests.index') }}" :isActive="request()->routeIs('admin.guests.*')">
                                <x-lucide-user />
                                <span>Guests</span>
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    @endcan

                    @can('plans.view')
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button href="{{ route('admin.plans.index') }}" :isActive="request()->routeIs('admin.plans.*')">
                                <x-lucide-credit-card />
                                <span>Plans</span>
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    @endcan

                    @can('subscriptions.view')
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button href="{{ route('admin.subscriptions.index') }}" :isActive="request()->routeIs('admin.subscriptions.*')">
                                <x-lucide-receipt />
                                <span>Subscriptions</span>
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    @endcan

                </x-ui.sidebar-menu>
            @endcanany

            @canany(['languages.view', 'feedback.view', 'notifications.view'])
                <x-ui.sidebar-group-label>Application</x-ui.sidebar-group-label>

                <x-ui.sidebar-menu>
                    @can('languages.view')
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button href="{{ route('admin.languages.index') }}" :isActive="request()->routeIs('admin.languages.*')">
                                <x-lucide-globe />
                                <span>Languages</span>
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    @endcan

                    @can('feedback.view')
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button href="{{ route('admin.feedback.index') }}" :isActive="request()->routeIs('admin.feedback.*')">
                                <x-lucide-message-square-quote />
                                <span>Feedback</span>
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    @endcan

                    @can('notifications.view')
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button href="{{ route('admin.notifications.index') }}" :isActive="request()->routeIs('admin.notifications.*')">
                                <x-lucide-bell />
                                <span>Notifications</span>
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    @endcan
                </x-ui.sidebar-menu>
            @endcanany

            @canany(['tickets.view', 'ticket_categories.view'])
                <x-ui.sidebar-group-label>Support</x-ui.sidebar-group-label>

                <x-ui.sidebar-menu>
                    @can('tickets.view')
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button href="{{ route('admin.tickets.index') }}" :isActive="request()->routeIs('admin.tickets.*')">
                                <x-lucide-life-buoy />
                                <span>Tickets</span>
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    @endcan

                    @can('ticket_categories.view')
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button href="{{ route('admin.ticket-categories.index') }}" :isActive="request()->routeIs('admin.ticket-categories.*')">
                                <x-lucide-tags />
                                <span>Categories</span>
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    @endcan
                </x-ui.sidebar-menu>
            @endcanany

            @if (auth()->user()->canAny(['staff.view', 'roles.view', 'activity_logs.view']) || auth()->user()->canAccessModule('settings'))
                <x-ui.sidebar-group-label>Administration</x-ui.sidebar-group-label>

                <x-ui.sidebar-menu>

                    @can('staff.view')
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button href="{{ route('admin.staff.index') }}" :isActive="request()->routeIs('admin.staff.*')">
                                <x-lucide-shield-user />
                                <span>Staff</span>
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    @endcan

                    @can('roles.view')
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button href="{{ route('admin.roles.index') }}" :isActive="request()->routeIs('admin.roles.*')">
                                <x-lucide-key />
                                <span>Roles</span>
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    @endcan

                    @can('activity_logs.view')
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button href="{{ route('admin.activity-logs.index') }}" :isActive="request()->routeIs('admin.activity-logs.*')">
                                <x-lucide-clipboard-list />
                                <span>Activity Logs</span>
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    @endcan

                    @if (auth()->user()->canAccessModule('settings'))
                        <x-ui.sidebar-menu-item>
                            <x-ui.sidebar-menu-button href="{{ route('admin.settings.index') }}" :isActive="request()->routeIs('admin.settings.*')">
                                <x-lucide-settings />
                                <span>Settings</span>
                            </x-ui.sidebar-menu-button>
                        </x-ui.sidebar-menu-item>
                    @endif

                </x-ui.sidebar-menu>
            @endif
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
                                @if(auth()->user()->avatarUrl())
                                    <x-ui.avatar-image src="{{ auth()->user()->avatarUrl() }}"
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
                                    @if(auth()->user()->avatarUrl())
                                        <x-ui.avatar-image src="{{ auth()->user()->avatarUrl() }}"
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
                        <x-ui.dropdown-menu-item href="{{ route('admin.account') }}">
                            <x-lucide-user class="size-4" />
                            My Account
                        </x-ui.dropdown-menu-item>
                        @can('settings.view')
                            <x-ui.dropdown-menu-item href="{{ route('admin.settings.general') }}">
                                <x-lucide-settings class="size-4" />
                                Settings
                            </x-ui.dropdown-menu-item>
                        @endcan
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
