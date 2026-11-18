<?php

namespace App\Console\Commands;

use App\Enums\Shipping\FulfillmentMethodEnum;
use App\Enums\Shipping\ShipmentStatusEnum;
use App\Models\Shipment;
use App\Services\ShipmentService;
use App\Services\Shipping\Providers\ShiprocketProvider;
use App\Services\Shipping\ShiprocketClient;
use Illuminate\Console\Command;

class ShiprocketPollTrackingCommand extends Command
{
    protected $signature = 'shiprocket:poll-tracking';

    protected $description = 'Poll Shiprocket for in-flight shipments: bulk AWB tracking plus order-status sync for shipments without an AWB (webhook fallback).';

    public function handle(ShiprocketClient $client, ShipmentService $shipmentService, ShiprocketProvider $provider): int
    {
        if (!$client->isEnabled()) {
            $this->comment('Shiprocket disabled — nothing to poll.');
            return self::SUCCESS;
        }

        $terminal = array_map(fn ($s) => (string) $s, ShipmentStatusEnum::terminal());
        $scanned = 0;
        $updated = 0;

        Shipment::query()
            ->where('provider_code', (string) FulfillmentMethodEnum::SHIPROCKET())
            ->whereNotNull('awb_number')
            ->whereNotIn('status', $terminal)
            ->chunkById(50, function ($shipments) use (&$scanned, &$updated, $client, $provider, $shipmentService) {
                $scanned += $shipments->count();

                try {
                    $res = $client->trackBulk($shipments->pluck('awb_number')->all());
                } catch (\Throwable $e) {
                    return;
                }

                $byAwb = $this->indexTrackingByAwb($res);

                $cancelled = (string) ShipmentStatusEnum::CANCELLED();

                foreach ($shipments as $shipment) {
                    $awb = (string) $shipment->awb_number;
                    $current = $this->extractStatus((array) ($byAwb[$awb] ?? []));
                    if (!$current) {
                        continue;
                    }
                    $status = $provider->mapWebhookStatus(['current_status' => $current]);
                    if (!$status) {
                        continue;
                    }
                    if ($status === $cancelled) {
                        try {
                            if ($shipmentService->refreshCarrierAssignment($shipment)) {
                                $updated++;
                                continue;
                            }
                        } catch (\Throwable $e) {
                        }
                    }
                    if ($shipmentService->applyCarrierStatus($shipment, $status, __('labels.shiprocket_poll_note'))) {
                        $updated++;
                    }
                }
            });

        Shipment::query()
            ->where('provider_code', (string) FulfillmentMethodEnum::SHIPROCKET())
            ->whereNull('awb_number')
            ->whereNotIn('status', $terminal)
            ->chunkById(50, function ($shipments) use (&$scanned, &$updated, $provider, $shipmentService) {
                foreach ($shipments as $shipment) {
                    $scanned++;
                    try {
                        $snapshot = $provider->remoteOrderSnapshot($shipment);
                        if (!$snapshot) {
                            continue;
                        }

                        if (str_contains($snapshot['status'], 'CANCEL')) {
                            $cancelled = (string) ShipmentStatusEnum::CANCELLED();
                            if ($shipmentService->applyCarrierStatus($shipment, $cancelled, __('labels.shiprocket_remote_cancel_note'))) {
                                $updated++;
                            }
                            continue;
                        }

                        if ($snapshot['awb']) {
                            $shipment->awb_number = $snapshot['awb'];
                            $shipment->tracking_number = $snapshot['awb'];
                            $shipment->tracking_url = 'https://shiprocket.co/tracking/' . $snapshot['awb'];
                            if (!empty($snapshot['courier_name'])) {
                                $shipment->courier_name = $snapshot['courier_name'];
                            }
                            $shipment->save();

                            $confirmed = (string) ShipmentStatusEnum::CONFIRMED();
                            if ($shipmentService->applyCarrierStatus($shipment, $confirmed, __('labels.shiprocket_poll_note'))) {
                                $updated++;
                            }
                        }
                    } catch (\Throwable $e) {
                    }
                }
            });

        $this->info('Scanned ' . $scanned . ', updated ' . $updated . '.');
        return self::SUCCESS;
    }

    private function indexTrackingByAwb(array $res): array
    {
        $byAwb = [];

        foreach ($res as $key => $value) {
            if (!is_array($value)) {
                continue;
            }
            if (isset($value['tracking_data'])) {
                $byAwb[(string) $key] = $value;
                continue;
            }
            foreach ($value as $awb => $entry) {
                if (is_array($entry)) {
                    $byAwb[(string) $awb] = $entry;
                }
            }
        }

        return $byAwb;
    }

    private function extractStatus(array $res): ?string
    {
        $td = $res['tracking_data'] ?? $res;

        if (isset($td['track_status']) && is_numeric($td['track_status']) && !(int) $td['track_status']) {
            return null;
        }

        return $td['shipment_track'][0]['current_status']
            ?? $td['current_status']
            ?? null;
    }
}
