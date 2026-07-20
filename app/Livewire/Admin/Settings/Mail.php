<?php

namespace App\Livewire\Admin\Settings;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\MailPurpose;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Mail\TestMail;
use App\Models\EmailDomain;
use App\Models\EmailSender;
use App\Models\SmtpSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail as MailFacade;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

use function Illuminate\Log\log;

/**
 * Which section renders is driven entirely by `config('mail.default')`
 * (i.e. the env-set `MAIL_MAILER`) — the driver itself isn't admin-editable
 * here, only the settings for whichever driver is active:
 *   - smtp    → one connection + From identity ({@see SmtpSetting}).
 *   - resend  → admin-managed sending domains ({@see EmailDomain}) assigned
 *               to a fixed set of purposes ({@see EmailSender}).
 */
class Mail extends BaseSettings
{
    use LogsAdminActivity;

    // ── SMTP (only relevant when the active driver is smtp) ─────────────────

    public string $smtp_host = '';

    public string $smtp_port = '587';

    public string $smtp_encryption = '';

    public string $smtp_username = '';

    /** Left blank on load; only overwrites the stored password if re-typed. */
    public string $smtp_password = '';

    public string $smtp_from_address = '';

    public string $smtp_from_name = '';

    // ── Resend: domain add/edit dialog ───────────────────────────────────────

    public ?int $editingDomainId = null;

    public string $domain_name = '';

    public string $domain_domain = '';

    public string $domain_description = '';

    public bool $domain_is_default = false;

    public bool $domain_is_active = true;

    public ?int $deletingDomainId = null;

    // ── Resend: sender edit dialog (fixed rows, no add/delete) ───────────────

    public ?int $editingSenderId = null;

    public ?string $sender_email_domain_id = null;

    public string $sender_local_part = 'noreply';

    public string $sender_from_name = '';

    public bool $sender_is_enabled = true;

    // ── Send test email ───────────────────────────────────────────────────────

    public string $test_email_address = '';

    public string $test_email_purpose = 'default';

    protected function editPermission(): string
    {
        return 'settings.mail.edit';
    }

    protected function successMessage(): string
    {
        return 'Mail settings saved.';
    }

    protected function loadSettings(): void
    {
        $smtp = SmtpSetting::current();

        $this->smtp_host = $smtp->host ?? '';
        $this->smtp_port = $smtp->port ? (string) $smtp->port : '587';
        $this->smtp_encryption = $smtp->encryption ?? '';
        $this->smtp_username = $smtp->username ?? '';
        $this->smtp_from_address = $smtp->from_address ?? '';
        $this->smtp_from_name = $smtp->from_name ?? '';
    }

    /**
     * Rules for the single SMTP form — the resend domain/sender dialogs
     * validate inline. `password` is NOT NULL on the table, so it must be
     * required on first-ever save (no existing row to fall back to) even
     * though it's optional — "keep the current one" — on every save after.
     */
    protected function rules(): array
    {
        return [
            'smtp_host' => ['required', 'string', 'max:255'],
            'smtp_port' => ['required', 'integer', 'between:1,65535'],
            'smtp_encryption' => ['nullable', Rule::in(['', 'tls', 'ssl'])],
            'smtp_username' => ['required', 'string', 'max:255'],
            'smtp_password' => [SmtpSetting::current()->exists ? 'nullable' : 'required', 'string'],
            'smtp_from_address' => ['required', 'email', 'max:255'],
            'smtp_from_name' => ['required', 'string', 'max:255'],
        ];
    }

    protected function saveSettings(): void
    {
        $smtp = SmtpSetting::current();

        $smtp->fill([
            'host' => $this->smtp_host,
            'port' => (int) $this->smtp_port,
            'encryption' => $this->smtp_encryption ?: null,
            'username' => $this->smtp_username,
            'from_address' => $this->smtp_from_address,
            'from_name' => $this->smtp_from_name,
        ]);

        if ($this->smtp_password !== '') {
            $smtp->password = $this->smtp_password;
        }

        $smtp->save();
        $this->smtp_password = '';

        $this->logActivity(ActivityModule::Setting, ActivityAction::Updated, null, ['area' => 'smtp']);
    }

