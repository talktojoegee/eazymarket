<?php

use App\Enums\AdminPermissionEnum;
use App\Enums\DefaultSystemRolesEnum;
use App\Models\Order;
use App\Models\SupportThread;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

$canViewSupport = static fn (User $user): bool => $user->hasRole(DefaultSystemRolesEnum::SUPER_ADMIN())
    || $user->hasPermissionTo(AdminPermissionEnum::SUPPORT_VIEW());

Broadcast::channel('support.thread.{uuid}', function (User $user, string $uuid) use ($canViewSupport): bool {
    $thread = SupportThread::where('uuid', $uuid)->first();

    return $thread !== null && ($thread->user_id === $user->id || $canViewSupport($user));
});

Broadcast::channel('support.presence.thread.{uuid}', function (User $user, string $uuid) use ($canViewSupport): array|false {
    $thread = SupportThread::where('uuid', $uuid)->first();
    if ($thread === null || ($thread->user_id !== $user->id && ! $canViewSupport($user))) {
        return false;
    }

    return [
        'id' => (string) $user->id,
        'role' => $thread->user_id === $user->id ? 'customer' : 'admin',
    ];
});

Broadcast::channel('support.admin.inbox', fn (User $user): bool => $canViewSupport($user));
Broadcast::channel('support.user.{userId}', fn (User $user, int $userId): bool => $user->id === $userId);
Broadcast::channel('orders.admin', fn (User $user): bool => request()->is('admin/*')
    && $user->can('viewAny',Order::class));
Broadcast::channel('orders.seller.{sellerId}', fn (User $user, int $sellerId): bool => request()->is('seller/*')
    && $user->belongsToSeller($sellerId)
    && $user->can('viewAny', Order::class));
