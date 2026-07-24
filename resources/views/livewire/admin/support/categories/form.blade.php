<div class="max-w-2xl">

    <x-admin.page-header :title="$isEditing ? 'Edit Category' : 'Create Category'" :description="$isEditing
        ? 'Update the category and its agent pool.'
        : 'Categories route tickets to a pool of agents, auto-assigned by current workload.'" :breadcrumbs="$isEditing
        ? [
            ['label' => 'Home', 'url' => route('admin.dashboard')],
            ['label' => 'Ticket Categories', 'url' => route('admin.ticket-categories.index')],
            ['label' => $name],
            ['label' => 'Edit'],
        ]
        : [
            ['label' => 'Home', 'url' => route('admin.dashboard')],
            ['label' => 'Ticket Categories', 'url' => route('admin.ticket-categories.index')],
            ['label' => 'Create'],
        ]" :back="route('admin.ticket-categories.index')" />

    <x-ui.card class="mt-6">
        <x-ui.card-content class="space-y-6 pt-6">
            <form wire:submit="save" class="space-y-6">

                <x-ui.field>
                    <x-ui.field-label for="name" required>Name</x-ui.field-label>
                    <x-ui.input id="name" wire:model="name" placeholder="e.g. Billing"
                        aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" />
                    @error('name')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <x-ui.field>
                        <x-ui.field-label for="sort_order">Sort Order</x-ui.field-label>
                        <x-ui.input id="sort_order" type="number" min="0" wire:model="sort_order" />
                        @error('sort_order')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    <div class="flex items-center gap-3 self-end rounded-lg border border-border p-3.5">
                        <x-ui.checkbox id="is_active" wire:model="is_active" />
                        <x-ui.label for="is_active" class="cursor-pointer text-sm font-medium">Active</x-ui.label>
                    </div>
                </div>

                <x-ui.field>
                    <x-ui.field-label>Agents</x-ui.field-label>
                    <x-ui.field-description>Staff eligible for auto-assignment on this category — the least-loaded agent gets the next ticket.</x-ui.field-description>

                    @if ($agentOptions->isEmpty())
                        <p class="text-xs text-muted-foreground italic">No staff accounts exist yet.</p>
                    @else
                        <div class="grid grid-cols-1 gap-2 rounded-lg border border-border p-3 sm:grid-cols-2">
                            @foreach ($agentOptions as $agent)
                                <label for="agent-{{ $agent->id }}" class="flex cursor-pointer items-center gap-2.5 rounded-md p-1.5 hover:bg-muted/50">
                                    <x-ui.checkbox native id="agent-{{ $agent->id }}" wire:model="agentIds" value="{{ $agent->id }}" />
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium">{{ $agent->name }}</span>
                                        <span class="block truncate text-xs text-muted-foreground">{{ $agent->email }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                    @error('agentIds')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <div class="flex items-center justify-end gap-3 border-t border-border pt-4">
                    <x-ui.button variant="outline" href="{{ route('admin.ticket-categories.index') }}" type="button">Cancel</x-ui.button>
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                            <x-lucide-save class="size-4" />
                            {{ $isEditing ? 'Save Changes' : 'Create Category' }}
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
