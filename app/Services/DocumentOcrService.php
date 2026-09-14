<?php

namespace App\Services;

use App\Models\Application;
use App\Models\DocumentSubmission;
use App\Models\OcrExtractedField;
use App\Models\OcrResult;
use App\Support\OcrExtractor;
use App\Support\OcrFields;
use Illuminate\Support\Str;

class DocumentOcrService
{
    public function __construct(
        private OcrSpaceClient $client,
        private NotificationService $notifications,
        private OcrExtractor $extractor,
    ) {}

    public function process(DocumentSubmission $document, bool $notifyOnMismatch = true): OcrResult
    {
        $document->loadMissing([
            'application.applicant.profile',
            'application.applicant.primaryAddress',
            'application.answers',
            'application.program',
            'requirement',
        ]);

        $result = $document->ocrResult()->firstOrCreate(
            ['document_submission_id' => $document->id],
            ['status' => 'pending']
        );

        try {
            $text = $this->client->extractText(
                $document->file_path,
                $document->mime_type,
                $document->original_name,
            );

            $expectedType = OcrFields::typeForName($document->requirement_name);
            $detectedType = $this->detectType($text, $expectedType);
            $typeMatches = $detectedType === $expectedType || $this->textLooksLike($text, $expectedType);

            $result->update([
                'status' => 'processed',
                'raw_text' => $text,
                'expected_type' => $expectedType,
                'detected_type' => $detectedType,
                'type_matches' => $typeMatches,
                'error_message' => null,
                'processed_at' => now(),
            ]);

            $this->syncFields($result->fresh('fields'), $document->application, $document, $text);
            $this->score($result->fresh('fields'));

            if ($notifyOnMismatch && ! $typeMatches) {
                $this->notifications->notifyApplicant(
                    $document->application,
                    'Uploaded document may be the wrong type',
                    'The file uploaded for "'.$document->requirement_name.'" on application '.$document->application->application_no
                        .' does not look like that requirement. Please review it and upload the correct document.',
                    'warning'
                );
            }
        } catch (\Throwable $exception) {
            report($exception);
            $result->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
                'overall_status' => 'failed',
                'summary' => 'OCR could not read this file.',
                'processed_at' => now(),
            ]);
        }

        return $result->fresh('fields');
    }

    /**
     * @param  list<array{id: int, extracted_value?: string|null, corrected_value?: string|null}>  $updates
     */
    public function updateFields(OcrResult $result, array $updates): OcrResult
    {
        $result->loadMissing(['fields', 'document.application.applicant.profile', 'document.application.applicant.primaryAddress', 'document.application.answers']);

        foreach ($updates as $update) {
            $field = $result->fields->firstWhere('id', $update['id'] ?? null);

            if (! $field) {
                continue;
            }

            $value = $update['corrected_value'] ?? $update['extracted_value'] ?? $field->displayValue();
            $field->update(['corrected_value' => $value]);
        }

        $this->rescoreFields($result->fresh('fields'));
        $this->score($result->fresh('fields'));

        return $result->fresh('fields');
    }

    private function syncFields(OcrResult $result, Application $application, DocumentSubmission $document, string $text): void
    {
        $keys = $document->requirement?->ocr_fields ?: OcrFields::defaultsForName($document->requirement_name);
        $keys = array_values(array_intersect($keys, OcrFields::keys()));

        if ($keys === []) {
            $keys = OcrFields::defaultsForName($document->requirement_name);
        }

        $result->fields()->delete();

        foreach ($keys as $key) {
            $expected = $this->expectedValue($application, $key);
            $extracted = $this->extractor->value($text, $key, $expected);
            [$status, $score] = $this->extractor->compare($expected, $extracted, $key);

            $result->fields()->create([
                'field_key' => $key,
                'field_label' => OcrFields::label($key),
                'expected_value' => $expected,
                'extracted_value' => $extracted,
                'match_status' => $status,
                'match_score' => $score,
            ]);
        }
    }

    private function rescoreFields(OcrResult $result): void
    {
        foreach ($result->fields as $field) {
            [$status, $score] = $this->extractor->compare($field->expected_value, $field->displayValue(), $field->field_key);
            $field->update([
                'match_status' => $status,
                'match_score' => $score,
            ]);
        }
    }

    private function score(OcrResult $result): void
    {
        $result->loadMissing('fields');

        if ($result->type_matches === false) {
            $result->update([
                'overall_status' => 'type_mismatch',
                'overall_score' => $result->fields->avg('match_score') ? (int) round($result->fields->avg('match_score')) : 0,
                'summary' => 'Document type does not match the required file.',
            ]);

            return;
        }

        if ($result->fields->isEmpty()) {
            $result->update([
                'overall_status' => 'extracted',
                'overall_score' => null,
                'summary' => 'Text extracted. No comparison fields were configured.',
            ]);

            return;
        }

        $score = (int) round($result->fields->avg('match_score') ?? 0);
        $hasMismatch = $result->fields->contains(fn (OcrExtractedField $field) => $field->match_status === 'mismatch');
        $allMissing = $result->fields->every(fn (OcrExtractedField $field) => $field->match_status === 'missing');
        $allMatched = $result->fields->every(fn (OcrExtractedField $field) => in_array($field->match_status, ['matched', 'manual'], true));

        $status = match (true) {
            $allMatched && $score >= 80 => 'matched',
            $allMissing => 'review',
            $hasMismatch || $score < 50 => 'mismatch',
            default => 'review',
        };

        $summary = match ($status) {
            'matched' => $score.'% match. Potentially verified — staff must still confirm.',
            'mismatch' => 'One or more fields do not match the application.',
            default => $allMissing
                ? 'Text was read, but the expected fields could not be picked out automatically. Staff should review the document.'
                : $score.'% match. Staff should review the extracted information.',
        };

        $result->update([
            'overall_status' => $status,
            'overall_score' => $score,
            'summary' => $summary,
        ]);
    }

    private function expectedValue(Application $application, string $key): ?string
    {
        $applicant = $application->applicant;
        $profile = $applicant?->profile;
        $address = $applicant?->primaryAddress;
        $answers = $application->answers->mapWithKeys(fn ($answer) => [$answer->field_name => $answer->value]);

        $value = match ($key) {
            'full_name' => $applicant?->full_name,
            'date_of_birth' => $applicant?->date_of_birth?->format('Y-m-d'),
            'school_name' => $profile?->school_name ?: $answers->get('school_name'),
            'course_or_program' => $profile?->course_or_program ?: $answers->get('course_or_program'),
            'year_level' => $profile?->year_level ?: $answers->get('year_level'),
            'student_number' => $answers->get('student_number') ?: $answers->get('student_id_no'),
            'address' => $applicant?->fullAddress(),
            'barangay' => $address?->barangay,
            'hospital' => $answers->get('hospital') ?: $answers->get('hospital_name'),
            'diagnosis' => $answers->get('diagnosis'),
            'amount' => $answers->get('amount') ?: $answers->get('hospital_bill') ?: $answers->get('tuition_amount'),
            default => $answers->get($key),
        };

        $value = is_string($value) ? trim($value) : $value;

        return $value === '' || $value === '—' ? null : ($value !== null ? (string) $value : null);
    }

    private function detectType(string $text, string $expectedType): string
    {
        $normalized = $this->normalize($text);
        $bestType = $expectedType;
        $bestScore = 0;

        foreach (OcrFields::typeKeywords() as $type => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if ($this->contains($normalized, $this->normalize($keyword))) {
                    $score++;
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestType = $type;
            }
        }

        return $bestType;
    }

    private function textLooksLike(string $text, string $expectedType): bool
    {
        $normalized = $this->normalize($text);

        foreach (OcrFields::typeKeywords()[$expectedType] ?? [] as $keyword) {
            if ($this->contains($normalized, $this->normalize($keyword))) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = Str::lower($value);
        $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
    }

    private function contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_contains($haystack, $needle);
    }
}
