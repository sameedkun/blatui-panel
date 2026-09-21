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
            <form wire:submit="login" class="flex flex-col gap-6">
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
                <x-ui.button type="submit" class="w-full" wire:loading.attr="disabled" wire:target="login">
                    <span wire:loading.remove wire:target="login" class="inline-flex items-center gap-2">
                        <x-lucide-log-in />
                        Sign in
                    </span>
                    <span wire:loading.flex wire:target="login" class="items-center gap-2">
                        <x-ui.spinner class="size-4" /> Signing in…
                    </span>
                </x-ui.button>
            </form>

            <div class="relative my-4">
                <div class="absolute inset-0 flex items-center">
                    <span class="w-full border-t border-border"></span>
                </div>
                <div class="relative flex justify-center text-xs uppercase">
                    <span class="bg-card px-2 text-muted-foreground">Or</span>
                </div>
            </div>

            <x-authenticate-passkey>
                <x-ui.button type="button" variant="outline" class="w-full">
                    <x-lucide-fingerprint class="size-4" />
                    Sign in with a passkey
                </x-ui.button>
            </x-authenticate-passkey>

            <p id="passkey-login-error" class="mt-2 hidden text-xs text-destructive"></p>
        </x-ui.card-content>
    </x-ui.card>

    <script>
        // The vendor authenticate-passkey Blade component defines window.authenticateWithPasskey
        // with no error handling of its own — a cancelled prompt, an unsupported browser, or a
        // network hiccup all fail as a swallowed promise rejection with no visible feedback.
        // Wrap it once to surface anything other than a plain user-cancellation.
        (function wrapAuthenticateWithPasskey() {
            if (typeof window.authenticateWithPasskey !== 'function' || window.authenticateWithPasskey.__wrapped) {
                return;
            }

            const original = window.authenticateWithPasskey;

            const wrapped = async function (...args) {
                const errorEl = document.getElementById('passkey-login-error');
                errorEl?.classList.add('hidden');

                if (!window.browserSupportsWebAuthn || !browserSupportsWebAuthn()) {
                    if (errorEl) {
                        errorEl.textContent = @js(__('auth.passkey_unsupported_browser'));
                        errorEl.classList.remove('hidden');
                    }

                    return;
                }

                try {
                    await original(...args);
                } catch (e) {
                    if (e && e.name === 'NotAllowedError') {
                        return; // user cancelled the browser/password-manager prompt
                    }

                    if (errorEl) {
                        errorEl.textContent = @js(__('auth.passkey_failed'));
                        errorEl.classList.remove('hidden');
                    }
                }
            };

            wrapped.__wrapped = true;
            window.authenticateWithPasskey = wrapped;
        })();
    </script>
</div>
