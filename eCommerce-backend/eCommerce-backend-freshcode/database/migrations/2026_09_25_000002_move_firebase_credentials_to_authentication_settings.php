<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $authenticationRow = DB::table('settings')->where('variable', 'authentication')->first();
            $notificationRow = DB::table('settings')->where('variable', 'notification')->first();

            if (! $notificationRow) {
                return;
            }

            $authentication = $this->decode($authenticationRow?->value);
            $notification = $this->decode($notificationRow->value);
            $authenticationProjectId = trim((string) ($authentication['fireBaseProjectId'] ?? ''));
            $notificationProjectId = trim((string) ($notification['firebaseProjectId'] ?? ''));
            $serviceAccountPath = (string) ($authentication['serviceAccountFile'] ?? $notification['serviceAccountFile'] ?? '');
            $serviceAccountProjectId = '';

            if ($serviceAccountPath !== '' && Storage::disk('local')->exists($serviceAccountPath)) {
                $serviceAccount = json_decode(Storage::disk('local')->get($serviceAccountPath), true);
                $serviceAccountProjectId = is_array($serviceAccount) ? trim((string) ($serviceAccount['project_id'] ?? '')) : '';
            }

            if (($authenticationProjectId !== '' && $notificationProjectId !== '' && $authenticationProjectId !== $notificationProjectId)
                || ($authenticationProjectId !== '' && $serviceAccountProjectId !== '' && $authenticationProjectId !== $serviceAccountProjectId)) {
                Log::warning('Firebase settings migration found conflicting project IDs; Authentication was preserved.', [
                    'authentication_project_id' => $authenticationProjectId,
                    'notification_project_id' => $notificationProjectId,
                    'service_account_project_id' => $serviceAccountProjectId,
                ]);
            }

            if (empty($authentication['fireBaseProjectId']) && ! empty($notification['firebaseProjectId'])) {
                $authentication['fireBaseProjectId'] = $notification['firebaseProjectId'];
            }

            if (empty($authentication['serviceAccountFile']) && ! empty($notification['serviceAccountFile'])) {
                $authentication['serviceAccountFile'] = $notification['serviceAccountFile'];
            }

            unset($notification['firebaseProjectId'], $notification['serviceAccountFile']);

            DB::table('settings')->updateOrInsert(
                ['variable' => 'authentication'],
                ['value' => json_encode($authentication), 'updated_at' => now(), 'created_at' => $authenticationRow?->created_at ?? now()]
            );
            DB::table('settings')->where('variable', 'notification')->update([
                'value' => json_encode($notification),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        $authenticationRow = DB::table('settings')->where('variable', 'authentication')->first();
        $notificationRow = DB::table('settings')->where('variable', 'notification')->first();

        if (! $authenticationRow || ! $notificationRow) {
            return;
        }

        $authentication = $this->decode($authenticationRow->value);
        $notification = $this->decode($notificationRow->value);

        $notification['firebaseProjectId'] = $authentication['fireBaseProjectId'] ?? '';
        $notification['serviceAccountFile'] = $authentication['serviceAccountFile'] ?? '';

        DB::table('settings')->where('variable', 'notification')->update([
            'value' => json_encode($notification),
            'updated_at' => now(),
        ]);
    }

    private function decode(?string $value): array
    {
        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }
};
