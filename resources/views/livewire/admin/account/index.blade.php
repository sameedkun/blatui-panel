@php
    use Illuminate\Support\Str;
    /** @var \App\Models\User $user */

    // Same action→badge treatment the activity-log viewer uses, so a row scans identically here.
    $actionBadge = function (?string $event): array {
        return match ($event) {
            'banned', 'deleted', 'force_deleted', 'purged', 'failed' => ['variant' => 'destructive', 'class' => ''],
            'created' => [
                'variant' => 'default',
                'class' => 'border-0 bg-emerald-500/15 text-emerald-700 dark:text-emerald-400',
            ],
            'restored', 'unbanned' => [
                'variant' => 'default',
                'class' => 'border-0 bg-sky-500/15 text-sky-700 dark:text-sky-400',
            ],
            'login', 'password_reset' => ['variant' => 'secondary', 'class' => ''],
            default => ['variant' => 'outline', 'class' => ''],
        };
    };

    $badgeVariants = ['default', 'secondary', 'outline'];

    $roleBadgeVariant = fn(string $role) => $badgeVariants[abs(crc32($role)) % count($badgeVariants)];
@endphp

<div class="mx-auto flex w-full max-w-[1280px] flex-col gap-8">

    <x-admin.page-header title="My Account" description="Manage your own profile, password, and security."
        :breadcrumbs="[['label' => 'Home', 'url' => route('admin.dashboard')], ['label' => 'My Account']]" />

    <x-ui.tabs value="overview" orientation="vertical" class="flex flex-col gap-6 lg:flex-row lg:gap-10">
        
        {{-- Navigation sidebar --}}
        <x-ui.tabs-list variant="pills" class="flex flex-row overflow-x-auto pb-2 lg:pb-0 lg:flex-col lg:items-stretch lg:w-1/5 gap-1 shrink-0 bg-transparent text-muted-foreground w-full justify-start h-auto p-0 border-b lg:border-b-0 border-border mb-4 lg:mb-0">
            <x-ui.tabs-trigger value="overview" class="group-data-[variant=pills]/tabs-list:rounded-md group-data-[variant=pills]/tabs-list:px-3 group-data-[variant=pills]/tabs-list:py-2 group-data-[variant=pills]/tabs-list:data-[state=active]:bg-muted group-data-[variant=pills]/tabs-list:data-[state=active]:text-foreground justify-start cursor-pointer w-full text-left font-medium hover:bg-muted/50 transition-colors">
                <x-lucide-layout-dashboard class="mr-2 size-4" />
                Overview
            </x-ui.tabs-trigger>
            
            <x-ui.tabs-trigger value="profile" class="group-data-[variant=pills]/tabs-list:rounded-md group-data-[variant=pills]/tabs-list:px-3 group-data-[variant=pills]/tabs-list:py-2 group-data-[variant=pills]/tabs-list:data-[state=active]:bg-muted group-data-[variant=pills]/tabs-list:data-[state=active]:text-foreground justify-start cursor-pointer w-full text-left font-medium hover:bg-muted/50 transition-colors">
                <x-lucide-user-cog class="mr-2 size-4" />
                Edit Profile
            </x-ui.tabs-trigger>
            
            <x-ui.tabs-trigger value="security" class="group-data-[variant=pills]/tabs-list:rounded-md group-data-[variant=pills]/tabs-list:px-3 group-data-[variant=pills]/tabs-list:py-2 group-data-[variant=pills]/tabs-list:data-[state=active]:bg-muted group-data-[variant=pills]/tabs-list:data-[state=active]:text-foreground justify-start cursor-pointer w-full text-left font-medium hover:bg-muted/50 transition-colors">
                <x-lucide-lock class="mr-2 size-4" />
                Security
            </x-ui.tabs-trigger>
            
            <x-ui.tabs-trigger value="activity" class="group-data-[variant=pills]/tabs-list:rounded-md group-data-[variant=pills]/tabs-list:px-3 group-data-[variant=pills]/tabs-list:py-2 group-data-[variant=pills]/tabs-list:data-[state=active]:bg-muted group-data-[variant=pills]/tabs-list:data-[state=active]:text-foreground justify-start cursor-pointer w-full text-left font-medium hover:bg-muted/50 transition-colors">
                <x-lucide-history class="mr-2 size-4" />
                My Activity
            </x-ui.tabs-trigger>
        </x-ui.tabs-list>

        {{-- Content areas --}}
        <div class="flex-1 lg:max-w-4xl space-y-6">

            {{-- ── Tab 1: Overview ────────────────────────────────────────────── --}}
            <x-ui.tabs-content value="overview" class="space-y-6 outline-none">
                <div>
                    <h3 class="text-lg font-semibold tracking-tight">Overview</h3>
                    <p class="text-sm text-muted-foreground">General information, roles, and status of your account.</p>
                </div>
                <x-ui.separator />

                {{-- Welcome Card --}}
                <div class="relative overflow-hidden rounded-xl border bg-card p-6 shadow-xs">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center gap-4">
                            <x-ui.avatar class="size-16 rounded-full border">
                                @if ($user->avatarUrl())
                                    <x-ui.avatar-image :src="$user->avatarUrl()" :alt="$user->name" />
                                @endif
                                <x-ui.avatar-fallback class="text-base">
                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                </x-ui.avatar-fallback>
                            </x-ui.avatar>
                            <div>
                                <h3 class="text-xl font-semibold tracking-tight">{{ $user->name }}</h3>
                                <p class="text-sm text-muted-foreground">{{ $user->email }}</p>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            @forelse ($roles as $role)
                                <x-ui.badge :variant="$roleBadgeVariant($role)">
                                    {{ Str::headline($role) }}
                                </x-ui.badge>
                            @empty
                                <x-ui.badge variant="outline">No Assigned Roles</x-ui.badge>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Account Details Grid --}}
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {{-- General Stats --}}
                    <div class="rounded-xl border bg-card p-5 shadow-xs">
                        <h4 class="text-sm font-semibold mb-4 text-muted-foreground uppercase tracking-wider text-[11px]">Account Details</h4>
                        <dl class="space-y-3">
                            <div class="flex justify-between border-b border-border/50 pb-2">
                                <dt class="text-sm text-muted-foreground">User ID</dt>
                                <dd class="text-sm font-medium text-foreground">#{{ $user->id }}</dd>
                            </div>
                            <div class="flex justify-between border-b border-border/50 pb-2">
                                <dt class="text-sm text-muted-foreground">External API ID</dt>
                                <dd class="text-sm font-mono text-foreground select-all text-xs">{{ $user->external_id }}</dd>
                            </div>
                            <div class="flex justify-between border-b border-border/50 pb-2">
                                <dt class="text-sm text-muted-foreground">Registered On</dt>
                                <dd class="text-sm font-medium text-foreground">{{ $user->registration_date?->format('M d, Y') ?? '—' }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-muted-foreground">Email Status</dt>
                                <dd class="text-sm font-medium">
                                    @if ($user->hasVerifiedEmail())
                                        <span class="text-emerald-600 dark:text-emerald-400">Verified</span>
                                    @else
                                        <span class="text-amber-600 dark:text-amber-400">Unverified</span>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Security Stats --}}
                    <div class="rounded-xl border bg-card p-5 shadow-xs">
                        <h4 class="text-sm font-semibold mb-4 text-muted-foreground uppercase tracking-wider text-[11px]">Security & Status</h4>
                        <dl class="space-y-3">
                            <div class="flex justify-between border-b border-border/50 pb-2">
                                <dt class="text-sm text-muted-foreground">Last Login</dt>
                                <dd class="text-sm font-medium text-foreground">
                                    {{ $user->last_login?->diffForHumans() ?? 'Never' }}
                                </dd>
                            </div>
                            <div class="flex justify-between border-b border-border/50 pb-2">
                                <dt class="text-sm text-muted-foreground">Password Changed</dt>
                                <dd class="text-sm font-medium text-foreground">
                                    {{ $user->password_changed_at?->diffForHumans() ?? 'Never' }}
                                </dd>
                            </div>
                            <div class="flex justify-between border-b border-border/50 pb-2">
                                <dt class="text-sm text-muted-foreground">Account Type</dt>
                                <dd class="text-sm font-medium text-foreground">
                                    {{ Str::headline($user->type->value ?? 'Staff') }}
                                </dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-sm text-muted-foreground">Lifecycle State</dt>
                                <dd class="text-sm font-medium">
                                    @if ($user->isBanned())
                                        <x-ui.badge variant="destructive">Banned</x-ui.badge>
                                    @elseif ($user->isPendingDeletion())
                                        <x-ui.badge variant="destructive">Pending Deletion</x-ui.badge>
                                    @else
                                        <x-ui.badge variant="outline" class="border-emerald-500 text-emerald-600 dark:text-emerald-400 bg-emerald-500/10">Active</x-ui.badge>
                                    @endif
                                </dd>
                            </div>
                        </dl>
                    </div>
                </div>

                {{-- Permissions Matrix --}}
                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <h4 class="text-sm font-semibold mb-4 text-muted-foreground uppercase tracking-wider text-[11px]">Assigned Permissions</h4>
                    @if ($groupedPermissions->isEmpty())
                        <p class="text-sm text-muted-foreground py-2">No direct permissions assigned to your roles.</p>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach ($groupedPermissions as $module => $perms)
                                <div class="rounded-lg border bg-muted/20 p-3">
                                    <div class="font-medium text-sm text-foreground capitalize mb-2 border-b border-border pb-1">
                                        {{ Str::headline($module) }}
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($perms as $perm)
                                            <x-ui.badge variant="outline" class="text-[10px] py-0 px-1.5 font-normal">
                                                {{ Str::after($perm, $module . '.') }}
                                            </x-ui.badge>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Quick Activity --}}
                <div class="rounded-xl border bg-card p-5 shadow-xs">
                    <div class="flex items-center justify-between mb-4 border-b border-border pb-2">
                        <h4 class="text-sm font-semibold text-muted-foreground uppercase tracking-wider text-[11px]">Recent Activity Snippet</h4>
                        <x-ui.button variant="link" size="sm" class="p-0 h-auto cursor-pointer" @click="tab = 'activity'">
                            View full log &rarr;
                        </x-ui.button>
                    </div>
                    <ul class="flex flex-col gap-2">
                        @forelse ($recentActivity->take(3) as $activity)
                            @php $badge = $actionBadge($activity->event); @endphp
                            <li class="flex items-center justify-between text-xs py-1 border-b last:border-b-0 border-border/40">
                                <div class="flex items-center gap-2 truncate">
                                    <x-ui.badge :variant="$badge['variant']" class="{{ $badge['class'] }} scale-90 px-1.5 py-0 shrink-0">
                                        {{ Str::headline($activity->event ?? '—') }}
                                    </x-ui.badge>
                                    <span class="truncate text-muted-foreground">
                                        {{ Str::headline($activity->properties['module'] ?? '') }}
                                        @if ($activity->subject_type)
                                            · {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}
                                        @endif
                                    </span>
                                </div>
                                <span class="text-muted-foreground text-[10px] shrink-0">{{ $activity->created_at?->diffForHumans() ?? '—' }}</span>
                            </li>
                        @empty
                            <li class="text-xs text-muted-foreground text-center py-4">No activity yet.</li>
                        @endforelse
                    </ul>
                </div>
            </x-ui.tabs-content>

            {{-- ── Tab 2: Profile Settings ────────────────────────────────────── --}}
            <x-ui.tabs-content value="profile" class="space-y-6 outline-none">
                <div>
                    <h3 class="text-lg font-semibold tracking-tight">Profile Settings</h3>
                    <p class="text-sm text-muted-foreground">Update your photo, name, and email address.</p>
                </div>
                <x-ui.separator />

                <form wire:submit="saveProfile" class="flex flex-col gap-6 max-w-2xl">
                    <div class="flex items-center gap-4">
                        <x-ui.avatar class="size-20 rounded-full border">
                            @if ($avatarUpload)
                                <x-ui.avatar-image :src="$avatarUpload->temporaryUrl()" :alt="$user->name" />
                            @elseif ($user->avatarUrl())
                                <x-ui.avatar-image :src="$user->avatarUrl()" :alt="$user->name" />
                            @endif

                            <x-ui.avatar-fallback class="text-lg">
                                {{ strtoupper(substr($user->name, 0, 2)) }}
                            </x-ui.avatar-fallback>
                        </x-ui.avatar>

                        <div class="min-w-0">
                            <p class="truncate font-medium text-sm">{{ $name ?: $user->name }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ $email ?: $user->email }}</p>

                            <label class="mt-1 inline-block cursor-pointer text-xs font-medium text-primary hover:underline">
                                Change photo
                                <input type="file" wire:model="avatarUpload" accept="image/*" class="hidden">
                            </label>
                            <p class="text-[10px] text-muted-foreground">JPG, PNG or GIF. Max 2 MB.</p>

                            <div wire:loading wire:target="avatarUpload" class="mt-1 text-xs text-muted-foreground">
                                Uploading...
                            </div>

                            @error('avatarUpload')
                                <x-ui.field-error class="mt-1">
                                    {{ $message }}
                                </x-ui.field-error>
                            @enderror
                        </div>
                    </div>

                    <div class="space-y-5">
                        <x-ui.field>
                            <x-ui.field-label for="name" required>Name</x-ui.field-label>
                            <x-ui.input id="name" wire:model="name" placeholder="Your full name"
                                aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" />
                            @error('name')
                                <x-ui.field-error>{{ $message }}</x-ui.field-error>
                            @enderror
                        </x-ui.field>

                        <x-ui.field>
                            <x-ui.field-label for="email" :required="$this->canEditEmail()">Email</x-ui.field-label>
                            @if ($this->canEditEmail())
                                <x-ui.input id="email" type="email" wire:model.live="email"
                                    placeholder="you@example.com"
                                    aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" />
                                @if ($email !== $user->email)
                                    <x-ui.field-description class="text-amber-600 dark:text-amber-400">
                                        <x-lucide-triangle-alert class="inline size-3.5" />
                                        Changing your email will require verification of the new address.
                                    </x-ui.field-description>
                                @endif
                            @else
                                <x-ui.input id="email" type="email" :value="$user->email" readonly
                                    class="cursor-not-allowed bg-muted/50 text-muted-foreground" />
                                <x-ui.field-description>
                                    Contact an administrator to change your email.
                                </x-ui.field-description>
                            @endif
                            @error('email')
                                <x-ui.field-error>{{ $message }}</x-ui.field-error>
                            @enderror
                        </x-ui.field>
                    </div>

                    <div class="flex justify-start">
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="saveProfile,avatarUpload">
                            <span wire:loading.remove wire:target="saveProfile" class="inline-flex items-center gap-2">
                                <x-lucide-save class="size-4" />
                                Save changes
                            </span>
                            <span wire:loading.flex wire:target="saveProfile" class="items-center gap-2">
                                <x-ui.spinner class="size-4" />
                                Saving...
                            </span>
                        </x-ui.button>
                    </div>
                </form>
            </x-ui.tabs-content>

            {{-- ── Tab 3: Security Settings ───────────────────────────────────── --}}
            <x-ui.tabs-content value="security" class="space-y-6 outline-none">
                <div>
                    <h3 class="text-lg font-semibold tracking-tight">Security Settings</h3>
                    <p class="text-sm text-muted-foreground">Manage your password and account security settings.</p>
                </div>
                <x-ui.separator />

                <div class="space-y-8 max-w-2xl">
                    {{-- Change password --}}
                    <x-ui.card class="p-6">
                        <div class="mb-4">
                            <h4 class="text-sm font-medium">Change password</h4>
                            <p class="text-xs text-muted-foreground">Enter your current password to set a new one.</p>
                        </div>

                        <form wire:submit="updatePassword" class="space-y-4">
                            <x-ui.field>
                                <x-ui.field-label for="current_password" required>Current password</x-ui.field-label>
                                <x-ui.input id="current_password" type="password" wire:model="current_password"
                                    placeholder="••••••••"
                                    aria-invalid="{{ $errors->has('current_password') ? 'true' : 'false' }}" />
                                @error('current_password')
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>

                            <x-ui.field>
                                <x-ui.field-label for="password" required>New password</x-ui.field-label>
                                <x-ui.input id="password" type="password" wire:model="password"
                                    placeholder="Minimum 8 characters"
                                    aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" />
                                @error('password')
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>

                            <x-ui.field>
                                <x-ui.field-label for="password_confirmation" required>Confirm new password</x-ui.field-label>
                                <x-ui.input id="password_confirmation" type="password" wire:model="password_confirmation"
                                    placeholder="Re-enter new password" />
                            </x-ui.field>

                            <div class="flex justify-start pt-2">
                                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="updatePassword">
                                    <span wire:loading.remove wire:target="updatePassword" class="inline-flex items-center gap-2">
                                        <x-lucide-key class="size-4" />
                                        Update password
                                    </span>
                                    <span wire:loading.flex wire:target="updatePassword" class="items-center gap-2">
                                        <x-ui.spinner class="size-4" />
                                        Updating…
                                    </span>
                                </x-ui.button>
                            </div>
                        </form>
                    </x-ui.card>

                    {{-- Log out other devices --}}
                    <x-ui.card class="p-6">
                        <div class="mb-4">
                            <h4 class="text-sm font-medium text-destructive">Log out other devices</h4>
                            <p class="text-xs text-muted-foreground">
                                Sign out of every other browser and device. This one stays signed in. Confirm with your password.
                            </p>
                        </div>

                        <form wire:submit="logoutOtherDevices" class="space-y-4">
                            <x-ui.field>
                                <x-ui.field-label for="logout_password" required>Password</x-ui.field-label>
                                <x-ui.input id="logout_password" type="password" wire:model="logout_password"
                                    placeholder="••••••••"
                                    aria-invalid="{{ $errors->has('logout_password') ? 'true' : 'false' }}" />
                                @error('logout_password')
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>

                            <div class="flex justify-start pt-2">
                                <x-ui.button type="submit" variant="outline" wire:loading.attr="disabled" wire:target="logoutOtherDevices">
                                    <span wire:loading.remove wire:target="logoutOtherDevices" class="inline-flex items-center gap-2">
                                        <x-lucide-log-out class="size-4" />
                                        Log out other devices
                                    </span>
                                    <span wire:loading.flex wire:target="logoutOtherDevices" class="items-center gap-2">
                                        <x-ui.spinner class="size-4" />
                                        Working…
                                    </span>
                                </x-ui.button>
                            </div>
                        </form>
                    </x-ui.card>
                </div>
            </x-ui.tabs-content>

            {{-- ── Tab 4: My Activity ────────────────────────────────────────── --}}
            <x-ui.tabs-content value="activity" class="space-y-6 outline-none">
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold tracking-tight">My Activity</h3>
                        <p class="text-sm text-muted-foreground">Your most recent actions across the panel.</p>
                    </div>
                    @if ($canViewFullLog)
                        <x-ui.button variant="outline" size="sm" class="cursor-pointer"
                            href="{{ route('admin.activity-logs.index', ['filters' => ['causer' => [$user->id]]]) }}">
                            View all
                            <x-lucide-arrow-right class="ml-2 size-4" />
                        </x-ui.button>
                    @endif
                </div>
                <x-ui.separator />

                <x-ui.card class="p-6">
                    <ul class="flex flex-col divide-y divide-border">
                        @forelse ($recentActivity as $activity)
                            @php $badge = $actionBadge($activity->event); @endphp
                            <li class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                                <div class="flex min-w-0 items-center gap-3">
                                    <x-ui.badge :variant="$badge['variant']" class="{{ $badge['class'] }} shrink-0">
                                        {{ Str::headline($activity->event ?? '—') }}
                                    </x-ui.badge>
                                    <span class="truncate text-sm text-muted-foreground">
                                        {{ Str::headline($activity->properties['module'] ?? '') }}
                                        @if ($activity->subject_type)
                                            · {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}
                                        @endif
                                    </span>
                                </div>
                                <x-admin.tooltip :text="$activity->created_at?->toDayDateTimeString() ?? ''">
                                    <span class="shrink-0 text-xs whitespace-nowrap text-muted-foreground">
                                        {{ $activity->created_at?->diffForHumans() ?? '—' }}
                                    </span>
                                </x-admin.tooltip>
                            </li>
                        @empty
                            <li class="py-10 text-center text-sm text-muted-foreground">
                                <x-lucide-clipboard-list class="mx-auto mb-2 size-8 opacity-30" />
                                No activity yet.
                            </li>
                        @endforelse
                    </ul>
                </x-ui.card>
            </x-ui.tabs-content>

        </div>
    </x-ui.tabs>

</div>
