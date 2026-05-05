<?php

namespace App\Exceptions;

use Exception;
use Throwable;



class TextChunkingException extends ServiceException
{
    public const INVALID_PARAMETERS = 400;
    public const EMPTY_TEXT = 422;
    public const CHUNKING_FAILED = 500;
}
