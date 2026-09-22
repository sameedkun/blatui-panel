<div class="max-w-3xl">

    <x-admin.page-header :title="$isEditing ? __('announcements.form.edit_title') : __('announcements.form.create_title')" :description="$isEditing
        ? __('announcements.form.edit_description')
        : __('announcements.form.create_description')" :breadcrumbs="$isEditing
        ? [
            ['label' => __('navigation.home'), 'url' => route('admin.dashboard')],
            ['label' => __('announcements.title'), 'url' => route('admin.announcements.index')],
            ['label' => $title],
            ['label' => __('announcements.form.breadcrumb_edit')],
        ]
        : [
            ['label' => __('navigation.home'), 'url' => route('admin.dashboard')],
            ['label' => __('announcements.title'), 'url' => route('admin.announcements.index')],
            ['label' => __('announcements.form.breadcrumb_create')],
        ]" :back="route('admin.announcements.index')" />

    <x-ui.card class="mt-6">
        <x-ui.card-content class="space-y-6 pt-6">
            <form wire:submit="save" class="space-y-6">

                <x-ui.field>
                    <x-ui.field-label for="title" required>{{ __('announcements.fields.title') }}</x-ui.field-label>
                    <x-ui.input id="title" wire:model="title" :placeholder="__('announcements.form.title_placeholder')"
                        aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}" />
                    @error('title')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <x-ui.field>
                    <x-ui.field-label for="message" required>{{ __('announcements.fields.message') }}</x-ui.field-label>
                    <x-ui.textarea id="message" wire:model="message" rows="4"
                        placeholder="{{ __('announcements.form.message_placeholder') }}"
                        aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}" />
                    @error('message')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.field>
                        <x-ui.field-label for="type" required>{{ __('announcements.fields.type') }}</x-ui.field-label>
                        <x-ui.select native id="type" wire:model="type" :options="$typeOptions" />
                        @error('type')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="link">{{ __('announcements.fields.link') }}</x-ui.field-label>
                        <x-ui.input id="link" wire:model="link" placeholder="https://example.com/whats-new" />
                        <x-ui.field-description>{{ __('announcements.form.link_description') }}</x-ui.field-description>
                        @error('link')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>
                </div>

                @if (! $isEditing)
                    <div class="flex items-start gap-3 rounded-lg border border-border p-3.5">
                        <x-ui.checkbox id="sendNow" wire:model="sendNow" class="mt-0.5" />
                        <div class="space-y-0.5">
                            <x-ui.label for="sendNow" class="cursor-pointer text-sm font-medium">{{ __('announcements.form.send_now') }}</x-ui.label>
                            <p class="text-xs text-muted-foreground">{{ __('announcements.form.send_now_description') }}</p>
                        </div>
                    </div>
                @else
                    <div class="flex items-start gap-3 rounded-lg border border-border p-3.5">
                        <x-ui.checkbox id="resendAfterUpdate" wire:model="resendAfterUpdate" class="mt-0.5" />
                        <div class="space-y-0.5">
                            <x-ui.label for="resendAfterUpdate" class="cursor-pointer text-sm font-medium">{{ __('announcements.form.resend_after_update') }}</x-ui.label>
                            <p class="text-xs text-muted-foreground">{{ __('announcements.form.resend_after_update_description') }}</p>
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
                    <x-ui.button variant="outline" href="{{ route('admin.announcements.index') }}" type="button">{{ __('announcements.actions.cancel') }}</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                            <x-lucide-save class="size-4" />
                            {{ $isEditing
                                ? __('announcements.actions.save_changes')
                                : ($sendNow ? __('announcements.actions.create_send') : __('announcements.actions.save_draft')) }}
                        </span>
                        <span wire:loading.flex wire:target="save" class="items-center gap-2">
                            <x-ui.spinner class="size-4" />
                            {{ $isEditing ? __('announcements.form.saving') : __('announcements.form.creating') }}
                        </span>
                    </x-ui.button>
                </div>

            </form>
        </x-ui.card-content>
    </x-ui.card>

</div>
