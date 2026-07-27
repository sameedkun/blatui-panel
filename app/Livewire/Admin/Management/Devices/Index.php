<?php

namespace App\Livewire\Admin\Management\Devices;

use App\Enum\DeviceType;
use App\Livewire\Admin\Concerns\HasFilters;
use App\Livewire\Admin\Concerns\HasToast;
use App\Livewire\Admin\Concerns\LogsAdminActivity;
use App\Livewire\Admin\Management\Devices\Concerns\HandlesDeviceRowActions;
use App\Livewire\Admin\Management\Users\Show;
use App\Models\UserDevice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Global, filterable device list — an investigation tool for work the
 * per-user Devices tab (a plain inline table on {@see Show})
 * can't do: one fingerprint across many accounts, every device on one IP,
 * everything stuck on a broken app version.
 */
#[Layout('layouts.admin.app')]
#[Title('Devices')]
class Index extends Component
{
    use HandlesDeviceRowActions;
    use HasFilters;
    use HasToast;
    use LogsAdminActivity;
    use WithPagination;

    public int $perPage = 10;

    public string $sortBy = 'last_seen_at';

    public string $sortDir = 'desc';

    public function mount(): void
    {
        $this->authorize('devices.view');

        $this->filters = [
            'platform' => '',
            'device_type' => '',
            'country' => '',
            'app_version' => '',
            'status' => '',
            'ip_address' => '',
            'fingerprint' => '',
        ];
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function sort(string $column): void
    {
        if ($this->sortBy === $column) {
            $this->sortDir = $this->sortDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortBy = $column;
            $this->sortDir = 'asc';
        }

        $this->resetPage();
    }

    /**
     * @return array<int, array{label: string, value: int, icon: string, description: string}>
     */
    protected function stats(): array
    {
        return [
            [
                'label' => 'Total Devices',
                'value' => UserDevice::count(),
                'icon' => 'smartphone',
                'description' => 'Ever registered',
            ],
            [
                'label' => 'Active',
                'value' => UserDevice::active()->count(),
                'icon' => 'check-circle',
                'description' => 'Currently signed in',
            ],
            [
                'label' => 'Revoked',
                'value' => UserDevice::revoked()->count(),
                'icon' => 'shield-off',
                'description' => 'Signed out, may reactivate',
            ],
            [
                'label' => 'Blocked',
                'value' => UserDevice::blocked()->count(),
                'icon' => 'shield-ban',
                'description' => 'Cannot reactivate by login',
            ],
        ];
    }

    protected function baseQuery(): Builder
    {
        return UserDevice::query()->with('user:id,name,email');
    }

    protected function filterConfig(): array
    {
        $config = [
            'platform' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('platform', 'like', "%{$v}%")],
            'device_type' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('device_type', $v)],
            'country' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('country', 'like', "%{$v}%")],
            'app_version' => ['apply' => fn (Builder $q, string $v): Builder => $q->where('app_version', 'like', "%{$v}%")],
            'status' => ['apply' => fn (Builder $q, string $v): Builder => match ($v) {
                'active' => $q->active(),
                'revoked' => $q->revoked(),
                'blocked' => $q->blocked(),
                default => $q,
            }],
        ];

        if (auth()->user()->can('devices.investigate')) {
            $config['ip_address'] = ['apply' => fn (Builder $q, string $v): Builder => $q->where('ip_address', $v)];
            $config['fingerprint'] = ['apply' => fn (Builder $q, string $v): Builder => $q->where('device_fingerprint', hash('sha256', $v))];
        }

        return $config;
    }

    protected function filterBarConfig(): array
    {
        $config = [
            'platform' => ['label' => 'Platform', 'type' => 'text'],
            'device_type' => [
                'label' => 'Device Type',
                'type' => 'select',
                'options' => collect(DeviceType::cases())->mapWithKeys(fn (DeviceType $t) => [$t->value => $t->label()])->all(),
            ],
            'country' => ['label' => 'Country', 'type' => 'text'],
            'app_version' => ['label' => 'App Version', 'type' => 'text'],
            'status' => [
                'label' => 'Status',
                'type' => 'select',
                'options' => ['active' => 'Active', 'revoked' => 'Revoked', 'blocked' => 'Blocked'],
            ],
        ];

        if (auth()->user()->can('devices.investigate')) {
            $config['ip_address'] = ['label' => 'IP Address', 'type' => 'text', 'placeholder' => '203.0.113.1'];
            $config['fingerprint'] = ['label' => 'Fingerprint', 'type' => 'text', 'placeholder' => 'Raw client value'];
        }

        return $config;
    }

    protected function getRecords(): LengthAwarePaginator
    {
        return $this->applyFilters($this->baseQuery())
            ->orderBy($this->sortBy, $this->sortDir)
            ->paginate($this->perPage);
    }

    public function exportCsv(): StreamedResponse
    {
        $this->authorize('devices.export');

        $devices = $this->applyFilters($this->baseQuery())->orderBy($this->sortBy, $this->sortDir)->get();

        return Response::streamDownload(function () use ($devices): void {
            $out = fopen('php://output', 'w');

            fputcsv($out, ['User', 'Name', 'Platform', 'OS', 'Device Type', 'App Version', 'Country', 'IP Address', 'Status', 'Last Seen']);

            foreach ($devices as $device) {
                $status = match (true) {
                    $device->is_blocked => 'Blocked',
                    $device->is_revoked => 'Revoked',
                    default => 'Active',
                };

                fputcsv($out, [
                    $device->user?->email,
                    $device->name,
                    $device->platform,
                    $device->os,
                    $device->device_type?->label(),
                    $device->app_version,
                    $device->country,
                    $device->ip_address,
                    $status,
                    $device->last_seen_at?->toDateTimeString(),
                ]);
            }

            fclose($out);
        }, 'devices-'.now()->format('Y-m-d-His').'.csv');
    }

    public function render(): View
    {
        return view('livewire.admin.management.devices.index', [
            'devices' => $this->getRecords(),
            'stats' => $this->stats(),
        ]);
    }
}
