<?php

namespace App\Console\Commands;

use App\Services\Shipping\ShiprocketClient;
use Illuminate\Console\Command;

class ShiprocketSmokeCommand extends Command
{
    protected $signature = 'shiprocket:smoke
        {--from= : Pickup pincode for the serviceability check}
        {--to= : Delivery pincode for the serviceability check}
        {--weight=0.5 : Parcel weight in kg}
        {--cod=0 : 1 to quote COD, 0 for prepaid}';

    protected $description = 'Non-destructive Shiprocket check: auth, pickup locations, serviceability. Books nothing.';

    private ?string $connectionMessage = null;

    public function handle(ShiprocketClient $client): int
    {
        $cfg = $client->config();
        $this->line('Shiprocket enabled: ' . (($cfg['shiprocketEnabled'] ?? false) ? 'yes' : 'no'));
        if (empty($cfg['shiprocketEmail']) || empty($cfg['shiprocketPassword'])) {
            $this->error('No Shiprocket credentials saved. Set them in Admin → Settings → Shipping first.');
            return self::FAILURE;
        }

        $this->components->task('Authenticate', function () use ($client) {
            $res = $client->testConnection();
            $this->connectionMessage = $res['message'];
            return $res['success'];
        });
        $this->line('  ' . ($this->connectionMessage ?? ''));

        if (!$client->authToken()) {
            $this->error('Auth failed — stopping. Fix credentials and retry.');
            return self::FAILURE;
        }

        $ok = true;

        try {
            $pickups = $client->pickupLocations();
            $list = $pickups['data']['shipping_address'] ?? [];
            $this->info('Pickup locations: ' . count($list));
            foreach (array_slice($list, 0, 10) as $p) {
                $this->line('  • ' . ($p['pickup_location'] ?? '?') . ' — ' . ($p['city'] ?? '') . ' ' . ($p['pin_code'] ?? ''));
            }
        } catch (\Throwable $e) {
            $ok = false;
            $this->error('pickupLocations failed: ' . $e->getMessage());
        }

        $from = $this->option('from');
        $to = $this->option('to');
        if ($from && $to) {
            try {
                $res = $client->serviceability([
                    'pickup_postcode'   => $from,
                    'delivery_postcode' => $to,
                    'weight'            => (float) $this->option('weight'),
                    'cod'               => (int) $this->option('cod'),
                ]);
                $couriers = $res['data']['available_courier_companies'] ?? [];
                $this->info('Serviceable couriers (' . $from . ' → ' . $to . '): ' . count($couriers));

                $rows = [];
                foreach (array_slice($couriers, 0, 8) as $c) {
                    $rows[] = [
                        $c['id'] ?? '?',
                        $c['courier_name'] ?? '?',
                        $c['rate'] ?? '?',
                        ($c['estimated_delivery_days'] ?? $c['etd'] ?? '?'),
                        ($c['cod'] ?? '') ? 'yes' : 'no',
                    ];
                }
                if ($rows) {
                    $this->table(['ID','Courier', 'Rate', 'ETA', 'COD'], $rows);
                } else {
                    $this->warn('  No serviceable courier for this lane.');
                }
            } catch (\Throwable $e) {
                $ok = false;
                $this->error('serviceability failed: ' . $e->getMessage());
            }
        } else {
            $this->comment('Skipped serviceability — pass --from=<pincode> --to=<pincode> to run it.');
        }

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
