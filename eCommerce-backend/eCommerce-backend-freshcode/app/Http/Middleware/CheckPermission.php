<?php

namespace App\Http\Middleware;

use App\Enums\DefaultSystemRolesEnum;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $permission
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();
        $panel = $request->segment(1);

        if (!$user) {
            if ($panel === 'seller') {
                return redirect()->route('seller.login');
            }
            return redirect()->route('admin.login');
        }
        if ($panel === 'seller' && $user->hasRole(DefaultSystemRolesEnum::SELLER())) {
            return $next($request);
        }

        if ($user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN()) || $user->hasPermissionTo($permission)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'You do not have permission to access this resource.'], 403);
        }

        return response()->view('errors.403', ['message' => 'You do not have permission to access this resource.'], 403);
    }
}
