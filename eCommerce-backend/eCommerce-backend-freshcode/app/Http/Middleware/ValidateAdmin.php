<?php

namespace App\Http\Middleware;

use App\Enums\DefaultSystemRolesEnum;
use App\Enums\GuardNameEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ValidateAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param \Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect()->route('admin.login');
        }
        if ($user->getDefaultGuardName() == GuardNameEnum::ADMIN() || $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())) {
            return $next($request);
        }

        Auth::logout();
        return redirect()->route('admin.login')->with('error', 'You do not have permission to access the admin panel.');
    }
}
