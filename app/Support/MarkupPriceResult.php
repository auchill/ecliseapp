<?php

namespace App\Support;

class MarkupPriceResult
{
    public function __construct(
        public readonly ?float $base_price,
        public readonly ?string $markup_type,
        public readonly float $markup_value,
        public readonly float $markup_amount,
        public readonly ?float $selling_price,
        public readonly ?int $applied_rule_id,
        public readonly ?string $applied_scope,
    ) {}

    public function hasPrice(): bool
    {
        return $this->selling_price !== null;
    }
}
