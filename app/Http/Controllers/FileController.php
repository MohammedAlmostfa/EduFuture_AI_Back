<?php

namespace App\Http\Controllers;

use App\Http\Requests\File\UploadFileRequest;
use App\Services\File\FileUploadService;
use Illuminate\Support\Facades\Log;

class FileController extends Controller
{
    protected FileUploadService $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    public function upload(UploadFileRequest $request)
    {
        // Log::info('Upload request received', [
        //     'user_id' => auth()->id(),
        //     'file_name' => $request->file('file')->getClientOriginalName(),
        // ]);
$validatedData = $request->validated();

            $result = $this->fileUploadService->upload($request['file']);



         return $result['status'] === 200
            ? self::success($result['data'], $result['message'], $result['status'])
            : self::error(null, $result['message'], $result['status']);


    }
}
