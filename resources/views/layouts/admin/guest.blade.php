<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-bind:class="$store.theme.isDark ? 'dark' : ''">
<head>
    @include('partials.admin.head', [
        'title' => trim(View::yieldContent('title')),
        'description' => trim(View::yieldContent('description')),
    ])
</head>
<body class="min-h-svh bg-background font-sans antialiased">

    <div class="flex min-h-svh flex-col items-center justify-center gap-6 p-6 md:p-10">

        {{-- Brand mark --}}
        <a href="{{ url('/') }}" class="flex items-center gap-2 self-center font-semibold text-foreground no-underline">
            <div class="flex size-6 items-center justify-center rounded-md bg-primary text-primary-foreground">
                <x-lucide-command class="size-4" aria-hidden="true" />
            </div>
            {{ config('app.name', 'Laravel') }}
        </a>

        {{-- Auth card --}}
        <div class="w-full max-w-sm">
            {{ $slot }}
        </div>

    </div>

    @include('partials.admin.scripts')

</body>
</html>
