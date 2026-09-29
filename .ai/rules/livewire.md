---
paths:
  - 'resources/views/livewire/**'
---

# Livewire

## Never name a Livewire view variable $slots
Livewire 4 injects its own `$slots` (Livewire\Features\SupportSlots\Slot) into every component view, overwriting any `'slots' => ...` you pass from render(). The view then crashes (e.g. count() on a Slot). The dashboard Overview passes its widget slots as `$overview` for this reason.
