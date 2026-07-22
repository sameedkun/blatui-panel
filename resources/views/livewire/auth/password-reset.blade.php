<div>
    <x-ui.card variant="sectioned" class="w-full max-w-sm">
        <x-ui.card-header>
            <x-ui.card-title>Reset password</x-ui.card-title>
            <x-ui.card-description>Choose a new password for your account.</x-ui.card-description>
        </x-ui.card-header>
        <x-ui.card-content>
            <form class="flex flex-col gap-6">
                <x-ui.field>
                    <x-ui.field-label for="reset-password">New password</x-ui.field-label>
                    <x-ui.input id="reset-password" type="password" wire:model="password"
                        aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" />
                    @error('password')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>
                <x-ui.field>
                    <x-ui.field-label for="reset-password-confirmation">Confirm password</x-ui.field-label>
                    <x-ui.input id="reset-password-confirmation" type="password" wire:model="password_confirmation" />
                </x-ui.field>
            </form>
        </x-ui.card-content>
        <x-ui.card-footer>
            <x-ui.button type="submit" class="w-full" wire:click="resetPassword" wire:loading.attr="disabled" wire:target="resetPassword">
                <span wire:loading.remove wire:target="resetPassword" class="inline-flex items-center gap-2">
                    <x-lucide-key-round />
                    Reset password
                </span>
                <span wire:loading.flex wire:target="resetPassword" class="items-center gap-2">
                    <x-ui.spinner class="size-4" /> Resetting…
                </span>
            </x-ui.button>
        </x-ui.card-footer>
    </x-ui.card>
</div>
