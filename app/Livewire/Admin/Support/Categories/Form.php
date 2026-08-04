<?php

namespace App\Livewire\Admin\Support\Categories;

use App\Enum\ActivityAction;
use App\Enum\ActivityModule;
use App\Livewire\Admin\BaseForm;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Models\TicketCategory;
use App\Services\Ticket\AssignmentService;
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
            $eligibleIds = app(AssignmentService::class)->eligibleAgents()->pluck('id')->all();
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

    protected function validationAttributes(): array
    {
        return [
            'name' => __('ticket_categories.validation_attributes.name'),
            'is_active' => __('ticket_categories.validation_attributes.is_active'),
            'sort_order' => __('ticket_categories.validation_attributes.sort_order'),
            'agentIds' => __('ticket_categories.validation_attributes.agents'),
            'agentIds.*' => __('ticket_categories.validation_attributes.agent'),
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => __('ticket_categories.validation.name_required'),
            'name.string' => __('ticket_categories.validation.name_invalid'),
            'name.max' => __('ticket_categories.validation.name_max', ['max' => 100]),
            'name.unique' => __('ticket_categories.validation.name_unique'),
            'is_active.boolean' => __('ticket_categories.validation.active_boolean'),
            'sort_order.integer' => __('ticket_categories.validation.sort_order_integer'),
            'sort_order.min' => __('ticket_categories.validation.sort_order_min'),
            'agentIds.array' => __('ticket_categories.validation.agents_array'),
            'agentIds.*.integer' => __('ticket_categories.validation.agent_invalid'),
            'agentIds.*.exists' => __('ticket_categories.validation.agent_exists'),
        ];
    }

    public function save(): mixed
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
            $this->isEditing
                ? __('ticket_categories.toasts.updated', ['name' => $category->name])
                : __('ticket_categories.toasts.created', ['name' => $category->name]),
        );
    }

    public function render(): View
    {
        return view('livewire.admin.support.categories.form', [
            'agentOptions' => app(AssignmentService::class)->eligibleAgents(),
        ])->title($this->isEditing
            ? __('ticket_categories.form.edit_title')
            : __('ticket_categories.form.create_title'));
    }
}
