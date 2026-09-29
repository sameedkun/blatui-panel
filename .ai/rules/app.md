---
paths:
  - 'app/**'
---

# App

## Cache never returns objects (serializable_classes = false)
config/cache.php keeps `serializable_classes => false`, so any object stored via Cache::remember() comes back as __PHP_Incomplete_Class on serializing stores (file/redis/database). The array store used in tests does NOT serialize, so tests won't catch it — set `cache.stores.array.serialize` to true (then Cache::forgetDriver('array')) in a test. Cache arrays/scalars, or serialize yourself with an allow-list like App\Support\Dashboard\DashboardCache does.
