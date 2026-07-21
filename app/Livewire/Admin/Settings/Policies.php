<?php

namespace App\Livewire\Admin\Settings;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\PolicyType;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\Policy;
use App\Models\PolicyVersion;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class Policies extends BaseSettings
{
    use LogsAdminActivity;

    public int $deletion_grace_hours = 24;

    /** @var array<string, array{title: string, version: string, content: string}> */
    public array $policies = [];

    /** Which version's full content the read-only history dialog is showing. */
    public ?int $viewingVersionId = null;

    protected function editPermission(): string
    {
        return 'settings.policies.edit';
    }

    protected function successMessage(): string
    {
        return 'Policies saved successfully.';
    }

    protected function loadSettings(): void
    {
        $this->deletion_grace_hours = (int) config('panel.account_deletion_grace_hours', 24);

        foreach (PolicyType::cases() as $type) {
            $policy = Policy::firstOrCreate(['key' => $type->value], ['title' => $type->label()]);
            $active = $policy->activeVersion()->first();

            $this->policies[$type->value] = [
                'title' => $policy->title,
                'version' => $active->version ?? '',
                'content' => $active->content ?? '',
            ];
        }
    }

    protected function saveSettings(): void
    {
        foreach (PolicyType::cases() as $type) {
            $data = $this->policies[$type->value];

            if ($this->savePolicy($type->value, $data['title'], $data['version'], $data['content'])) {
                $this->logActivity(ActivityModule::Setting, ActivityAction::Updated, null, [
                    'area' => "policy_{$type->value}",
                    'version' => $data['version'],
                ]);
            }
        }
    }

    /**
     * Returns whether the title or the active version's content actually
     * changed. A blank version/content never counts as a change — a policy
     * type that hasn't been authored yet (e.g. a just-added {@see PolicyType}
     * case with nothing typed into it) must stay silently unpublished rather
     * than getting a garbage empty version every time an unrelated policy is
     * saved on the same form.
     */
    private function savePolicy(string $key, string $title, string $version, string $content): bool
    {
        $policy = Policy::where('key', $key)->firstOrCreate(['key' => $key], ['title' => $title]);

        $titleChanged = $policy->title !== $title;
        if ($titleChanged) {
            $policy->update(['title' => $title]);
        }

        $activeVersion = $policy->activeVersion()->first();
        $contentChanged = $content !== ''
            && (! $activeVersion || $activeVersion->version !== $version || $activeVersion->content !== $content);

        if ($contentChanged) {
            $policy->versions()->update(['is_active' => false]);

            $policy->versions()->create([
                'version' => $version,
                'content' => $content,
                'is_active' => true,
                'published_at' => now(),
            ]);
        }

        return $titleChanged || $contentChanged;
    }

    /**
     * Version/content are only required for a policy type that's already
     * published — a freshly-added {@see PolicyType} case with nothing
     * written yet must not block saving every other tab on this form until
     * someone gets around to authoring it.
     */
    protected function rules(): array
    {
        $rules = ['deletion_grace_hours' => ['required', 'integer', 'min:1']];

        foreach (PolicyType::cases() as $type) {
            $isPublished = Policy::where('key', $type->value)->first()?->activeVersion()->exists() ?? false;
            $required = $isPublished ? 'required' : 'nullable';

            $rules["policies.{$type->value}.title"] = ['required', 'string', 'max:255'];
            $rules["policies.{$type->value}.version"] = [$required, 'string', 'max:50'];
            $rules["policies.{$type->value}.content"] = [$required, 'string'];
        }

        return $rules;
    }

    /** Opens the read-only dialog showing one historical version's full content. */
    public function viewVersion(int $versionId): void
    {
        $this->viewingVersionId = $versionId;
        $this->dispatch('open-dialog-policy-version');
    }

    /**
     * @return Collection<int, PolicyVersion>
     */
    protected function versionsFor(PolicyType $type): Collection
    {
        // `published_at` only has second precision, so a save that replaces a
        // version within the same second as its predecessor needs `id` as a
        // tiebreaker to keep "newest first" actually newest-first.
        return Policy::where('key', $type->value)->first()
            ?->versions()->orderByDesc('published_at')->orderByDesc('id')->get()
            ?? collect();
    }

    public function render(): View
    {
        $policyTypes = PolicyType::cases();

        return view('livewire.admin.settings.policies', [
            'policyTypes' => $policyTypes,
            'policyVersions' => collect($policyTypes)->mapWithKeys(
                fn (PolicyType $type) => [$type->value => $this->versionsFor($type)]
            ),
            'viewingVersion' => $this->viewingVersionId
                ? PolicyVersion::with('policy')->find($this->viewingVersionId)
                : null,
        ]);
    }
}
