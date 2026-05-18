<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PrometheusMetricsMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);

        $response = $next($request);

        $duration = microtime(true) - $startTime;
        $routeName = $request->route() ? $request->route()->uri() : 'unknown';
        $statusCode = $response->getStatusCode();

        $cleanRoute = str_replace(['/', '{', '}', '-'], '_', $routeName);

        $counterKey = "metrics_requests_total_{$cleanRoute}_{$statusCode}";
        $currentCount = Cache::get($counterKey, 0);
        Cache::put($counterKey, $currentCount + 1, now()->addDays(1));

        $durationKey = "metrics_request_duration_seconds_{$cleanRoute}";
        Cache::put($durationKey, $duration, now()->addDays(1));

        $routesList = Cache::get('metrics_registered_routes', []);
        $routeInfo = ['route' => $cleanRoute, 'status' => $statusCode];
        if (!in_array($routeInfo, $routesList)) {
            $routesList[] = $routeInfo;
            Cache::put('metrics_registered_routes', $routesList, now()->addDays(1));
        }

        return $response;
    }
}