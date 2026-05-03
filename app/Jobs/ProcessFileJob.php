<?php

namespace App\Jobs;

use App\Models\File;
use App\Models\AnalysisResult;
use App\Services\File\FileParsingService;
use App\Services\File\TextChunkingService;
use App\Services\File\AIAnalysisService;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessFileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600;
    public $tries = 3;

    public function __construct(public File $file)
    {}

    public function handle()
    {
        Log::info('ProcessFileJob started', [
            'file_id' => $this->file->id,
            'path' => $this->file->path
        ]);

        try {
            // Step 0: update status
            $this->file->update([
                'status' => 'processing'
            ]);

            Log::info('File status updated to processing', [
                'file_id' => $this->file->id
            ]);

            // Step 1: Extract text
            Log::info('Starting text extraction', [
                'file_id' => $this->file->id
            ]);

            $fileParsingService = new FileParsingService();

            // ✅ FIX: correct storage access (NO str_replace, NO storage_path)
            $filePath = Storage::disk('private')->path($this->file->path);

            Log::info('Resolved file path', [
                'file_path' => $filePath,
                'exists' => file_exists($filePath)
            ]);

            // optional safety check
            if (!file_exists($filePath)) {
                throw new \Exception("File not found: " . $filePath);
            }

            $extractedText = $fileParsingService->extractText($filePath);

            Log::info('Text extraction completed', [
                'file_id' => $this->file->id,
                'text_length' => strlen($extractedText)
            ]);

            $this->file->update([
                'extracted_text' => $extractedText,
            ]);

            // Step 2: Chunking
            Log::info('Starting text chunking', [
                'file_id' => $this->file->id
            ]);

            $chunkingService = new TextChunkingService();
            $chunks = $chunkingService->chunk($extractedText, 2000);

            Log::info('Chunking completed', [
                'file_id' => $this->file->id,
                'chunks_count' => count($chunks)
            ]);

            // Step 3: AI Analysis
            Log::info('Starting AI analysis', [
                'file_id' => $this->file->id
            ]);

            $aiService = new AIAnalysisService();

            foreach ($chunks as $index => $chunk) {

                Log::info('Analyzing chunk', [
                    'file_id' => $this->file->id,
                    'chunk_index' => $index,
                    'chunk_length' => strlen($chunk)
                ]);

                $analysis = $aiService->analyzeChunk($chunk);

                AnalysisResult::create([
                    'file_id' => $this->file->id,
                    'chunk_index' => $index,
                    'simple_explanation' => $analysis['simple_explanation'] ?? null,
                    'key_ideas' => json_encode($analysis['key_ideas'] ?? []),
                    'historical_context' => $analysis['historical_context'] ?? null,
                    'market_usage' => $analysis['market_usage'] ?? null,
                    'related_jobs' => json_encode($analysis['related_jobs'] ?? [])
                ]);

                Log::info('Chunk processed', [
                    'file_id' => $this->file->id,
                    'chunk_index' => $index
                ]);
            }

            // Finish
            $this->file->update([
                'status' => 'completed'
            ]);

            Log::info('ProcessFileJob completed successfully', [
                'file_id' => $this->file->id
            ]);

        } catch (\Throwable $e) {

            $this->file->update([
                'status' => 'failed',
                'error_message' => $e->getMessage()
            ]);

            Log::error('ProcessFileJob failed', [
                'file_id' => $this->file->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }
}
