<?php

namespace App\Jobs;

use App\Models\File;
use App\Models\AnalysisResult;
use App\Services\File\FileParsingService;
use App\Services\File\TextChunkingService;
use App\Services\File\AIAnalysisService;
use App\Events\FileProcessingStarted;
use App\Events\FileProcessingCompleted;
use App\Events\FileProcessingFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    public $tries = 3;
    public $backoff = [60, 300, 900];
    public $maxExceptions = 3;

    public File $file;
    private FileParsingService $parsingService;
    private TextChunkingService $chunkingService;
    private AIAnalysisService $analysisService;

    public function __construct(File $file)
    {
        $this->file = $file;
        $this->onQueue('files');
    }

    public function handle(): void
    {
        Log::info('ProcessFileJob: Starting', [
            'file_id' => $this->file->id,
            'user_id' => $this->file->user_id,
            'file_name' => $this->file->name,
            'file_size' => $this->file->size
        ]);

        $startTime = microtime(true);

        try {
            $this->initializeServices();
            $this->updateFileStatus('processing');
            event(new FileProcessingStarted($this->file));

            // Step 1: Extract text
            Log::info('ProcessFileJob: Step 1 - Text Extraction', ['file_id' => $this->file->id]);
            $extractedText = $this->extractText();

            // Step 2: Chunk text
            Log::info('ProcessFileJob: Step 2 - Text Chunking', [
                'file_id' => $this->file->id,
                'text_length' => strlen($extractedText)
            ]);
            $chunks = $this->chunkText($extractedText);

            // Step 3: Analyze chunks
            Log::info('ProcessFileJob: Step 3 - AI Analysis', [
                'file_id' => $this->file->id,
                'chunks_count' => count($chunks)
            ]);
            $this->analyzeChunks($chunks);

            // Step 4: Complete
            $this->updateFileStatus('completed');
            event(new FileProcessingCompleted($this->file));

            Log::info('ProcessFileJob: Completed successfully', [
                'file_id' => $this->file->id,
                'execution_time' => round((microtime(true) - $startTime), 2) . 's',
                'chunks_processed' => count($chunks)
            ]);

        } catch (Throwable $e) {
            $this->handleJobFailure($e, $startTime);
        }
    }

    private function initializeServices(): void
    {
        $this->parsingService = new FileParsingService();
        $this->chunkingService = new TextChunkingService();
        $this->analysisService = new AIAnalysisService();
    }

    private function extractText(): string
    {
        try {
            $filePath = Storage::disk('private')->path($this->file->path);

            Log::info('ProcessFileJob: Resolving file path', [
                'file_id' => $this->file->id,
                'path' => $filePath,
                'exists' => file_exists($filePath)
            ]);

            if (!file_exists($filePath)) {
                throw new \Exception("File not found: {$filePath}");
            }

            $extractedText = $this->parsingService->extractText($filePath);

            if (empty($extractedText)) {
                throw new \Exception('File contains no extractable text');
            }

            $this->file->update([
                'extracted_text' => $extractedText
            ]);

            Log::info('ProcessFileJob: Text extracted', [
                'file_id' => $this->file->id,
                'text_length' => strlen($extractedText)
            ]);

            return $extractedText;

        } catch (Throwable $e) {
            Log::error('ProcessFileJob: Text extraction failed', [
                'file_id' => $this->file->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function chunkText(string $text): array
    {
        try {
            $chunks = strpos($text, "\n") !== false
                ? $this->chunkingService->chunkSmart($text)
                : $this->chunkingService->chunk($text);

            if (empty($chunks)) {
                throw new \Exception('Failed to chunk text');
            }

            $stats = $this->chunkingService->getChunkingStats($chunks);

            Log::info('ProcessFileJob: Text chunked', [
                'file_id' => $this->file->id,
                'chunks' => $stats['total_chunks'],
                'total_words' => $stats['total_words'],
                'average' => $stats['average_words_per_chunk']
            ]);

            return $chunks;

        } catch (Throwable $e) {
            Log::error('ProcessFileJob: Text chunking failed', [
                'file_id' => $this->file->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function analyzeChunks(array $chunks): void
    {
        $totalChunks = count($chunks);
        $successCount = 0;
        $failureCount = 0;

        DB::beginTransaction();

        try {
            foreach ($chunks as $index => $chunk) {
                $chunkNumber = $index + 1;

                try {
                    Log::info('ProcessFileJob: Analyzing chunk', [
                        'file_id' => $this->file->id,
                        'chunk' => "{$chunkNumber}/{$totalChunks}",
                        'chunk_length' => strlen($chunk)
                    ]);

                    $analysis = $this->analysisService->analyzeChunk($chunk, [
                        'file_id' => $this->file->id,
                        'chunk_index' => $index
                    ]);

                    AnalysisResult::create([
                        'file_id' => $this->file->id,
                        'chunk_index' => $index,
                        'chunk_text' => $chunk,
                        'simple_explanation' => $analysis['simple_explanation'] ?? null,
                        'key_ideas' => json_encode($analysis['key_ideas'] ?? [], JSON_UNESCAPED_UNICODE),
                        'historical_context' => $analysis['historical_context'] ?? null,
                        'market_usage' => $analysis['market_usage'] ?? null,
                        'related_jobs' => json_encode($analysis['related_jobs'] ?? [], JSON_UNESCAPED_UNICODE),
                        'analyzed_at' => now()
                    ]);

                    $successCount++;

                    Log::info('ProcessFileJob: Chunk analyzed', [
                        'file_id' => $this->file->id,
                        'chunk' => "{$chunkNumber}/{$totalChunks}",
                        'success_rate' => round(($successCount / $chunkNumber) * 100, 2) . '%'
                    ]);

                } catch (Throwable $e) {
                    $failureCount++;

                    Log::warning('ProcessFileJob: Chunk analysis failed, continuing', [
                        'file_id' => $this->file->id,
                        'chunk' => "{$chunkNumber}/{$totalChunks}",
                        'error' => $e->getMessage()
                    ]);

                    continue;
                }
            }

            DB::commit();

            $this->file->update([
                'chunks_total' => $totalChunks,
                'chunks_processed' => $successCount,
                'chunks_failed' => $failureCount
            ]);

            Log::info('ProcessFileJob: Analysis summary', [
                'file_id' => $this->file->id,
                'total' => $totalChunks,
                'success' => $successCount,
                'failure' => $failureCount,
                'success_rate' => round(($successCount / $totalChunks) * 100, 2) . '%'
            ]);

            if ($failureCount > ($totalChunks * 0.5)) {
                throw new \Exception(
                    "More than 50% of chunks failed to analyze ({$failureCount}/{$totalChunks})"
                );
            }

        } catch (Throwable $e) {
            DB::rollBack();
            Log::error('ProcessFileJob: Analysis transaction failed', [
                'file_id' => $this->file->id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function updateFileStatus(string $status, ?string $errorMessage = null): void
    {
        try {
            $this->file->update([
                'status' => $status,
                'error_message' => $errorMessage
            ]);

            Log::info('ProcessFileJob: Status updated', [
                'file_id' => $this->file->id,
                'status' => $status
            ]);

        } catch (Throwable $e) {
            Log::error('ProcessFileJob: Failed to update status', [
                'file_id' => $this->file->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function handleJobFailure(Throwable $e, float $startTime): void
    {
        $errorMessage = $e->getMessage();
        $executionTime = round((microtime(true) - $startTime), 2);

        Log::error('ProcessFileJob: Failed', [
            'file_id' => $this->file->id,
            'attempt' => $this->attempts(),
            'max_attempts' => $this->tries,
            'error' => $errorMessage,
            'execution_time' => $executionTime . 's'
        ]);

        $this->updateFileStatus('failed', $errorMessage);
        event(new FileProcessingFailed($this->file, $e));

        if ($this->attempts() >= $this->tries) {
            Log::error('ProcessFileJob: Exhausted all retries', [
                'file_id' => $this->file->id,
                'total_attempts' => $this->attempts()
            ]);
        }

        throw $e;
    }

    public function failed(Throwable $e): void
    {
        Log::critical('ProcessFileJob: Permanently failed', [
            'file_id' => $this->file->id,
            'final_error' => $e->getMessage(),
            'total_attempts' => $this->attempts()
        ]);

        $this->file->update([
            'status' => 'failed',
            'error_message' => 'Processing failed after ' . $this->tries . ' attempts'
        ]);

        event(new FileProcessingFailed($this->file, $e));
    }

    public function timeout(): void
    {
        Log::warning('ProcessFileJob: Timeout', [
            'file_id' => $this->file->id,
            'timeout' => $this->timeout
        ]);

        $this->updateFileStatus('failed', 'Processing timeout');
    }
}
