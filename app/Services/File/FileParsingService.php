<?php

namespace App\Services\File;

use Smalot\PdfParser\Parser;
use Illuminate\Support\Facades\Log;
use App\Exceptions\FileParsingException;
use App\Helpers\FileHelper;
use Throwable;

class FileParsingService
{
    private const SUPPORTED_EXTENSIONS = ['pdf', 'txt', 'docx'];
    private const TEXT_CHUNK_SIZE = 8192;
    private const MAX_FILE_SIZE = 104857600;

    private Parser $pdfParser;

    public function __construct()
    {
        $this->pdfParser = new Parser();
    }

    public function extractText(string $filePath): string
    {
        $startTime = microtime(true);

        try {
            FileHelper::validateFile($filePath);
            $extension = FileHelper::getFileExtension($filePath);

            Log::info('FileParsing: Starting', [
                'file_path' => $filePath,
                'extension' => $extension,
                'file_size' => filesize($filePath)
            ]);

            $text = match ($extension) {
                'pdf' => $this->extractFromPDF($filePath),
                'txt' => $this->extractFromTxt($filePath),
                'docx' => $this->extractFromDocx($filePath),
            };

            $cleanedText = FileHelper::cleanText($text);

            Log::info('FileParsing: Completed', [
                'extension' => $extension,
                'original_length' => strlen($text),
                'cleaned_length' => strlen($cleanedText),
                'execution_time' => round((microtime(true) - $startTime), 3) . 's'
            ]);

            return $cleanedText;

        } catch (FileParsingException $e) {
            Log::error('FileParsing: Validation Error', ['message' => $e->getMessage()]);
            throw $e;
        } catch (Throwable $e) {
            Log::error('FileParsing: Critical Failure', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            throw new FileParsingException('Failed to parse file: ' . $e->getMessage(), 0, $e);
        }
    }

    private function extractFromPDF(string $filePath): string
    {
        try {
            $pdf = $this->pdfParser->parseFile($filePath);

            if (!$pdf) {
                throw new FileParsingException('Failed to parse PDF file');
            }

            $text = '';
            $pageCount = 0;

            foreach ($pdf->getPages() as $page) {
                try {
                    $pageText = $page->getText();
                    if (!empty($pageText)) {
                        $text .= $pageText . ' ';
                        $pageCount++;
                    }
                } catch (Throwable $e) {
                    Log::warning('FileParsing: Page extraction failed', [
                        'page' => $pageCount + 1,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            if (empty($text)) {
                throw new FileParsingException('PDF contains no extractable text');
            }

            Log::info('FileParsing: PDF extraction completed', [
                'pages' => $pageCount,
                'text_length' => strlen($text)
            ]);

            return $text;

        } catch (Throwable $e) {
            throw new FileParsingException('PDF parsing failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function extractFromTxt(string $filePath): string
    {
        try {
            $text = '';
            $handle = fopen($filePath, 'r');

            if ($handle === false) {
                throw new FileParsingException('Failed to open TXT file');
            }

            while (!feof($handle)) {
                $chunk = fread($handle, self::TEXT_CHUNK_SIZE);
                if ($chunk === false) {
                    fclose($handle);
                    throw new FileParsingException('Failed to read TXT file');
                }
                $text .= $chunk;
            }

            fclose($handle);

            if (empty($text)) {
                throw new FileParsingException('TXT file is empty');
            }

            Log::info('FileParsing: TXT extraction completed', [
                'text_length' => strlen($text)
            ]);

            return $text;

        } catch (FileParsingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FileParsingException('TXT parsing failed: ' . $e->getMessage(), 0, $e);
        }
    }

    private function extractFromDocx(string $filePath): string
    {
        try {
            $zip = new \ZipArchive();
            $result = $zip->open($filePath);

            if ($result !== true) {
                throw new FileParsingException("Failed to open DOCX file: {$result}");
            }

            $xmlContent = $zip->getFromName('word/document.xml');
            $zip->close();

            if ($xmlContent === false) {
                throw new FileParsingException('DOCX file does not contain valid document.xml');
            }

            $text = FileHelper::extractTextFromDocxXml($xmlContent);

            if (empty($text)) {
                throw new FileParsingException('DOCX contains no extractable text');
            }

            Log::info('FileParsing: DOCX extraction completed', [
                'text_length' => strlen($text)
            ]);

            return $text;

        } catch (FileParsingException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FileParsingException('DOCX parsing failed: ' . $e->getMessage(), 0, $e);
        }
    }
}
