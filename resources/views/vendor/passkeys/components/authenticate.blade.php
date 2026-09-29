<div>
    @include('passkeys::components.partials.authenticateScript')

    <form id="passkey-login-form" method="POST" action="{{ route('passkeys.login') }}">
        @csrf
    </form>

    @if($message = session()->get('authenticatePasskey::message'))
        <x-ui.alert tone="danger" class="mb-4">
            <x-lucide-circle-alert />
            <x-ui.alert-description>{{ $message }}</x-ui.alert-description>
        </x-ui.alert>
    @endif

    <div onclick="authenticateWithPasskey()">
        @if ($slot->isEmpty())
            <div class="underline cursor-pointer">
                {{ __('passkeys::passkeys.authenticate_using_passkey') }}
            </div>
        @else
            {{ $slot }}
        @endif
    </div>
</div>
