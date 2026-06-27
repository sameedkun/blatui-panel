<div>
    <x-ui.card variant="sectioned" class="w-full max-w-sm">
        <x-ui.card-header>
            <x-ui.card-title>Sign in</x-ui.card-title>
            <x-ui.card-description>Enter your email below to login to your account.</x-ui.card-description>
        </x-ui.card-header>
        <x-ui.card-content>
            <form class="flex flex-col gap-6">
                <x-ui.field>
                    <x-ui.field-label for="card-login-email">Email</x-ui.field-label>
                    <x-ui.input id="card-login-email" type="email" placeholder="m@example.com" />
                </x-ui.field>
                <x-ui.field>
                    <div class="flex items-center">
                        <x-ui.field-label for="card-login-password">Password</x-ui.field-label>
                    </div>
                    <x-ui.input id="card-login-password" type="password" />
                </x-ui.field>
            </form>
        </x-ui.card-content>
        <x-ui.card-footer class="flex-col gap-2">
            <x-ui.button type="submit" class="w-full">
                <x-lucide-log-in />
                Sign in
            </x-ui.button>
        </x-ui.card-footer>
    </x-ui.card>
</div>
