<?php

namespace App\Http\Middleware;

use App\Enums\GuardNameEnum;
use App\Enums\Seller\SellerVerificationStatusEnum;
use App\Services\SettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class ValidateSeller
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
            $authResult = $this->authenticateFromToken($request);
            if ($authResult instanceof Response) {
                return $authResult;
            }
            $user = Auth::user();
        }

        if (!$user) {
            return $this->deny($request, __('labels.unauthenticated') ?? 'Unauthenticated.', 401);
        }

        if ($user->getDefaultGuardName() !== GuardNameEnum::SELLER()) {
            if (!$request->expectsJson() && !$request->wantsJson()) {
                Auth::logout();
            }
            return $this->deny($request, 'You do not have permission to access the seller panel.');
        }

        $seller = $user->seller();
        if (!$seller) {
            return $this->deny($request, __('labels.not_a_seller') ?? 'Not a seller account.');
        }

        $isImpersonating = session('impersonating_as_seller', false);
        if (!$isImpersonating && $seller->verification_status !== SellerVerificationStatusEnum::Approved()) {
            $message = __('labels.account_not_verified') ?? 'Your seller account is not approved yet.';
            if (!$request->expectsJson() && !$request->wantsJson()) {
                Auth::logout();
            }
            return $this->deny($request, $message, 403, [
                'verification_status' => $seller->verification_status,
            ]);
        }
        return $next($request);
    }

    /**
     * Attempt to authenticate user from a query param token.
     * Returns Response when redirect is needed to strip token, or null otherwise.
     */
    private function authenticateFromToken(Request $request): ?Response
    {
        $rawToken = $request->query('token');
        if (empty($rawToken)) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($rawToken);
        if ($accessToken && $accessToken->tokenable instanceof User) {
            Auth::login($accessToken->tokenable);
            return redirect()->to($request->url());
        }

        return redirect()->route('seller.login')->with('error', __('labels.invalid_or_expired_token') ?? 'Invalid or expired token.');
    }

    /**
     * Unified denial handler for JSON and web requests.
     */
    private function deny(Request $request, string $message, int $status = 403, array $data = [])
    {
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => $data,
            ], $status);
        }
        return redirect()->route('seller.login')->with('error', $message);
    }
}
