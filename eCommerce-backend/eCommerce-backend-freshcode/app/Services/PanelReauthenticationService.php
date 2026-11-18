<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;

final class PanelReauthenticationService
{
    public function issue(User $user, string $panel): string
    {
        return Crypt::encryptString(json_encode([
            'user_id' => $user->id,
            'panel' => $panel,
        ], JSON_THROW_ON_ERROR));
    }

    public function matches(?string $token, User $user, string $panel): bool
    {
        $identity = $this->identity($token);

        return $identity !== null
            && $identity['user_id'] === $user->id
            && $identity['panel'] === $panel;
    }

    public function user(?string $token, string $panel): ?User
    {
        $identity = $this->identity($token);

        if ($identity === null || $identity['panel'] !== $panel) {
            return null;
        }

        return User::find($identity['user_id']);
    }

    private function identity(?string $token): ?array
    {
        if (empty($token)) {
            return null;
        }

        try {
            $identity = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($identity)
            || ! is_int($identity['user_id'] ?? null)
            || ! in_array($identity['panel'] ?? null, ['admin', 'seller'], true)) {
            return null;
        }

        return $identity;
    }
}
