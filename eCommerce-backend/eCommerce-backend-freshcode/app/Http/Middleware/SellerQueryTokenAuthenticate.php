<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class SellerQueryTokenAuthenticate
{
    /**
     * Handle an incoming request.
     *
     * If a ?token= is present in the query and no user session exists, attempt to authenticate
     * the request using the provided personal access token. In success, strip the token from the URL
     * by redirecting to the same path without a query token. On failure, redirect to the seller login.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            $rawToken = $request->query('token');
            if (!empty($rawToken)) {
                $accessToken = PersonalAccessToken::findToken($rawToken);
                if ($accessToken && $accessToken->tokenable instanceof User && $this->tokenIsUsable($accessToken)) {
                    Auth::login($accessToken->tokenable);

                    if ($request->hasSession()) {
                        $request->session()->regenerate();
                    }

                    return redirect()->to($request->url());
                }

                return redirect()->route('seller.login')->with('error', __('labels.invalid_or_expired_token') ?? 'Invalid or expired token.');
            }
        }

        return $next($request);
    }

    private function tokenIsUsable(PersonalAccessToken $accessToken): bool
    {
        $expiration = config('sanctum.expiration');

        if ($accessToken->expires_at !== null && $accessToken->expires_at->isPast()) {
            return false;
        }

        if ($expiration && $accessToken->created_at->lte(now()->subMinutes($expiration))) {
            return false;
        }

        return $accessToken->can('*') || $accessToken->can('seller-panel');
    }
}
