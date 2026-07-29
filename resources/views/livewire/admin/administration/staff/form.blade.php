<div class="w-full">

    <x-admin.page-header :title="$isEditing ? __('staff.form.edit_title') : __('staff.form.create_title')" :description="$isEditing
        ? __('staff.form.edit_description')
        : __('staff.form.create_description')" :breadcrumbs="$isEditing
        ? [
            ['label' => __('staff.common.home'), 'url' => route('admin.dashboard')],
            ['label' => __('staff.title'), 'url' => route('admin.staff.index')],
            ['label' => Str::limit($name, 30)],
            ['label' => __('staff.form.edit_breadcrumb')],
        ]
        : [
            ['label' => __('staff.common.home'), 'url' => route('admin.dashboard')],
            ['label' => __('staff.title'), 'url' => route('admin.staff.index')],
            ['label' => __('staff.form.create_breadcrumb')],
        ]" :back="route('admin.staff.index')" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3 mt-3">

        <x-ui.card class="lg:col-span-2">
            <x-ui.card-content class="pt-6">
                <form wire:submit="save" class="space-y-5">

                    {{-- Name --}}
                    <x-ui.field>
                        <x-ui.field-label for="name" required>{{ __('staff.fields.name') }}</x-ui.field-label>
                        <x-ui.input id="name" wire:model="name" :placeholder="__('staff.form.name_placeholder')"
                            aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" />
                        @error('name')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    {{-- Email --}}
                    <x-ui.field>
                        <x-ui.field-label for="email" required>{{ __('staff.fields.email') }}</x-ui.field-label>
                        <x-ui.input id="email" type="email" wire:model.live="email" :placeholder="__('staff.form.email_placeholder')"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" />
                        @error('email')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror

                        @if ($isEditing && $emailChanged)
                            <x-ui.field-description class="text-amber-600 dark:text-amber-400">
                                <x-lucide-triangle-alert class="inline size-3.5" />
                                {{ __('staff.form.email_changed') }}
                            </x-ui.field-description>
                        @endif
                    </x-ui.field>

                    {{-- Password --}}
                    <x-ui.field>
                        <x-ui.field-label for="password" :required="!$isEditing">
                            {{ __('staff.fields.password') }}
                            @if ($isEditing)
                                <span class="text-muted-foreground font-normal">{{ __('staff.form.leave_password_blank') }}</span>
                            @endif
                        </x-ui.field-label>
                        <x-ui.input id="password" type="password" wire:model="password"
                            :placeholder="$isEditing ? '••••••••' : __('staff.form.password_placeholder')"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" />
                        @error('password')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    {{-- Roles --}}
                    <x-ui.field>
                        <x-ui.field-label required>{{ __('staff.fields.roles') }}</x-ui.field-label>
                        <x-ui.field-description>
                            {{ __('staff.form.roles_description') }}
                        </x-ui.field-description>
                        <div wire:ignore>
                            <x-ui.select wire:model.live="roles" multiple :options="$roleOptions"
                                :placeholder="__('staff.form.roles_placeholder')"
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
                                <x-ui.label for="send-verification" class="cursor-pointer">{{ __('staff.form.require_verification') }}</x-ui.label>
                                <p class="text-xs text-muted-foreground">{{ __('staff.form.require_verification_description') }}</p>
                            </div>
                        </div>
                    @endif

                    {{-- Edit-only: force password reset --}}
                    @if ($isEditing)
                        <div class="flex items-start gap-2">
                            <x-ui.checkbox id="force-reset" wire:model="forcePasswordReset" class="mt-0.5" />
                            <div>
                                <x-ui.label for="force-reset" class="cursor-pointer">{{ __('staff.form.force_password_reset') }}</x-ui.label>
                                <p class="text-xs text-muted-foreground">{{ __('staff.form.force_password_reset_description') }}</p>
                            </div>
                        </div>
                    @endif

                    {{-- Edit-only: auto-verify a changed email --}}
                    @if ($isEditing && $emailChanged)
                        <div class="flex items-start gap-2">
                            <x-ui.checkbox id="auto-verify-changed-email" wire:model="autoVerifyChangedEmail" class="mt-0.5" />
                            <div>
                                <x-ui.label for="auto-verify-changed-email" class="cursor-pointer">{{ __('staff.form.auto_verify_email') }}</x-ui.label>
                                <p class="text-xs text-muted-foreground">{{ __('staff.form.auto_verify_email_description') }}</p>
                            </div>
                        </div>
                    @endif

                    {{-- Actions --}}
                    <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
                        <x-ui.button variant="outline" href="{{ route('admin.staff.index') }}"
                            type="button">{{ __('staff.common.cancel') }}</x-ui.button>
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                                <x-lucide-save class="size-4" />
                                {{ $isEditing ? __('staff.actions.save_changes') : __('staff.actions.create') }}
                            </span>
                            <span wire:loading.flex wire:target="save" class="items-center gap-2">
                                <x-ui.spinner class="size-4" />
                                {{ $isEditing ? __('staff.form.saving') : __('staff.form.creating') }}
                            </span>
                        </x-ui.button>
                    </div>

                </form>
            </x-ui.card-content>
        </x-ui.card>

        {{-- Effective permissions preview — combined, deduplicated across selected roles --}}
        <x-ui.card class="lg:col-span-1">
            <x-ui.card-header>
                <x-ui.card-title>{{ __('staff.form.permissions_title') }}</x-ui.card-title>
                <x-ui.card-description>{{ __('staff.form.permissions_description') }}</x-ui.card-description>
            </x-ui.card-header>
            <x-ui.card-content class="space-y-4">
                @forelse ($groupedPermissions as $module => $permissions)
                    <div>
                        <p class="mb-1.5 text-xs font-semibold text-muted-foreground uppercase">
                            {{ $moduleLabels[$module] }}
                        </p>
                        <div class="flex flex-wrap gap-1">
                            @foreach ($permissions as $permission)
                                <x-ui.badge variant="secondary">{{ $permissionLabels[$permission] }}</x-ui.badge>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-muted-foreground">{{ __('staff.form.permissions_empty') }}</p>
                @endforelse
            </x-ui.card-content>
        </x-ui.card>

    </div>

</div>
