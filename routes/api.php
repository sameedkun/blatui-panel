<?php

// Versioned routes are registered by grazulex/laravel-apiroute directly from
// config/apiroute.php's "versions" map (routes/api/v1.php, etc.) — see
// ApiRouteServiceProvider::boot(). This file only exists because
// bootstrap/app.php's withRouting(api: ...) requires an entry point; it stays
// empty unless a route genuinely needs to live outside version negotiation.
