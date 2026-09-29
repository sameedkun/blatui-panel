@php
    /** @var array<string, list<\App\Support\Dashboard\Blocks\Block>> $overview */
    $name = \Illuminate\Support\Str::before(trim((string) auth()->user()->name), ' ') ?: auth()->user()->name;
    $greetings = [
        'morning' => __('dashboard.overview.greetings.morning', ['name' => $name]),
        'afternoon' => __('dashboard.overview.greetings.afternoon', ['name' => $name]),
        'evening' => __('dashboard.overview.greetings.evening', ['name' => $name]),
    ];
    $hour = now()->hour;
    $serverGreeting = $greetings[$hour < 12 ? 'morning' : ($hour < 18 ? 'afternoon' : 'evening')];

    // Literal class strings (not interpolated) so Tailwind's scanner sees them.
    $kpiGrid = match (count($overview['kpis'])) {
        1 => 'grid-cols-1',
        2 => 'grid-cols-1 sm:grid-cols-2',
        3 => 'grid-cols-1 sm:grid-cols-3',
        4 => 'grid-cols-1 sm:grid-cols-2 xl:grid-cols-4',
        default => 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5',
    };
    $hasActivity = count($overview['activity']) > 0;
    $hasActions = count($overview['actions']) > 0;
    $isEmpty = collect($overview)->flatten()->isEmpty();
@endphp

<div class="flex flex-col gap-6">

    <x-admin.dashboard.header :description="__('dashboard.overview.subtitle')"
        :breadcrumbs="[['label' => __('navigation.home'), 'url' => route('admin.dashboard')], ['label' => __('dashboard.overview.breadcrumb')]]">
        {{-- Greeting follows the viewer's own clock; the server's is only the no-JS fallback. --}}
        <span x-data="{
            greeting: @js($serverGreeting),
            init() {
                const hour = new Date().getHours();
                const greetings = @js($greetings);
                this.greeting = hour < 12 ? greetings.morning : (hour < 18 ? greetings.afternoon : greetings.evening);
            },
        }" x-text="greeting">{{ $serverGreeting }}</span>

        <x-slot:actions>
            @include('livewire.admin.dashboard.partials.range-controls')
        </x-slot:actions>
    </x-admin.dashboard.header>

    @if ($isEmpty)
        <x-ui.empty class="border">
            <x-ui.empty-header>
                <x-ui.empty-media variant="icon"><x-lucide-layout-dashboard /></x-ui.empty-media>
                <x-ui.empty-title>{{ __('dashboard.overview.empty_title') }}</x-ui.empty-title>
                <x-ui.empty-description>{{ __('dashboard.overview.empty_description') }}</x-ui.empty-description>
            </x-ui.empty-header>
        </x-ui.empty>
    @endif

    <div class="flex flex-col gap-6 transition-opacity" wire:loading.class="pointer-events-none opacity-60" wire:target="selectRange,refresh">

        {{-- 1. Headline KPIs — the most important numbers on the page --}}
        @if (count($overview['kpis']))
            <section class="grid gap-4 {{ $kpiGrid }}" aria-label="{{ __('dashboard.overview.sections.kpis') }}">
                @foreach ($overview['kpis'] as $block)
                    @include($block->view(), ['block' => $block, 'prominent' => true])
                @endforeach
            </section>
        @endif

        {{-- 2. The two main trends --}}
        @if (count($overview['trends']))
            <section class="grid grid-cols-1 gap-4 {{ count($overview['trends']) > 1 ? 'xl:grid-cols-2' : '' }}" aria-label="{{ __('dashboard.overview.sections.trends') }}">
                @foreach ($overview['trends'] as $block)
                    @include($block->view(), ['block' => $block])
                @endforeach
            </section>
        @endif

        {{-- 3. Business status --}}
        @if (count($overview['status']))
            <section class="flex flex-col gap-3" aria-labelledby="overview-status-heading">
                <h2 id="overview-status-heading" class="text-sm font-medium text-muted-foreground">{{ __('dashboard.overview.sections.status') }}</h2>
                <div class="grid grid-cols-1 gap-4 {{ count($overview['status']) > 1 ? 'lg:grid-cols-2' : '' }}">
                    @foreach ($overview['status'] as $block)
                        @include($block->view(), ['block' => $block])
                    @endforeach
                </div>
            </section>
        @endif

        {{-- 4. Activity + quick actions --}}
        @if ($hasActivity || $hasActions)
            <section class="grid grid-cols-1 gap-4 {{ $hasActivity && $hasActions ? 'xl:grid-cols-3' : '' }}" aria-label="{{ __('dashboard.overview.sections.activity') }}">
                @if ($hasActivity)
                    <div class="flex min-w-0 flex-col gap-4 {{ $hasActions ? 'xl:col-span-2' : '' }}">
                        @foreach ($overview['activity'] as $block)
                            @include($block->view(), ['block' => $block])
                        @endforeach
                    </div>
                @endif
                @if ($hasActions)
                    <div class="flex min-w-0 flex-col gap-4">
                        @foreach ($overview['actions'] as $block)
                            @include($block->view(), ['block' => $block])
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        {{-- 5. Health snapshot — least prominent, one glance --}}
        @if (count($overview['health']))
            <section aria-labelledby="overview-health-heading">
                <x-ui.card class="flex flex-col gap-4 p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h2 id="overview-health-heading" class="flex items-center gap-2 text-sm font-semibold text-foreground">
                                <x-lucide-heart-pulse class="size-4 text-muted-foreground" />
                                {{ __('dashboard.health.title') }}
                            </h2>
                            <p class="mt-1 text-xs text-muted-foreground">{{ __('dashboard.health.hint') }}</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-5">
                        @foreach ($overview['health'] as $block)
                            @include($block->view(), ['block' => $block])
                        @endforeach
                    </div>
                </x-ui.card>
            </section>
        @endif
    </div>
</div>
