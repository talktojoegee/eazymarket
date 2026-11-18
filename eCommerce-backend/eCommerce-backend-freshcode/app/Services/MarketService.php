<?php

namespace App\Services;

use App\Enums\Market\MarketStatusEnum;
use App\Models\Market;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Market resolution + lookups.
 *
 * Resolution chain (most-specific → fallback):
 *   1. Explicit X-Market / ?market= override (validated to active markets)
 *   2. user_market pivot for the authenticated user
 *   3. `market` cookie (1-year, set by /api/markets/switch)
 *   4. IP → country (via X-Country-Code header or geo-IP service) → market
 *   5. Setting('default_market_id') — the seeded GLOBAL market
 *
 * The resolved market is request-bound (cached on the Request instance) and
 * the convenience accessor `current()` re-reads it from the bound attribute.
 *
 * Catalog lookups (`active()`, `byCountry()`) are short-cached so the same
 * request doesn't hit the DB twice for unchanged read paths.
 */
class MarketService
{
    /** @var Market|null Request-scope memoized resolution. */
    protected ?Market $resolved = null;

    public const COOKIE_NAME = 'market';
    public const HEADER_NAME = 'X-Market';
    public const QUERY_PARAM = 'market';

    /**
     * Resolve the market for the given request. Result is memoized on `$this`
     * so repeat calls within a single request reuse the same object.
     */
    public function forget(): void
    {
        $this->resolved = null;
    }

    public function resolveForRequest(Request $request): Market
    {
        if ($this->resolved instanceof Market) {
            return $this->resolved;
        }

        return $this->resolved = $this->resolveChain($request) ?? $this->default();
    }

    /**
     * Convenience accessor — returns the market bound on the current request.
     * Falls back to default if nothing's been resolved (e.g. CLI commands).
     */
    public function current(): Market
    {
        if ($this->resolved instanceof Market) {
            return $this->resolved;
        }

        if (request()) {
            return $this->resolveForRequest(request());
        }

        return $this->resolved = $this->default();
    }

    /**
     * The system-wide default market. Reads Setting('default_market_id'); falls
     * back to the row with is_default=true; ultimately throws if neither exists
     * (which would mean the seed migration didn't run).
     */
    public function default(): Market
    {
        return Cache::driver('array')->remember('market.default', 60, function () {
            $market = Market::query()->with('countries')->where('is_default', true)->first();

            if (!$market instanceof Market) {
                throw new \RuntimeException(
                    'No default market found. Run migrations + seeders so GLOBAL is created.'
                );
            }

            return $market;
        });
    }

    /**
     * Find an active market that covers the given country (ISO2). Ties are
     * broken by priority DESC (set on the markets table).
     */
    public function byCountry(string $iso2): ?Market
    {
        $iso2 = strtoupper(trim($iso2));

        if (strlen($iso2) !== 2) {
            return null;
        }

        return Cache::driver('array')->remember(
            "market.byCountry.$iso2",
            60,
            fn() => Market::query()
                ->where('status', MarketStatusEnum::ACTIVE())
                ->forCountry($iso2)
                ->first()
        );
    }

    /**
     * All active markets, ordered by priority then name. Cached per-request.
     *
     * @return Collection<int, Market>
     */
    public function active()
    {
        return Cache::driver('array')->remember(
            'market.active',
            60,
            fn() => Market::query()
                ->with(['countries'])
                ->where('status', MarketStatusEnum::ACTIVE())
                ->orderByDesc('priority')
                ->orderBy('name')
                ->get(),
        );
    }

    /**
     * Sets the user's market preference into the user_market pivot.
     * Replaces any existing row (UNIQUE on user_id enforces 1:1).
     */
    public function setForUser(int $userId, Market $market): void
    {
        DB::table('user_market')->updateOrInsert(
            ['user_id' => $userId],
            ['market_id' => $market->id, 'updated_at' => now()],
        );

        DB::table('user_market')
            ->where('user_id', $userId)
            ->whereNull('created_at')
            ->update(['created_at' => now()]);
    }


    public const ADMIN_SESSION_KEY = 'admin_market_id';

    /**
     * The market the admin panel is currently scoped to. Reads the session
     * selection, validates it is still an active market, and otherwise falls
     * back to the default market (self-healing the session).
     */
    public function adminSelectedMarket(): Market
    {
        $sessionId = session(self::ADMIN_SESSION_KEY);
        $market = $sessionId
            ? $this->active()->firstWhere('id', (int) $sessionId)
            : null;

        if (!$market instanceof Market) {
            $market = $this->default();
            session([self::ADMIN_SESSION_KEY => $market->id]);
        }

        return $market;
    }

    /** Convenience id accessor for controllers/queries. */
    public function adminSelectedId(): int
    {
        return $this->adminSelectedMarket()->id;
    }

    /**
     * Persist the admin's market selection into the session. Invalid/inactive
     * ids fall back to the default market. Returns the market actually set.
     */
    public function setAdminMarket(int $marketId): Market
    {
        $market = $this->active()->firstWhere('id', $marketId) ?? $this->default();
        session([self::ADMIN_SESSION_KEY => $market->id]);

        return $market;
    }


    protected function resolveChain(Request $request): ?Market
    {
        return $this->fromHeader($request)
            ?? $this->fromQuery($request)
            ?? $this->fromAuthUser($request)
            ?? $this->fromCookie($request)
            ?? $this->fromCountryHeader($request);
    }

    protected function fromHeader(Request $request): ?Market
    {
        $code = trim((string)$request->header(self::HEADER_NAME));

        return $code === '' ? null : $this->byActiveCode($code);
    }

    protected function fromQuery(Request $request): ?Market
    {
        $code = trim((string)$request->query(self::QUERY_PARAM, ''));

        return $code === '' ? null : $this->byActiveCode($code);
    }

    protected function fromAuthUser(Request $request): ?Market
    {
        $user = $request->user();

        if (!$user) {
            return null;
        }

        $marketId = DB::table('user_market')->where('user_id', $user->id)->value('market_id');

        if (!$marketId) {
            return null;
        }

        $market = Market::find((int)$marketId);

        return $market && $market->isActive() ? $market : null;
    }

    protected function fromCookie(Request $request): ?Market
    {
        $code = trim((string)$request->cookie(self::COOKIE_NAME));

        return $code === '' ? null : $this->byActiveCode($code);
    }

    /**
     * Some PaaS hosts (Cloudflare, Vercel, AWS CloudFront) inject the resolved
     * country into a header. If present, prefer that over re-running IP detection.
     */
    protected function fromCountryHeader(Request $request): ?Market
    {
        foreach (['CF-IPCountry', 'X-Country-Code', 'X-AppEngine-Country'] as $header) {
            $iso2 = trim((string)$request->header($header));
            if ($iso2 !== '' && strtoupper($iso2) !== 'XX') {
                $market = $this->byCountry($iso2);
                if ($market) {
                    return $market;
                }
            }
        }

        return null;
    }

    protected function byActiveCode(string $code): ?Market
    {
        $code = strtoupper($code);

        return Market::query()
            ->where('code', $code)
            ->where('status', MarketStatusEnum::ACTIVE())
            ->first();
    }
}
