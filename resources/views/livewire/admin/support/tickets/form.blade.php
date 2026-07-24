<div class="max-w-2xl">

    <x-admin.page-header title="Log Ticket" description="Raise a support ticket on behalf of an existing app user — useful for phone or email support." :breadcrumbs="[
        ['label' => 'Home', 'url' => route('admin.dashboard')],
        ['label' => 'Tickets', 'url' => route('admin.tickets.index')],
        ['label' => 'Create'],
    ]" :back="route('admin.tickets.index')" />

    <x-ui.card class="mt-6">
        <x-ui.card-content class="space-y-6 pt-6">
            <form wire:submit="save" class="space-y-6">

                <x-ui.field>
                    <x-ui.field-label for="requesterId" required>Requester</x-ui.field-label>
                    <x-ui.select native id="requesterId" wire:model="requesterId" :options="$requesterOptions" placeholder="Select the app user" />
                    <x-ui.field-description>The account this ticket is being raised on behalf of.</x-ui.field-description>
                    @error('requesterId')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <x-ui.field>
                    <x-ui.field-label for="subject" required>Subject</x-ui.field-label>
                    <x-ui.input id="subject" wire:model="subject" placeholder="e.g. Can't connect to the VPN"
                        aria-invalid="{{ $errors->has('subject') ? 'true' : 'false' }}" />
                    @error('subject')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.field>
                        <x-ui.field-label for="categoryId">Category</x-ui.field-label>
                        <x-ui.select native id="categoryId" wire:model="categoryId" :options="$categoryOptions" placeholder="No category" />
                        <x-ui.field-description>Drives auto-assignment to the category's agents.</x-ui.field-description>
                        @error('categoryId')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="priority" required>Priority</x-ui.field-label>
                        <x-ui.select native id="priority" wire:model="priority" :options="$priorityOptions" />
                        @error('priority')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>
                </div>

                <x-ui.field>
                    <x-ui.field-label for="message" required>Message</x-ui.field-label>
                    <x-ui.textarea id="message" wire:model="message" rows="5"
                        placeholder="Describe the issue as reported by the requester..."
                        aria-invalid="{{ $errors->has('message') ? 'true' : 'false' }}" />
                    @error('message')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
                    <x-ui.button variant="outline" href="{{ route('admin.tickets.index') }}" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                            <x-lucide-life-buoy class="size-4" />
                            Create Ticket
                        </span>
                        <span wire:loading.flex wire:target="save" class="items-center gap-2">
                            <x-ui.spinner class="size-4" />
                            Creating…
                        </span>
                    </x-ui.button>
                </div>

            </form>
        </x-ui.card-content>
    </x-ui.card>

</div>
