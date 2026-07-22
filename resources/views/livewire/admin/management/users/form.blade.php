<div class="max-w-3xl">

    <x-admin.page-header :title="$isEditing ? 'Edit User' : 'Create User'" :description="$isEditing
        ? 'Update the user account details below.'
        : 'Fill in the details to create a new user account.'" :breadcrumbs="$isEditing
        ? [
            ['label' => 'Home', 'url' => route('admin.dashboard')],
            ['label' => 'Users', 'url' => route('admin.users.index')],
            ['label' => Str::limit($name, 30)],
            ['label' => 'Edit'],
        ]
        : [
            ['label' => 'Home', 'url' => route('admin.dashboard')],
            ['label' => 'Users', 'url' => route('admin.users.index')],
            ['label' => 'Create'],
        ]" :back="route('admin.users.index')" />

    <x-ui.card>
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

                {{-- Create-only: auto-verify email --}}
                @if (!$isEditing)
                    <div class="flex items-start gap-2">
                        <x-ui.checkbox id="auto-verify" wire:model="autoVerifyEmail" class="mt-0.5" />
                        <div>
                            <x-ui.label for="auto-verify" class="cursor-pointer">Auto-verify email address</x-ui.label>
                            <p class="text-xs text-muted-foreground">Mark the email as verified without sending a
                                verification link.</p>
                        </div>
                    </div>
                @endif

                {{-- Edit-only: force password reset --}}
                @if ($isEditing)
                    <div class="flex items-start gap-2">
                        <x-ui.checkbox id="force-reset" wire:model="forcePasswordReset" class="mt-0.5" />
                        <div>
                            <x-ui.label for="force-reset" class="cursor-pointer">Force password reset</x-ui.label>
                            <p class="text-xs text-muted-foreground">Emails the user a password reset link they'll need
                                to use to set a new password.</p>
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
                    <x-ui.button variant="outline" href="{{ route('admin.users.index') }}"
                        type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                            <x-lucide-save class="size-4" />
                            {{ $isEditing ? 'Save Changes' : 'Create User' }}
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

</div>