    // ── Domains ───────────────────────────────────────────────────────────────

    public function openDomainDialog(?int $domainId = null): void
    {
        $this->authorize($this->editPermission());

        $domain = $domainId ? EmailDomain::query()->findOrFail($domainId) : null;

        $this->editingDomainId = $domain?->id;
        $this->domain_name = $domain->name ?? '';
        $this->domain_domain = $domain->domain ?? '';
        $this->domain_description = $domain->description ?? '';
        $this->domain_is_default = $domain->is_default ?? false;
        $this->domain_is_active = $domain->is_active ?? true;
        $this->resetErrorBag(['domain_name', 'domain_domain', 'domain_description']);

        $this->dispatch('open-dialog-email-domain');
    }

    public function saveDomain(): void
    {
        $this->authorize($this->editPermission());

        $this->validate([
            'domain_name' => ['required', 'string', 'max:100'],
            'domain_domain' => ['required', 'string', 'max:255', Rule::unique('email_domains', 'domain')->ignore($this->editingDomainId)],
            'domain_description' => ['nullable', 'string', 'max:500'],
        ]);

        $domain = $this->editingDomainId ? EmailDomain::query()->findOrFail($this->editingDomainId) : new EmailDomain;
        $isCreate = ! $domain->exists;

        $domain->fill([
            'name' => $this->domain_name,
            'domain' => $this->domain_domain,
            'description' => $this->domain_description ?: null,
            'is_default' => $this->domain_is_default,
            'is_active' => $this->domain_is_active,
        ])->save();

        if ($this->domain_is_default) {
            // Only one domain can be "the" default at a time.
            EmailDomain::query()->where('id', '!=', $domain->id)->update(['is_default' => false]);

            // The default domain doubles as the Default purpose's domain, since
            // every other purpose without its own explicit domain falls back to
            // the Default purpose (see EmailSender::resolve()) — so marking a
            // domain default is enough to cover every unconfigured purpose,
            // without editing each one by hand.
            EmailSender::query()->where('key', MailPurpose::Default->value)->update(['email_domain_id' => $domain->id]);
        }

        $this->logActivity(
            ActivityModule::Setting,
            $isCreate ? ActivityAction::Created : ActivityAction::Updated,
            null,
            ['area' => 'email_domain', 'domain' => $domain->domain],
        );

        $this->editingDomainId = null;
        $this->dispatch('close-dialog-email-domain');
        $this->toastSuccess($isCreate ? 'Domain added.' : 'Domain updated.');
    }

    public function confirmDeleteDomain(int $domainId): void
    {
        $this->authorize($this->editPermission());

        $this->deletingDomainId = $domainId;
        $this->dispatch('open-alert-dialog-delete-email-domain');
    }

    public function deleteDomain(): void
    {
        $this->authorize($this->editPermission());

        $domain = EmailDomain::query()->findOrFail($this->deletingDomainId);
        $domainName = $domain->domain;
        $domain->delete();

        $this->logActivity(ActivityModule::Setting, ActivityAction::Deleted, null, ['area' => 'email_domain', 'domain' => $domainName]);

        $this->deletingDomainId = null;
        $this->toastSuccess("Domain \"{$domainName}\" deleted.");
    }

    // ── Senders (fixed one-per-purpose rows, edit only) ──────────────────────

    public function openSenderDialog(int $senderId): void
    {
        $this->authorize($this->editPermission());

        $sender = EmailSender::query()->findOrFail($senderId);

        $this->editingSenderId = $sender->id;
        $this->sender_email_domain_id = $sender->email_domain_id ? (string) $sender->email_domain_id : null;
        $this->sender_local_part = $sender->local_part;
        $this->sender_from_name = $sender->from_name;
        $this->sender_is_enabled = $sender->is_enabled;
        $this->resetErrorBag(['sender_local_part', 'sender_from_name']);

        $this->dispatch('open-dialog-email-sender');
    }

