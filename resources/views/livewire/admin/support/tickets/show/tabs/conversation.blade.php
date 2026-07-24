{{--
    Conversation tab — ticket thread on the left built with BlatUI Chat components (<x-ui.chat> and <x-ui.chat-message>),
    a reply box below, and a ticket management sidebar on the right.

    Expects: $record, $categoryOptions, $agentOptions, $statusOptions, $priorityOptions
--}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

    {{-- Thread Column --}}
    <div class="space-y-6 lg:col-span-2">

        {{-- BlatUI Chat Component Thread Card --}}
        <x-ui.card class="overflow-hidden">
            <x-ui.card-header class="border-b border-border/50 pb-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                        <x-lucide-messages-square class="size-4.5" />
                    </div>
                    <div>
                        <x-ui.card-title class="text-base">Support Conversation</x-ui.card-title>
                        <x-ui.card-description>Full message history between requester and support staff.</x-ui.card-description>
                    </div>
                </div>
            </x-ui.card-header>

            <x-ui.card-content class="p-4 sm:p-6">
                <x-ui.chat class="max-h-[550px] space-y-4">
                    @forelse ($record->messages as $message)
                        @if ($message->isSystem())
                            <div class="my-2 flex justify-center" wire:key="ticket-message-{{ $message->id }}">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-muted px-3.5 py-1 text-xs font-medium text-muted-foreground border border-border/50">
                                    <x-lucide-zap class="size-3 text-amber-500" />
                                    <span>{{ $message->message }}</span>
                                    <x-ui.local-time :value="$message->created_at" show-diff="true" />
                                </span>
                            </div>
                        @else
                            @php $isStaff = $message->author_type === \App\Enum\TicketMessageAuthorType::Staff; @endphp
                            <x-ui.chat-message
                                wire:key="ticket-message-{{ $message->id }}"
                                role="{{ $isStaff ? 'user' : 'assistant' }}"
                                name="{{ $message->user?->name ?? 'Unknown' }}"
                                avatar="{{ $message->user?->avatarUrl() }}"
                            >
                                <x-slot:time>
                                    <x-ui.local-time :value="$message->created_at" show-diff="true" />
                                </x-slot:time>
                                <div class="whitespace-pre-line leading-relaxed">{{ $message->message }}</div>
                            </x-ui.chat-message>
                        @endif
                    @empty
                        <div class="flex flex-col items-center justify-center py-12 text-center text-muted-foreground">
                            <x-lucide-message-square class="size-8 opacity-30 mb-2" />
                            <p class="text-sm font-medium">No messages in this conversation thread yet.</p>
                        </div>
                    @endforelse
                </x-ui.chat>
            </x-ui.card-content>
        </x-ui.card>

        {{-- Reply Box --}}
        @can('tickets.manage')
            <x-ui.card>
                <x-ui.card-header class="border-b border-border/50 pb-4">
                    <div class="flex items-center gap-3">
                        <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                            <x-lucide-send class="size-4.5" />
                        </div>
                        <div>
                            <x-ui.card-title class="text-base">Reply to Ticket</x-ui.card-title>
                            <x-ui.card-description>Send a message back to the requester.</x-ui.card-description>
                        </div>
                    </div>
                </x-ui.card-header>

                <x-ui.card-content class="pt-6">
                    @if ($record->status === \App\Enum\TicketStatus::Closed)
                        <div class="flex items-center gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-xs font-medium text-amber-900 dark:text-amber-200">
                            <x-lucide-lock class="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
                            <span>This ticket is closed. Reopen it using the top banner action to send a reply.</span>
                        </div>
                    @else
                        <form wire:submit="reply" class="space-y-4">
                            <x-ui.field>
                                <x-ui.textarea id="replyMessage" wire:model="replyMessage" rows="4" placeholder="Type your response to the requester..." class="bg-muted/20 text-xs leading-relaxed" />
                                @error('replyMessage')
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>

                            <div class="flex justify-end">
                                <x-ui.button type="submit" size="sm" wire:loading.attr="disabled" wire:target="reply" class="gap-1.5 shadow-2xs">
                                    <x-lucide-send class="size-3.5" />
                                    <span>Send Reply</span>
                                </x-ui.button>
                            </div>
                        </form>
                    @endif
                </x-ui.card-content>
            </x-ui.card>
        @endcan

    </div>

    {{-- Sidebar Column --}}
    <div class="space-y-6 lg:col-span-1">

        {{-- Requester Card --}}
        <x-ui.card>
            <x-ui.card-header class="border-b border-border/50 pb-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                        <x-lucide-user class="size-4.5" />
                    </div>
                    <div>
                        <x-ui.card-title class="text-base">Requester Account</x-ui.card-title>
                        <x-ui.card-description>User who opened this ticket.</x-ui.card-description>
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
                            <x-ui.avatar-fallback class="text-xs font-bold">
                                {{ strtoupper(substr($record->user->name, 0, 2)) }}
                            </x-ui.avatar-fallback>
                        </x-ui.avatar>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-foreground">{{ $record->user->name }}</p>
                            <p class="truncate text-xs text-muted-foreground">{{ $record->user->email }}</p>
                        </div>
                    </div>
                    @can('users.manage')
                        <x-ui.button variant="outline" size="sm" class="w-full gap-1.5 text-xs shadow-2xs" href="{{ route('admin.users.show', $record->user) }}">
                            <x-lucide-external-link class="size-3.5" />
                            <span>View User Profile</span>
                        </x-ui.button>
                    @endcan
                @else
                    <p class="text-xs text-muted-foreground italic">Requester account no longer exists.</p>
                @endif
            </x-ui.card-content>
        </x-ui.card>

        {{-- Ticket Management Card --}}
        <x-ui.card>
            <x-ui.card-header class="border-b border-border/50 pb-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                        <x-lucide-sliders class="size-4.5" />
                    </div>
                    <div>
                        <x-ui.card-title class="text-base">Ticket Properties</x-ui.card-title>
                        <x-ui.card-description>Status, priority, and agent controls.</x-ui.card-description>
                    </div>
                </div>
            </x-ui.card-header>

            <x-ui.card-content class="space-y-4 pt-6">
                @can('tickets.manage')
                    <x-ui.field>
                        <x-ui.field-label for="ticketStatus" class="text-xs">Ticket Status</x-ui.field-label>
                        <x-ui.select native id="ticketStatus" size="sm" :value="$record->status->value" :options="$statusOptions"
                            wire:change="updateStatus($event.target.value)" />
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="ticketPriority" class="text-xs">Priority Level</x-ui.field-label>
                        <x-ui.select native id="ticketPriority" size="sm" :value="$record->priority->value" :options="$priorityOptions"
                            wire:change="updatePriority($event.target.value)" />
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="ticketCategory" class="text-xs">Category</x-ui.field-label>
                        <x-ui.select native id="ticketCategory" size="sm" :value="(string) $record->category_id" :options="$categoryOptions"
                            placeholder="No category" wire:change="updateCategory($event.target.value)" />
                        <p class="text-[11px] text-muted-foreground mt-0.5">Changing this may trigger auto-assignment.</p>
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="ticketAgent" class="text-xs">Assigned Staff Agent</x-ui.field-label>
                        <x-ui.select native id="ticketAgent" size="sm" :value="(string) $record->assigned_to" :options="$agentOptions"
                            placeholder="Unassigned" wire:change="reassignAgent($event.target.value)" />
                    </x-ui.field>
                @else
                    <dl class="space-y-3 text-sm">
                        <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                            <dt class="text-xs font-medium text-muted-foreground">Category</dt>
                            <dd class="mt-0.5 text-xs font-semibold text-foreground">{{ $record->category?->name ?? 'None' }}</dd>
                        </div>
                        <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                            <dt class="text-xs font-medium text-muted-foreground">Assigned Agent</dt>
                            <dd class="mt-0.5 text-xs font-semibold text-foreground">{{ $record->agent?->name ?? 'Unassigned' }}</dd>
                        </div>
                    </dl>
                @endcan
            </x-ui.card-content>
        </x-ui.card>

    </div>

</div>
