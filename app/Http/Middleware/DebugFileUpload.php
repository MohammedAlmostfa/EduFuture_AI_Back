<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DebugFileUpload
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('api/files/upload')) {
            Log::info('Raw request debug', [
                'method' => $request->getMethod(),
                'content_type' => $request->header('Content-Type'),
                'has_file' => $request->hasFile('file'),
                'file_exists' => $request->file('file') !== null,
                'files_all' => $request->files->keys(),
                'input_all' => array_keys($request->input()),
            ]);

            // التحقق المفصل
            $file = $request->file('file');
            if ($file) {
                Log::info('File exists - detailed check', [
                    'name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'is_valid' => $file->isValid(),
                    'error' => $file->getError(),
                    'error_message' => $this->getUploadErrorMessage($file->getError()),
                    'tmp_name' => $file->getPathname(),
                    'real_path' => realpath($file->getPathname()),
                ]);
            } else {
                Log::warning('File is null!');
            }
        }

        return $next($request);
    }

    private function getUploadErrorMessage($code)
    {
        $messages = [
            UPLOAD_ERR_OK => 'No error',
            UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form max_file_size',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write to disk',
            UPLOAD_ERR_EXTENSION => 'File upload blocked by extension',
        ];

        return $messages[$code] ?? 'Unknown error';
    }
}
