<?php

namespace App\Services\File;

use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FileParsingService
{
    public function extractText(string $filePath): string
    {
        Log::info('FileParsing: start', [
            'file_path' => $filePath,
        ]);

        // ❌ بدل file_exists استخدم Storage check
        if (!file_exists($filePath)) {
            Log::error('FileParsing: file not found', [
                'file_path' => $filePath,
            ]);

            throw new \Exception("الملف غير موجود: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        Log::info('FileParsing: extension detected', [
            'extension' => $extension,
        ]);

        return match ($extension) {
            'pdf' => $this->extractFromPDF($filePath),
            'txt' => $this->extractFromTxt($filePath),
            default => throw new \Exception('صيغة الملف غير مدعومة حالياً: ' . $extension),
        };
    }

    private function extractFromPDF(string $filePath): string
    {
        Log::info('FileParsing: PDF extraction started');

        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);

        $text = '';

        foreach ($pdf->getPages() as $page) {
            $text .= $page->getText() . ' ';
        }

        Log::info('FileParsing: PDF extraction completed', [
            'length' => strlen($text),
        ]);

        return $this->cleanText($text);
    }

    private function extractFromTxt(string $filePath): string
    {
        Log::info('FileParsing: TXT extraction started');

        $text = file_get_contents($filePath);

        if ($text === false) {

            Log::error('FileParsing: TXT read failed', [
                'file_path' => $filePath,
            ]);

            throw new \Exception('فشل قراءة ملف TXT');
        }

        Log::info('FileParsing: TXT extraction completed', [
            'length' => strlen($text),
        ]);

        return $this->cleanText($text);
    }

    private function cleanText(string $text): string
    {
        $before = strlen($text);

        $text = preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);

        Log::info('FileParsing: text cleaned', [
            'before' => $before,
            'after' => strlen($text),
        ]);

        return $text;
    }
}
