<?php

namespace App\Exceptions;

use Exception;
use Throwable;


class AIAnalysisException extends ServiceException
{
    public const INVALID_RESPONSE = 400;
    public const API_ERROR = 401;
    public const RATE_LIMIT = 429;
    public const TIMEOUT = 504;
    public const CONFIGURATION_ERROR = 500;
}
