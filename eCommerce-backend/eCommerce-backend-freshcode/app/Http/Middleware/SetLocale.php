<?php

namespace App\Http\Middleware;

use App\Services\LanguageService;
use Closure;
use Illuminate\Http\Request;

class SetLocale
{
    public function __construct(private readonly LanguageService $languages) {}

    /**
     * Handle an incoming request.
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $language = $this->languages->resolveForRequest($request);
        $this->languages->apply($language);

        $request->attributes->set('language', $language);
        view()->share('availableLanguages', $this->languages->active());
        view()->share('textDirection', $language->direction);

        return $next($request);
    }
}
