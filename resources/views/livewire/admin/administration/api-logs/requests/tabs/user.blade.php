<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

    <x-ui.card>
        <x-ui.card-header class="flex flex-row items-center justify-between">
            <x-ui.card-title class="text-sm">{{ __('api_logs.fields.user') }}</x-ui.card-title>
            @if ($log->user_id)
                <x-ui.button variant="ghost" size="sm" href="{{ route('admin.api-logs.requests.index', ['user_id' => $log->user_id]) }}" wire:navigate>
                    {{ __('api_logs.actions.view_user') }}
                    <x-lucide-arrow-right class="size-3.5" />
                </x-ui.button>
            @endif
        </x-ui.card-header>
        <x-ui.card-content>
            @if (! $log->user_id)
                <p class="text-sm text-muted-foreground">{{ __('api_logs.show.guest') }}</p>
            @elseif (! $user)
                <p class="text-sm text-muted-foreground">#{{ $log->user_id }} — {{ __('api_logs.show.user_missing') }}</p>
            @else
                <div class="flex items-center gap-3">
                    <x-ui.avatar class="size-10">
                        @if ($user->avatarUrl())
                            <x-ui.avatar-image :src="$user->avatarUrl()" :alt="$user->name" />
                        @endif
                        <x-ui.avatar-fallback>{{ \Illuminate\Support\Str::of($user->name)->substr(0, 2)->upper() }}</x-ui.avatar-fallback>
                    </x-ui.avatar>
                    <div class="min-w-0">
                        <p class="truncate font-medium">{{ $user->name }}</p>
                        <p class="truncate text-sm text-muted-foreground">{{ $user->email }}</p>
                    </div>
                    <x-ui.badge variant="secondary" class="ml-auto">{{ __('enums.user_type.'.$user->type->name) }}</x-ui.badge>
                </div>
                @if ($profileUrl)
                    <x-ui.button variant="outline" size="sm" class="mt-4" href="{{ $profileUrl }}" wire:navigate>
                        <x-lucide-user-round class="size-4" />
                        {{ __('api_logs.actions.open_profile') }}
                    </x-ui.button>
                @endif
            @endif
        </x-ui.card-content>
    </x-ui.card>

    <x-ui.card>
        <x-ui.card-header><x-ui.card-title class="text-sm">{{ __('api_logs.fields.client_type') }}</x-ui.card-title></x-ui.card-header>
        <x-ui.card-content>
            <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-muted-foreground">{{ __('api_logs.fields.client_type') }}</dt>
                    <dd class="mt-0.5 text-sm">{{ __('api_logs.client_types.'.($log->client_type ?? 'app')) }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">{{ __('api_logs.fields.token') }}</dt>
                    <dd class="mt-0.5 font-mono text-sm">{{ $log->token_id ? '#'.$log->token_id : '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">{{ __('api_logs.fields.device') }}</dt>
                    <dd class="mt-0.5 text-sm">
                        @if ($device)
                            {{ $device->displayName() }}
                            <span class="block text-xs text-muted-foreground">{{ trim(($device->platform ?? '').' '.($device->app_version ?? '')) ?: '—' }}</span>
                        @else
                            <span class="text-muted-foreground">{{ __('api_logs.show.device_missing') }}</span>
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">{{ __('api_logs.fields.browser_os') }}</dt>
                    <dd class="mt-0.5 text-sm">{{ $agent ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">{{ __('api_logs.fields.ip') }}</dt>
                    <dd class="mt-0.5 flex flex-wrap items-center gap-2 font-mono text-sm">
                        {{ $log->ip ?? '—' }}
                        @if ($log->ip)
                            <a href="{{ route('admin.api-logs.requests.index', ['ip' => $log->ip]) }}" wire:navigate class="font-sans text-xs text-primary hover:underline">{{ __('api_logs.actions.view_ip') }}</a>
                        @endif
                    </dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-xs text-muted-foreground">{{ __('api_logs.fields.user_agent') }}</dt>
                    <dd class="mt-0.5 break-all font-mono text-xs">{{ $log->user_agent ?? '—' }}</dd>
                </div>
            </dl>
        </x-ui.card-content>
    </x-ui.card>

</div>
