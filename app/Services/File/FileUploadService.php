<?php

namespace App\Services\File;

use App\Jobs\ProcessFileJob;
use App\Models\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\UploadedFile;
use App\Exceptions\FileUploadException;
use App\Helpers\FileHelper;
use Throwable;

class FileUploadService
{
    private const ALLOWED_MIME_TYPES = [
        'pdf' => 'application/pdf',
        'txt' => 'text/plain',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];
    private const MAX_FILE_SIZE = 104857600; // 100 MB

    public function upload(UploadedFile $uploadedFile): array
    {
        $userId = auth()->id();
        $startTime = microtime(true);

        DB::beginTransaction();

        try {
            $this->validateUploadedFile($uploadedFile);

            Log::info('FileUpload: Starting', [
                'user_id' => $userId,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'size' => $uploadedFile->getSize()
            ]);

            // Store file
            $path = FileHelper::storeFile($uploadedFile, $userId);

            // Create database record
            $file = File::create([
                'name' => FileHelper::sanitizeFileName($uploadedFile->getClientOriginalName()),
                'path' => $path,
                'size' => $uploadedFile->getSize(),
                'type' => $uploadedFile->getClientMimeType(),
                'extension' => strtolower($uploadedFile->getClientOriginalExtension()),
                'user_id' => $userId,
                'status' => 'pending',
                'checksum' => FileHelper::generateFileChecksum($uploadedFile),
            ]);

            Log::info('FileUpload: File record created', [
                'file_id' => $file->id,
                'path' => $file->path
            ]);

            // Dispatch processing job
            ProcessFileJob::dispatch($file)->onQueue('files');

            Log::info('FileUpload: Job dispatched', [
                'file_id' => $file->id,
                'queue' => 'files'
            ]);

            DB::commit();

            Log::info('FileUpload: Completed', [
                'file_id' => $file->id,
                'execution_time' => round((microtime(true) - $startTime), 3) . 's'
            ]);

            return [
                'status' => 200,
                'message' => 'تم رفع الملف بنجاح وبدأ معالجته',
                'data' => [
                    'file_id' => $file->id,
                    'name' => $file->name,
                    'size' => $file->size,
                    'status' => $file->status,
                ]
            ];

        } catch (FileUploadException $e) {
            DB::rollBack();
            Log::error('FileUpload: Validation Error', [
                'error' => $e->getMessage(),
                'code' => $e->getCode()
            ]);

            return [
                'status' => 422,
                'message' => 'خطأ في التحقق من الملف',
                'error' => $e->getMessage(),
                'data' => null
            ];

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('FileUpload: Critical Failure', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $userId,
            ]);

            return [
                'status' => 500,
                'message' => 'حدث خطأ أثناء رفع الملف',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
                'data' => null
            ];
        }
    }

    private function validateUploadedFile(UploadedFile $file): void
    {
        // Check file size
        if ($file->getSize() > self::MAX_FILE_SIZE) {
            throw new FileUploadException(
                'حجم الملف يتجاوز الحد الأقصى (' . FileHelper::formatBytes($file->getSize()) . ')'
            );
        }

        // Check extension
        $extension = strtolower($file->getClientOriginalExtension());
        if (!array_key_exists($extension, self::ALLOWED_MIME_TYPES)) {
            throw new FileUploadException(
                'نوع الملف غير مسموح. الأنواع المسموحة: ' . implode(', ', array_keys(self::ALLOWED_MIME_TYPES))
            );
        }

        // Verify MIME type matches extension
        $expectedMimeType = self::ALLOWED_MIME_TYPES[$extension];
        $actualMimeType = $file->getClientMimeType();

        if ($actualMimeType !== $expectedMimeType && !$this->isValidMimeVariant($extension, $actualMimeType)) {
            Log::warning('FileUpload: MIME type mismatch', [
                'extension' => $extension,
                'expected' => $expectedMimeType,
                'actual' => $actualMimeType
            ]);
        }
    }

    private function isValidMimeVariant(string $extension, string $mimeType): bool
    {
        $variants = [
            'docx' => [
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/octet-stream',
            ],
            'pdf' => [
                'application/pdf',
                'application/octet-stream',
            ],
            'txt' => [
                'text/plain',
                'application/octet-stream',
            ],
        ];

        return in_array($mimeType, $variants[$extension] ?? [], true);
    }
}
