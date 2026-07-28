{{-- Convert/merge dialogs — profile-page only (no Guests index row action for
     either), so unlike single-row-dialogs.blade.php these aren't shared with
     the index. --}}

<x-ui.dialog id="convert-user">
    <x-ui.dialog-content class="sm:max-w-md">
        <x-ui.dialog-header>
            <x-ui.dialog-title>{{ __('guests.dialogs.convert_title') }}</x-ui.dialog-title>
            <x-ui.dialog-description>
                {{ __('guests.dialogs.convert_desc') }}
            </x-ui.dialog-description>
        </x-ui.dialog-header>

        <div class="space-y-4">
            <x-ui.field>
                <x-ui.field-label for="convert-email" required>{{ __('guests.fields.email') }}</x-ui.field-label>
                <x-ui.input id="convert-email" type="email" wire:model="convertEmail" placeholder="you@example.com"
                    aria-invalid="{{ $errors->has('convertEmail') ? 'true' : 'false' }}" />
                @error('convertEmail')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="convert-name">{{ __('users.fields.name') }}</x-ui.field-label>
                <x-ui.input id="convert-name" wire:model="convertName" placeholder="Full name" />
            </x-ui.field>

            <div class="flex items-start gap-2">
                <x-ui.checkbox id="convert-mark-verified" wire:model="convertMarkEmailVerified" class="mt-0.5" />
                <div>
                    <x-ui.label for="convert-mark-verified" class="cursor-pointer">{{ __('guests.dialogs.mark_email_verified') }}</x-ui.label>
                    <p class="text-xs text-muted-foreground">{{ __('guests.dialogs.mark_email_verified_help') }}</p>
                </div>
            </div>
        </div>

        <x-ui.dialog-footer>
            <x-ui.button variant="outline" @click="open = false; $wire.set('convertingId', null)">{{ __('common.cancel') }}</x-ui.button>
            <x-ui.button wire:click="confirmConvert">{{ __('guests.actions.convert') }}</x-ui.button>
        </x-ui.dialog-footer>
    </x-ui.dialog-content>
</x-ui.dialog>

<x-ui.dialog id="merge-guest">
    <x-ui.dialog-content class="sm:max-w-md">
        <x-ui.dialog-header>
            <x-ui.dialog-title>{{ __('guests.dialogs.merge_title') }}</x-ui.dialog-title>
            <x-ui.dialog-description>
                {{ __('guests.dialogs.merge_desc') }}
            </x-ui.dialog-description>
        </x-ui.dialog-header>

        <div class="space-y-4">
            <x-ui.field>
                <x-ui.field-label for="merge-search" required>{{ __('guests.dialogs.merge_destination') }}</x-ui.field-label>

                @if ($mergeDestinationId)
                    @php $mergeDestination = \App\Models\User::find($mergeDestinationId); @endphp
                    <div class="flex items-center justify-between rounded-md border px-3 py-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ $mergeDestination?->name }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ $mergeDestination?->email }}</p>
                        </div>
                        <x-ui.button type="button" variant="ghost" size="sm" wire:click="clearMergeDestination">
                            {{ __('guests.dialogs.change_destination') }}
                        </x-ui.button>
                    </div>
                @else
                    <x-ui.input id="merge-search" wire:model.live.debounce.300ms="mergeSearch"
                        :placeholder="__('guests.dialogs.merge_search_placeholder')" autocomplete="off"
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
                                <p class="px-3 py-2 text-sm text-muted-foreground">{{ __('guests.dialogs.no_candidates_found') }}</p>
                            @endforelse
                        </x-ui.scroll-area>
                    @endif
                @endif

                @error('mergeDestinationId')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="merge-reason" required>{{ __('guests.dialogs.merge_reason') }}</x-ui.field-label>
                <x-ui.textarea id="merge-reason" wire:model="mergeReason" rows="3"
                    :placeholder="__('guests.dialogs.merge_reason_placeholder')" />
                @error('mergeReason')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>
        </div>

        <x-ui.dialog-footer>
            <x-ui.button variant="outline" @click="open = false; $wire.set('mergingId', null)">{{ __('common.cancel') }}</x-ui.button>
            <x-ui.button variant="destructive" wire:click="confirmMerge">{{ __('guests.actions.merge') }}</x-ui.button>
        </x-ui.dialog-footer>
    </x-ui.dialog-content>
</x-ui.dialog>
