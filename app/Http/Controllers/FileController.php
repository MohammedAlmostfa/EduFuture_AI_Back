<?php

namespace App\Http\Controllers;

use App\Http\Requests\File\UploadFileRequest;
use App\Services\File\FileUploadService;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;

class FileController extends Controller
{
    private FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function upload(UploadFileRequest $request): JsonResponse
    {
        Log::info('FileController: Upload request received', [
            'user_id' => auth()->id(),
            'file_name' => $request->file('file')->getClientOriginalName(),
        ]);

        $result = $this->fileUploadService->upload($request->file('file'));

        if ($result['status'] === 200) {
            return $this->success($result['data'], $result['message'], $result['status']);
        }

        return $this->error(null, $result['message'], $result['status']);
    }
}
