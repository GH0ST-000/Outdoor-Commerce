<?php

declare(strict_types=1);

namespace App\Domains\Catalog\Exceptions;

/**
 * Raised when stored bytes fail the security re-check during processing.
 * Assets that hit this are quarantined, never retried.
 */
final class MediaSecurityException extends MediaProcessingException {}
