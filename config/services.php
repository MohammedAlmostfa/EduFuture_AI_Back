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
    // إعدادات الـ File Upload
    'file_upload' => [
        'max_file_size' => env('FILE_UPLOAD_MAX_SIZE', 104857600), // 100 MB
        'allowed_extensions' => ['pdf', 'txt', 'docx'],
        'disk' => 'private', // storage disk
        'user_quota_bytes' => env('FILE_UPLOAD_QUOTA', 5368709120), // 5 GB
        'max_concurrent_files' => env('FILE_UPLOAD_MAX_CONCURRENT', 50),
    ],

    // إعدادات Gemini API
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-1.5-flash'),
        'max_retries' => env('GEMINI_MAX_RETRIES', 3),
        'max_output_tokens' => env('GEMINI_MAX_TOKENS', 2048),
        'temperature' => env('GEMINI_TEMPERATURE', 0.2),
        'chunk_size_limit' => env('GEMINI_CHUNK_LIMIT', 12000),
        'cache_hours' => env('GEMINI_CACHE_HOURS', 24),
        'timeout' => env('GEMINI_TIMEOUT', 90), // ثانية
    ],

    // إعدادات Text Chunking
    'text_chunking' => [
        'default_words_per_chunk' => env('CHUNKING_WORDS_PER_CHUNK', 2000),
        'default_overlap' => env('CHUNKING_OVERLAP', 100),
        'min_chunk_size' => env('CHUNKING_MIN_SIZE', 100),
        'max_chunk_size' => env('CHUNKING_MAX_SIZE', 5000),
    ],

    // إعدادات Queue للـ Jobs
    'queue' => [
        'timeout' => env('QUEUE_JOB_TIMEOUT', 3600), // ساعة واحدة
        'tries' => env('QUEUE_JOB_TRIES', 3),
        'max_exceptions' => env('QUEUE_JOB_MAX_EXCEPTIONS', 3),
    ],

    // إعدادات الـ Caching
    'cache' => [
        'driver' => env('CACHE_DRIVER', 'redis'),
        'ttl' => [
            'analysis_results' => env('CACHE_ANALYSIS_TTL', 86400), // 24 ساعة
            'file_metadata' => env('CACHE_METADATA_TTL', 3600), // ساعة واحدة
        ],
    ],

    // إعدادات المراقبة والـ Logging
    'monitoring' => [
        'log_level' => env('APP_LOG_LEVEL', 'info'),
        'enable_metrics' => env('ENABLE_METRICS', true),
        'metrics_database' => env('METRICS_DB', 'metrics'), // اختياري
    ],

    // إعدادات الأمان
    'security' => [
        'enable_file_integrity_check' => env('SECURITY_FILE_CHECK', true),
        'enable_virus_scan' => env('SECURITY_VIRUS_SCAN', false),
        'allowed_mime_types' => [
            'application/pdf',
            'text/plain',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ],
    ],

    // إعدادات الأداء
    'performance' => [
        'enable_caching' => env('PERFORMANCE_CACHING', true),
        'chunk_batch_size' => env('CHUNK_BATCH_SIZE', 5), // معالجة عدة chunks بالتوازي
        'memory_limit' => env('PROCESSING_MEMORY_LIMIT', 512), // MB
    ],
    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
