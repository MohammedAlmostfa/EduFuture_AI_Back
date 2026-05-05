<?php

namespace App\Exceptions;

use Exception;
use Throwable;



class FileParsingException extends ServiceException
{
    public const FILE_NOT_FOUND = 404;
    public const INVALID_FORMAT = 415;
    public const CORRUPTED_FILE = 422;
    public const UNSUPPORTED_TYPE = 400;
    public const EXTRACTION_FAILED = 500;
}
