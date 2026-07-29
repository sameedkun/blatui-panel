<div class="max-w-2xl">

    <x-admin.page-header :title="__('tickets.form.title')" :description="__('tickets.form.description')" :breadcrumbs="[
        ['label' => __('tickets.common.home'), 'url' => route('admin.dashboard')],
        ['label' => __('tickets.title'), 'url' => route('admin.tickets.index')],
        ['label' => __('tickets.form.create_breadcrumb')],
    ]" :back="route('admin.tickets.index')" />

    <x-ui.card class="mt-6">
        <x-ui.card-content class="space-y-6 pt-6">
            <form wire:submit="save" class="space-y-6">

                <x-ui.field>
                    <x-ui.field-label for="userSearch" required>{{ __('tickets.fields.requester') }}</x-ui.field-label>

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
                                <span class="sr-only">{{ __('tickets.form.change_user') }}</span>
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
                                    :placeholder="__('tickets.form.user_search')"
                                    class="pl-9"
                                    autocomplete="off"
                                />
                            </div>

                            <div x-show="open" x-cloak class="absolute z-50 mt-1 max-h-60 w-full overflow-y-auto rounded-md border border-border bg-popover p-1 shadow-md">
                                @forelse ($this->userSearchResults as $user)
                                    <button
                                        type="button"
                                        wire:click="selectUser('{{ $user->id }}')"
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
                                        {{ $userSearch
                                            ? __('tickets.form.no_users_matching', ['search' => $userSearch])
                                            : __('tickets.form.no_users') }}
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    <x-ui.field-description>{{ __('tickets.form.requester_description') }}</x-ui.field-description>
                    @error('requesterId')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <x-ui.field>
                    <x-ui.field-label for="subject" required>{{ __('tickets.fields.subject') }}</x-ui.field-label>
                    <x-ui.input id="subject" wire:model="subject" :placeholder="__('tickets.form.subject_placeholder')"
                        aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}" />
                    @error('subject')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.field>
                        <x-ui.field-label for="categoryId">{{ __('tickets.fields.category') }}</x-ui.field-label>
                        <x-ui.select native id="categoryId" wire:model="categoryId" :options="$categoryOptions" :placeholder="__('tickets.form.no_category')" />
                        <x-ui.field-description>{{ __('tickets.form.category_description') }}</x-ui.field-description>
                        @error('categoryId')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="priority" required>{{ __('tickets.fields.priority') }}</x-ui.field-label>
                        <x-ui.select native id="priority" wire:model="priority" :options="$priorityOptions" />
                        @error('priority')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>
                </div>

                <x-ui.field>
                    <x-ui.field-label for="message" required>{{ __('tickets.fields.message') }}</x-ui.field-label>
                    <x-ui.textarea id="message" wire:model="message" rows="5"
                        :placeholder="__('tickets.form.message_placeholder')"
                        aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}" />
                    @error('message')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
                    <x-ui.button variant="outline" href="{{ route('admin.tickets.index') }}" type="button">{{ __('tickets.common.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                            <x-lucide-life-buoy class="size-4" />
                            {{ __('tickets.actions.create') }}
                        </span>
                        <span wire:loading.flex wire:target="save" class="items-center gap-2">
                            <x-ui.spinner class="size-4" />
                            {{ __('tickets.form.creating') }}
                        </span>
                    </x-ui.button>
                </div>

            </form>
        </x-ui.card-content>
    </x-ui.card>

</div>
