<?php

namespace App\Services\File;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIAnalysisService
{
    private string $apiKey;
    private string $apiEndpoint;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');

        // ملاحظة: تم تصحيح الرابط وإزالة الأقواس الزائدة
        $this->apiEndpoint = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
    }

    public function analyzeChunk(string $chunkText): array
    {
        $startTime = microtime(true);

        // تنظيف النص وتحديده لضمان عدم تجاوز حدود الـ Context
        $chunkText = mb_substr(trim($chunkText), 0, 12000);

        try {
            $response = Http::retry(3, 200)
                ->timeout(60)
                ->post($this->apiEndpoint . '?key=' . $this->apiKey, [
                    'contents' => [
                        ['parts' => [['text' => $this->buildAnalysisPrompt($chunkText)]]]
                    ],
                    'generationConfig' => [
                        'response_mime_type' => 'application/json',
                        'max_output_tokens' => 2048, // ضروري لضمان عدم انقطاع الـ JSON قبل نهايته
                        'temperature' => 0.2,       // درجة حرارة منخفضة تجعل النتائج أكثر دقة واتباعاً للهيكل
                    ]
                ]);

            if ($response->failed()) {
                Log::error('Gemini API Error Response', ['body' => $response->body()]);
                throw new \Exception("Gemini API Error occurred");
            }

            $data = $response->json();
            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';

            $json = json_decode($text, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('AI JSON Decode Failed', [
                    'error' => json_last_error_msg(),
                    'raw_text' => substr($text, 0, 500) // تسجيل بداية النص لمعرفة المشكلة
                ]);
                return $this->getFallbackResponse();
            }

            Log::info('AI Analysis successful', [
                'execution_time' => round((microtime(true) - $startTime), 2) . 's'
            ]);

            return $json;

        } catch (\Throwable $e) {
            Log::error('AI Analysis Critical Failure', ['message' => $e->getMessage()]);
            return $this->getFallbackResponse();
        }
    }

    private function buildAnalysisPrompt(string $text): string
    {
        // تم توضيح الهيكل للنموذج بشكل أدق لتجنب أخطاء المصفوفات
        return <<<EOT
أنت خبير أكاديمي. حلّل النص التالي واستخرج منه المعلومات المطلوبة بدقة تامة وبصيغة JSON فقط.

الهيكل المطلوب:
{
  "simple_explanation": "شرح مبسط وشامل (150 كلمة)",
  "key_ideas": ["فكرة رئيسية 1", "فكرة رئيسية 2", "فكرة رئيسية 3"],
  "historical_context": "تطور هذا المجال عبر الزمن (100 كلمة)",
  "market_usage": "كيفية الاستفادة من هذا المحتوى في سوق العمل الحالي",
  "related_jobs": ["اسم وظيفة 1", "اسم وظيفة 2", "اسم وظيفة 3"]
}

النص المُراد تحليله:
$text
EOT;
    }

    private function getFallbackResponse(): array
    {
        return [
            'simple_explanation' => 'تعذر تحليل هذا الجزء حالياً، يرجى المحاولة لاحقاً.',
            'key_ideas' => [],
            'historical_context' => 'المعلومات التاريخية غير متوفرة لهذا المقطع.',
            'market_usage' => 'غير متوفر.',
            'related_jobs' => [],
        ];
    }
}
