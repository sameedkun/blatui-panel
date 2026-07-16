{{-- Convert/merge dialogs — profile-page only (no Guests index row action for
     either), so unlike single-row-dialogs.blade.php these aren't shared with
     the index. --}}

<x-ui.dialog id="convert-user">
    <x-ui.dialog-content class="sm:max-w-md">
        <x-ui.dialog-header>
            <x-ui.dialog-title>Convert to App User</x-ui.dialog-title>
            <x-ui.dialog-description>
                This guest becomes a real app user in place — same account, same history.
                They'll receive a password-reset link to set their own credentials.
            </x-ui.dialog-description>
        </x-ui.dialog-header>

        <div class="space-y-4">
            <x-ui.field>
                <x-ui.field-label for="convert-email" required>Email</x-ui.field-label>
                <x-ui.input id="convert-email" type="email" wire:model="convertEmail" placeholder="you@example.com"
                    aria-invalid="{{ $errors->has('convertEmail') ? 'true' : 'false' }}" />
                @error('convertEmail')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="convert-name">Name</x-ui.field-label>
                <x-ui.input id="convert-name" wire:model="convertName" placeholder="Full name" />
            </x-ui.field>

            <div class="flex items-start gap-2">
                <x-ui.checkbox id="convert-mark-verified" wire:model="convertMarkEmailVerified" class="mt-0.5" />
                <div>
                    <x-ui.label for="convert-mark-verified" class="cursor-pointer">Mark email as verified</x-ui.label>
                    <p class="text-xs text-muted-foreground">Skips the verification email — use only when the admin
                        already confirmed this address belongs to the account holder.</p>
                </div>
            </div>
        </div>

        <x-ui.dialog-footer>
            <x-ui.button variant="outline" @click="open = false; $wire.set('convertingId', null)">Cancel</x-ui.button>
            <x-ui.button wire:click="confirmConvert">Convert</x-ui.button>
        </x-ui.dialog-footer>
    </x-ui.dialog-content>
</x-ui.dialog>

<x-ui.dialog id="merge-guest">
    <x-ui.dialog-content class="sm:max-w-md">
        <x-ui.dialog-header>
            <x-ui.dialog-title>Merge into Existing Account</x-ui.dialog-title>
            <x-ui.dialog-description>
                Merges this guest's identity into another app account. The guest record is
                permanently removed; the destination account survives. This <strong>cannot be undone</strong>.
            </x-ui.dialog-description>
        </x-ui.dialog-header>

        <div class="space-y-4">
            <x-ui.field>
                <x-ui.field-label for="merge-search" required>Destination account</x-ui.field-label>

                @if ($mergeDestinationId)
                    @php $mergeDestination = \App\Models\User::find($mergeDestinationId); @endphp
                    <div class="flex items-center justify-between rounded-md border px-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ $mergeDestination?->name }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ $mergeDestination?->email }}</p>
                        </div>
                        <x-ui.button type="button" variant="ghost" size="sm" wire:click="clearMergeDestination">
                            Change
                        </x-ui.button>
                    </div>
                @else
                    <x-ui.input id="merge-search" wire:model.live.debounce.300ms="mergeSearch"
                        placeholder="Search app users by name or email..." autocomplete="off"
                        aria-invalid="{{ $errors->has('mergeDestinationId') ? 'true' : 'false' }}" />

                    @if (trim($mergeSearch) !== '')
                        <x-ui.scroll-area class="h-48 rounded-md border">
                            @forelse ($this->mergeCandidates() as $candidate)
                                <button type="button" wire:click="selectMergeDestination({{ $candidate->id }})"
                                    wire:key="merge-candidate-{{ $candidate->id }}"
                                    class="flex w-full flex-col items-start px-3 py-2 text-left text-sm hover:bg-muted">
                                    <span class="font-medium">{{ $candidate->name }}</span>
                                    <span class="text-xs text-muted-foreground">{{ $candidate->email }}</span>
                                </button>
                            @empty
                                <p class="px-3 py-2 text-sm text-muted-foreground">No app users found.</p>
                            @endforelse
                        </x-ui.scroll-area>
                    @endif
                @endif

                @error('mergeDestinationId')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="merge-reason" required>Reason</x-ui.field-label>
                <x-ui.textarea id="merge-reason" wire:model="mergeReason" rows="3"
                    placeholder="Why are these the same person?" />
                @error('mergeReason')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>
        </div>

        <x-ui.dialog-footer>
            <x-ui.button variant="outline" @click="open = false; $wire.set('mergingId', null)">Cancel</x-ui.button>
            <x-ui.button variant="destructive" wire:click="confirmMerge">Merge</x-ui.button>
        </x-ui.dialog-footer>
    </x-ui.dialog-content>
</x-ui.dialog>
