<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

final class DemoMode
{
    public static function isDemoUser(?User $user): bool
    {
        if (! $user || ! Setting::isDemoModeEnabled()) {
            return false;
        }

        $email = strtolower(trim((string) $user->email));
        $demoEmails = array_map(
            static fn (mixed $configuredEmail): string => strtolower(trim((string) $configuredEmail)),
            config('demo.user_emails', [])
        );

        return in_array($email, $demoEmails, true);
    }
}
