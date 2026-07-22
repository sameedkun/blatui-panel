<div>
    @if (session('status'))
        <x-ui.alert tone="success" class="mb-4">
            <x-lucide-circle-check />
            <x-ui.alert-description>{{ session('status') }}</x-ui.alert-description>
        </x-ui.alert>
    @endif
    <x-ui.card variant="sectioned" class="w-full max-w-sm">
        <x-ui.card-header>
            <x-ui.card-title>Sign in</x-ui.card-title>
            <x-ui.card-description>Enter your email below to login to your account.</x-ui.card-description>
        </x-ui.card-header>
        <x-ui.card-content>
            <form class="flex flex-col gap-6">
                <x-ui.field>
                    <x-ui.field-label for="card-login-email">Email</x-ui.field-label>
                    <x-ui.input id="card-login-email" type="email" placeholder="m@example.com" wire:model="email"
                        aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" />
                    @error('email')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>
                <x-ui.field>
                    <div class="flex items-center">
                        <x-ui.field-label for="card-login-password">Password</x-ui.field-label>
                    </div>
                    <x-ui.input id="card-login-password" type="password" wire:model="password"
                        aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" />
                    @error('password')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>
                <div class="flex items-center gap-1">
                    <x-ui.checkbox id="terms" wire:model="remember" />
                    <x-ui.label for="terms">Remember me</x-ui.label>
                </div>
            </form>
        </x-ui.card-content>
        <x-ui.card-footer class="flex-col gap-2">
            <x-ui.button type="submit" class="w-full" wire:click="login" wire:loading.attr="disabled" wire:target="login">
                <span wire:loading.remove wire:target="login" class="inline-flex items-center gap-2">
                    <x-lucide-log-in />
                    Sign in
                </span>
                <span wire:loading.flex wire:target="login" class="items-center gap-2">
                    <x-ui.spinner class="size-4" /> Signing in…
                </span>
            </x-ui.button>
        </x-ui.card-footer>
    </x-ui.card>
</div>
