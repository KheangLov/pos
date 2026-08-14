<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when a checkout tries to sell more units than are on hand.
 * Caught by the POS page / eMenu component, which surface it to the user
 * and abort the sale instead of letting stock go negative (P0-2).
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $productName,
        public readonly int $onHand,
        public readonly int $requested,
    ) {
        parent::__construct(sprintf(
            'Insufficient stock for "%s": %d on hand, %d requested.',
            $this->productName,
            $this->onHand,
            $this->requested,
        ));
    }
}
