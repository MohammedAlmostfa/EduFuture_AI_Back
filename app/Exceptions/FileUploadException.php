<?php

namespace App\Exceptions;

use Exception;
use Throwable;




class FileUploadException extends ServiceException
{
    public const FILE_TOO_LARGE = 413;
    public const INVALID_MIME_TYPE = 415;
    public const QUOTA_EXCEEDED = 507;
    public const INVALID_EXTENSION = 422;
    public const STORAGE_ERROR = 500;
}
