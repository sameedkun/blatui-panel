<?php

namespace App\Livewire\Admin\Concerns;

trait HasStats
{
    /** @return array<int, array{label: string, value: callable, icon: string, description: string}> */
    protected function statsConfig(): array
    {
        return [];
    }

    /** @return array<int, array{label: string, value: int|string, icon: string, description: string}> */
    protected function resolveStats(): array
    {
        return array_map(function (array $stat): array {
            return [
                ...$stat,
                'value' => is_callable($stat['value']) ? ($stat['value'])() : $stat['value'],
            ];
        }, $this->statsConfig());
    }
}
