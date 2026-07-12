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

<div class="mx-auto flex w-full max-w-[1280px] flex-col gap-10">

    <x-admin.page-header title="My Account" description="Manage your own profile, password, and security."
        :breadcrumbs="[['label' => 'Home', 'url' => route('admin.dashboard')], ['label' => 'My Account']]" />

    {{-- ── Section 1 · Profile ─────────────────────────────────────────────── --}}
    <section class="flex flex-col gap-4">
        <div>
            <h2 class="text-lg font-semibold">Profile</h2>
            <p class="text-sm text-muted-foreground">Your name, photo, and email address.</p>
        </div>

        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

            {{-- Editable profile --}}
            <x-ui.card variant="sectioned" class="lg:col-span-2">
                <form wire:submit="saveProfile" class="flex flex-col gap-6">
                    <div class="flex items-center gap-4 px-6">
                        <x-ui.avatar class="size-16 rounded-full">
                            @if ($avatarUpload)
                                <x-ui.avatar-image :src="$avatarUpload->temporaryUrl()" :alt="$user->name" />
                            @elseif ($user->avatar)
                                <x-ui.avatar-image :src="$user->avatar" :alt="$user->name" />
                            @endif

                            <x-ui.avatar-fallback class="text-lg">
                                {{ strtoupper(substr($user->name, 0, 2)) }}
                            </x-ui.avatar-fallback>
                        </x-ui.avatar>

                        <div class="min-w-0">
                            <p class="truncate font-medium">{{ $name ?: $user->name }}</p>
                            <p class="truncate text-sm text-muted-foreground">{{ $email ?: $user->email }}</p>

                            <label class="mt-1 inline-block cursor-pointer text-sm font-medium text-primary hover:underline">
                                Change photo
                                <input type="file" wire:model="avatarUpload" accept="image/*" class="hidden">
                            </label>

                            <p class="text-xs text-muted-foreground">JPG, PNG or GIF. Max 2 MB.</p>

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

                    <x-ui.separator />

                    <div class="space-y-5 px-6">

                        <x-ui.field>
                            <x-ui.field-label for="name" required>
                                Name
                            </x-ui.field-label>

                            <x-ui.input id="name" wire:model="name" placeholder="Your full name"
                                aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" />

                            @error('name')
                                <x-ui.field-error>{{ $message }}</x-ui.field-error>
                            @enderror
                        </x-ui.field>

                        <x-ui.field>
                            <x-ui.field-label for="email" :required="$this->canEditEmail()">
                                Email
                            </x-ui.field-label>

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

                    <x-ui.separator />

                    <div class="flex justify-end px-6">
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
            </x-ui.card>

            {{-- Read-only account details — compact, no duplicate name/email inputs --}}
            <x-ui.card class="lg:col-span-1">
                <div class="mb-6">
                    <h3 class="text-base font-semibold">Account Details</h3>
                    <p class="text-sm text-muted-foreground">
                        Read-only information about your account.
                    </p>
                </div>

                <div class="space-y-6">

                    {{-- Roles --}}
                    <div>
                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Roles
                        </p>

                        <div class="flex flex-wrap gap-2">
                            @forelse ($roles as $role)
                                <x-ui.badge :variant="$roleBadgeVariant($role)">
                                    {{ Str::headline($role) }}
                                </x-ui.badge>
                            @empty
                                <span class="text-sm text-muted-foreground">
                                    No roles assigned
                                </span>
                            @endforelse
                        </div>
                    </div>

                    {{-- User ID --}}
                    <div>
                        <p class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            User ID
                        </p>

                        <p class="text-sm font-medium">
                            #{{ $user->id }}
                        </p>
                    </div>

                    {{-- Registered --}}
                    <div>
                        <p class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Registered
                        </p>

                        <p class="text-sm font-medium">
                            {{ $user->registration_date?->format('M d, Y') ?? '—' }}
                        </p>
                    </div>

                    {{-- Last Login --}}
                    <div>
                        <p class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Last Login
                        </p>

                        <p class="text-sm font-medium">
                            {{ $user->last_login?->diffForHumans() ?? 'Never' }}
                        </p>

                        @if ($user->last_login)
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ $user->last_login->format('M d, Y H:i') }}
                            </p>
                        @endif
                    </div>

                    {{-- Email Status --}}
                    <div>
                        <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Email Status
                        </p>

                        @if ($user->hasVerifiedEmail())
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.badge variant="default">
                                    Verified
                                </x-ui.badge>

                                <span class="text-xs text-muted-foreground">
                                    {{ $user->email_verified_at?->format('M d, Y H:i') }}
                                </span>
                            </div>
                        @else
                            <x-ui.badge variant="destructive">
                                Not Verified
                            </x-ui.badge>
                        @endif
                    </div>

                    {{-- Password --}}
                    <div>
                        <p class="mb-1 text-xs font-medium uppercase tracking-wide text-muted-foreground">
                            Password Changed
                        </p>

                        @if ($user->password_changed_at)
                            <p class="text-sm font-medium">
                                {{ $user->password_changed_at->diffForHumans() }}
                            </p>

                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ $user->password_changed_at->format('M d, Y H:i') }}
                            </p>
                        @else
                            <span class="text-sm text-muted-foreground">
                                Never changed
                            </span>
                        @endif
                    </div>

                </div>
            </x-ui.card>

        </div>
    </section>

    {{-- ── Section 2 · Security ────────────────────────────────────────────── --}}
    <section class="flex flex-col gap-4">
        <div>
            <h2 class="text-lg font-semibold">Security</h2>
            <p class="text-sm text-muted-foreground">Manage your password and account security.</p>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">

            {{-- Change password --}}
            <x-ui.card>
                <p class="text-sm font-medium">Change password</p>
                <p class="mb-4 text-sm text-muted-foreground">Enter your current password to set a new one.</p>

                <form wire:submit="updatePassword" class="flex flex-col gap-4">
                    <div class="grid max-w-xl gap-4">
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
                            <x-ui.field-label for="password_confirmation" required>Confirm new
                                password</x-ui.field-label>
                            <x-ui.input id="password_confirmation" type="password" wire:model="password_confirmation"
                                placeholder="Re-enter new password" />
                        </x-ui.field>
                    </div>

                    <div class="flex items-center justify-end md:justify-start">
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="updatePassword">
                            <span wire:loading.remove wire:target="updatePassword"
                                class="inline-flex items-center gap-2">
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
            <x-ui.card>
                <p class="text-sm font-medium">Log out other devices</p>
                <p class="mb-4 text-sm text-muted-foreground">
                    Sign out of every other browser and device. This one stays signed in. Confirm with your
                    password.
                </p>

                <form wire:submit="logoutOtherDevices" class="flex flex-col gap-4">
                    <div class="grid max-w-xl gap-4">
                        <x-ui.field>
                            <x-ui.field-label for="logout_password" required>Password</x-ui.field-label>
                            <x-ui.input id="logout_password" type="password" wire:model="logout_password"
                                placeholder="••••••••"
                                aria-invalid="{{ $errors->has('logout_password') ? 'true' : 'false' }}" />
                            @error('logout_password')
                                <x-ui.field-error>{{ $message }}</x-ui.field-error>
                            @enderror
                        </x-ui.field>
                    </div>

                    <div class="flex items-center justify-end md:justify-start">
                        <x-ui.button type="submit" variant="outline" wire:loading.attr="disabled"
                            wire:target="logoutOtherDevices">
                            <span wire:loading.remove wire:target="logoutOtherDevices"
                                class="inline-flex items-center gap-2">
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

            {{-- Two-factor authentication slots in here as a sibling card when built. --}}

        </div>
    </section>

    {{-- ── Section 3 · My activity ─────────────────────────────────────────── --}}
    <section class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-semibold">My Activity</h2>
                <p class="text-sm text-muted-foreground">Your most recent actions across the panel.</p>
            </div>
            @if ($canViewFullLog)
                <x-ui.button variant="outline" size="sm"
                    href="{{ route('admin.activity-logs.index', ['filters' => ['causer' => [$user->id]]]) }}">
                    View all
                    <x-lucide-arrow-right class="size-4" />
                </x-ui.button>
            @endif
        </div>

        <x-ui.card>
            <ul class="flex flex-col">
                @forelse ($recentActivity as $activity)
                    @php $badge = $actionBadge($activity->event); @endphp
                    <li class="flex items-center justify-between gap-4 py-3">
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
    </section>

</div>
