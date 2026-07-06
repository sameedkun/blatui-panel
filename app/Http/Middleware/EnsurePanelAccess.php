<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePanelAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            ! $user
            || ! $user->isStaff()
            || ! $user->can(config('panel.access')['admin'])
            || $user->isBanned()
        ) {
            abort(403);
        }

        return $next($request);
    }
}
