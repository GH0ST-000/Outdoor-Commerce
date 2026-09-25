<?php

declare(strict_types=1);

namespace App\Domains\Legal\Exceptions;

use App\Domains\Shared\Exceptions\DomainException;
use App\Domains\Shared\Exceptions\ProvidesErrorDetails;

class LegalException extends DomainException implements ProvidesErrorDetails
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

    public static function notFound(string $subject = 'Legal record'): self
    {
        return new self($subject.' was not found.', 'LEGAL_NOT_FOUND', [], 404);
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self(
            'This legal status change is not allowed.',
            'LEGAL_INVALID_TRANSITION',
            ['from' => $from, 'to' => $to],
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function publicationInvalid(array $details = []): self
    {
        return new self('This legal rule cannot be published.', 'LEGAL_PUBLICATION_INVALID', $details);
    }

    public static function citationRequired(): self
    {
        return new self(
            'A published rule requires a primary citation to an approved provision.',
            'LEGAL_CITATION_REQUIRED',
        );
    }

    public static function sourceUnverified(): self
    {
        return new self('The legal source is not verified and active.', 'LEGAL_SOURCE_UNVERIFIED');
    }

    public static function versionImmutable(): self
    {
        return new self('An approved legal document version cannot be overwritten.', 'LEGAL_VERSION_IMMUTABLE');
    }

    public static function checksumConflict(): self
    {
        return new self('A document version with this checksum already exists.', 'LEGAL_CHECKSUM_CONFLICT');
    }

    public static function fileRejected(string $reason): self
    {
        return new self($reason, 'LEGAL_FILE_REJECTED');
    }

    public static function urlRejected(string $reason = 'The official URL is not allowed.'): self
    {
        return new self($reason, 'LEGAL_URL_REJECTED');
    }

    public static function circularRelation(): self
    {
        return new self('Circular legal relationships are not allowed.', 'LEGAL_CIRCULAR_RELATION');
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function conflictOpen(array $details = []): self
    {
        return new self(
            'Unresolved high-severity legal conflicts block this action.',
            'LEGAL_CONFLICT_OPEN',
            $details,
        );
    }

    public static function conditionInvalid(string $reason): self
    {
        return new self($reason, 'LEGAL_CONDITION_INVALID');
    }

    public static function permissionRequired(): self
    {
        return new self('You do not have permission to perform this legal action.', 'LEGAL_PERMISSION_REQUIRED', [], 403);
    }

    public static function versionConflict(): self
    {
        return new self('This legal record was updated in another request.', 'LEGAL_VERSION_CONFLICT', [], 409);
    }
}
