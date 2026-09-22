<?php

declare(strict_types=1);

namespace App\Domains\Checkout\DTOs;

final readonly class CheckoutAddressWriteData
{
    public function __construct(
        public string $recipientFirstName,
        public string $recipientLastName,
        public string $phone,
        public string $countryCode,
        public ?string $region,
        public ?string $municipalityOrCity,
        public ?string $district,
        public ?string $street,
        public ?string $houseNumber,
        public ?string $apartment,
        public ?string $entrance,
        public ?string $floor,
        public ?string $postalCode,
        public ?string $landmark,
        public ?string $deliveryInstructions,
        public bool $billingSameAsShipping = true,
        public bool $saveToAccount = false,
    ) {}
}
