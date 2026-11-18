<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts maintenance/utility routes to development installs.
 *
 * A route behind this middleware only exists while the marker file
 * `storage/dev` is present. Production installs ship without it, so the route
 * responds 404 exactly as if it were never registered. Delete the file to turn
 * the routes off; create it (`touch storage/dev`) to turn them back on.
 */
class DevOnly
{
    public static function enabled(): bool
    {
        return file_exists(storage_path('dev'));
    }

    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(self::enabled(), 404);

        return $next($request);
    }
}
