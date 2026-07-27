{{--
    Reusable right-side confirmation drawer with a reason textarea (drawer
    equivalent of <x-admin.reason-dialog>).

    Open it from anywhere with:  $dispatch('open-drawer-{id}')

    Props:
      id            drawer id (drives the open/close events)
      title         heading text
      description   sub-text under the title
      model         the wire:model property the textarea binds to
      confirm       Livewire method called on confirm (wire:click), e.g. "block()"
      confirmLabel  confirm button text (default "Confirm")
      cancel        optional JS expression run on cancel, e.g. "$wire.set('blockingId', null)"
      variant       confirm button variant (default "destructive")
      label         textarea field label (default "Reason")
      placeholder   textarea placeholder
      minLength     minimum characters required before the confirm button enables (default 0)
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
    'minLength' => 0,
])

<x-ui.drawer :id="$id" direction="right">
    <x-ui.drawer-content>
        <x-ui.drawer-header>
            <x-ui.drawer-title>{{ $title }}</x-ui.drawer-title>
            @if($description)
                <x-ui.drawer-description>{{ $description }}</x-ui.drawer-description>
            @endif
        </x-ui.drawer-header>

        <div class="px-4">
            <x-ui.field>
                <x-ui.field-label>{{ $label }}</x-ui.field-label>
                <x-ui.textarea wire:model="{{ $model }}" rows="4" placeholder="{{ $placeholder }}" />
                @if($minLength > 0)
                    <x-ui.field-description>Minimum {{ $minLength }} characters.</x-ui.field-description>
                @endif
                @error($model)
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>
        </div>

        <x-ui.drawer-footer class="flex-row justify-end">
            <x-ui.button variant="outline" @click="open = false; {!! $cancel !!}">Cancel</x-ui.button>
            <x-ui.button
                variant="{{ $variant }}"
                @click="open = false"
                wire:click="{!! $confirm !!}"
            >{{ $confirmLabel }}</x-ui.button>
        </x-ui.drawer-footer>
    </x-ui.drawer-content>
</x-ui.drawer>
