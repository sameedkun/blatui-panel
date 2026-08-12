---
paths:
  - 'tests/Feature/Admin/**'
---

# Admin

## Testing #[Lazy] Livewire components
`Livewire::test()` on a component marked `#[Lazy]` renders only its `placeholder()`, so `viewData()` returns null and `assertOk()` passes against an empty skeleton — the test looks green while measuring nothing.

Call `Livewire::withoutLazyLoading()` immediately before **each** `Livewire::test()` call. It applies to the next call only; hoisting it above a loop or a second render silently stops working.

See `tests/Feature/Admin/DashboardTest::renderTab()` for the helper pattern.
