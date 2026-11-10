<?php

namespace App\Exceptions;

use RuntimeException;

class EnquiryProductsUnavailable extends RuntimeException
{
    /** @param list<string> $productIds */
    public function __construct(public readonly array $productIds)
    {
        parent::__construct('One or more products are unavailable.');
    }
}
