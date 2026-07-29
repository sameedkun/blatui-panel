<div class="max-w-4xl">

    <x-admin.page-header :title="$isEditing ? ($isProtectedRole ? __('roles.form.view_title') : __('roles.form.edit_title')) : __('roles.form.create_title')" :description="$isEditing
        ? __('roles.form.edit_description')
        : __('roles.form.create_description')" :breadcrumbs="$isEditing
        ? [
            ['label' => __('roles.common.home'), 'url' => route('admin.dashboard')],
            ['label' => __('roles.title'), 'url' => route('admin.roles.index')],
            ['label' => $roleLabel],
        ]
        : [
            ['label' => __('roles.common.home'), 'url' => route('admin.dashboard')],
            ['label' => __('roles.title'), 'url' => route('admin.roles.index')],
            ['label' => __('roles.form.create_breadcrumb')],
        ]" :back="route('admin.roles.index')" />

    @if ($isProtectedRole)
        <x-ui.alert tone="warning" class="mb-6">
            <x-lucide-lock class="size-4" />
            <x-ui.alert-title>{{ __('roles.form.protected_title') }}</x-ui.alert-title>
            <x-ui.alert-description>
                {{ __('roles.form.protected_description', ['name' => $roleLabel]) }}
            </x-ui.alert-description>
        </x-ui.alert>
    @endif

    <x-ui.card>
        <x-ui.card-content class="space-y-6 pt-6">
            <form wire:submit="save" class="space-y-6">

                {{-- Name --}}
                <x-ui.field>
                    <x-ui.field-label for="name" required>{{ __('roles.fields.name') }}</x-ui.field-label>
                    <x-ui.input id="name" wire:model="name" :placeholder="__('roles.form.name_placeholder')" :disabled="$isProtectedRole"
                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" />
                    <x-ui.field-description>
                        {{ __('roles.form.name_description') }}
                    </x-ui.field-description>
                    @error('name')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <div class="space-y-6">

                    {{-- Panel access --}}
                    <div class="rounded-md border border-border p-4">
                        <p class="mb-3 text-sm font-semibold">{{ __('roles.form.panel_access') }}</p>
                        <div class="flex flex-wrap gap-4">
                            @forelse ($panelAccessOptions as $permission => $label)
                                <label class="flex cursor-pointer items-center gap-2">
                                    <x-ui.checkbox native wire:model="permissions" value="{{ $permission }}"
                                        :disabled="$isProtectedRole" />
                                    <span class="text-sm">{{ $label }}</span>
                                </label>
                            @empty
                                <p class="text-sm text-muted-foreground">{{ __('roles.form.no_panel_access') }}</p>
                            @endforelse
                        </div>
                    </div>

                    {{-- Permission matrix --}}
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold">{{ __('roles.form.module_permissions') }}</p>
                        <div class="flex items-center gap-2 text-xs">
                            <span class="text-muted-foreground" x-text="$wire.permissions.length + ' ' + @js(__('roles.form.selected_suffix'))"></span>
                            @unless ($isProtectedRole)
                                <span class="text-muted-foreground">·</span>
                                <button type="button" class="text-primary underline hover:no-underline"
                                    @click="$wire.permissions = @js($allPermissionNames)">{{ __('roles.actions.select_all') }}</button>
                                <span class="text-muted-foreground">·</span>
                                <button type="button" class="text-primary underline hover:no-underline"
                                    @click="$wire.permissions = []">{{ __('roles.actions.clear_all') }}</button>
                            @endunless
                        </div>
                    </div>

                    @foreach ($moduleGroups as $groupLabel => $modules)
                        <div>
                            <p class="mb-2 text-xs font-semibold text-muted-foreground uppercase">{{ $groupLabel }}</p>
                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($modules as $module)
                                    @php $modulePermissions = $module['permissions']->keys()->all(); @endphp
                                    <div class="rounded-md border border-border p-3" x-data="{ modulePerms: @js($modulePermissions) }">
                                        <button type="button"
                                            @unless ($isProtectedRole)
                                                @click="$wire.permissions = modulePerms.every(p => $wire.permissions.includes(p))
                                                    ? $wire.permissions.filter(p => !modulePerms.includes(p))
                                                    : [...new Set([...$wire.permissions, ...modulePerms])]"
                                            @endunless
                                            :disabled="{{ $isProtectedRole ? 'true' : 'false' }}"
                                            class="mb-2 flex w-full items-center justify-between gap-2 text-left text-sm font-medium hover:text-primary disabled:cursor-not-allowed disabled:hover:text-inherit"
                                        >
                                            <span>{{ $module['label'] }}</span>
                                            <span class="text-xs font-normal text-muted-foreground"
                                                x-text="modulePerms.filter(p => $wire.permissions.includes(p)).length + '/' + modulePerms.length"></span>
                                        </button>
                                        <div class="space-y-1.5">
                                            @foreach ($module['permissions'] as $permission => $actionLabel)
                                                <label class="flex cursor-pointer items-center gap-2">
                                                    <x-ui.checkbox native wire:model="permissions" value="{{ $permission }}"
                                                        :disabled="$isProtectedRole" />
                                                    <span class="text-sm">{{ $actionLabel }}</span>
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                </div>

                @error('permissions')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
                @error('permissions.*')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror

                {{-- Actions --}}
                <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
                    <x-ui.button variant="outline" href="{{ route('admin.roles.index') }}"
                        type="button">{{ $isProtectedRole ? __('roles.common.back') : __('roles.common.cancel') }}</x-ui.button>
                    @unless ($isProtectedRole)
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                                <x-lucide-save class="size-4" />
                                {{ $isEditing ? __('roles.actions.save_changes') : __('roles.actions.create') }}
                            </span>
                            <span wire:loading.flex wire:target="save" class="items-center gap-2">
                                <x-ui.spinner class="size-4" />
                                {{ $isEditing ? __('roles.form.saving') : __('roles.form.creating') }}
                            </span>
                        </x-ui.button>
                    @endunless
                </div>

            </form>
        </x-ui.card-content>
    </x-ui.card>

</div>
