<?php

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Exception أساسية للخدمات
 */
abstract class ServiceException extends Exception
{
    protected string $context = '';

    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        string $context = ''
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    public function getContext(): string
    {
        return $this->context;
    }
}

/**
 * Exception خاص بـ AI Analysis
 */
class AIAnalysisException extends ServiceException
{
    public const INVALID_RESPONSE = 400;
    public const API_ERROR = 401;
    public const RATE_LIMIT = 429;
    public const TIMEOUT = 504;
    public const CONFIGURATION_ERROR = 500;
}

/**
 * Exception خاص بـ File Parsing
 */
class FileParsingException extends ServiceException
{
    public const FILE_NOT_FOUND = 404;
    public const INVALID_FORMAT = 415;
    public const CORRUPTED_FILE = 422;
    public const UNSUPPORTED_TYPE = 400;
    public const EXTRACTION_FAILED = 500;
}

/**
 * Exception خاص بـ File Upload
 */
class FileUploadException extends ServiceException
{
    public const FILE_TOO_LARGE = 413;
    public const INVALID_MIME_TYPE = 415;
    public const QUOTA_EXCEEDED = 507;
    public const INVALID_EXTENSION = 422;
    public const STORAGE_ERROR = 500;
}

/**
 * Exception خاص بـ Text Chunking
 */
class TextChunkingException extends ServiceException
{
    public const INVALID_PARAMETERS = 400;
    public const EMPTY_TEXT = 422;
    public const CHUNKING_FAILED = 500;
}

/**
 * Exception خاص بـ Database Operations
 */
class DatabaseException extends ServiceException
{
    public const TRANSACTION_FAILED = 500;
    public const RECORD_NOT_FOUND = 404;
    public const CONSTRAINT_VIOLATION = 409;
}
