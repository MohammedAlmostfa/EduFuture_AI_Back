<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class AIAnalysisService
{
    private string $apiKey;
    private string $model;
    private int $maxRetries;
    private int $maxTokens;
    private float $temperature;
    private int $cacheHours;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key');
        $this->model = config('services.gemini.model', 'gemini-1.5-flash');
        $this->maxRetries = config('services.gemini.max_retries', 3);
        $this->maxTokens = config('services.gemini.max_output_tokens', 2048);
        $this->temperature = config('services.gemini.temperature', 0.2);
        $this->cacheHours = config('services.gemini.cache_hours', 24);
    }

    public function analyze(string $text): array
    {
        // تنظيف النص من الأحرف الغريبة
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $cacheKey = 'ai_analysis_' . md5($text);

        return Cache::remember($cacheKey, now()->addHours($this->cacheHours), function () use ($text) {
            return $this->callGeminiWithRetry($text);
        });
    }

    private function callGeminiWithRetry(string $text): array
    {
        if (empty($this->apiKey)) {
            Log::error('Gemini API key is missing');
            return $this->getFallbackResponse('مفتاح API غير مُهيأ. يرجى مراجعة الإعدادات.');
        }

        $attempt = 1;
        $lastError = null;

        while ($attempt <= $this->maxRetries) {
            try {
                $url = "https://generativelanguage.googleapis.com/v1/models/{$this->model}:generateContent?key=" . urlencode($this->apiKey);

                Log::info("Calling Gemini API - Attempt {$attempt}/{$this->maxRetries}", [
                    'model' => $this->model,
                    'text_length' => strlen($text)
                ]);

                $response = Http::timeout(90)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                    ])
                    ->post($url, $this->buildPayload($text));

                if ($response->successful()) {
                    $result = $this->parseResponse($response->json());
                    Log::info('Gemini analysis successful', ['ideas_count' => count($result['key_ideas'])]);
                    return $result;
                }

                // معالجة أخطاء HTTP محددة
                $status = $response->status();
                $errorBody = $response->json();
                $errorMsg = $errorBody['error']['message'] ?? $response->body();

                Log::warning("Gemini API error", [
                    'status' => $status,
                    'message' => $errorMsg,
                    'attempt' => $attempt
                ]);

                if ($status === 400) {
                    // خطأ في الطلب - لا معنى لإعادة المحاولة
                    throw new \Exception("طلب غير صحيح: {$errorMsg}");
                }

                if ($status === 403 || $status === 401) {
                    throw new \Exception("مشكلة في المصادقة: تحقق من مفتاح API");
                }

                if ($status === 429 || $status >= 500) {
                    // خطأ مؤقت: نعيد المحاولة
                    $wait = min(2 ** ($attempt - 1), 15);
                    Log::info("تأخير {$wait} ثوانٍ قبل إعادة المحاولة");
                    sleep($wait);
                    $attempt++;
                    continue;
                }

                // أي خطأ آخر نعتبره غير قابل للإعادة
                throw new \Exception("HTTP {$status}: {$errorMsg}");

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $lastError = "مشكلة في الاتصال بالإنترنت أو الخادم غير متاح: " . $e->getMessage();
                Log::warning($lastError, ['attempt' => $attempt]);
                if ($attempt < $this->maxRetries) {
                    sleep(min(2 ** ($attempt - 1), 15));
                    $attempt++;
                } else {
                    break;
                }
            } catch (\Exception $e) {
                $lastError = $e->getMessage();
                Log::error("Gemini call exception", ['message' => $lastError, 'attempt' => $attempt]);
                if ($attempt < $this->maxRetries && strpos($lastError, 'HTTP 40') === false) {
                    sleep(min(2 ** ($attempt - 1), 10));
                    $attempt++;
                } else {
                    break;
                }
            }
        }

        Log::error("جميع محاولات Gemini باءت بالفشل", ['last_error' => $lastError]);
        return $this->getFallbackResponse($lastError ?? 'فشل الاتصال بعد عدة محاولات');
    }

    private function buildPayload(string $text): array
    {
        $prompt = <<<PROMPT
أنت خبير أكاديمي متخصص في تحليل المحتوى العلمي والتقني.

المطلوب: استخرج من النص التالي المعلومات التالية بدقة، وأخرجها بصيغة JSON فقط، بدون أي نص إضافي أو أحرف قبل أو بعد الـ JSON.

{
  "simple_explanation": "شرح مبسط وشامل (150-200 كلمة)",
  "key_ideas": ["فكرة رئيسية 1", "فكرة رئيسية 2", "فكرة رئيسية 3"],
  "historical_context": "تطور هذا المجال عبر التاريخ (100-150 كلمة)",
  "market_usage": "كيفية تطبيق هذا المحتوى في سوق العمل الحالي",
  "related_jobs": ["وظيفة 1", "وظيفة 2", "وظيفة 3"]
}

النص المراد تحليله:
{$text}
PROMPT;

        return [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => $this->temperature,
                'maxOutputTokens' => $this->maxTokens,
                'topP' => 0.95,
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_NONE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_NONE'
                ]
            ]
        ];
    }

    private function parseResponse(array $data): array
    {
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            Log::error('Unexpected Gemini response structure', ['response' => json_encode($data)]);
            throw new \Exception('استجابة غير متوقعة من Gemini');
        }

        $responseText = $data['candidates'][0]['content']['parts'][0]['text'];

        // استخراج JSON من النص (قد يكون محاطاً بعلامات ```json)
        preg_match('/```json\s*(\{.*?\})\s*```|(\{.*\})/s', $responseText, $matches);
        $jsonStr = $matches[1] ?? $matches[2] ?? $responseText;

        $result = json_decode($jsonStr, true);

        if (!is_array($result)) {
            Log::error('Failed to parse JSON from Gemini', ['raw' => substr($responseText, 0, 500)]);
            throw new \Exception('الرد من Gemini ليس بصيغة JSON صالحة');
        }

        return [
            'simple_explanation' => $result['simple_explanation'] ?? 'لم يتم العثور على شرح',
            'key_ideas' => array_slice($result['key_ideas'] ?? [], 0, 5),
            'historical_context' => $result['historical_context'] ?? 'لم يتم العثور على سياق تاريخي',
            'market_usage' => $result['market_usage'] ?? 'لم يتم العثور على معلومات عن سوق العمل',
            'related_jobs' => array_slice($result['related_jobs'] ?? [], 0, 5),
        ];
    }

    private function getFallbackResponse(string $errorMessage = ''): array
    {
        return [
            'simple_explanation' => "تعذر تحليل المحتوى حالياً. السبب: {$errorMessage} يرجى التحقق من إعدادات API أو المحاولة لاحقاً.",
            'key_ideas' => ['تعذر استخراج الأفكار الرئيسية'],
            'historical_context' => 'غير متوفر بسبب خطأ تقني.',
            'market_usage' => 'غير متوفر حالياً.',
            'related_jobs' => [],
        ];
    }
}
