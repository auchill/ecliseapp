<?php

namespace App\Services\MobileSentrix;

use App\Models\CartItem;
use App\Models\MobileSentrixDevice;
use App\Models\OrderItem;
use App\Models\Part;
use App\Models\RepairPartOption;
use App\Services\MobileSentrixMarkupService;

/**
 * Single place that answers "is this a MobileSentrix item, and what does MobileSentrix charge
 * us for it?".
 *
 * Devices and parts live in separate tables with different identity columns, so resolution is
 * explicit rather than polymorphic.
 */
class MobileSentrixItemResolver
{
    /** Value stored on synced parts in both external_api_source and supplier. */
    public const SUPPLIER = 'MobileSentrix';

    public function __construct(private readonly MobileSentrixMarkupService $markups) {}

    /**
     * Shop order lines carry authoritative source metadata; names and SKU prefixes are never used.
     */
    public function isMobileSentrixOrderItem(OrderItem $item): bool
    {
        return $item->source === CartItem::SOURCE_MOBILESENTRIX;
    }

    /**
     * A repair option only counts when it is a real catalogue part backed by the MobileSentrix
     * API. System options ("I Have the Parts") and locally created parts are excluded.
     */
    public function isMobileSentrixRepairOption(RepairPartOption $option): bool
    {
        if (! $option->isPartOption() || $option->source_type !== Part::class) {
            return false;
        }

        return $this->resolvePart((int) $option->source_id, (string) $option->sku_snapshot) !== null;
    }

    public function resolveDevice(int $sourceId, string $sku): ?MobileSentrixDevice
    {
        return MobileSentrixDevice::query()
            ->where('entity_id', $sourceId)
            ->where('sku', $sku)
            ->first();
    }

    /**
     * Locally created parts are excluded: only parts synced from the MobileSentrix API can be
     * procured from MobileSentrix.
     */
    public function resolvePart(int $sourceId, string $sku): ?Part
    {
        return Part::query()
            ->whereKey($sourceId)
            ->where('sku', $sku)
            ->where('is_api_item', true)
            ->where(fn ($query) => $query
                ->where('external_api_source', self::SUPPLIER)
                ->orWhere('supplier', self::SUPPLIER))
            ->first();
    }

    /**
     * The price Eclise expects to pay MobileSentrix.
     *
     * This is the markup service's own base price — the value Eclise markup is applied *to* —
     * so the cost is read from the authoritative synced record rather than reverse-engineered
     * from the customer's selling price.
     */
    public function procurementPrice(Part|MobileSentrixDevice|null $record): ?float
    {
        if ($record instanceof MobileSentrixDevice) {
            return $this->markups->calculatePreOwnedDevicePrice($record)->base_price;
        }

        if ($record instanceof Part) {
            return $this->markups->calculatePartPrice($record)->base_price;
        }

        return null;
    }

    public function displayName(Part|MobileSentrixDevice|null $record, string $fallback = 'Unknown MobileSentrix item'): string
    {
        return match (true) {
            $record instanceof MobileSentrixDevice => (string) ($record->name ?: $fallback),
            $record instanceof Part => (string) ($record->name ?: $fallback),
            default => $fallback,
        };
    }
}
