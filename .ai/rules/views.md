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

## No x-admin.tooltip on controls pinned to the page's right edge
x-admin.tooltip is CSS-only: its bubble is always in the layout (just transparent), centred over the trigger, w-max + whitespace-nowrap. On a button at the right edge of a page header it sticks out past the viewport and gives the whole page a horizontal scrollbar (this happened on the dashboard's Refresh button). Use a native title="" there, or keep the tooltip inside an overflow-x-auto container (tables are fine).

## Keep wire:ignore on the x-ui.chart canvas
resources/views/components/ui/chart.blade.php has `wire:ignore` on its `x-ref="canvas"` div (a local change to the BlatUI component — re-apply it after `blatui:update chart`). Without it, any Livewire re-render with unchanged data (e.g. dashboard Refresh) morphs the div back to its empty server markup and the ApexCharts SVG disappears until a window resize. To redraw with new data, change a parent `wire:key` (the dashboard chart block hashes its data into one).
