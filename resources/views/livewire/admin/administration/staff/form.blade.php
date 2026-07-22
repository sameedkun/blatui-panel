<div class="w-full">

    <x-admin.page-header :title="$isEditing ? 'Edit Staff' : 'Create Staff'" :description="$isEditing
        ? 'Update the staff account details and roles below.'
        : 'Fill in the details and assign roles to create a new staff account.'" :breadcrumbs="$isEditing
        ? [
            ['label' => 'Home', 'url' => route('admin.dashboard')],
            ['label' => 'Staff', 'url' => route('admin.staff.index')],
            ['label' => Str::limit($name, 30)],
            ['label' => 'Edit'],
        ]
        : [
            ['label' => 'Home', 'url' => route('admin.dashboard')],
            ['label' => 'Staff', 'url' => route('admin.staff.index')],
            ['label' => 'Create'],
        ]" :back="route('admin.staff.index')" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mt-3">

        <x-ui.card class="lg:col-span-2">
            <x-ui.card-content class="pt-6">
                <form wire:submit="save" class="space-y-5">

                    {{-- Name --}}
                    <x-ui.field>
                        <x-ui.field-label for="name" required>Name</x-ui.field-label>
                        <x-ui.input id="name" wire:model="name" placeholder="Full name"
                            aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" />
                        @error('name')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    {{-- Email --}}
                    <x-ui.field>
                        <x-ui.field-label for="email" required>Email</x-ui.field-label>
                        <x-ui.input id="email" type="email" wire:model.live="email" placeholder="user@example.com"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" />
                        @error('email')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror

                        @if ($isEditing && $emailChanged)
                            <x-ui.field-description class="text-amber-600 dark:text-amber-400">
                                <x-lucide-triangle-alert class="inline size-3.5" />
                                Email changed — the user will need to verify the new address.
                            </x-ui.field-description>
                        @endif
                    </x-ui.field>

                    {{-- Password --}}
                    <x-ui.field>
                        <x-ui.field-label for="password" :required="!$isEditing">
                            Password
                            @if ($isEditing)
                                <span class="text-muted-foreground font-normal">(leave blank to keep current)</span>
                            @endif
                        </x-ui.field-label>
                        <x-ui.input id="password" type="password" wire:model="password"
                            placeholder="{{ $isEditing ? '••••••••' : 'Minimum 8 characters' }}"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" />
                        @error('password')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    {{-- Roles --}}
                    <x-ui.field>
                        <x-ui.field-label required>Roles</x-ui.field-label>
                        <x-ui.field-description>
                            Staff are granted permissions through roles, not assigned directly.
                        </x-ui.field-description>
                        <div wire:ignore>
                            <x-ui.select wire:model.live="roles" multiple :options="$roleOptions"
                                placeholder="Select roles..."
                                aria-invalid="{{ $errors->has('roles') ? 'true' : 'false' }}" />
                        </div>
                        @error('roles')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                        @error('roles.*')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    {{-- Create-only: email verification --}}
                    @if (!$isEditing)
                        <div class="flex items-start gap-2">
                            <x-ui.checkbox id="send-verification" wire:model="sendVerificationEmail" class="mt-0.5" />
                            <div>
                                <x-ui.label for="send-verification" class="cursor-pointer">Require email
                                    verification</x-ui.label>
                                <p class="text-xs text-muted-foreground">By default the email is auto-verified. Check
                                    this to leave it unverified and send a verification link instead.</p>
                            </div>
                        </div>
                    @endif

                    {{-- Edit-only: force password reset --}}
                    @if ($isEditing)
                        <div class="flex items-start gap-2">
                            <x-ui.checkbox id="force-reset" wire:model="forcePasswordReset" class="mt-0.5" />
                            <div>
                                <x-ui.label for="force-reset" class="cursor-pointer">Force password reset</x-ui.label>
                                <p class="text-xs text-muted-foreground">Emails the staff member a password reset link
                                    they'll need to use to set a new password.</p>
                            </div>
                        </div>
                    @endif

                    {{-- Edit-only: auto-verify a changed email --}}
                    @if ($isEditing && $emailChanged)
                        <div class="flex items-start gap-2">
                            <x-ui.checkbox id="auto-verify-changed-email" wire:model="autoVerifyChangedEmail" class="mt-0.5" />
                            <div>
                                <x-ui.label for="auto-verify-changed-email" class="cursor-pointer">Auto-verify new email</x-ui.label>
                                <p class="text-xs text-muted-foreground">Skip the verification email and mark the new
                                    address as verified immediately.</p>
                            </div>
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
                        <x-ui.button variant="outline" href="{{ route('admin.staff.index') }}"
                            type="button">Cancel</x-ui.button>
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                                <x-lucide-save class="size-4" />
                                {{ $isEditing ? 'Save Changes' : 'Create Staff' }}
                            </span>
                            <span wire:loading.flex wire:target="save" class="items-center gap-2">
                                <x-ui.spinner class="size-4" />
                                {{ $isEditing ? 'Saving…' : 'Creating…' }}
                            </span>
                        </x-ui.button>
                    </div>

                </form>
            </x-ui.card-content>
        </x-ui.card>

        {{-- Effective permissions preview — combined, deduplicated across selected roles --}}
        <x-ui.card class="lg:col-span-1">
            <x-ui.card-header>
                <x-ui.card-title>Effective Permissions</x-ui.card-title>
                <x-ui.card-description>Combined permissions granted by the selected roles.</x-ui.card-description>
            </x-ui.card-header>
            <x-ui.card-content class="space-y-4">
                @forelse ($groupedPermissions as $module => $permissions)
                    <div>
                        <p class="mb-1.5 text-xs font-semibold text-muted-foreground uppercase">
                            {{ config("panel.modules.{$module}.label", Str::headline($module)) }}
                        </p>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($permissions as $permission)
                                <x-ui.badge variant="secondary">{{ Str::headline(Str::after($permission, '.')) }}</x-ui.badge>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">Select one or more roles to preview their combined
                        permissions.</p>
                @endforelse
            </x-ui.card-content>
        </x-ui.card>

    </div>

</div>
