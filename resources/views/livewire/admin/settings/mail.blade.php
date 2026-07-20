<div class="space-y-8">
    <div>
        <h3 class="text-lg font-medium">Mail Configuration</h3>
        <p class="text-sm text-muted-foreground">
            Active driver: <span class="font-medium text-foreground">{{ config('mail.default') }}</span>
            — set via <code>MAIL_MAILER</code> in <code>.env</code>, not editable here.
        </p>
    </div>
    <x-ui.separator />

    @if (config('mail.default') === 'smtp')

        {{-- ── SMTP ─────────────────────────────────────────────────────────── --}}
        <form wire:submit="save" class="max-w-2xl space-y-4">
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="smtp_host" required>Host</x-ui.field-label>
                    <x-ui.input id="smtp_host" wire:model="smtp_host" placeholder="smtp.mailtrap.io" />
                    @error('smtp_host')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <x-ui.field>
                    <x-ui.field-label for="smtp_port" required>Port</x-ui.field-label>
                    <x-ui.input id="smtp_port" type="number" wire:model="smtp_port" placeholder="587" />
                    @error('smtp_port')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>
            </div>

            <x-ui.field class="text-muted-foreground">
                <x-ui.field-label for="smtp_encryption">Encryption</x-ui.field-label>
                <x-ui.select native id="smtp_encryption" wire:model="smtp_encryption"
                    :options="['' => 'None', 'tls' => 'TLS', 'ssl' => 'SSL']" />
                @error('smtp_encryption')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="smtp_username" required>Username</x-ui.field-label>
                <x-ui.input id="smtp_username" wire:model="smtp_username" autocomplete="off" />
                @error('smtp_username')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
            </x-ui.field>

            <x-ui.field>
                <x-ui.field-label for="smtp_password">Password</x-ui.field-label>
                <x-ui.input id="smtp_password" type="password" wire:model="smtp_password" autocomplete="new-password" />
                @error('smtp_password')
                    <x-ui.field-error>{{ $message }}</x-ui.field-error>
                @enderror
                <x-ui.field-description>Leave blank to keep the currently stored password.</x-ui.field-description>
            </x-ui.field>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <x-ui.field>
                    <x-ui.field-label for="smtp_from_address" required>From address</x-ui.field-label>
                    <x-ui.input id="smtp_from_address" type="email" wire:model="smtp_from_address"
                        placeholder="noreply@example.com" />
                    @error('smtp_from_address')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>

                <x-ui.field>
                    <x-ui.field-label for="smtp_from_name" required>From name</x-ui.field-label>
                    <x-ui.input id="smtp_from_name" wire:model="smtp_from_name" />
                    @error('smtp_from_name')
                        <x-ui.field-error>{{ $message }}</x-ui.field-error>
                    @enderror
                </x-ui.field>
            </div>

            @can('settings.mail.edit')
                <div class="flex justify-end pt-4">
                    <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="save">
                        <span wire:loading.remove wire:target="save" class="inline-flex items-center gap-2">
                            <x-lucide-save class="size-4" />
                            Save SMTP Settings
                        </span>
                        <span wire:loading.flex wire:target="save" class="items-center gap-2">
                            <x-ui.spinner class="size-4" />
                            Saving...
                        </span>
                    </x-ui.button>
                </div>
            @endcan
        </form>

        @include('livewire.admin.settings.partials.test-email')

    @elseif (config('mail.default') === 'resend')

        {{-- ── Sending domains ──────────────────────────────────────────────── --}}
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-semibold">Sending Domains</h4>
                    <p class="text-sm text-muted-foreground">Verified domains available to assign to a purpose below.</p>
                </div>
                @can('settings.mail.edit')
                    <x-ui.button size="sm" wire:click="openDomainDialog">
                        <x-lucide-plus class="size-4" />
                        Add Domain
                    </x-ui.button>
                @endcan
            </div>

            <div class="overflow-hidden rounded-md border border-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border bg-muted/40">
                            <th class="px-4 py-3 text-left font-medium">Name</th>
                            <th class="px-4 py-3 text-left font-medium">Domain</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="w-24 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse ($domains as $domain)
                            <tr wire:key="email-domain-{{ $domain->id }}">
                                <td class="px-4 py-3 font-medium">
                                    {{ $domain->name }}
                                    @if ($domain->is_default)
                                        <x-ui.badge variant="outline" class="ml-2">Default</x-ui.badge>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-muted-foreground">{{ $domain->domain }}</td>
                                <td class="px-4 py-3">
                                    <x-ui.badge :variant="$domain->is_active ? 'default' : 'secondary'">
                                        {{ $domain->is_active ? 'Active' : 'Inactive' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-4 py-3">
                                    @can('settings.mail.edit')
                                        <div class="flex justify-end gap-1">
                                            <x-ui.button variant="ghost" size="icon" wire:click="openDomainDialog({{ $domain->id }})">
                                                <x-lucide-pencil class="size-4" />
                                            </x-ui.button>
                                            <x-ui.button variant="ghost" size="icon" wire:click="confirmDeleteDomain({{ $domain->id }})">
                                                <x-lucide-trash class="size-4 text-destructive" />
                                            </x-ui.button>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-muted-foreground">
                                    No sending domains added yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Purposes ─────────────────────────────────────────────────────── --}}
        <div class="space-y-4">
            <div>
                <h4 class="text-sm font-semibold">Purposes</h4>
                <p class="text-sm text-muted-foreground">Assign a domain and from-address to each mail purpose.</p>
            </div>

            <div class="overflow-hidden rounded-md border border-border">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-border bg-muted/40">
                            <th class="px-4 py-3 text-left font-medium">Purpose</th>
                            <th class="px-4 py-3 text-left font-medium">From</th>
                            <th class="px-4 py-3 text-left font-medium">Status</th>
                            <th class="w-16 px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @foreach ($senders as $sender)
                            <tr wire:key="email-sender-{{ $sender->id }}">
                                <td class="px-4 py-3 font-medium">{{ $sender->label }}</td>
                                <td class="px-4 py-3 text-muted-foreground">
                                    @if ($sender->fromAddress)
                                        {{ $sender->fromAddress }}
                                    @elseif ($sender->key === \App\Enum\MailPurpose::Default)
                                        <span class="italic">— not configured —</span>
                                    @else
                                        <span class="italic">Falls back to Default</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <x-ui.badge :variant="$sender->is_enabled ? 'default' : 'secondary'">
                                        {{ $sender->is_enabled ? 'Enabled' : 'Disabled' }}
                                    </x-ui.badge>
                                </td>
                                <td class="px-4 py-3">
                                    @can('settings.mail.edit')
                                        <div class="flex justify-end">
                                            <x-ui.button variant="ghost" size="icon" wire:click="openSenderDialog({{ $sender->id }})">
                                                <x-lucide-pencil class="size-4" />
                                            </x-ui.button>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        {{-- ── Domain add/edit dialog ───────────────────────────────────────── --}}
        <x-ui.dialog id="email-domain">
            <x-ui.dialog-content class="sm:max-w-md">
                <x-ui.dialog-header>
                    <x-ui.dialog-title>{{ $editingDomainId ? 'Edit Domain' : 'Add Domain' }}</x-ui.dialog-title>
                </x-ui.dialog-header>

                <div class="space-y-4">
                    <x-ui.field>
                        <x-ui.field-label for="domain_name" required>Name</x-ui.field-label>
                        <x-ui.input id="domain_name" wire:model="domain_name" placeholder="Transactional"
                            aria-invalid="{{ $errors->has('domain_name') ? 'true' : 'false' }}" />
                        @error('domain_name')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="domain_domain" required>Domain</x-ui.field-label>
                        <x-ui.input id="domain_domain" wire:model="domain_domain" placeholder="mail.example.com"
                            aria-invalid="{{ $errors->has('domain_domain') ? 'true' : 'false' }}" />
                        @error('domain_domain')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="domain_description">Description</x-ui.field-label>
                        <x-ui.textarea id="domain_description" wire:model="domain_description" rows="2" />
                        @error('domain_description')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    <div class="flex items-start gap-2">
                        <x-ui.checkbox id="domain_is_default" wire:model="domain_is_default" class="mt-0.5" />
                        <div>
                            <x-ui.label for="domain_is_default" class="cursor-pointer">Default domain</x-ui.label>
                            <p class="text-xs text-muted-foreground">
                                Used automatically for every purpose that doesn't have its own domain assigned.
                                Only one domain can be default — marking this one unmarks any other.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <x-ui.checkbox id="domain_is_active" wire:model="domain_is_active" />
                        <x-ui.label for="domain_is_active" class="cursor-pointer">Active</x-ui.label>
                    </div>
                </div>

                <x-ui.dialog-footer>
                    <x-ui.button variant="outline" @click="open = false; $wire.set('editingDomainId', null)">Cancel</x-ui.button>
                    <x-ui.button wire:click="saveDomain">Save</x-ui.button>
                </x-ui.dialog-footer>
            </x-ui.dialog-content>
        </x-ui.dialog>

        {{-- ── Domain delete confirmation ───────────────────────────────────── --}}
        <x-admin.confirm-dialog id="delete-email-domain" title="Delete domain?" confirm="$wire.deleteDomain()"
            cancel="$wire.set('deletingDomainId', null)" variant="destructive" confirmLabel="Delete">
            This permanently removes the domain. Any purpose currently assigned to it falls back to the Default sender.
        </x-admin.confirm-dialog>

        {{-- ── Sender edit dialog ───────────────────────────────────────────── --}}
        <x-ui.dialog id="email-sender">
            <x-ui.dialog-content class="sm:max-w-md">
                <x-ui.dialog-header>
                    <x-ui.dialog-title>Edit Sender</x-ui.dialog-title>
                </x-ui.dialog-header>

                <div class="space-y-4">
                    <x-ui.field class="text-muted-foreground">
                        <x-ui.field-label for="sender_email_domain_id">Domain</x-ui.field-label>
                        {{-- Compositional slot API (not the `options` shorthand) — PHP coerces
                             numeric-string array keys back to int, and the shorthand treats
                             integer keys as a plain list (value used as the label too), which
                             would silently swap each domain's id for its name as the value. --}}
                        <x-ui.select native id="sender_email_domain_id" wire:model="sender_email_domain_id">
                            <option value="">— No domain assigned —</option>
                            @foreach ($domains->where('is_active', true) as $domainOption)
                                <option value="{{ $domainOption->id }}">{{ $domainOption->domain }}</option>
                            @endforeach
                        </x-ui.select>
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="sender_local_part" required>Local part</x-ui.field-label>
                        <x-ui.input id="sender_local_part" wire:model="sender_local_part" placeholder="noreply"
                            aria-invalid="{{ $errors->has('sender_local_part') ? 'true' : 'false' }}" />
                        @error('sender_local_part')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    <x-ui.field>
                        <x-ui.field-label for="sender_from_name" required>From name</x-ui.field-label>
                        <x-ui.input id="sender_from_name" wire:model="sender_from_name"
                            aria-invalid="{{ $errors->has('sender_from_name') ? 'true' : 'false' }}" />
                        @error('sender_from_name')
                            <x-ui.field-error>{{ $message }}</x-ui.field-error>
                        @enderror
                    </x-ui.field>

                    <div class="flex items-center gap-2">
                        <x-ui.checkbox id="sender_is_enabled" wire:model="sender_is_enabled" />
                        <x-ui.label for="sender_is_enabled" class="cursor-pointer">Enabled</x-ui.label>
                    </div>
                </div>

                <x-ui.dialog-footer>
                    <x-ui.button variant="outline" @click="open = false; $wire.set('editingSenderId', null)">Cancel</x-ui.button>
                    <x-ui.button wire:click="saveSender">Save</x-ui.button>
                </x-ui.dialog-footer>
            </x-ui.dialog-content>
        </x-ui.dialog>

        @include('livewire.admin.settings.partials.test-email')

    @else

        <x-ui.alert tone="warning">
            <x-lucide-triangle-alert class="size-4" />
            <x-ui.alert-title>Unsupported mail driver</x-ui.alert-title>
            <x-ui.alert-description>
                <code>MAIL_MAILER</code> is set to <code>{{ config('mail.default') }}</code>, which this panel
                doesn't have settings for yet. Switch it to <code>smtp</code> or <code>resend</code> in <code>.env</code>.
            </x-ui.alert-description>
        </x-ui.alert>

    @endif
</div>
