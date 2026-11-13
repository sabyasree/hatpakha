<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * D166: Laravel's plain `throttle:N,1` keeps ONE counter per signed-in user for every route that uses it, so saving
 * ten pieces in a minute blocked saving the profile, and reading-progress pings blocked clearing the history.
 * Each route now counts on its own. Named limiters (`throttle:login` …) are unchanged; they build their own keys.
 */
class PerRouteThrottle extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $route = $request->route();

        return parent::resolveRequestSignature($request).'|'.$request->method().'|'.($route ? $route->uri() : $request->path());
    }
}
