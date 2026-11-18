<?php

namespace App\Console\Commands;

use App\Enums\GuardNameEnum;
use App\Models\Cart;
use App\Notifications\AbandonedCartNotification;
use App\Enums\SettingTypeEnum;
use App\Services\SettingService;
use App\Services\WhatsAppEventNotifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SendAbandonedCartReminders extends Command
{
    protected $signature = 'carts:notify-abandoned';

    protected $description = 'Send initial and repeat reminders for carts left unchanged until checkout';

    public function handle(): int
    {
        $settings = app(SettingService::class)->getSettingValues(SettingTypeEnum::NOTIFICATION());

        if (!($settings['abandonedCartEnabled'] ?? true)) {
            $this->info('Abandoned cart reminders are disabled.');

            return self::SUCCESS;
        }

        $delayMinutes = max(1, (int) ($settings['abandonedCartDelayMinutes'] ?? 1440));
        $repeatMinutes = max(1, (int) ($settings['abandonedCartRepeatMinutes'] ?? 180));
        $now = now();
        $cutoff = $now->copy()->subMinutes($delayMinutes);
        $sentCount = 0;

        Cart::query()
            ->whereHas('user', fn ($query) => $query->where('access_panel', GuardNameEnum::WEB->value))
            ->whereHas('items', fn ($query) => $query->where('save_for_later', '0'))
            ->with(['user', 'items' => fn ($query) => $query->where('save_for_later', '0')])
            ->where(function ($query) use ($cutoff, $now) {
                $query->where(function ($initial) use ($cutoff) {
                    $initial->where('updated_at', '<=', $cutoff)
                        ->where(function ($unreminded) {
                            $unreminded->whereNull('abandoned_cart_reminded_for')
                                ->orWhereColumn('abandoned_cart_reminded_for', '!=', 'updated_at');
                        });
                })->orWhere(function ($repeat) use ($now) {
                    $repeat->whereColumn('abandoned_cart_reminded_for', 'updated_at')
                        ->whereNotNull('abandoned_cart_next_reminder_at')
                        ->where('abandoned_cart_next_reminder_at', '<=', $now);
                });
            })
            ->orderBy('id')
            ->chunkById(100, function ($carts) use (&$sentCount, $repeatMinutes, $now) {
                foreach ($carts as $cart) {
                    $activityAt = $cart->getRawOriginal('updated_at');
                    $remindedFor = $cart->getRawOriginal('abandoned_cart_reminded_for');
                    $isRepeat = $remindedFor !== null && $remindedFor === $activityAt;
                    $previousReminderAt = $cart->getRawOriginal('abandoned_cart_next_reminder_at');
                    $nextReminderAt = now()->addMinutes($repeatMinutes);
                    $claim = DB::table('carts')->where('id', $cart->id)->where('updated_at', $activityAt);

                    if ($isRepeat) {
                        $claim->where('abandoned_cart_reminded_for', $activityAt)
                            ->whereNotNull('abandoned_cart_next_reminder_at')
                            ->where('abandoned_cart_next_reminder_at', '<=', $now);
                        $claimData = ['abandoned_cart_next_reminder_at' => $nextReminderAt];
                    } else {
                        $claim->where(function ($query) {
                            $query->whereNull('abandoned_cart_reminded_for')
                                ->orWhereColumn('abandoned_cart_reminded_for', '!=', 'updated_at');
                        });
                        $claimData = [
                            'abandoned_cart_reminded_for' => $activityAt,
                            'abandoned_cart_next_reminder_at' => $nextReminderAt,
                        ];
                    }

                    $claimed = $claim->update($claimData);

                    if ($claimed !== 1 || !$cart->user) {
                        continue;
                    }

                    try {
                        $cart->user->notify(new AbandonedCartNotification($cart->items->count()));
                        app(WhatsAppEventNotifier::class)->send($cart->user, 'customer_abandoned_cart', [
                            'customer_name' => (string) ($cart->user->name ?? ''),
                        ]);
                        $sentCount++;
                    } catch (\Throwable $exception) {
                        DB::table('carts')
                            ->where('id', $cart->id)
                            ->where('updated_at', $activityAt)
                            ->where('abandoned_cart_reminded_for', $activityAt)
                            ->update($isRepeat
                                ? ['abandoned_cart_next_reminder_at' => $previousReminderAt]
                                : [
                                    'abandoned_cart_reminded_for' => null,
                                    'abandoned_cart_next_reminder_at' => null,
                                ]);
                        report($exception);
                    }
                }
            });

        $this->info("Queued {$sentCount} abandoned cart reminder(s).");

        return self::SUCCESS;
    }
}
