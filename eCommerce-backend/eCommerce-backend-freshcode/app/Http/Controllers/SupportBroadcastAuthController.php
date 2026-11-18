<?php

namespace App\Http\Controllers;

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Models\Order;
use App\Models\SupportThread;
use App\Services\BroadcastSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

class SupportBroadcastAuthController extends Controller
{
    public function __construct(private readonly BroadcastSettingsService $broadcastSettings) {}

    public function __invoke(Request $request): mixed
    {
        if ($this->broadcastSettings->isConfigured()) {
            $this->broadcastSettings->apply();
        }

        $validated = $request->validate([
            'socket_id' => ['required', 'string', 'max:191'],
            'channel_name' => ['required', 'string', 'max:255'],
        ]);
        $user = $request->user();
        $channel = preg_replace('/^(?:private|presence)-/', '', $validated['channel_name']);

        if ($channel === 'support.admin.inbox') {
            abort_unless($this->canViewAdminInbox($user), 403);
        } elseif ($channel === 'orders.admin') {
            abort_unless(request()->is('admin/*') && $user->can('viewAny', Order::class), 403);
        } elseif (preg_match('/^orders\.seller\.(\d+)$/', $channel, $matches)) {
            $sellerId = (int) $matches[1];
            abort_unless(
                request()->is('seller/*')
                && $user->belongsToSeller($sellerId)
                && $user->can('viewAny', Order::class),
                403
            );
        } elseif (str_starts_with($channel, 'support.user.')) {
            $userId = substr($channel, strlen('support.user.'));
            abort_unless(ctype_digit($userId) && (int) $userId === $user->id, 403);
        } elseif (str_starts_with($channel, 'support.thread.') || str_starts_with($channel, 'support.presence.thread.')) {
            $prefix = str_starts_with($channel, 'support.presence.thread.') ? 'support.presence.thread.' : 'support.thread.';
            $uuid = substr($channel, strlen($prefix));
            $thread = SupportThread::where('uuid', $uuid)->first();
            abort_unless($thread && ($thread->user_id === $user->id || $this->canViewAdminInbox($user)), 403);
        } else {
            abort(403);
        }

        return Broadcast::auth($request);
    }

    private function canViewAdminInbox($user): bool
    {
        try {
            return $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())
                || $user->hasPermissionTo(AdminPermissionEnum::SUPPORT_VIEW());
        } catch (\Throwable) {
            return false;
        }
    }
}
