<?php

namespace App\Http\Controllers;

use App\Services\PanelReauthenticationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PanelSessionController extends Controller
{
    public function __invoke(
        Request $request,
        PanelReauthenticationService $reauthentication,
    ): JsonResponse {
        $panel = $request->routeIs('seller.*') ? 'seller' : 'admin';
        $expectedUser = $reauthentication->user($request->header('X-Reauthentication-Token'), $panel);

        if (! $expectedUser) {
            return response()->json([
                'success' => false,
                'message' => __('labels.invalid_session'),
                'data' => [],
            ], 422);
        }

        $currentUser = $request->user();
        $authenticated = $currentUser !== null
            && $reauthentication->matches($request->header('X-Reauthentication-Token'), $currentUser, $panel);

        if ($currentUser !== null && ! $authenticated) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $lifetime = (int) config('session.lifetime', 120) * 60;

        return response()->json([
            'success' => true,
            'message' => $authenticated ? __('labels.session_active') : __('labels.session_expired'),
            'data' => [
                'authenticated' => $authenticated,
                'csrf_token' => csrf_token(),
                'expires_in' => $lifetime,
                'expires_at' => now()->addSeconds($lifetime)->timestamp,
            ],
        ]);
    }
}
