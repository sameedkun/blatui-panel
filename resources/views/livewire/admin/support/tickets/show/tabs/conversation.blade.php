{{--
    Conversation tab — ticket thread on the left built with BlatUI Chat components (<x-ui.chat> and <x-ui.chat-message>),
    media image previews & file cards, reply box below, and ticket management sidebar on the right.

    Expects: $record, $categoryOptions, $agentOptions, $statusOptions, $priorityOptions
--}}
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3" x-data="{ previewUrl: null, previewTitle: null }">

    {{-- Thread Column --}}
    <div class="space-y-6 lg:col-span-2">

        {{-- BlatUI Chat Component Thread Card --}}
        <x-ui.card
            class="overflow-hidden scroll-mt-6"
            x-ref="threadCard"
            @scroll-to-latest-message.window="
                $refs.threadCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
                $nextTick(() => $refs.chatBox.scrollTo({ top: $refs.chatBox.scrollHeight, behavior: 'smooth' }));
            "
        >
            <x-ui.card-header class="border-b border-border/50 pb-4">
                <div class="flex items-center gap-3">
                    <div class="flex size-9 shrink-0 items-center justify-center rounded-lg border border-primary/20 bg-primary/10 text-primary">
                        <x-lucide-messages-square class="size-4.5" />
                    </div>
                    <div>
                        <x-ui.card-title class="text-base">{{ __('tickets.conversation.title') }}</x-ui.card-title>
                        <x-ui.card-description>{{ __('tickets.conversation.description') }}</x-ui.card-description>
                    </div>
                </div>
            </x-ui.card-header>

            <x-ui.card-content class="p-4 sm:p-6">
                <x-ui.chat x-ref="chatBox" class="max-h-[580px] space-y-4">
                    @forelse ($record->messages as $message)
                        @if ($message->isSystem())
                            <div class="my-2 flex justify-center" wire:key="ticket-message-{{ $message->id }}">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-muted/80 px-3.5 py-1 text-xs font-medium text-muted-foreground border border-border/60 shadow-2xs">
                                    <x-lucide-zap class="size-3 text-amber-500" />
                                    <span>{{ $message->message }}</span>
                                    <x-ui.local-time :value="$message->created_at" show-diff="true" />
                                </span>
                            </div>
                        @else
                            @php
                                $isStaff = $message->author_type === \App\Enum\TicketMessageAuthorType::Staff;
                                $attachments = $message->attachmentsWithUrls();
                                $images = [];
                                $files = [];
                                foreach ($attachments as $att) {
                                    $ext = strtolower(pathinfo($att['name'] ?? '', PATHINFO_EXTENSION));
                                    $isImg = str_starts_with($att['mime'] ?? '', 'image/') || in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif']);
                                    if ($isImg) {
                                        $images[] = $att;
                                    } else {
                                        $files[] = $att;
                                    }
                                }
                            @endphp

                            <x-ui.chat-message
                                wire:key="ticket-message-{{ $message->id }}"
                                role="{{ $isStaff ? 'user' : 'assistant' }}"
                                name="{{ $message->user?->name ?? __('tickets.common.unknown') }}"
                                avatar="{{ $message->user?->avatarUrl() }}"
                                bubbleClass="{{ $isStaff ? 'bg-primary text-primary-foreground font-medium rounded-ee-sm border border-primary/20' : 'bg-muted/90 text-foreground rounded-es-sm border border-border/60' }}"
                            >
                                <x-slot:time>
                                    <x-ui.local-time :value="$message->created_at" show-diff="true" />
                                </x-slot:time>

                                <div class="whitespace-pre-line leading-relaxed">{{ $message->message }}</div>

                                {{-- Attachments Container --}}
                                @if (! empty($attachments))
                                    <div class="mt-2 space-y-2 border-t border-current/15 pt-2 w-fit max-w-full">

                                        {{-- Image Media Previews --}}
                                        @if (! empty($images))
                                            @php $imgCount = count($images); @endphp
                                            @if ($imgCount === 1)
                                                @php $img = $images[0]; @endphp
                                                <div class="mt-2 w-44 sm:w-48 max-w-full">
                                                    <div class="group relative overflow-hidden rounded-xl border border-black/20 dark:border-white/20 bg-card shadow-2xs transition-all hover:border-primary/40 w-full">
                                                        @if ($img['url'])
                                                            <button
                                                                type="button"
                                                                @click="previewUrl = '{{ $img['url'] }}'; previewTitle = '{{ e($img['name']) }}'"
                                                                class="block w-full overflow-hidden text-left cursor-pointer"
                                                            >
                                                                <img
                                                                    src="{{ $img['url'] }}"
                                                                    alt="{{ $img['name'] }}"
                                                                    class="block h-32 sm:h-36 w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                                    loading="lazy"
                                                                />
                                                            </button>
                                                        @else
                                                            <div class="flex h-24 items-center justify-center text-xs text-muted-foreground px-3">{{ __('tickets.conversation.image_unavailable') }}</div>
                                                        @endif

                                                        <div class="flex items-center justify-between gap-1 border-t border-black/10 dark:border-white/10 bg-card px-2 py-1.5 w-full">
                                                            <span class="truncate text-[9px] font-medium text-foreground flex-1 min-w-0" title="{{ $img['name'] }}">{{ $img['name'] }}</span>
                                                            @if ($img['url'])
                                                                <a
                                                                    href="{{ $img['url'] }}"
                                                                    target="_blank"
                                                                    rel="noopener"
                                                                    title="{{ __('tickets.conversation.open_full_size') }}"
                                                                    class="inline-flex shrink-0 items-center gap-0.5 font-mono text-[9px] text-muted-foreground hover:text-primary transition-colors"
                                                                >
                                                                    <span>{{ number_format($img['size'] / 1024, 1) }} KB</span>
                                                                    <x-lucide-external-link class="size-2.5" />
                                                                </a>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @else
                                                @php
                                                    $colsClass = match ($imgCount) {
                                                        2 => 'grid-cols-2 sm:max-w-xs',
                                                        3 => 'grid-cols-3 sm:max-w-md',
                                                        4 => 'grid-cols-2 sm:max-w-xs',
                                                        default => 'grid-cols-3 sm:max-w-md',
                                                    };
                                                @endphp
                                                <div class="mt-2 grid {{ $colsClass }} gap-1.5 w-fit max-w-full">
                                                    @foreach ($images as $img)
                                                        <div class="group relative overflow-hidden rounded-xl border border-black/20 dark:border-white/20 bg-card shadow-2xs transition-all hover:border-primary/40 min-w-0">
                                                            @if ($img['url'])
                                                                <button
                                                                    type="button"
                                                                    @click="previewUrl = '{{ $img['url'] }}'; previewTitle = '{{ e($img['name']) }}'"
                                                                    class="block w-full overflow-hidden text-left cursor-pointer"
                                                                >
                                                                    <img
                                                                        src="{{ $img['url'] }}"
                                                                        alt="{{ $img['name'] }}"
                                                                        class="block h-24 sm:h-28 w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                                        loading="lazy"
                                                                    />
                                                                </button>
                                                            @else
                                                                <div class="flex h-20 items-center justify-center text-xs text-muted-foreground">{{ __('tickets.conversation.image_unavailable') }}</div>
                                                            @endif

                                                            <div class="flex items-center justify-between gap-1 border-t border-black/10 dark:border-white/10 bg-card px-1.5 py-1 w-full">
                                                                <span class="truncate text-[9px] font-medium text-foreground flex-1 min-w-0" title="{{ $img['name'] }}">{{ $img['name'] }}</span>
                                                                @if ($img['url'])
                                                                    <a
                                                                        href="{{ $img['url'] }}"
                                                                        target="_blank"
                                                                        rel="noopener"
                                                                        title="{{ __('tickets.conversation.open_full_size') }}"
                                                                        class="inline-flex shrink-0 items-center gap-0.5 font-mono text-[8px] text-muted-foreground hover:text-primary transition-colors"
                                                                    >
                                                                        <span>{{ number_format($img['size'] / 1024, 1) }} KB</span>
                                                                        <x-lucide-external-link class="size-2" />
                                                                    </a>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                </div>
                                            @endif
                                        @endif

                                        {{-- Non-Image Document File Cards --}}
                                        @if (! empty($files))
                                            <div class="space-y-1 w-fit max-w-full sm:max-w-[240px]">
                                                @foreach ($files as $file)
                                                    @php $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION)); @endphp
                                                    <a
                                                        href="{{ $file['url'] ?? '#' }}"
                                                        target="_blank"
                                                        rel="noopener"
                                                        class="flex items-center gap-2.5 rounded-lg border border-black/15 dark:border-white/15 bg-card p-2 text-xs transition-all hover:bg-card hover:border-primary/50 shadow-2xs group max-w-full {{ ! $file['url'] ? 'pointer-events-none opacity-50' : '' }}"
                                                    >
                                                        <div class="flex size-7 shrink-0 items-center justify-center rounded-md border border-primary/20 bg-primary/10 text-primary">
                                                            @if ($ext === 'pdf')
                                                                <x-lucide-file-text class="size-3.5" />
                                                            @elseif (in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz']))
                                                                <x-lucide-file-archive class="size-3.5" />
                                                            @elseif (in_array($ext, ['json', 'php', 'js', 'html', 'css', 'ts']))
                                                                <x-lucide-file-code class="size-3.5" />
                                                            @else
                                                                <x-lucide-file class="size-3.5" />
                                                            @endif
                                                        </div>

                                                        <div class="min-w-0 flex-1 space-y-0.5">
                                                            <p class="truncate font-semibold text-foreground text-[11px] group-hover:text-primary transition-colors">{{ $file['name'] }}</p>
                                                            <p class="font-mono text-[9px] text-muted-foreground">{{ number_format($file['size'] / 1024, 1) }} KB</p>
                                                        </div>

                                                        <div class="flex size-5.5 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground group-hover:bg-primary/10 group-hover:text-primary transition-colors">
                                                            <x-lucide-external-link class="size-2.5" />
                                                        </div>
                                                    </a>
                                                @endforeach
                                            </div>
                                        @endif

                                    </div>
                                @endif
                            </x-ui.chat-message>
                        @endif
                    @empty
                        <div class="flex flex-col items-center justify-center py-12 text-center text-muted-foreground">
                            <x-lucide-message-square class="size-8 opacity-30 mb-2" />
                            <p class="text-sm font-medium">{{ __('tickets.empty.messages') }}</p>
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
                            <x-ui.card-title class="text-base">{{ __('tickets.conversation.reply_title') }}</x-ui.card-title>
                            <x-ui.card-description>{{ __('tickets.conversation.reply_description') }}</x-ui.card-description>
                        </div>
                    </div>
                </x-ui.card-header>

                <x-ui.card-content class="pt-6">
                    @if ($record->status === \App\Enum\TicketStatus::Closed)
                        <div class="flex items-center gap-3 rounded-xl border border-amber-500/30 bg-amber-500/10 p-4 text-xs font-medium text-amber-900 dark:text-amber-200">
                            <x-lucide-lock class="size-4 text-amber-600 dark:text-amber-400 shrink-0" />
                            <span>{{ __('tickets.conversation.closed_notice') }}</span>
                        </div>
                    @else
                        <form wire:submit="reply" class="space-y-4">
                            <x-ui.field>
                                <x-ui.textarea id="replyMessage" wire:model="replyMessage" rows="4" :placeholder="__('tickets.conversation.reply_placeholder')" class="bg-muted/20 text-xs leading-relaxed" />
                                @error('replyMessage')
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>

                            <x-ui.field>
                                <x-ui.field-label class="text-xs font-medium">{{ __('tickets.conversation.upload_attachments') }}</x-ui.field-label>
                                <x-ui.file-upload
                                    wire:key="reply-attachments-{{ $replyAttachmentsKey }}"
                                    name="replyAttachments"
                                    multiple
                                    :max-size-label="__('tickets.conversation.attachment_limit')"
                                    wire:model="replyAttachments"
                                />
                                @error('replyAttachments')
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                                @error('replyAttachments.*')
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>

                            <div class="flex justify-end">
                                <x-ui.button type="submit" size="sm" wire:loading.attr="disabled" wire:target="reply,replyAttachments" class="gap-1.5 shadow-2xs">
                                    <x-lucide-send class="size-3.5" />
                                    <span>{{ __('tickets.actions.reply') }}</span>
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
                        <x-ui.card-title class="text-base">{{ __('tickets.conversation.requester_title') }}</x-ui.card-title>
                        <x-ui.card-description>{{ __('tickets.conversation.requester_description') }}</x-ui.card-description>
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
                            <span>{{ __('tickets.actions.view_profile') }}</span>
                        </x-ui.button>
                    @endcan
                @else
                    <p class="text-xs text-muted-foreground italic">{{ __('tickets.conversation.requester_missing') }}</p>
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
                        <x-ui.card-title class="text-base">{{ __('tickets.conversation.properties_title') }}</x-ui.card-title>
                        <x-ui.card-description>{{ __('tickets.conversation.properties_description') }}</x-ui.card-description>
                    </div>
                </div>
            </x-ui.card-header>

            <x-ui.card-content class="space-y-4 pt-6">
                @can('tickets.manage')
                    <x-ui.field>
                        <x-ui.field-label for="ticketStatus" class="text-xs">{{ __('tickets.conversation.ticket_status') }}</x-ui.field-label>
                        <x-ui.select native id="ticketStatus" size="sm" :value="$record->status->value" :options="$statusOptions"
                            wire:change="updateStatus($event.target.value)" />
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="ticketPriority" class="text-xs">{{ __('tickets.conversation.priority_level') }}</x-ui.field-label>
                        <x-ui.select native id="ticketPriority" size="sm" :value="$record->priority->value" :options="$priorityOptions"
                            wire:change="updatePriority($event.target.value)" />
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="ticketCategory" class="text-xs">{{ __('tickets.fields.category') }}</x-ui.field-label>
                        <x-ui.select native id="ticketCategory" size="sm" :value="(string) $record->category_id" :options="$categoryOptions"
                            :placeholder="__('tickets.form.no_category')" wire:change="updateCategory($event.target.value)" />
                        <p class="text-[11px] text-muted-foreground mt-0.5">{{ __('tickets.conversation.category_change_hint') }}</p>
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="ticketAgent" class="text-xs">{{ __('tickets.conversation.assigned_staff') }}</x-ui.field-label>
                        <x-ui.select native id="ticketAgent" size="sm" :value="(string) $record->assigned_to" :options="$agentOptions"
                            :placeholder="__('tickets.unassigned')" wire:change="reassignAgent($event.target.value)" />
                    </x-ui.field>
                @else
                    <dl class="space-y-3 text-sm">
                        <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                            <dt class="text-xs font-medium text-muted-foreground">{{ __('tickets.fields.category') }}</dt>
                            <dd class="mt-0.5 text-xs font-semibold text-foreground">{{ $record->category?->name ?? __('tickets.common.none') }}</dd>
                        </div>
                        <div class="rounded-lg border border-border/60 bg-muted/20 p-3">
                            <dt class="text-xs font-medium text-muted-foreground">{{ __('tickets.fields.assigned_to') }}</dt>
                            <dd class="mt-0.5 text-xs font-semibold text-foreground">{{ $record->agent?->name ?? __('tickets.unassigned') }}</dd>
                        </div>
                    </dl>
                @endcan
            </x-ui.card-content>
        </x-ui.card>

    </div>

    {{-- Alpine Lightbox Image Preview Modal --}}
    <div
        x-show="previewUrl"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4 backdrop-blur-xs"
        @click.self="previewUrl = null"
        @keydown.escape.window="previewUrl = null"
    >
        <div class="relative max-w-4xl max-h-[90vh] overflow-hidden rounded-2xl border border-white/20 bg-card p-3 shadow-2xl space-y-3">
            <div class="flex items-center justify-between border-b border-border/50 pb-2 px-2">
                <span class="text-xs font-semibold text-foreground truncate max-w-md" x-text="previewTitle"></span>
                <div class="flex items-center gap-3">
                    <a :href="previewUrl" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
                        <span>{{ __('tickets.conversation.open_new_tab') }}</span>
                        <x-lucide-external-link class="size-3.5" />
                    </a>
                    <button type="button" @click="previewUrl = null" class="rounded-lg p-1 text-muted-foreground hover:text-foreground hover:bg-muted">
                        <x-lucide-x class="size-5" />
                    </button>
                </div>
            </div>
            <div class="flex items-center justify-center overflow-auto max-h-[78vh] p-1">
                <img :src="previewUrl" :alt="previewTitle" class="max-h-[75vh] w-auto max-w-full rounded-xl object-contain" />
            </div>
        </div>
    </div>

</div>
