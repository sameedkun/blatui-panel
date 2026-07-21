@php
    use Illuminate\Support\Str;
@endphp

<div class="space-y-6">
    <div>
        <h3 class="text-lg font-medium">Policies & Rules</h3>
        <p class="text-sm text-muted-foreground">Configure global legal terms and data retention policies.</p>
    </div>
    <x-ui.separator />

    <form wire:submit="save" class="space-y-6 max-w-3xl">
        <x-ui.tabs value="{{ $policyTypes[0]->value }}">
            <x-ui.tabs-list class="mb-4">
                @foreach ($policyTypes as $type)
                    <x-ui.tabs-trigger value="{{ $type->value }}">{{ $type->label() }}</x-ui.tabs-trigger>
                @endforeach
                <x-ui.tabs-trigger value="deletion">Data Retention</x-ui.tabs-trigger>
            </x-ui.tabs-list>

            @foreach ($policyTypes as $type)
                @php
                    $titleField = "policies.{$type->value}.title";
                    $versionField = "policies.{$type->value}.version";
                    $contentField = "policies.{$type->value}.content";
                @endphp
                <x-ui.tabs-content value="{{ $type->value }}" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="md:col-span-3">
                            <x-ui.field>
                                <x-ui.field-label for="{{ $titleField }}" required>Document Title</x-ui.field-label>
                                <x-ui.input id="{{ $titleField }}" wire:model="{{ $titleField }}" />
                                @error($titleField)
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>
                        </div>
                        <div>
                            <x-ui.field>
                                <x-ui.field-label for="{{ $versionField }}" required>Version</x-ui.field-label>
                                <x-ui.input id="{{ $versionField }}" wire:model="{{ $versionField }}" placeholder="1.0" />
                                @error($versionField)
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>
                        </div>
                    </div>

                    <x-ui.field>
                        <x-ui.field-label for="{{ $contentField }}" required>Content</x-ui.field-label>
                        <x-ui.rich-text-editor id="{{ $contentField }}" wire:model="{{ $contentField }}" :value="$policies[$type->value]['content']" placeholder="Write the {{ Str::lower($type->label()) }}…" />
                        @error($contentField)
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                        <x-ui.field-description>Content for the {{ Str::lower($type->label()) }}.</x-ui.field-description>
                    </x-ui.field>

                    @can('settings.policies.edit')
                        <div class="flex justify-end">
                            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                                <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                                    <x-lucide-save class="size-4" />
                                    Save Policies Settings
                                </span>
                                <span wire:loading.flex wire:target="save" class="items-center gap-2">
                                    <x-ui.spinner class="size-4" />
                                    Saving...
                                </span>
                            </x-ui.button>
                        </div>
                    @endcan

                    {{-- ── Version history ──────────────────────────────────────── --}}
                    <div class="space-y-2 border-t border-border pt-4">
                        <h4 class="text-sm font-semibold">Version History</h4>
                        @forelse ($policyVersions[$type->value] as $version)
                            <div wire:key="policy-version-{{ $version->id }}"
                                class="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">v{{ $version->version }}</span>
                                    @if ($version->is_active)
                                        <x-ui.badge variant="outline">Current</x-ui.badge>
                                    @endif
                                    <span class="text-muted-foreground">{{ $version->published_at?->format('M j, Y g:i A') }}</span>
                                </div>
                                <x-ui.button variant="ghost" size="sm" wire:click="viewVersion({{ $version->id }})">
                                    <x-lucide-eye class="size-4" />
                                    View
                                </x-ui.button>
                            </div>
                        @empty
                            <p class="text-sm text-muted-foreground">No published versions yet.</p>
                        @endforelse
                    </div>
                </x-ui.tabs-content>
            @endforeach

            <!-- Account Deletion / Data Retention Tab -->
            <x-ui.tabs-content value="deletion" class="space-y-4">
                <x-ui.field>
                    <x-ui.field-label for="deletion_grace" required>Account Deletion Grace Period</x-ui.field-label>
                    <div class="flex items-center gap-2">
                        <x-ui.input id="deletion_grace" wire:model="deletion_grace_hours" class="w-24" />
                        <span class="text-sm text-muted-foreground">Hours</span>
                    </div>
                    @error('deletion_grace_hours')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                    <x-ui.field-description>Grace period for app users before permanent account deletion sweep.</x-ui.field-description>
                </x-ui.field>

                @can('settings.policies.edit')
                    <div class="flex justify-end pt-2">
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                                <x-lucide-save class="size-4" />
                                Save Policies Settings
                            </span>
                            <span wire:loading.flex wire:target="save" class="items-center gap-2">
                                <x-ui.spinner class="size-4" />
                                Saving...
                            </span>
                        </x-ui.button>
                    </div>
                @endcan
            </x-ui.tabs-content>
        </x-ui.tabs>
    </form>

    {{-- ── Read-only version viewer ──────────────────────────────────────── --}}
    <x-ui.dialog id="policy-version">
        <x-ui.dialog-content class="flex max-h-[85vh] flex-col overflow-hidden sm:max-w-2xl">
            <x-ui.dialog-header>
                <x-ui.dialog-title>{{ $viewingVersion?->policy?->title }} — v{{ $viewingVersion?->version }}</x-ui.dialog-title>
                <x-ui.dialog-description>
                    Published {{ $viewingVersion?->published_at?->toDayDateTimeString() ?? '—' }}
                    @if ($viewingVersion?->is_active)
                        · <span class="font-medium text-foreground">Current version</span>
                    @endif
                </x-ui.dialog-description>
            </x-ui.dialog-header>

            <div class="min-h-0 flex-1 overflow-y-auto rounded-md border border-border px-4 py-3 text-sm leading-7 [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_h1]:mb-2 [&_h1]:text-2xl [&_h1]:font-semibold [&_h2]:mb-2 [&_h2]:text-xl [&_h2]:font-semibold [&_li]:mt-1 [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:ps-6 [&_p]:my-2 [&_ul]:my-2 [&_ul]:list-disc [&_ul]:ps-6">
                {!! $viewingVersion?->content !!}
            </div>

            <x-ui.dialog-footer>
                <x-ui.button variant="outline" @click="open = false">Close</x-ui.button>
            </x-ui.dialog-footer>
        </x-ui.dialog-content>
    </x-ui.dialog>
</div>
