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
            </x-ui.tabs-content>
        </x-ui.tabs>

        @can('settings.policies.edit')
            <div class="flex justify-end pt-4 border-t border-border">
                <x-ui.button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove class="inline-flex items-center gap-2">
                        <x-lucide-save class="size-4" />
                        Save Policies Settings
                    </span>
                    <span wire:loading.flex class="items-center gap-2">
                        <x-ui.spinner class="size-4" />
                        Saving...
                    </span>
                </x-ui.button>
            </div>
        @endcan
    </form>
</div>
