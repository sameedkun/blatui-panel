<div class="flex flex-col gap-6">

    {{-- Page header --}}
    <x-admin.page-header :title="$this->title()" :breadcrumbs="$this->breadcrumbs()" :back="route('admin.feedback.index')" />

    {{-- Hero Feedback Command Banner --}}
    <div class="relative overflow-hidden rounded-xl border border-border bg-card p-6 shadow-sm">
        <div class="pointer-events-none absolute -right-12 -top-12 size-56 rounded-full bg-primary/5 blur-3xl"></div>

        <div class="relative flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-start gap-4">
                <div class="flex size-12 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-gradient-to-br from-primary/15 via-primary/10 to-primary/5 text-primary shadow-xs">
                    <x-lucide-message-square class="size-6" />
                </div>
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2.5">
                        <h1 class="text-xl font-bold tracking-tight text-foreground">{{ $record->subject ?: 'No subject' }}</h1>
                        <x-ui.badge variant="secondary" class="text-xs font-medium">{{ $record->type->label() }}</x-ui.badge>
                        
                        @if ($record->status === \App\Enum\FeedbackStatus::Resolved)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/10 px-2.5 py-0.5 text-xs font-medium text-emerald-600 dark:text-emerald-400 border border-emerald-500/20">
                                <span class="size-1.5 rounded-full bg-emerald-500"></span>
                                {{ $record->status->label() }}
                            </span>
                        @elseif ($record->status === \App\Enum\FeedbackStatus::New)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-500/10 px-2.5 py-0.5 text-xs font-medium text-blue-600 dark:text-blue-400 border border-blue-500/20">
                                <span class="size-1.5 rounded-full bg-blue-500"></span>
                                {{ $record->status->label() }}
                            </span>
                        @elseif ($record->status === \App\Enum\FeedbackStatus::Ignored)
                            <x-ui.badge variant="outline" class="text-xs">{{ $record->status->label() }}</x-ui.badge>
                        @else
                            <x-ui.badge variant="secondary" class="text-xs">{{ $record->status->label() }}</x-ui.badge>
                        @endif
                    </div>
                    <p class="text-xs text-muted-foreground">
                        Submitted <x-ui.local-time :value="$record->created_at" format="MMM D, YYYY [at] h:mm A" />
                        &bull; Feedback #{{ $record->id }}
                    </p>
                </div>
            </div>

            {{-- Header Actions --}}
            <div class="flex shrink-0 flex-wrap items-center gap-2">
                @if ($record->status === \App\Enum\FeedbackStatus::New)
                    <x-ui.button size="sm" wire:click="markAsRead" class="gap-1.5 shadow-2xs">
                        <x-lucide-eye class="size-3.5" />
                        <span>Mark Read</span>
                    </x-ui.button>
                @endif

                @if ($record->status !== \App\Enum\FeedbackStatus::Resolved)
                    <x-ui.button size="sm" variant="outline" wire:click="resolve" class="gap-1.5 shadow-2xs">
                        <x-lucide-check class="size-3.5 text-emerald-500" />
                        <span>Resolve</span>
                    </x-ui.button>
                @endif

                @if ($record->status !== \App\Enum\FeedbackStatus::Ignored)
                    <x-ui.button size="sm" variant="outline" wire:click="ignore" class="gap-1.5 text-muted-foreground shadow-2xs">
                        <x-lucide-eye-off class="size-3.5" />
                        <span>Ignore</span>
                    </x-ui.button>
                @endif

                @if (in_array($record->status, [\App\Enum\FeedbackStatus::Resolved, \App\Enum\FeedbackStatus::Ignored], true))
                    <x-ui.button size="sm" variant="ghost" wire:click="reopen" class="gap-1.5 shadow-2xs">
                        <x-lucide-rotate-ccw class="size-3.5" />
                        <span>Reopen</span>
                    </x-ui.button>
                @endif
            </div>
        </div>
    </div>

    {{-- Layout Grid --}}
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Main column --}}
        <div class="space-y-6 lg:col-span-2">

            {{-- Submission message card --}}
            <x-ui.card>
                <x-ui.card-header class="border-b border-border/50 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                            <x-lucide-message-square class="size-4.5" />
                        </div>
                        <div>
                            <x-ui.card-title class="text-base">User Message</x-ui.card-title>
                            <x-ui.card-description>Full submitted feedback details.</x-ui.card-description>
                        </div>
                    </div>
                </x-ui.card-header>
                <x-ui.card-content class="pt-6">
                    <div class="relative rounded-xl border border-border/70 bg-muted/20 p-5 leading-relaxed text-sm text-foreground space-y-2">
                        <p class="whitespace-pre-line leading-relaxed">{{ $record->message }}</p>
                    </div>
                </x-ui.card-content>
            </x-ui.card>

            {{-- Admin notes card --}}
            <x-ui.card>
                <x-ui.card-header class="border-b border-border/50 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                            <x-lucide-sticky-note class="size-4.5" />
                        </div>
                        <div>
                            <x-ui.card-title class="text-base">Internal Admin Notes</x-ui.card-title>
                            <x-ui.card-description>Private team notes — never visible to the submitter.</x-ui.card-description>
                        </div>
                    </div>
                </x-ui.card-header>
                <x-ui.card-content class="space-y-4 pt-6">
                    <x-ui.field>
                        <x-ui.textarea wire:model="adminNotes" rows="4" placeholder="Add internal notes, resolution details, or staff investigation logs..." class="bg-muted/20 text-xs leading-relaxed" />
                        @error('adminNotes')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>
                    <div class="flex justify-end">
                        <x-ui.button size="sm" wire:click="saveNotes" wire:loading.attr="disabled" wire:target="saveNotes" class="gap-1.5 shadow-2xs">
                            <x-lucide-save class="size-4" />
                            <span>Save Internal Notes</span>
                        </x-ui.button>
                    </div>
                </x-ui.card-content>
            </x-ui.card>

        </div>

        {{-- Sidebar column --}}
        <div class="space-y-6 lg:col-span-1">

            {{-- Submitter Card --}}
            <x-ui.card>
                <x-ui.card-header class="border-b border-border/50 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                            <x-lucide-user class="size-4.5" />
                        </div>
                        <div>
                            <x-ui.card-title class="text-base">Submitter Info</x-ui.card-title>
                            <x-ui.card-description>Account details & origin.</x-ui.card-description>
                        </div>
                    </div>
                </x-ui.card-header>
                <x-ui.card-content class="space-y-4 pt-6">
                    @if ($record->user)
                        <div class="flex items-center gap-3 bg-muted/20 p-3 rounded-lg border border-border/60">
                            <x-ui.avatar class="size-11 shrink-0 rounded-full border border-border">
                                @if ($record->user->avatarUrl())
                                    <x-ui.avatar-image :src="$record->user->avatarUrl()" :alt="$record->user->name" />
                                @endif
                                <x-ui.avatar-fallback class="font-bold text-xs">
                                    {{ strtoupper(substr($record->user->name, 0, 2)) }}
                                </x-ui.avatar-fallback>
                            </x-ui.avatar>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-foreground">{{ $record->user->name }}</p>
                                <p class="truncate text-xs text-muted-foreground">{{ $record->user->email }}</p>
                            </div>
                        </div>
                        @can('users.manage')
                            @if ($record->user->isAppUser())
                                <x-ui.button variant="outline" size="sm" class="w-full gap-1.5 text-xs shadow-2xs" href="{{ route('admin.users.show', $record->user) }}">
                                    <x-lucide-external-link class="size-3.5" />
                                    <span>View User Profile</span>
                                </x-ui.button>
                            @endif
                        @endcan
                        @can('guests.manage')
                            @if ($record->user->isGuest())
                                <x-ui.button variant="outline" size="sm" class="w-full gap-1.5 text-xs shadow-2xs" href="{{ route('admin.guests.show', $record->user) }}">
                                    <x-lucide-external-link class="size-3.5" />
                                    <span>View Guest Profile</span>
                                </x-ui.button>
                            @endif
                        @endcan
                    @else
                        <div class="space-y-3">
                            <x-ui.badge variant="outline" class="gap-1.5 text-xs font-medium">
                                <x-lucide-user-x class="size-3.5 text-muted-foreground" />
                                Anonymous Submission
                            </x-ui.badge>

                            @if ($record->email)
                                <div class="rounded-lg border border-border/60 bg-muted/20 p-3 space-y-0.5">
                                    <p class="text-xs font-medium text-muted-foreground">Provided Email Address</p>
                                    <p class="text-xs font-semibold text-foreground font-mono select-all">{{ $record->email }}</p>
                                </div>

                                @if ($matchingAccount)
                                    <div class="rounded-lg border border-blue-500/30 bg-blue-500/10 p-3.5 space-y-1.5 text-xs text-blue-900 dark:text-blue-200">
                                        <div class="flex items-center gap-1.5 font-semibold text-blue-700 dark:text-blue-300">
                                            <x-lucide-info class="size-4" />
                                            <span>Matching Account Found</span>
                                        </div>
                                        <p>{{ $matchingAccount->name }} ({{ $matchingAccount->email }})</p>
                                        @can('users.manage')
                                            @if ($matchingAccount->isAppUser())
                                                <a href="{{ route('admin.users.show', $matchingAccount) }}" class="inline-flex items-center gap-1 text-primary hover:underline font-medium">
                                                    <span>View User Profile</span>
                                                    <x-lucide-arrow-up-right class="size-3" />
                                                </a>
                                            @endif
                                        @endcan
                                        @can('guests.manage')
                                            @if ($matchingAccount->isGuest())
                                                <a href="{{ route('admin.guests.show', $matchingAccount) }}" class="inline-flex items-center gap-1 text-primary hover:underline font-medium">
                                                    <span>View Guest Profile</span>
                                                    <x-lucide-arrow-up-right class="size-3" />
                                                </a>
                                            @endif
                                        @endcan
                                    </div>
                                @endif
                            @else
                                <p class="text-xs text-muted-foreground italic">No contact email provided.</p>
                            @endif
                        </div>
                    @endif
                </x-ui.card-content>
            </x-ui.card>

            {{-- Quick Actions Card --}}
            <x-ui.card>
                <x-ui.card-header class="border-b border-border/50 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                            <x-lucide-sliders class="size-4.5" />
                        </div>
                        <div>
                            <x-ui.card-title class="text-base">Quick Status Controls</x-ui.card-title>
                            <x-ui.card-description>Update submission state.</x-ui.card-description>
                        </div>
                    </div>
                </x-ui.card-header>
                <x-ui.card-content class="space-y-2.5 pt-6">
                    @if ($record->status === \App\Enum\FeedbackStatus::New)
                        <x-ui.button class="w-full gap-2 shadow-2xs" wire:click="markAsRead">
                            <x-lucide-eye class="size-4" />
                            <span>Mark as Read</span>
                        </x-ui.button>
                    @endif

                    @if ($record->status !== \App\Enum\FeedbackStatus::Resolved)
                        <x-ui.button class="w-full gap-2 shadow-2xs" variant="outline" wire:click="resolve">
                            <x-lucide-check class="size-4 text-emerald-500" />
                            <span>Mark Resolved</span>
                        </x-ui.button>
                    @endif

                    @if ($record->status !== \App\Enum\FeedbackStatus::Ignored)
                        <x-ui.button class="w-full gap-2 text-muted-foreground shadow-2xs" variant="outline" wire:click="ignore">
                            <x-lucide-eye-off class="size-4" />
                            <span>Ignore Feedback</span>
                        </x-ui.button>
                    @endif

                    @if (in_array($record->status, [\App\Enum\FeedbackStatus::Resolved, \App\Enum\FeedbackStatus::Ignored], true))
                        <x-ui.button class="w-full gap-2 shadow-2xs" variant="ghost" wire:click="reopen">
                            <x-lucide-rotate-ccw class="size-4" />
                            <span>Reopen Submission</span>
                        </x-ui.button>
                    @endif
                </x-ui.card-content>
            </x-ui.card>

        </div>

    </div>

</div>
