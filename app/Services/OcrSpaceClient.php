<?php

namespace App\Services;

use App\Support\DocumentFiles;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OcrSpaceClient
{
    public function extractText(string $path, ?string $mime = null, ?string $originalName = null): string
    {
        $key = (string) config('services.ocrspace.key');

        if ($key === '') {
            throw new \RuntimeException('OCR is not configured. Add OCRSPACE_API_KEY to the environment.');
        }

        $contents = DocumentFiles::contents($path);

        if ($contents === null || $contents === '') {
            throw new \RuntimeException('The uploaded file could not be read for OCR.');
        }

        $filename = $originalName ?: basename($path);
        $filetype = $this->fileType($mime, $filename);

        $response = Http::timeout((int) config('services.ocrspace.timeout', 60))
            ->withHeaders(['apikey' => $key])
            ->attach('file', $contents, $filename)
            ->post((string) config('services.ocrspace.url'), [
                'language' => 'eng',
                'isOverlayRequired' => 'false',
                'OCREngine' => (string) config('services.ocrspace.engine', '2'),
                'scale' => 'true',
                'detectOrientation' => 'true',
                'filetype' => $filetype,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('The OCR service could not be reached.');
        }

        $payload = $response->json();

        if (($payload['IsErroredOnProcessing'] ?? false) === true) {
            $message = $payload['ErrorMessage'] ?? 'OCR processing failed.';
            throw new \RuntimeException(is_array($message) ? implode(' ', $message) : (string) $message);
        }

        $text = collect($payload['ParsedResults'] ?? [])
            ->pluck('ParsedText')
            ->filter()
            ->implode("\n");

        if (trim($text) === '') {
            throw new \RuntimeException('OCR did not find readable text in this file.');
        }

        return $text;
    }

    private function fileType(?string $mime, string $filename): string
    {
        if (DocumentFiles::isPdf($mime, $filename)) {
            return 'PDF';
        }

        $extension = strtoupper(pathinfo($filename, PATHINFO_EXTENSION));

        return in_array($extension, ['PNG', 'JPG', 'JPEG', 'GIF', 'TIF', 'BMP'], true)
            ? ($extension === 'JPEG' ? 'JPG' : $extension)
            : 'JPG';
    }
}
