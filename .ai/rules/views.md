---
paths:
  - 'resources/views/**'
---

# Views

## BlatUI chart + select traps inside Livewire
Three traps that cost real debugging time on the dashboard:

1. `x-ui.chart` ships `aspect-video` in its base classes. On a wide card that forces a box hundreds of pixels taller than the chart, leaving a big empty gap. Always pass `class="aspect-auto h-[260px]"` (or similar) to override it.

2. Never pass `null` for an ApexCharts `formatter` (e.g. `'yaxis' => ['labels' => ['formatter' => null]]`). ApexCharts invokes it as a function, so an explicit null throws `r is not a function` during render. Omit the key entirely instead.

3. Do not use `x-ui.select` inside a Livewire-rendered region. It teleports its panel to `<body>`; Livewire's morph then orphans the Alpine scope and the control dies with `isSelected is not defined` / `seedSelected is not defined`, staying dead until a full page refresh. Use `x-admin.dropdown`, which renders inline for exactly this reason. Same applies to BlatUI's `dropdown-menu`.
