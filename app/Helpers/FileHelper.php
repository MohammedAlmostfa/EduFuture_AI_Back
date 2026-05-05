<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;

class FileHelper
{
    private const MAX_FILE_SIZE = 104857600; // 100 MB
    private const SUPPORTED_EXTENSIONS = ['pdf', 'txt', 'docx'];
    private const SANITIZE_PATTERN = '/[^a-zA-Z0-9._\-\x{0621}-\x{064A}]/u';
    private const MAX_FILENAME_LENGTH = 255;

    public static function storeFile(UploadedFile $file, int $userId): string
    {
        try {
            $timestamp = now()->format('Y/m/d');
            $randomName = uniqid('file_', true);
            $extension = strtolower($file->getClientOriginalExtension());

            $path = $file->storeAs(
                "users/{$userId}/{$timestamp}",
                "{$randomName}.{$extension}",
                'private'
            );

            if ($path === false) {
                throw new \App\Exceptions\FileUploadException('Failed to store file on disk');
            }

            return $path;
        } catch (\Throwable $e) {
            Log::error('FileHelper: File storage failed', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            throw new \App\Exceptions\FileUploadException('File storage failed: ' . $e->getMessage());
        }
    }

    public static function generateFileChecksum(UploadedFile $file): string
    {
        try {
            return hash_file('sha256', $file->getRealPath(), false);
        } catch (\Throwable $e) {
            Log::warning('FileHelper: Checksum generation failed, using fallback', [
                'error' => $e->getMessage()
            ]);
            return hash('sha256', $file->getClientOriginalName() . microtime(true));
        }
    }

    public static function sanitizeFileName(string $fileName): string
    {
        $fileName = basename($fileName);
        $fileName = preg_replace(self::SANITIZE_PATTERN, '_', $fileName);
        $fileName = mb_substr($fileName, 0, self::MAX_FILENAME_LENGTH);
        return trim($fileName) ?: 'file_' . uniqid();
    }

    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function validateFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new \App\Exceptions\FileParsingException("File not found: {$filePath}");
        }

        if (!is_readable($filePath)) {
            throw new \App\Exceptions\FileParsingException("File is not readable: {$filePath}");
        }

        if (!is_file($filePath)) {
            throw new \App\Exceptions\FileParsingException("Path is not a regular file: {$filePath}");
        }

        $fileSize = @filesize($filePath);
        if ($fileSize === false || $fileSize > self::MAX_FILE_SIZE) {
            throw new \App\Exceptions\FileParsingException(
                "File size exceeds maximum limit (" . self::formatBytes($fileSize ?? 0) . " > " . self::formatBytes(self::MAX_FILE_SIZE) . ")"
            );
        }
    }

    public static function getFileExtension(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if (!in_array($extension, self::SUPPORTED_EXTENSIONS, true)) {
            throw new \App\Exceptions\FileParsingException("Unsupported file extension: {$extension}");
        }

        return $extension;
    }

    public static function cleanText(string $text, int $maxLength = PHP_INT_MAX): string
    {
        $beforeLength = strlen($text);

        // Remove control characters
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        // Normalize whitespace
        $text = preg_replace('/\s+/u', ' ', $text);
        // Trim
        $text = trim($text);
        // Remove Arabic control characters
        $text = preg_replace('/[\x{200E}\x{200F}]/u', '', $text);
        // Apply max length
        $text = mb_substr($text, 0, $maxLength);

        Log::debug('FileHelper: Text cleaned', [
            'before' => $beforeLength,
            'after' => strlen($text),
            'reduction_percent' => round(((1 - strlen($text) / max($beforeLength, 1)) * 100), 2)
        ]);

        return $text;
    }

    public static function normalizeText(string $text, int $maxLength = PHP_INT_MAX): string
    {
        if (empty(trim($text))) {
            throw new \App\Exceptions\AIAnalysisException('Text is empty before normalization');
        }

        $normalized = self::cleanText($text, $maxLength);

        if (empty(trim($normalized))) {
            throw new \App\Exceptions\AIAnalysisException('Text is empty after normalization');
        }

        return $normalized;
    }

    public static function extractTextFromDocxXml(string $xmlContent): string
    {
        try {
            $xml = new \SimpleXMLElement($xmlContent);
            $text = '';

            foreach ($xml->xpath('//w:p') as $paragraph) {
                foreach ($paragraph->xpath('.//w:t') as $t) {
                    $text .= (string)$t . ' ';
                }
                $text .= "\n";
            }

            return $text ?: '';
        } catch (\Throwable $e) {
            Log::warning('FileHelper: XML parsing fallback used', [
                'error' => $e->getMessage()
            ]);

            return preg_replace('/<[^>]*>/', '', $xmlContent);
        }
    }

    public static function validateString(
        string $value,
        string $fallback,
        int $minLength = 1,
        int $maxLength = PHP_INT_MAX
    ): string {
        $value = trim($value ?? '');
        $value = mb_substr($value, 0, $maxLength);

        return (strlen($value) >= $minLength && !empty($value)) ? $value : $fallback;
    }

    public static function validateArray(
        mixed $value,
        array $fallback,
        int $maxItems = 10
    ): array {
        if (!is_array($value)) {
            return $fallback;
        }

        $items = [];
        foreach (array_slice($value, 0, $maxItems) as $item) {
            if (is_string($item) && !empty(trim($item))) {
                $items[] = trim(mb_substr($item, 0, 500));
            }
        }

        return !empty($items) ? $items : $fallback;
    }

    public static function extractJsonFromResponse(string $text): ?array
    {
        // Try direct JSON decode
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Try markdown code block
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $text, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // Try finding JSON object
        if (preg_match('/(\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\})(?=\s*$|```)/s', $text, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        return null;
    }

    public static function generateCacheKey(string $text, string $prefix = 'cache'): string
    {
        $hash = hash('sha256', $text);
        return "{$prefix}:{$hash}";
    }
}
