<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SplashScreen\SaveSplashScreenRequest;
use App\Http\Resources\SplashScreenResource;
use App\Models\SplashScreen;
use App\Services\SplashScreenService;
use App\Types\Api\ApiResponseType;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SplashScreenController extends Controller
{
    use AuthorizesRequests;

    public function index(): View
    {
        $this->authorize('viewAny', SplashScreen::class);

        return view('admin.splash-screen.index', [
            'screens' => SplashScreen::query()->get()->keyBy(fn (SplashScreen $screen) => $screen->surface.'_'.$screen->slot),
            'canEdit' => auth()->user()->can('update', SplashScreen::class),
        ]);
    }

    public function save(SaveSplashScreenRequest $request, SplashScreenService $screens): JsonResponse|RedirectResponse
    {
        $this->authorize('update', SplashScreen::class);
        $data = $request->safe()->except(['background_image', 'background_video', 'overlay_image', 'overlay_lottie']);
        $screen = $screens->save($data, [
            'background_image' => $request->file('background_image'),
            'background_video' => $request->file('background_video'),
            'overlay_image' => $request->file('overlay_image'),
            'overlay_lottie' => $request->file('overlay_lottie'),
        ]);

        if (! $request->expectsJson()) {
            return redirect()->route('admin.splash-screen.index')->with('success', __('labels.splash_screen_saved'));
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.splash_screen_saved', new SplashScreenResource($screen));
    }

    public function destroy(string $surface, string $slot, Request $request, SplashScreenService $screens): JsonResponse|RedirectResponse
    {
        $this->authorize('update', SplashScreen::class);
        $screens->remove($surface, $slot);

        if (! $request->expectsJson()) {
            return redirect()->route('admin.splash-screen.index')->with('success', __('labels.splash_screen_removed'));
        }

        return ApiResponseType::sendJsonResponse(true, 'labels.splash_screen_removed', [
            'surface' => $surface,
            'slot' => $slot,
        ]);
    }
}
