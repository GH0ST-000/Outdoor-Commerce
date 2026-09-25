<?php

declare(strict_types=1);

namespace App\Domains\Hunting\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Exceptions\ProvidesErrorDetails;

class SpeciesException extends DomainException implements ProvidesErrorDetails
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(
        string $message,
        string $errorCode,
        private readonly array $details = [],
        private readonly int $httpStatus = 422,
    ) {
        parent::__construct($message, $errorCode);
    }

    /**
     * @return array<string, mixed>
     */
    public function errorDetails(): array
    {
        return $this->details;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public static function notFound(): self
    {
        return new self('Species was not found.', 'SPECIES_NOT_FOUND', [], 404);
    }

    public static function slugConflict(): self
    {
        return new self('This species slug is already in use.', 'SPECIES_SLUG_CONFLICT');
    }

    public static function scientificNameConflict(): self
    {
        return new self('This scientific name is already in use.', 'SPECIES_SCIENTIFIC_NAME_CONFLICT');
    }

    public static function translationRequired(string $locale = 'ka'): self
    {
        return new self(
            'A published Georgian translation is required before publication.',
            'SPECIES_TRANSLATION_REQUIRED',
            ['locale' => $locale],
        );
    }

    public static function taxonomyInvalid(string $reason): self
    {
        return new self($reason, 'SPECIES_TAXONOMY_INVALID');
    }

    public static function aliasDuplicate(): self
    {
        return new self('This alias already exists for the species.', 'SPECIES_ALIAS_DUPLICATE');
    }

    public static function similarRelationInvalid(string $reason): self
    {
        return new self($reason, 'SPECIES_SIMILAR_RELATION_INVALID');
    }

    public static function sourceRequired(): self
    {
        return new self('At least one cited source is required before publication.', 'SPECIES_SOURCE_REQUIRED');
    }

    public static function mediaRequired(): self
    {
        return new self(
            'A primary identification image is required, or editors must mark the record as deliberately without media.',
            'SPECIES_MEDIA_REQUIRED',
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function publicationInvalid(array $details = []): self
    {
        return new self('This species cannot be published yet.', 'SPECIES_PUBLICATION_INVALID', $details);
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(
            'This publication status change is not allowed.',
            'SPECIES_INVALID_TRANSITION',
            ['from' => $from, 'to' => $to],
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function versionConflict(array $details = []): self
    {
        return new self(
            'This species was updated in another request. Refresh and try again.',
            'SPECIES_VERSION_CONFLICT',
            $details,
            409,
        );
    }

    public static function permissionRequired(): self
    {
        return new self('You do not have permission to perform this species action.', 'SPECIES_PERMISSION_REQUIRED', [], 403);
    }

    public static function sourceUrlInvalid(): self
    {
        return new self('The source URL is not allowed.', 'SPECIES_SOURCE_URL_INVALID');
    }

    public static function contentUnsafe(): self
    {
        return new self('The submitted content contains disallowed markup.', 'SPECIES_CONTENT_UNSAFE');
    }

    public static function revisionNotFound(): self
    {
        return new self('Species revision was not found.', 'SPECIES_NOT_FOUND', [], 404);
    }
}
