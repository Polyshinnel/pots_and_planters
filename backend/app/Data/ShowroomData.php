<?php

namespace App\Data;

use App\Models\Showroom;

final readonly class ShowroomData
{
    /** @param list<array{label: string, href: string}> $phones @param list<string> $emails */
    public function __construct(
        public string $id,
        public string $name,
        public ?string $description,
        public string $address,
        public string $city,
        public string $country,
        public array $phones,
        public array $emails,
        public ?array $instagram,
    ) {}

    public static function fromModel(Showroom $showroom): self
    {
        return new self(
            id: $showroom->public_id,
            name: $showroom->name,
            description: $showroom->description,
            address: $showroom->address,
            city: $showroom->city,
            country: $showroom->country,
            phones: $showroom->phones ?? [],
            emails: $showroom->emails ?? [],
            instagram: $showroom->instagram_handle !== null || $showroom->instagram_url !== null
                ? ['handle' => $showroom->instagram_handle, 'url' => $showroom->instagram_url]
                : null,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'phones' => $this->phones,
            'emails' => $this->emails,
            'instagram' => $this->instagram,
        ];
    }
}
