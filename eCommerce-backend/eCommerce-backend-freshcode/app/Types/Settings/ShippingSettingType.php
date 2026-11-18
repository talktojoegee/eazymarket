<?php

namespace App\Types\Settings;

use App\Interfaces\SettingInterface;
use App\Traits\SettingTrait;

/**
 * Shipping-provider configuration (settings group `shipping`). Today this holds
 * the Shiprocket integration config; other providers can add their own keys.
 *
 * Sensitive keys (password / cached token) should be encrypted at rest by the
 * save/read layer — see docs-internal/shiprocket-integration-plan.md (Phase 2).
 *
 * COD and its charges live in market settings, not here. Courier selection is
 * omitted because Shiprocket exposes no courier-selection API (AWB auto-assigns).
 */
class ShippingSettingType implements SettingInterface
{
    use SettingTrait;

    // ── Manual shipping ────────────────────────────────────────────
    public bool $manualEnabled = true;
    /** Display title shown to sellers/customers instead of the raw "manual" code. */
    public string $manualTitle = 'Standard Shipping';
    /** Days after order placement by which a manual order must ship (0 = no SLA). */
    public int $manualDispatchSlaDays = 0;

    // ── Shiprocket: connection ─────────────────────────────────────
    public bool $shiprocketEnabled = false;
    /** Display title shown to sellers instead of the raw "shiprocket" code. */
    public string $shiprocketTitle = 'Easy Ship';
    public string $shiprocketEmail = '';
    public string $shiprocketPassword = '';
    public string $shiprocketChannelId = '';
    /** Secret we expect back on the incoming tracking webhook (x-api-key). */
    public string $shiprocketWebhookSecret = '';

    // ── Shiprocket: rate / pricing ─────────────────────────────────
    /** flat | free | carrier_calculated */
    public string $shiprocketRateMode = 'carrier_calculated';
    /** Fixed amount charged when rate mode is `flat`. */
    public float $shiprocketFlatRate = 0.0;
    /** Order subtotal at/above which shipping is free (0 = disabled). */
    public float $shiprocketFreeAbove = 0.0;

    // ── Shiprocket: parcel defaults ────────────────────────────────
    public float $shiprocketDefaultWeight = 0.5;
    public float $shiprocketDefaultLength = 10.0;
    public float $shiprocketDefaultBreadth = 10.0;
    public float $shiprocketDefaultHeight = 10.0;

    // ── Shiprocket: safety ─────────────────────────────────────────
    /** Admin alert when the Shiprocket wallet drops below this (0 = disabled). */
    public float $shiprocketWalletAlert = 0.0;

    protected static function getValidationRules(): array
    {
        return [
            'manualEnabled'           => 'nullable|boolean',
            'manualTitle'             => 'nullable|string|max:60',
            'manualDispatchSlaDays'   => 'nullable|integer|min:0|max:60',

            'shiprocketEnabled'       => 'nullable|boolean',
            'shiprocketTitle'         => 'nullable|string|max:60',
            'shiprocketEmail'         => 'required_if:shiprocketEnabled,true|nullable|email',
            'shiprocketPassword'      => 'nullable|string',
            'shiprocketChannelId'     => 'nullable|string|max:100',
            'shiprocketWebhookSecret' => 'nullable|string|max:255',

            'shiprocketRateMode'      => 'nullable|in:flat,free,carrier_calculated',
            'shiprocketFlatRate'      => 'nullable|numeric|min:0|required_if:shiprocketRateMode,flat',
            'shiprocketFreeAbove'     => 'nullable|numeric|min:0',

            'shiprocketDefaultWeight' => 'nullable|numeric|min:0',
            'shiprocketDefaultLength' => 'nullable|numeric|min:0',
            'shiprocketDefaultBreadth' => 'nullable|numeric|min:0',
            'shiprocketDefaultHeight' => 'nullable|numeric|min:0',

            'shiprocketWalletAlert'   => 'nullable|numeric|min:0',
        ];
    }

    protected static function getValidationMessages(): array
    {
        return [
            'shiprocketEmail.required_if'    => __('labels.email') . ' is required when Shiprocket is enabled.',
            'shiprocketEmail.email'          => 'Please provide a valid Shiprocket API-user email.',
            'shiprocketPassword.required_if' => 'Password / token is required when Shiprocket is enabled.',
            'shiprocketRateMode.in'          => 'The selected rate mode is invalid.',
            'shiprocketFlatRate.required_if' => 'Enter a flat rate when the rate mode is Flat.',
        ];
    }
}
