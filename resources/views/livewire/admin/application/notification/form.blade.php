<div class="max-w-3xl">

    <x-admin.page-header :title="$isEditing ? 'Edit Notification' : 'Create Notification'" :description="$isEditing
        ? 'Update the notification content below.'
        : 'Compose a push notification broadcast to every subscribed device.'" :breadcrumbs="$isEditing
        ? [
            ['label' => 'Home', 'url' => route('admin.dashboard')],
            ['label' => 'Notifications', 'url' => route('admin.notifications.index')],
            ['label' => $title],
            ['label' => 'Edit'],
        ]
        : [
            ['label' => 'Home', 'url' => route('admin.dashboard')],
            ['label' => 'Notifications', 'url' => route('admin.notifications.index')],
            ['label' => 'Create'],
        ]" :back="route('admin.notifications.index')" />

    <x-ui.card class="mt-6">
        <x-ui.card-content class="space-y-6 pt-6">
            <form wire:submit="save" class="space-y-6">

                <x-ui.field>
                    <x-ui.field-label for="title" required>Title</x-ui.field-label>
                    <x-ui.input id="title" wire:model="title" placeholder="e.g. New feature available!"
                        aria-invalid="{{ $errors->has('title') ? 'true' : 'false' }}" />
                    @error('title')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <x-ui.field>
                    <x-ui.field-label for="message" required>Message</x-ui.field-label>
                    <x-ui.textarea id="message" wire:model="message" rows="4"
                        placeholder="Write the notification body shown to recipients..."
                        aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}" />
                    @error('message')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.field>
                        <x-ui.field-label for="type" required>Type</x-ui.field-label>
                        <x-ui.select native id="type" wire:model="type" :options="$typeOptions" />
                        @error('type')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="link">Link</x-ui.field-label>
                        <x-ui.input id="link" wire:model="link" placeholder="https://example.com/whats-new" />
                        <x-ui.field-description>Opened when the notification is tapped (optional).</x-ui.field-description>
                        @error('link')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>
                </div>

                @if (! $isEditing)
                    <div class="flex items-start gap-3 rounded-lg border border-border p-3.5">
                        <x-ui.checkbox id="sendNow" wire:model="sendNow" class="mt-0.5" />
                        <div class="space-y-0.5">
                            <x-ui.label for="sendNow" class="cursor-pointer text-sm font-medium">Send push notification now</x-ui.label>
                            <p class="text-xs text-muted-foreground">Leave unchecked to save this as a draft you can send later.</p>
                        </div>
                    </div>
                @else
                    <div class="flex items-start gap-3 rounded-lg border border-border p-3.5">
                        <x-ui.checkbox id="resendAfterUpdate" wire:model="resendAfterUpdate" class="mt-0.5" />
                        <div class="space-y-0.5">
                            <x-ui.label for="resendAfterUpdate" class="cursor-pointer text-sm font-medium">Resend push notification after saving</x-ui.label>
                            <p class="text-xs text-muted-foreground">Broadcasts the updated content to every device again. Leave unchecked to just update the record.</p>
                        </div>
                    </div>
                @endif

                <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
                    <x-ui.button variant="outline" href="{{ route('admin.notifications.index') }}" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                            <x-lucide-save class="size-4" />
                            {{ $isEditing ? 'Save Changes' : ($sendNow ? 'Create & Send' : 'Save as Draft') }}
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
