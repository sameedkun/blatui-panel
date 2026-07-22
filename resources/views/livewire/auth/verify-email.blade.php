<div>
    <x-ui.card variant="sectioned" class="w-full max-w-sm">
        <x-ui.card-header>
            <x-ui.card-title>Email verified</x-ui.card-title>
            <x-ui.card-description>
                @if ($justVerified)
                    Your email address has been verified.
                @else
                    Your email address was already verified — nothing else to do here.
                @endif
            </x-ui.card-description>
        </x-ui.card-header>
        <x-ui.card-content>
            <x-ui.alert tone="success">
                <x-lucide-badge-check />
                <x-ui.alert-title>{{ $user->email }}</x-ui.alert-title>
                <x-ui.alert-description>This address is confirmed on your account.</x-ui.alert-description>
            </x-ui.alert>
        </x-ui.card-content>
    </x-ui.card>
</div>
