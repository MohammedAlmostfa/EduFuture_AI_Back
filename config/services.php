<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */
'gemini' => [
    'key' => env('GEMINI_API_KEY'),
    'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    'max_retries' => env('GEMINI_MAX_RETRIES', 3),
    'max_output_tokens' => env('GEMINI_MAX_TOKENS', 2048),
    'temperature' => env('GEMINI_TEMPERATURE', 0.2),
    'cache_hours' => env('GEMINI_CACHE_HOURS', 24),
],

];
