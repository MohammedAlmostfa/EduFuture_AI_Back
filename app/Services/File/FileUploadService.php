<?php

namespace App\Services\File;

use App\Jobs\ProcessFileJob;
use App\Models\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FileUploadService
{
    public function upload($file)
    {
        try {
            DB::beginTransaction();

            // 🔥 التخزين الصحيح داخل disk = private
            $path = $file->store('files', 'private');

            Log::info('File stored successfully', [
                'path' => $path,
                'disk' => 'private',
            ]);

            // إنشاء سجل في قاعدة البيانات
            $uploadedFile = File::create([
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'size' => $file->getSize(),
                'type' => $file->getClientMimeType(),
                'user_id' => auth()->id(),
            ]);

            Log::info('File record created', [
                'file_id' => $uploadedFile->id,
                'path' => $uploadedFile->path,
            ]);

            // إرسال Job للمعالجة
            ProcessFileJob::dispatch($uploadedFile)->onQueue('default');

            Log::info('ProcessFileJob dispatched', [
                'file_id' => $uploadedFile->id,
                'queue' => 'default',
            ]);

            DB::commit();

            Log::info('File upload completed successfully', [
                'file_id' => $uploadedFile->id,
            ]);

            return [
                'status' => 200,
                'message' => 'تم رفع الملف بنجاح',
                'data' => $uploadedFile
            ];

        } catch (\Throwable $e) {

            DB::rollBack();

            Log::error('File upload failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id(),
            ]);

            return [
                'status' => 500,
                'message' => 'فشل رفع الملف',
                'error' => $e->getMessage(),
                'data' => null
            ];
        }
    }
}
