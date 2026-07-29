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
                'label' => __('devices.stats.total'),
                'value' => UserDevice::count(),
                'icon' => 'smartphone',
                'description' => __('devices.stats.ever_registered'),
            ],
            [
                'label' => __('devices.status.active'),
                'value' => UserDevice::active()->count(),
                'icon' => 'check-circle',
                'description' => __('devices.stats.currently_signed_in'),
            ],
            [
                'label' => __('devices.status.revoked'),
                'value' => UserDevice::revoked()->count(),
                'icon' => 'shield-off',
                'description' => __('devices.stats.revoked_description'),
            ],
            [
                'label' => __('devices.status.blocked'),
                'value' => UserDevice::blocked()->count(),
                'icon' => 'shield-ban',
                'description' => __('devices.stats.blocked_description'),
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
            'platform' => ['label' => __('devices.fields.platform'), 'type' => 'text'],
            'device_type' => [
                'label' => __('devices.fields.device_type'),
                'type' => 'select',
                'options' => collect(DeviceType::cases())->mapWithKeys(fn (DeviceType $t) => [$t->value => $t->label()])->all(),
            ],
            'country' => ['label' => __('devices.fields.country'), 'type' => 'text'],
            'app_version' => ['label' => __('devices.fields.app_version'), 'type' => 'text'],
            'status' => [
                'label' => __('devices.fields.status'),
                'type' => 'select',
                'options' => [
                    'active' => __('devices.status.active'),
                    'revoked' => __('devices.status.revoked'),
                    'blocked' => __('devices.status.blocked'),
                ],
            ],
        ];

        if (auth()->user()->can('devices.investigate')) {
            $config['ip_address'] = ['label' => __('devices.fields.ip_address'), 'type' => 'text', 'placeholder' => '203.0.113.1'];
            $config['fingerprint'] = ['label' => __('devices.fields.fingerprint'), 'type' => 'text', 'placeholder' => __('devices.placeholders.fingerprint')];
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

            fputcsv($out, [
                __('devices.csv.user'),
                __('devices.csv.name'),
                __('devices.csv.platform'),
                __('devices.csv.os'),
                __('devices.csv.device_type'),
                __('devices.csv.app_version'),
                __('devices.csv.country'),
                __('devices.csv.ip_address'),
                __('devices.csv.status'),
                __('devices.csv.last_seen'),
            ]);

            foreach ($devices as $device) {
                $status = match (true) {
                    $device->is_blocked => __('devices.status.blocked'),
                    $device->is_revoked => __('devices.status.revoked'),
                    default => __('devices.status.active'),
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
        ])->title(__('devices.title'));
    }
}
