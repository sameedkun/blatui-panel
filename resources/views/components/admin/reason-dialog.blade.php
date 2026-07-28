{{--
    Reusable confirmation dialog with a reason textarea (dialog based).

    Open it from anywhere with:  $dispatch('open-dialog-{id}')

    Props:
      id            dialog id (drives the open/close events)
      title         heading text
      description   sub-text under the title
      model         the wire:model property the textarea binds to
      confirm       Livewire method called on confirm (wire:click), e.g. "confirmBan"
      confirmLabel  confirm button text (default "Confirm")
      cancel        optional JS expression run on cancel, e.g. "$wire.cancelBulkAction()"
      variant       confirm button variant (default "destructive")
      label         textarea field label (default "Reason")
      placeholder   textarea placeholder
--}}
@props([
    'id',
    'title',
    'description' => '',
    'model',
    'confirm',
    'confirmLabel' => 'Confirm',
    'cancel' => '',
    'variant' => 'destructive',
    'label' => 'Reason',
    'placeholder' => '',
])

<x-ui.dialog :id="$id">
    <x-ui.dialog-content class="sm:max-w-md">
        <x-ui.dialog-header>
            <x-ui.dialog-title>{{ $title }}</x-ui.dialog-title>
            @if($description)
                <x-ui.dialog-description>{{ $description }}</x-ui.dialog-description>
            @endif
        </x-ui.dialog-header>

        <x-ui.field>
            <x-ui.field-label>{{ $label }}</x-ui.field-label>
            <x-ui.textarea wire:model="{{ $model }}" rows="3" placeholder="{{ $placeholder }}" />
        </x-ui.field>

        <x-ui.dialog-footer>
            <x-ui.button variant="outline" @click="open = false; {!! $cancel !!}"> {{ __('common.cancel') }} </x-ui.button>
            <x-ui.button variant="{{ $variant }}" @click="open = false" wire:click="{!! $confirm !!}">
                {{ $confirmLabel }}
            </x-ui.button>
        </x-ui.dialog-footer>
    </x-ui.dialog-content>
</x-ui.dialog>
