<?php

namespace App\Livewire\Admin\Settings;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Enum\PolicyType;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\Policy;
use Illuminate\View\View;

class Policies extends BaseSettings
{
    use LogsAdminActivity;

    public int $deletion_grace_hours = 24;

    /** @var array<string, array{title: string, version: string, content: string}> */
    public array $policies = [];

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

    /** Returns whether the title or the active version's content actually changed. */
    private function savePolicy(string $key, string $title, string $version, string $content): bool
    {
        $policy = Policy::where('key', $key)->firstOrCreate(['key' => $key], ['title' => $title]);

        $titleChanged = $policy->title !== $title;
        if ($titleChanged) {
            $policy->update(['title' => $title]);
        }

        $activeVersion = $policy->activeVersion()->first();
        $contentChanged = ! $activeVersion || $activeVersion->version !== $version || $activeVersion->content !== $content;

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

    protected function rules(): array
    {
        $rules = ['deletion_grace_hours' => ['required', 'integer', 'min:1']];

        foreach (PolicyType::cases() as $type) {
            $rules["policies.{$type->value}.title"] = ['required', 'string', 'max:255'];
            $rules["policies.{$type->value}.version"] = ['required', 'string', 'max:50'];
            $rules["policies.{$type->value}.content"] = ['required', 'string'];
        }

        return $rules;
    }

    public function render(): View
    {
        return view('livewire.admin.settings.policies', [
            'policyTypes' => PolicyType::cases(),
        ]);
    }
}
