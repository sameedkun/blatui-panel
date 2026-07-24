<?php

namespace App\Livewire\Admin\Support\Categories;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\BaseForm;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;

#[Layout('layouts.admin.app')]
class Form extends BaseForm
{
    use LogsAdminActivity;

    public ?int $categoryId = null;

    public string $name = '';

    public bool $is_active = true;

    public int $sort_order = 0;

    /** @var array<int, int> Staff user IDs eligible for auto-assignment on this category. */
    public array $agentIds = [];

    protected function indexRoute(): string
    {
        return 'admin.ticket-categories.index';
    }

    public function mount(?TicketCategory $category = null): void
    {
        if ($category) {
            $this->isEditing = true;
            $this->categoryId = $category->id;
            $this->name = $category->name;
            $this->is_active = $category->is_active;
            $this->sort_order = $category->sort_order;

            // Intersect with the eligible pool so a staff member who has since lost
            // ticket access doesn't stay silently checked (and re-synced) via a hidden ID.
            $eligibleIds = $this->eligibleAgents()->pluck('id')->all();
            $this->agentIds = array_values(array_intersect(
                $category->agents()->pluck('users.id')->all(),
                $eligibleIds,
            ));
        }
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('categories', 'name')->ignore($this->categoryId)],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'agentIds' => ['array'],
            'agentIds.*' => ['integer', 'exists:users,id'],
        ];
    }

    public function save()
    {
        $this->validate();

        $category = $this->isEditing ? TicketCategory::findOrFail($this->categoryId) : new TicketCategory;
        $before = $this->isEditing ? $category->getOriginal() : null;

        DB::transaction(function () use ($category): void {
            $category->fill([
                'name' => $this->name,
                'is_active' => $this->is_active,
                'sort_order' => $this->sort_order,
            ]);
            $category->save();

            $category->agents()->sync($this->agentIds);
        });

        if ($this->isEditing) {
            $changes = $this->auditDiff($category, $before);
            if ($changes !== []) {
                $this->logActivity(ActivityModule::TicketCategory, ActivityAction::Updated, $category, $changes);
            }
        } else {
            $this->logActivity(ActivityModule::TicketCategory, ActivityAction::Created, $category, [
                'attributes' => ['name' => $category->name],
            ]);
        }

        return $this->redirectWithSuccess(
            "{$category->name} category ".($this->isEditing ? 'updated' : 'created').' successfully.',
        );
    }

    public function render(): View
    {
        return view('livewire.admin.support.categories.form', [
            'agentOptions' => $this->eligibleAgents(),
        ]);
    }

    /**
     * Staff who can actually view and reply to tickets — the only ones worth
     * offering as auto-assignment candidates for a category.
     *
     * @return Collection<int, User>
     */
    private function eligibleAgents(): Collection
    {
        return User::staff()
            ->permission('tickets.view')
            ->permission('tickets.manage')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
    }
}
