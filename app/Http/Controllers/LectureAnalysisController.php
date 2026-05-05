<?php

namespace App\Http\Controllers;

use App\Exceptions\AIAnalysisException;
use App\Exceptions\ExtractionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\UploadLectureRequest;
use App\Services\AI\AIAnalysisService;
use App\Services\File\TextExtractor;
use Illuminate\Support\Facades\Log;

class LectureAnalysisController extends Controller
{
    public function __construct(
        private TextExtractor $extractor,
        private AIAnalysisService $aiService
    ) {}

    public function uploadAndAnalyze(UploadLectureRequest $request)
    {
        try {
             set_time_limit(180); // 3 minutes
            $file = $request->file('file');
            $text = $this->extractor->extract($file);

            if (empty(trim($text))) {
                return response()->json([
                    'error' => 'لم نتمكن من استخراج نص من الملف. تأكد من أنه ليس فارغاً.',
                ], 422);
            }

            $analysis = $this->aiService->analyze($text);

            return response()->json([
                'success' => true,
                'data' => $analysis,
            ]);

        } catch (ExtractionException $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        } catch (AIAnalysisException $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        } catch (\Throwable $e) {
            Log::error($e->getMessage());
            return response()->json(['error' => 'حدث خطأ داخلي في الخادم'], 500);
        }
    }
}