    public function saveSender(): void
    {
        $this->authorize($this->editPermission());

        $this->validate([
            'sender_local_part' => ['required', 'string', 'max:64', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'sender_from_name' => ['required', 'string', 'max:255'],
        ]);

        $domainId = $this->sender_email_domain_id ? (int) $this->sender_email_domain_id : null;
        if ($domainId !== null) {
            EmailDomain::query()->findOrFail($domainId);
        }

        $sender = EmailSender::query()->findOrFail($this->editingSenderId);

        $sender->fill([
            'email_domain_id' => $domainId,
            'local_part' => $this->sender_local_part,
            'from_name' => $this->sender_from_name,
            'is_enabled' => $this->sender_is_enabled,
        ])->save();

        $this->logActivity(ActivityModule::Setting, ActivityAction::Updated, null, [
            'area' => 'email_sender',
            'purpose' => $sender->key->value,
        ]);

        $this->editingSenderId = null;
        $this->dispatch('close-dialog-email-sender');
        $this->toastSuccess("{$sender->label} sender updated.");
    }

    // ── Send test email ───────────────────────────────────────────────────────

    /**
     * Sends a real email through whichever driver + settings are currently
     * saved (not unsaved dialog state) — the actual proof the configuration
     * works, not just that it persisted.
     */
    public function sendTestEmail(): void
    {
        $this->authorize($this->editPermission());

        $driver = config('mail.default');

        $this->validate([
            'test_email_address' => ['required', 'email'],
            'test_email_purpose' => $driver === 'resend'
                ? ['required', Rule::in(array_column(MailPurpose::cases(), 'value'))]
                : ['nullable'],
        ]);

        $from = match ($driver) {
            'smtp' => $this->applySmtpRuntimeConfig(),
            'resend' => EmailSender::resolve(MailPurpose::from($this->test_email_purpose)),
            default => null,
        };

        if ($from === null) {
            $this->toastError("Mail driver \"{$driver}\" isn't supported here.");

            return;
        }

        if (! $from['address']) {
            $this->toastError('No from-address is configured yet — save your settings first.');

            return;
        }

        try {
            MailFacade::to($this->test_email_address)->send(
                (new TestMail($from['name'] ?? config('app.name')))->from($from['address'], $from['name'] ?? config('app.name')),
            );
        } catch (Throwable $e) {
            log()->error('Failed to send test email: '.$e->getMessage(), ['exception' => $e]);
            $this->toastError('Failed to send: '.$e->getMessage());

            return;
        }

        $this->logActivity(ActivityModule::Setting, ActivityAction::Sent, null, [
            'area' => 'test_email',
            'to' => $this->test_email_address,
        ]);

        $this->toastSuccess("Test email sent to {$this->test_email_address}.");
    }

    /**
     * @return array{address: ?string, name: ?string}
     */
    protected function applySmtpRuntimeConfig(): array
    {
        $smtp = SmtpSetting::current();

        config([
            'mail.mailers.smtp.host' => $smtp->host,
            'mail.mailers.smtp.port' => $smtp->port,
            'mail.mailers.smtp.encryption' => $smtp->encryption,
            'mail.mailers.smtp.username' => $smtp->username,
            'mail.mailers.smtp.password' => $smtp->password,
        ]);

        return ['address' => $smtp->from_address, 'name' => $smtp->from_name];
    }

    // ── View data ─────────────────────────────────────────────────────────────

    /** @return Collection<int, EmailDomain> */
    protected function domains(): Collection
    {
        return EmailDomain::query()->orderBy('name')->get();
    }

    /** @return Collection<int, EmailSender> */
    protected function senders(): Collection
    {
        $order = array_flip(array_column(MailPurpose::cases(), 'value'));

        return EmailSender::query()->with('domain')->get()
            ->sortBy(fn (EmailSender $sender): int => $order[$sender->key->value] ?? 999)
            ->values();
    }

    public function render(): View
    {
        return view('livewire.admin.settings.mail', [
            'domains' => $this->domains(),
            'senders' => $this->senders(),
        ]);
    }
}
