{{-- Shared by both the SMTP and Resend sections — sends a real email through
     whichever driver + settings are currently saved, so the admin can confirm
     mail actually goes out rather than just trusting the form saved. --}}
<div class="max-w-2xl space-y-4 border-t border-border pt-6">
    <div>
        <h4 class="text-sm font-semibold">Send Test Email</h4>
        <p class="text-sm text-muted-foreground">
            Sends a real email using your saved settings above, to confirm mail actually goes out.
        </p>
    </div>

    <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
        <x-ui.field class="flex-1">
            <x-ui.field-label for="test_email_address">Send to</x-ui.field-label>
            <x-ui.input id="test_email_address" type="email" wire:model="test_email_address"
                placeholder="you@example.com" aria-invalid="{{ $errors->has('test_email_address') ? 'true' : 'false' }}" />
            @error('test_email_address')
                <x-ui.field-error>{{ $message }}</x-ui.field-error>
            @enderror
        </x-ui.field>

        @if (config('mail.default') === 'resend')
            <x-ui.field class="sm:w-52">
                <x-ui.field-label for="test_email_purpose">Purpose</x-ui.field-label>
                <x-ui.select native id="test_email_purpose" wire:model="test_email_purpose">
                    @foreach (\App\Enum\MailPurpose::cases() as $purpose)
                        <option value="{{ $purpose->value }}">{{ $purpose->label() }}</option>
                    @endforeach
                </x-ui.select>
            </x-ui.field>
        @endif

        @can('settings.mail.edit')
            <x-ui.button variant="outline" wire:click="sendTestEmail" wire:loading.attr="disabled" wire:target="sendTestEmail">
                <span wire:loading.remove wire:target="sendTestEmail" class="inline-flex items-center gap-2">
                    <x-lucide-send class="size-4" />
                    Send Test Email
                </span>
                <span wire:loading.flex wire:target="sendTestEmail" class="items-center gap-2">
                    <x-ui.spinner class="size-4" />
                    Sending...
                </span>
            </x-ui.button>
        @endcan
    </div>
</div>
