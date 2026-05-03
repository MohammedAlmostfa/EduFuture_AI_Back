<?php

namespace App\Services\File;

use Illuminate\Support\Facades\Log;

class TextChunkingService
{
    public function chunk(string $text, int $wordsPerChunk = 2000, int $overlap = 100): array
    {
        $originalLength = strlen($text);

        // تنظيف النص بشكل أفضل
        $text = preg_replace('/\s+/u', ' ', trim($text));

        if ($text === '') {

            Log::warning('ChunkingService: Empty text received', [
                'original_length' => $originalLength,
            ]);

            return [];
        }

        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $totalWords = count($words);

        Log::info('ChunkingService: Starting chunking', [
            'total_words' => $totalWords,
            'words_per_chunk' => $wordsPerChunk,
            'overlap' => $overlap,
        ]);

        if ($totalWords <= $wordsPerChunk) {

            Log::info('ChunkingService: Single chunk returned', [
                'total_words' => $totalWords,
            ]);

            return [$text];
        }

        $chunks = [];
        $step = max(1, $wordsPerChunk - $overlap);

        for ($start = 0; $start < $totalWords; $start += $step) {

            $chunkWords = array_slice($words, $start, $wordsPerChunk);

            if (empty($chunkWords)) {
                continue;
            }

            $chunks[] = implode(' ', $chunkWords);
        }

        Log::info('ChunkingService: Chunking completed', [
            'total_chunks' => count($chunks),
            'first_chunk_size' => isset($chunks[0]) ? str_word_count($chunks[0]) : 0,
        ]);

        return $chunks;
    }
}
