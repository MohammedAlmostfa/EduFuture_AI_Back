<?php

namespace App\Services\File;

use Smalot\PdfParser\Parser as PdfParser;
use PhpOffice\PhpWord\IOFactory;
use Illuminate\Http\UploadedFile;
use App\Exceptions\ExtractionException;
use Illuminate\Support\Facades\Log;

class TextExtractor
{
    public function extract(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension());

        Log::info('Starting text extraction', [
            'file_name' => $file->getClientOriginalName(),
            'extension' => $extension,
            'size' => $file->getSize(),
        ]);

        try {
            $result = match ($extension) {
                'pdf'   => $this->fromPdf($file),
                'docx'  => $this->fromDocx($file),
                'txt'   => $this->fromTxt($file),
                default => throw new ExtractionException("نوع الملف غير مدعوم: {$extension}"),
            };

            // ✅ Log extracted text (safe preview)
            Log::info('Extracted text', [
                'file_name' => $file->getClientOriginalName(),
                'length' => strlen($result),
                'preview' => mb_substr($result, 0, 500),
            ]);

            // ⚠️ Optional: log full text only in local/dev
            if (app()->environment('local')) {
                Log::debug('Full extracted text', [
                    'file_name' => $file->getClientOriginalName(),
                    'text' => $result,
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Text extraction failed', [
                'file_name' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function fromPdf(UploadedFile $file): string
    {
        try {
            Log::info('Extracting from PDF', [
                'file' => $file->getClientOriginalName()
            ]);

            $parser = new PdfParser();
            $pdf = $parser->parseFile($file->getPathname());
            $text = $pdf->getText();

            return trim($text) ?: 'لا يمكن استخراج نص من PDF';

        } catch (\Exception $e) {
            Log::error('PDF extraction error', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            throw new ExtractionException('فشل استخراج النص من PDF: ' . $e->getMessage());
        }
    }

    private function fromDocx(UploadedFile $file): string
    {
        try {
            Log::info('Extracting from DOCX', [
                'file' => $file->getClientOriginalName()
            ]);

            $phpWord = IOFactory::load($file->getPathname());
            $text = '';

            foreach ($phpWord->getSections() as $section) {
                foreach ($section->getElements() as $element) {
                    if (method_exists($element, 'getText')) {
                        $text .= $element->getText() . ' ';
                    }
                }
            }

            return trim($text) ?: 'لا يمكن استخراج نص من DOCX';

        } catch (\Exception $e) {
            Log::error('DOCX extraction error', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
            ]);

            throw new ExtractionException('فشل استخراج النص من DOCX: ' . $e->getMessage());
        }
    }

    private function fromTxt(UploadedFile $file): string
    {
        Log::info('Extracting from TXT', [
            'file' => $file->getClientOriginalName()
        ]);

        $content = file_get_contents($file->getPathname());

        if ($content === false) {
            Log::warning('TXT extraction failed', [
                'file' => $file->getClientOriginalName(),
            ]);
            return '';
        }

        return $content;
    }
}
