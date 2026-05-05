<?php

namespace App\Exceptions;

use Exception;
use Throwable;


class DatabaseException extends ServiceException
{
    public const TRANSACTION_FAILED = 500;
    public const RECORD_NOT_FOUND = 404;
    public const CONSTRAINT_VIOLATION = 409;
}
