<?php

namespace App\Http\Middleware;

use App\Types\Api\ApiResponseType;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates a feature that is shipped but not yet enabled. Renders a
 * "coming soon" page for the current panel (admin / seller) instead of the
 * real feature, while leaving the underlying routes and menu links in place.
 *
 * Usage: ->middleware('coming.soon:ads') on a route or group.
 */
class ComingSoon
{
    /**
     * feature key => [title label, admin menu active, seller menu active]
     */
    private const FEATURES = [
        'ads' => ['labels.ad_campaigns', 'ad_campaigns', 'ad_campaigns'],
        'pos' => ['labels.pos', 'pos_dashboard', 'pos'],
        'subscription' => ['labels.subscriptions', 'subscriptions', 'subscriptions'],
    ];

    /**
     * Features currently gated as "coming soon".
     *
     * Single source of truth consulted OUTSIDE the routing layer (blade views,
     * other middleware) so non-route surfaces stay in sync with the route
     * gating. Routes themselves are gated by attaching the
     * `coming.soon:<feature>` middleware directly. When a feature ships, remove
     * it here and drop its route middleware in the same change.
     */
    private const GATED = ['ads', 'pos', 'subscription'];

    /**
     * Whether the given feature is currently gated behind "coming soon".
     */
    public static function isComingSoon(string $feature): bool
    {
        return in_array($feature, self::GATED, true);
    }

    public function handle(Request $request, Closure $next, string $feature = 'feature'): Response
    {
        if (! self::isComingSoon($feature)) {
            return $next($request);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return ApiResponseType::sendJsonResponse(
                success: false,
                message: 'labels.coming_soon',
                status: 503,
            );
        }

        [$titleKey, $adminActive, $sellerActive] = self::FEATURES[$feature]
            ?? ['labels.coming_soon', '', ''];

        $isSeller = $request->segment(1) === 'seller';

        return response()->view($isSeller ? 'seller.coming_soon' : 'admin.coming_soon', [
            'featureTitle' => __($titleKey),
            'activePage' => $isSeller ? $sellerActive : $adminActive,
        ]);
    }
}
