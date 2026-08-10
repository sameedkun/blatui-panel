<?php

namespace App\Traits;

trait HasFeatures
{
    public function feature(string $key, mixed $fallback = null): mixed
    {
        $config = config("panel.features.$key");

        if (! $config) {
            return $fallback;
        }

        return data_get($this->features, $key, $fallback ?? $config['default'] ?? null);
    }

    public function hasFeature(string $key): bool
    {
        return (bool) $this->feature($key, false);
    }

    public function setFeature(string $key, mixed $value): static
    {
        $features = $this->features ?? [];
        $features[$key] = $value;
        $this->features = $features;

        return $this;
    }

    public function features(): array
    {
        $defaults = collect(config('panel.features'))
            ->map(fn ($c) => $c['default'] ?? null)
            ->toArray();

        return array_merge($defaults, $this->features ?? []);
    }
}
