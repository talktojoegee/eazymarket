<?php

namespace App\Http\Middleware;

use App\Support\DemoMode;
use App\Types\Api\ApiResponseType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictDemoUserWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethodSafe() || ! DemoMode::isDemoUser($request->user())) {
            return $next($request);
        }

        $message = __('labels.demo_action_restricted');

        if ($request->expectsJson() || $request->ajax()) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: $message,
                data: []
            );
        }

        return redirect()->back()->with('error', $message);
    }
}
