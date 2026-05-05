<?php

namespace App\Services\File;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Exceptions\AIAnalysisException;
use App\Helpers\FileHelper;
use Throwable;

class AIAnalysisService
{
    private string $apiKey;
    private string $apiEndpoint;
    private string $model;
    private int $maxRetries;
    private int $maxTokens;
    private float $temperature;
    private int $chunkSizeLimit;
    private int $cacheHours;
    private int $requestTimeout = 90;

    public function __construct()
    {
        $this->apiKey = config('services.gemini.key', '');
        $this->model = config('services.gemini.model', 'gemini-1.5-flash');
        $this->maxRetries = config('services.gemini.max_retries', 3);
        $this->maxTokens = config('services.gemini.max_output_tokens', 2048);
        $this->temperature = config('services.gemini.temperature', 0.2);
        $this->chunkSizeLimit = config('services.gemini.chunk_size_limit', 12000);
        $this->cacheHours = config('services.gemini.cache_hours', 24);

        $this->apiEndpoint = "https://generativelanguage.googleapis.com/v1/models/{$this->model}:generateContent";

        $this->validateConfiguration();
    }

    public function analyzeChunk(string $chunkText, array $context = []): array
    {
        $startTime = microtime(true);

        try {
            $chunkText = FileHelper::normalizeText($chunkText, $this->chunkSizeLimit);

            $cacheKey = FileHelper::generateCacheKey($chunkText, 'analysis');
            if ($cachedResult = Cache::get($cacheKey)) {
                Log::info('AI Analysis: Cache hit', [
                    'execution_time' => round((microtime(true) - $startTime), 3) . 's'
                ]);
                return $cachedResult;
            }

            $analysis = $this->callGeminiWithRetry($chunkText);
            $validated = $this->validateAnalysisResult($analysis);

            Cache::put($cacheKey, $validated, now()->addHours($this->cacheHours));

            Log::info('AI Analysis: Successful', [
                'execution_time' => round((microtime(true) - $startTime), 3) . 's',
                'text_length' => strlen($chunkText),
                'ideas_count' => count($validated['key_ideas'] ?? [])
            ]);

            return $validated;

        } catch (AIAnalysisException $e) {
            Log::error('AI Analysis: Known Error', [
                'message' => $e->getMessage(),
                'code' => $e->getCode()
            ]);
            return $this->getFallbackResponse();

        } catch (Throwable $e) {
            Log::error('AI Analysis: Critical Failure', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return $this->getFallbackResponse();
        }
    }

    private function callGeminiWithRetry(string $text): array
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                Log::info('Gemini API: Attempt', [
                    'attempt' => $attempt,
                    'max_retries' => $this->maxRetries
                ]);

                $response = Http::timeout($this->requestTimeout)
                    ->withHeaders([
                        'Content-Type' => 'application/json',
                        'User-Agent' => 'FileAnalysisSystem/1.0'
                    ])
                    ->post($this->apiEndpoint . '?key=' . urlencode($this->apiKey), $this->buildRequestPayload($text));

                if ($response->failed()) {
                    $this->handleApiError($response, $attempt);
                    continue;
                }

                $data = $response->json();
                return $this->extractAnalysisFromResponse($data);

            } catch (AIAnalysisException $e) {
                $lastException = $e;
                if ($attempt < $this->maxRetries) {
                    sleep(min(2 ** ($attempt - 1), 10));
                }
            } catch (Throwable $e) {
                $lastException = new AIAnalysisException($e->getMessage(), 0, $e);
                if ($attempt < $this->maxRetries) {
                    sleep(min(2 ** ($attempt - 1), 10));
                }
            }
        }

        throw $lastException ?? new AIAnalysisException('All retry attempts failed');
    }

    private function handleApiError($response, int $attempt): void
    {
        $statusCode = $response->status();
        $body = $response->json() ?? $response->body();

        Log::warning('Gemini API: HTTP Error', [
            'status' => $statusCode,
            'body' => is_array($body) ? json_encode($body) : substr((string)$body, 0, 500)
        ]);

        if ($statusCode === 429) {
            // Rate limiting
            return;
        }

        if ($statusCode >= 500) {
            // Server error - retry
            return;
        }

        throw new AIAnalysisException("Gemini API returned {$statusCode}", $statusCode);
    }

    private function extractAnalysisFromResponse(array $data): array
    {
        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            throw new AIAnalysisException('Invalid response structure from Gemini API');
        }

        $responseText = $data['candidates'][0]['content']['parts'][0]['text'];
        $json = FileHelper::extractJsonFromResponse($responseText);

        if (!is_array($json)) {
            Log::error('AI Analysis: Invalid JSON response', [
                'raw_text' => substr($responseText, 0, 300)
            ]);
            throw new AIAnalysisException('Failed to parse JSON response');
        }

        return $json;
    }

    private function validateAnalysisResult(array $result): array
    {
        return [
            'simple_explanation' => FileHelper::validateString(
                $result['simple_explanation'] ?? '',
                'شرح غير متوفر',
                10,
                2000
            ),
            'key_ideas' => FileHelper::validateArray(
                $result['key_ideas'] ?? [],
                [],
                5
            ),
            'historical_context' => FileHelper::validateString(
                $result['historical_context'] ?? '',
                'السياق التاريخي غير متوفر',
                10,
                1000
            ),
            'market_usage' => FileHelper::validateString(
                $result['market_usage'] ?? '',
                'الاستخدام في السوق غير متوفر',
                10,
                1000
            ),
            'related_jobs' => FileHelper::validateArray(
                $result['related_jobs'] ?? [],
                [],
                10
            ),
        ];
    }

    private function buildRequestPayload(string $text): array
    {
        return [
            'contents' => [
                [
                    'parts' => [
                        [
                            'text' => $this->buildAnalysisPrompt($text)
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'maxOutputTokens' => $this->maxTokens,
                'temperature' => $this->temperature,
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_UNSPECIFIED',
                    'threshold' => 'BLOCK_NONE'
                ]
            ]
        ];
    }

    private function buildAnalysisPrompt(string $text): string
    {
        return <<<'EOT'
أنت خبير أكاديمي متخصص في تحليل وشرح المحتوى العلمي والتقني.

قم بتحليل النص التالي واستخرج المعلومات المطلوبة بدقة تامة وبصيغة JSON فقط، بدون أي نص إضافي.

تأكد من أن الرد يكون JSON صحيح بنسبة 100% يتبع هذا الهيكل بالضبط:
{
  "simple_explanation": "شرح مبسط وشامل يفهمه الطالب العادي (150-200 كلمة)",
  "key_ideas": ["فكرة رئيسية 1", "فكرة رئيسية 2", "فكرة رئيسية 3"],
  "historical_context": "تطور هذا المجال عبر التاريخ وأهم المحطات (100-150 كلمة)",
  "market_usage": "كيفية تطبيق هذا المحتوى في سوق العمل الحالي وفرص التوظيف",
  "related_jobs": ["اسم وظيفة 1", "اسم وظيفة 2", "اسم وظيفة 3"]
}

النص المراد تحليله:
EOT . $text;
    }

    private function getFallbackResponse(): array
    {
        return [
            'simple_explanation' => 'تعذر تحليل هذا الجزء حالياً بسبب خطأ تقني. يرجى المحاولة لاحقاً.',
            'key_ideas' => [],
            'historical_context' => 'معلومات السياق التاريخي غير متوفرة حالياً.',
            'market_usage' => 'معلومات الاستخدام السوقي غير متوفرة حالياً.',
            'related_jobs' => [],
        ];
    }

    private function validateConfiguration(): void
    {
        if (empty($this->apiKey)) {
            throw new AIAnalysisException('Gemini API key is not configured');
        }

        $this->maxRetries = max(1, min($this->maxRetries, 10));
        $this->maxTokens = max(100, min($this->maxTokens, 4000));
        $this->temperature = max(0, min($this->temperature, 1));
    }
}
