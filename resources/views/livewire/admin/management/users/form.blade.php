<div class="max-w-3xl">

    <x-admin.page-header :title="$isEditing ? __('users.edit_user') : __('users.create_user')" :description="$isEditing
        ? __('users.edit_description')
        : __('users.create_description')" :breadcrumbs="$isEditing
        ? [
            ['label' => __('navigation.modules.dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('users.title'), 'url' => route('admin.users.index')],
            ['label' => Str::limit($name, 30)],
            ['label' => __('common.edit')],
        ]
        : [
            ['label' => __('navigation.modules.dashboard'), 'url' => route('admin.dashboard')],
            ['label' => __('users.title'), 'url' => route('admin.users.index')],
            ['label' => __('common.create')],
        ]" :back="route('admin.users.index')" />

    <x-ui.card>
        <x-ui.card-content class="pt-6">
            <form wire:submit="save" class="space-y-5">

                {{-- Name --}}
                <x-ui.field>
                    <x-ui.field-label for="name" required>{{ __('users.fields.name') }}</x-ui.field-label>
                    <x-ui.input id="name" wire:model="name" placeholder="{{ __('users.fields.name') }}"
                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" />
                    @error('name')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                {{-- Email --}}
                <x-ui.field>
                    <x-ui.field-label for="email" required>{{ __('users.fields.email') }}</x-ui.field-label>
                    <x-ui.input id="email" type="email" wire:model.live="email" placeholder="user@example.com"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" />
                    @error('email')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror

                    @if ($isEditing && $emailChanged)
                        <x-ui.field-description class="text-amber-600 dark:text-amber-400">
                            <x-lucide-triangle-alert class="inline size-3.5" />
                            {{ __('users.fields.email_changed_warning') }}
                        </x-ui.field-description>
                    @endif
                </x-ui.field>

                {{-- Password --}}
                <x-ui.field>
                    <x-ui.field-label for="password" :required="!$isEditing">
                        {{ __('users.fields.password') }}
                        @if ($isEditing)
                            <span class="text-muted-foreground font-normal">{{ __('users.fields.password_leave_blank') }}</span>
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
                            <x-ui.label for="auto-verify" class="cursor-pointer">{{ __('users.fields.auto_verify_email') }}</x-ui.label>
                            <p class="text-xs text-muted-foreground">{{ __('users.fields.auto_verify_email_help') }}</p>
                        </div>
                    </div>
                @endif

                {{-- Edit-only: force password reset --}}
                @if ($isEditing)
                    <div class="flex items-start gap-2">
                        <x-ui.checkbox id="force-reset" wire:model="forcePasswordReset" class="mt-0.5" />
                        <div>
                            <x-ui.label for="force-reset" class="cursor-pointer">{{ __('users.fields.force_password_reset') }}</x-ui.label>
                            <p class="text-xs text-muted-foreground">{{ __('users.fields.force_password_reset_help') }}</p>
                        </div>
                    </div>
                @endif

                {{-- Edit-only: auto-verify a changed email --}}
                @if ($isEditing && $emailChanged)
                    <div class="flex items-start gap-2">
                        <x-ui.checkbox id="auto-verify-changed-email" wire:model="autoVerifyChangedEmail" class="mt-0.5" />
                        <div>
                            <x-ui.label for="auto-verify-changed-email" class="cursor-pointer">{{ __('users.fields.auto_verify_new_email') }}</x-ui.label>
                            <p class="text-xs text-muted-foreground">{{ __('users.fields.auto_verify_new_email_help') }}</p>
                        </div>
                    </div>
                @endif

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
                    <x-ui.button variant="outline" href="{{ route('admin.users.index') }}"
                        type="button">{{ __('common.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                            <x-lucide-save class="size-4" />
                            {{ $isEditing ? __('common.save_changes') : __('users.actions.create') }}
                        </span>
                        <span wire:loading.flex wire:target="save" class="items-center gap-2">
                            <x-ui.spinner class="size-4" />
                            {{ $isEditing ? __('users.actions.saving') : __('users.actions.creating') }}
                        </span>
                    </x-ui.button>
                </div>

            </form>
        </x-ui.card-content>
    </x-ui.card>

</div>
