@php
    use Illuminate\Support\Str;
@endphp

<div class="space-y-6">
    <div>
        <h3 class="text-lg font-medium">{{ __('settings.pages.policies_title') }}</h3>
        <p class="text-sm text-muted-foreground">{{ __('settings.policies.description') }}</p>
    </div>
    <x-ui.separator />

    <form wire:submit="save" class="max-w-3xl space-y-6">
        <x-ui.tabs value="{{ $policyTypes[0]->value }}">
            <x-ui.tabs-list class="mb-4">
                @foreach ($policyTypes as $type)
                    <x-ui.tabs-trigger value="{{ $type->value }}">{{ $type->label() }}</x-ui.tabs-trigger>
                @endforeach
                <x-ui.tabs-trigger value="deletion">{{ __('settings.policies.data_retention') }}</x-ui.tabs-trigger>
            </x-ui.tabs-list>

            @foreach ($policyTypes as $type)
                @php
                    $titleField = "policies.{$type->value}.title";
                    $versionField = "policies.{$type->value}.version";
                    $contentField = "policies.{$type->value}.content";
                    $policyLabel = Str::lower($type->label());
                @endphp
                <x-ui.tabs-content value="{{ $type->value }}" class="space-y-4">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-4">
                        <div class="md:col-span-3">
                            <x-ui.field>
                                <x-ui.field-label for="{{ $titleField }}" required>{{ __('settings.policies.document_title') }}</x-ui.field-label>
                                <x-ui.input id="{{ $titleField }}" wire:model="{{ $titleField }}" />
                                @error($titleField)
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>
                        </div>
                        <div>
                            <x-ui.field>
                                <x-ui.field-label for="{{ $versionField }}" required>{{ __('settings.policies.version') }}</x-ui.field-label>
                                <x-ui.input id="{{ $versionField }}" wire:model="{{ $versionField }}" placeholder="1.0" />
                                @error($versionField)
                                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                                @enderror
                            </x-ui.field>
                        </div>
                    </div>

                    <x-ui.field>
                        <x-ui.field-label for="{{ $contentField }}" required>{{ __('settings.policies.content') }}</x-ui.field-label>
                        <x-ui.rich-text-editor id="{{ $contentField }}" wire:model="{{ $contentField }}"
                            :value="$policies[$type->value]['content']"
                            :placeholder="__('settings.policies.content_placeholder', ['policy' => $policyLabel])" />
                        @error($contentField)
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                        <x-ui.field-description>{{ __('settings.policies.content_description', ['policy' => $policyLabel]) }}</x-ui.field-description>
                    </x-ui.field>

                    @can('settings.policies.edit')
                        <div class="flex justify-end">
                            <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                                <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                                    <x-lucide-save class="size-4" />
                                    {{ __('settings.actions.save_policies') }}
                                </span>
                                <span wire:loading.flex wire:target="save" class="items-center gap-2">
                                    <x-ui.spinner class="size-4" />
                                    {{ __('settings.actions.saving') }}
                                </span>
                            </x-ui.button>
                        </div>
                    @endcan

                    <div class="space-y-2 border-t border-border pt-4">
                        <h4 class="text-sm font-semibold">{{ __('settings.policies.version_history') }}</h4>
                        @forelse ($policyVersions[$type->value] as $version)
                            <div wire:key="policy-version-{{ $version->id }}"
                                class="flex items-center justify-between rounded-md border border-border px-3 py-2 text-sm">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium">v{{ $version->version }}</span>
                                    @if ($version->is_active)
                                        <x-ui.badge variant="outline">{{ __('settings.policies.current') }}</x-ui.badge>
                                    @endif
                                    <span class="text-muted-foreground"><x-ui.local-time :value="$version->published_at" /></span>
                                </div>
                                <x-ui.button variant="ghost" size="sm" wire:click="viewVersion({{ $version->id }})">
                                    <x-lucide-eye class="size-4" />
                                    {{ __('settings.actions.view') }}
                                </x-ui.button>
                            </div>
                        @empty
                            <p class="text-sm text-muted-foreground">{{ __('settings.policies.empty_history') }}</p>
                        @endforelse
                    </div>
                </x-ui.tabs-content>
            @endforeach

            <x-ui.tabs-content value="deletion" class="space-y-4">
                <x-ui.field>
                    <x-ui.field-label for="deletion_grace" required>{{ __('settings.policies.deletion_grace') }}</x-ui.field-label>
                    <div class="flex items-center gap-2">
                        <x-ui.input id="deletion_grace" wire:model="deletion_grace_hours" class="w-24" />
                        <span class="text-sm text-muted-foreground">{{ __('settings.policies.hours') }}</span>
                    </div>
                    @error('deletion_grace_hours')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                    <x-ui.field-description>{{ __('settings.policies.deletion_grace_description') }}</x-ui.field-description>
                </x-ui.field>

                @can('settings.policies.edit')
                    <div class="flex justify-end pt-2">
                        <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                            <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                                <x-lucide-save class="size-4" />
                                {{ __('settings.actions.save_policies') }}
                            </span>
                            <span wire:loading.flex wire:target="save" class="items-center gap-2">
                                <x-ui.spinner class="size-4" />
                                {{ __('settings.actions.saving') }}
                            </span>
                        </x-ui.button>
                    </div>
                @endcan
            </x-ui.tabs-content>
        </x-ui.tabs>
    </form>

    <x-ui.dialog id="policy-version">
        <x-ui.dialog-content class="flex max-h-[85vh] flex-col overflow-hidden sm:max-w-2xl">
            <x-ui.dialog-header>
                <x-ui.dialog-title>{{ $viewingVersion?->policy?->title }} — v{{ $viewingVersion?->version }}</x-ui.dialog-title>
                <x-ui.dialog-description>
                    {{ __('settings.policies.published') }}
                    @if ($viewingVersion?->published_at)
                        <x-ui.local-time :value="$viewingVersion->published_at" />
                    @else
                        —
                    @endif
                    @if ($viewingVersion?->is_active)
                        · <span class="font-medium text-foreground">{{ __('settings.policies.current_version') }}</span>
                    @endif
                </x-ui.dialog-description>
            </x-ui.dialog-header>

            <div class="min-h-0 flex-1 overflow-y-auto rounded-md border border-border px-4 py-3 text-sm leading-7 [&_a]:text-primary [&_a]:underline [&_a]:underline-offset-4 [&_h1]:mb-2 [&_h1]:text-2xl [&_h1]:font-semibold [&_h2]:mb-2 [&_h2]:text-xl [&_h2]:font-semibold [&_li]:mt-1 [&_ol]:my-2 [&_ol]:list-decimal [&_ol]:ps-6 [&_p]:my-2 [&_ul]:my-2 [&_ul]:list-disc [&_ul]:ps-6">
                {!! $viewingVersion?->content !!}
            </div>

            <x-ui.dialog-footer>
                <x-ui.button variant="outline" @click="open = false">{{ __('settings.actions.close') }}</x-ui.button>
            </x-ui.dialog-footer>
        </x-ui.dialog-content>
    </x-ui.dialog>
</div>
