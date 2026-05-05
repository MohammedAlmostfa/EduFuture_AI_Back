<?php

namespace App\Services\File;

use Illuminate\Support\Facades\Log;
use App\Exceptions\TextChunkingException;
use App\Helpers\FileHelper;
use Throwable;

class TextChunkingService
{
    private const DEFAULT_WORDS_PER_CHUNK = 2000;
    private const DEFAULT_OVERLAP = 100;
    private const MIN_CHUNK_SIZE = 100;
    private const MAX_CHUNK_SIZE = 5000;
    private const MIN_OVERLAP_PERCENT = 5;

    public function chunk(
        string $text,
        int $wordsPerChunk = self::DEFAULT_WORDS_PER_CHUNK,
        int $overlap = self::DEFAULT_OVERLAP
    ): array {
        $startTime = microtime(true);

        try {
            $wordsPerChunk = $this->validateChunkSize($wordsPerChunk);
            $overlap = $this->validateOverlap($overlap, $wordsPerChunk);

            $text = FileHelper::cleanText($text);

            if (empty($text)) {
                Log::warning('TextChunking: Empty text received');
                return [];
            }

            $words = $this->tokenizeText($text);
            $totalWords = count($words);

            Log::info('TextChunking: Starting', [
                'total_words' => $totalWords,
                'words_per_chunk' => $wordsPerChunk
            ]);

            if ($totalWords <= $wordsPerChunk) {
                return [$text];
            }

            $chunks = $this->performChunking($words, $wordsPerChunk, $overlap);

            Log::info('TextChunking: Completed', [
                'total_chunks' => count($chunks),
                'execution_time' => round((microtime(true) - $startTime), 3) . 's'
            ]);

            return $chunks;

        } catch (TextChunkingException $e) {
            Log::error('TextChunking: Known Error', ['message' => $e->getMessage()]);
            throw $e;
        } catch (Throwable $e) {
            Log::error('TextChunking: Critical Failure', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw new TextChunkingException('Text chunking failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function chunkSmart(
        string $text,
        int $wordsPerChunk = self::DEFAULT_WORDS_PER_CHUNK
    ): array {
        try {
            $text = FileHelper::cleanText($text);

            if (empty($text)) {
                return [];
            }

            $paragraphs = preg_split('/\n\n+/u', $text, -1, PREG_SPLIT_NO_EMPTY);

            if (count($paragraphs) <= 1) {
                return $this->chunk($text, $wordsPerChunk);
            }

            $chunks = [];
            $currentChunk = '';
            $currentWordCount = 0;

            foreach ($paragraphs as $paragraph) {
                $paragraphWords = str_word_count($paragraph);

                if ($currentWordCount + $paragraphWords > $wordsPerChunk && !empty($currentChunk)) {
                    $chunks[] = trim($currentChunk);
                    $currentChunk = '';
                    $currentWordCount = 0;
                }

                $currentChunk .= $paragraph . "\n";
                $currentWordCount += $paragraphWords;
            }

            if (!empty(trim($currentChunk))) {
                $chunks[] = trim($currentChunk);
            }

            Log::info('TextChunking: Smart chunking completed', [
                'total_chunks' => count($chunks),
                'method' => 'paragraph_based'
            ]);

            return $chunks;

        } catch (Throwable $e) {
            Log::warning('TextChunking: Smart chunking failed, falling back', [
                'error' => $e->getMessage()
            ]);
            return $this->chunk($text, $wordsPerChunk);
        }
    }

    public function getChunkingStats(array $chunks): array
    {
        $wordCounts = array_map('str_word_count', $chunks);
        $total = array_sum($wordCounts);

        return [
            'total_chunks' => count($chunks),
            'total_words' => $total,
            'average_words_per_chunk' => count($chunks) > 0 ? round($total / count($chunks), 2) : 0,
            'min_words' => !empty($wordCounts) ? min($wordCounts) : 0,
            'max_words' => !empty($wordCounts) ? max($wordCounts) : 0,
        ];
    }

    private function validateChunkSize(int $wordsPerChunk): int
    {
        if ($wordsPerChunk < self::MIN_CHUNK_SIZE) {
            Log::warning('TextChunking: Chunk size too small, using minimum');
            return self::MIN_CHUNK_SIZE;
        }

        if ($wordsPerChunk > self::MAX_CHUNK_SIZE) {
            Log::warning('TextChunking: Chunk size too large, using maximum');
            return self::MAX_CHUNK_SIZE;
        }

        return $wordsPerChunk;
    }

    private function validateOverlap(int $overlap, int $chunkSize): int
    {
        $minOverlap = (int)($chunkSize * self::MIN_OVERLAP_PERCENT / 100);
        $maxOverlap = (int)($chunkSize * 0.5);

        if ($overlap < $minOverlap) {
            return $minOverlap;
        }

        if ($overlap > $maxOverlap) {
            return $maxOverlap;
        }

        return $overlap;
    }

    private function tokenizeText(string $text): array
    {
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($words, fn($word) => !empty(trim($word)));
    }

    private function performChunking(array $words, int $chunkSize, int $overlap): array
    {
        $chunks = [];
        $step = max(1, $chunkSize - $overlap);
        $totalWords = count($words);

        for ($start = 0; $start < $totalWords; $start += $step) {
            $chunkWords = array_slice($words, $start, $chunkSize);

            if (empty($chunkWords)) {
                continue;
            }

            $chunk = implode(' ', $chunkWords);

            if (str_word_count($chunk) >= self::MIN_CHUNK_SIZE) {
                $chunks[] = $chunk;
            }
        }

        return $chunks;
    }
}
