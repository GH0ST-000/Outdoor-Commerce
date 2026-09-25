<?php

declare(strict_types=1);

namespace App\Domains\Shipping\Enums;

enum ShipmentExceptionCode: string
{
    case AddressIssue = 'address_issue';
    case RecipientUnavailable = 'recipient_unavailable';
    case DeliveryAttemptFailed = 'delivery_attempt_failed';
    case CarrierDelay = 'carrier_delay';
    case DamagedPackage = 'damaged_package';
    case LostPackage = 'lost_package';
    case WeatherDelay = 'weather_delay';
    case ProviderError = 'provider_error';
    case Unknown = 'unknown';
}
