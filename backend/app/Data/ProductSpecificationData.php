<?php

namespace App\Data;

final readonly class ProductSpecificationData
{
    public function __construct(public string $code, public string $label, public string $value, public string $displayText) {}

    public function toArray(): array
    {
        return ['code' => $this->code, 'label' => $this->label, 'value' => $this->value, 'displayText' => $this->displayText];
    }
}
