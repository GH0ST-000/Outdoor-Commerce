<?php

declare(strict_types=1);

namespace App\Domains\Geography\Enums;

enum SpatialSourceType: string
{
    case OfficialGeoportal = 'official_geoportal';
    case GovernmentDataset = 'government_dataset';
    case OfficialDocumentAttachment = 'official_document_attachment';
    case OfficialApi = 'official_api';
    case ManualVerifiedDataset = 'manual_verified_dataset';
}
