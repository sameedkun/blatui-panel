<div class="max-w-2xl">

    <x-admin.page-header :title="$isEditing ? 'Edit Block' : 'Block IP Address'" :description="$isEditing
        ? 'Update the reason or expiry for this block.'
        : 'Block traffic from a single IP address, globally or for one account.'" :breadcrumbs="$isEditing
        ? [
            ['label' => 'Home', 'url' => route('admin.dashboard')],
            ['label' => 'Blocked IPs', 'url' => route('admin.blocked-ips.index')],
            ['label' => $ipAddress],
            ['label' => 'Edit'],
        ]
        : [
            ['label' => 'Home', 'url' => route('admin.dashboard')],
            ['label' => 'Blocked IPs', 'url' => route('admin.blocked-ips.index')],
            ['label' => 'Block IP'],
        ]" :back="route('admin.blocked-ips.index')" />

    <x-ui.card class="mt-6">
        <x-ui.card-content class="space-y-6 pt-6">
            <form wire:submit="save" class="space-y-6">

                <x-ui.field>
                    <x-ui.field-label for="ipAddress" required>IP Address</x-ui.field-label>
                    <x-ui.input id="ipAddress" wire:model="ipAddress" placeholder="203.0.113.1" />
                    @error('ipAddress')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <x-ui.field>
                    <x-ui.field-label for="scope" required>Scope</x-ui.field-label>
                    <x-ui.select id="scope" native wire:model.live="scope" :options="['user' => 'Per-User', 'global' => 'Global (every user)']" />
                    @error('scope')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                @if ($scope === 'user')
                    <x-ui.field>
                        <x-ui.field-label for="userSearch" required>User Email</x-ui.field-label>

                        @if ($this->selectedUser)
                            <div class="flex items-center justify-between rounded-md border border-border bg-muted/20 p-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <x-ui.avatar class="size-9 shrink-0 rounded-full">
                                        @if ($this->selectedUser->avatarUrl())
                                            <x-ui.avatar-image :src="$this->selectedUser->avatarUrl()" :alt="$this->selectedUser->name" />
                                        @endif
                                        <x-ui.avatar-fallback class="text-xs font-semibold">
                                            {{ strtoupper(substr($this->selectedUser->name, 0, 2)) }}
                                        </x-ui.avatar-fallback>
                                    </x-ui.avatar>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-medium text-foreground text-sm">{{ $this->selectedUser->name }}</p>
                                        <p class="truncate text-xs text-muted-foreground">{{ $this->selectedUser->email }}</p>
                                    </div>
                                </div>
                                <x-ui.button variant="ghost" size="sm" type="button" wire:click="clearUser" class="shrink-0 text-muted-foreground hover:text-foreground">
                                    <x-lucide-x class="size-4" />
                                    <span class="sr-only">Change User</span>
                                </x-ui.button>
                            </div>
                        @else
                            <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                                <div class="relative">
                                    <x-lucide-search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted-foreground" />
                                    <x-ui.input
                                        id="userSearch"
                                        type="text"
                                        wire:model.live.debounce.150ms="userSearch"
                                        @focus="open = true"
                                        placeholder="Search app users by name or email..."
                                        class="pl-9"
                                        autocomplete="off"
                                    />
                                </div>

                                <div x-show="open" x-cloak class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-border bg-popover p-1 shadow-md">
                                    @forelse ($this->userSearchResults as $user)
                                        <button
                                            type="button"
                                            wire:click="selectUser('{{ $user->email }}')"
                                            @click="open = false"
                                            class="flex w-full items-center gap-3 rounded-sm px-3 py-2 text-left text-sm hover:bg-accent hover:text-accent-foreground transition-colors"
                                        >
                                            <x-ui.avatar class="size-8 shrink-0 rounded-full">
                                                @if ($user->avatarUrl())
                                                    <x-ui.avatar-image :src="$user->avatarUrl()" :alt="$user->name" />
                                                @endif
                                                <x-ui.avatar-fallback class="text-xs font-semibold">
                                                    {{ strtoupper(substr($user->name, 0, 2)) }}
                                                </x-ui.avatar-fallback>
                                            </x-ui.avatar>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate font-medium text-foreground">{{ $user->name }}</p>
                                                <p class="truncate text-xs text-muted-foreground">{{ $user->email }}</p>
                                            </div>
                                        </button>
                                    @empty
                                        <div class="px-3 py-4 text-center text-xs text-muted-foreground">
                                            No app users found{{ $userSearch ? ' matching "'.e($userSearch).'"' : '' }}.
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endif

                        @error('formUserEmail')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>
                @else
                    <div class="rounded-md border border-destructive/30 bg-destructive/5 p-3">
                        <p class="flex items-center gap-1.5 text-sm font-medium text-destructive">
                            <x-lucide-triangle-alert class="size-4" />
                            This blocks every account using this IP
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            <strong>{{ $this->globalDistinctUserCount() }}</strong> distinct
                            {{ str('account')->plural($this->globalDistinctUserCount()) }} seen on this IP in the last 30 days.
                        </p>
                        @if ($this->looksLikeCarrierNat())
                            <p class="mt-1 text-xs text-destructive">
                                This looks like a shared carrier IP (e.g. mobile-network NAT, common on Pakistani and Indian mobile networks) — blocking it may lock out many unrelated, legitimate users.
                            </p>
                        @endif
                        <label class="mt-3 flex cursor-pointer items-start gap-2 text-sm">
                            <x-ui.checkbox wire:model.live="globalConfirmed" class="mt-0.5" />
                            I understand and want to block this IP for every user.
                        </label>
                        @error('scope')
                            <x-ui.field-error class="mt-1">{{ $message }}</x-ui.field-error>
                        @enderror
                    </div>
                @endif

                <x-ui.field>
                    <x-ui.field-label for="reason">Reason</x-ui.field-label>
                    <x-ui.textarea id="reason" wire:model="reason" rows="3" placeholder="Why is this IP being blocked?" />
                    @error('reason')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <x-ui.checkbox wire:model.live="permanent" />
                    Permanent (no expiry)
                </label>

                @unless ($permanent)
                    <x-ui.field>
                        <x-ui.field-label for="expiresAt">Expires At</x-ui.field-label>
                        <x-ui.input id="expiresAt" type="datetime-local" wire:model="expiresAt" />
                        <x-ui.field-description>Defaults to 7 days out — permanent blocks accumulate, so make that an explicit choice.</x-ui.field-description>
                        @error('expiresAt')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>
                @endunless

                <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
                    <x-ui.button variant="outline" href="{{ route('admin.blocked-ips.index') }}" type="button">Cancel</x-ui.button>
                    <x-ui.button
                        type="submit"
                        wire:loading.attr="disabled"
                        wire:target="save"
                        :disabled="$scope === 'global' && ! $globalConfirmed"
                    >
                        <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                            <x-lucide-save class="size-4" />
                            {{ $isEditing ? 'Save Changes' : 'Block IP' }}
                        </span>
                        <span wire:loading.flex wire:target="save" class="items-center gap-2">
                            <x-ui.spinner class="size-4" />
                            {{ $isEditing ? 'Saving…' : 'Blocking…' }}
                        </span>
                    </x-ui.button>
                </div>

            </form>
        </x-ui.card-content>
    </x-ui.card>

</div>
