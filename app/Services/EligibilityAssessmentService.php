<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\DocumentVerificationStatus;
use App\Models\Application;
use App\Models\DocumentSubmission;
use App\Models\ProgramEligibilityRule;
use App\Support\OcrExtractor;
use App\Support\OcrFields;
use Carbon\Carbon;
use Illuminate\Support\Str;

class EligibilityAssessmentService
{
    public function __construct(private OcrExtractor $extractor) {}

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function fieldCatalog(): array
    {
        $fields = collect(OcrFields::catalog())
            ->map(fn (array $field) => ['key' => $field['key'], 'label' => $field['label']])
            ->values()
            ->all();

        return array_merge($fields, [
            ['key' => 'document_text', 'label' => 'Any scanned document text'],
            ['key' => 'age', 'label' => 'Age (from birth date on documents)'],
            ['key' => 'municipality', 'label' => 'Municipality'],
        ]);
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function operatorCatalog(): array
    {
        return [
            ['key' => 'contains', 'label' => 'Contains'],
            ['key' => 'equals', 'label' => 'Equals'],
            ['key' => 'not_contains', 'label' => 'Does not contain'],
            ['key' => 'min', 'label' => 'Minimum'],
            ['key' => 'max', 'label' => 'Maximum'],
            ['key' => 'present', 'label' => 'Found in documents'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function fieldKeys(): array
    {
        return array_column(self::fieldCatalog(), 'key');
    }

    /**
     * @return list<string>
     */
    public static function operatorKeys(): array
    {
        return array_column(self::operatorCatalog(), 'key');
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public static function checkModeCatalog(): array
    {
        return [
            ['key' => 'ocr', 'label' => 'OCR from documents'],
            ['key' => 'manual', 'label' => 'Manual staff check'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function checkModeKeys(): array
    {
        return array_column(self::checkModeCatalog(), 'key');
    }

    /**
     * @return array{
     *     eligible: bool|null,
     *     status: string,
     *     status_label: string,
     *     tone: string,
     *     summary: string,
     *     passed_count: int,
     *     failed_count: int,
     *     review_count: int,
     *     rules: list<array<string, mixed>>,
     *     sources: list<array<string, mixed>>
     * }
     */
    public function assess(Application $application): array
    {
        $application->loadMissing([
            'program.eligibilityRules',
            'documents.ocrResult.fields',
            'documents.verification',
        ]);

        $corpus = $this->corpus($application);
        $rules = $application->program?->eligibilityRules ?? collect();

        if ($rules->isEmpty()) {
            return $this->payload(
                'no_rules',
                'This program has no eligibility rules to check.',
                [],
                $corpus['sources'],
            );
        }

        $checked = $rules->map(function (ProgramEligibilityRule $rule) use ($application, $corpus) {
            if ($this->isManual($rule)) {
                return $this->manualResult($rule, $application);
            }

            if (! $corpus['has_ocr']) {
                return $this->ruleResult(
                    $rule,
                    'review',
                    'No OCR text is available from the verification documents yet. Scan the documents or confirm this rule manually.',
                    null,
                    'ocr',
                );
            }

            return $this->checkRule($rule, $corpus);
        })->values()->all();

        $failed = collect($checked)->where('status', 'failed')->count();
        $review = collect($checked)->where('status', 'review')->count();
        $passed = collect($checked)->where('status', 'passed')->count();
        $manual = collect($checked)->where('check_mode', 'manual')->count();
        $ocrRules = collect($checked)->where('check_mode', 'ocr');
        $needsScan = $ocrRules->isNotEmpty() && ! $corpus['has_ocr'];

        $status = match (true) {
            $failed > 0 => 'not_eligible',
            $needsScan && $manual === 0 => 'no_ocr',
            $review > 0 => 'review',
            default => 'eligible',
        };

        $summary = match ($status) {
            'not_eligible' => $failed.' program '.Str::plural('rule', $failed).' not met.',
            'no_ocr' => 'Scan the verification documents to check the OCR rules before saving.',
            'review' => $passed.' of '.$rules->count().' rules are met. '.$review.' '.Str::plural('rule', $review).' still need a staff decision.',
            default => 'All program eligibility rules are met.',
        };

        return $this->payload($status, $summary, $checked, $corpus['sources']);
    }

    /**
     * @param  list<array<string, mixed>>  $rules
     * @param  list<array<string, mixed>>  $sources
     * @return array<string, mixed>
     */
    private function payload(string $status, string $summary, array $rules, array $sources): array
    {
        return [
            'eligible' => match ($status) {
                'eligible' => true,
                'not_eligible' => false,
                default => null,
            },
            'status' => $status,
            'status_label' => match ($status) {
                'eligible' => 'Eligible',
                'not_eligible' => 'Not eligible',
                'review' => 'Needs review',
                'no_ocr' => 'Documents not scanned',
                default => 'No rules set',
            },
            'tone' => match ($status) {
                'eligible' => 'success',
                'not_eligible' => 'danger',
                'review' => 'warning',
                default => 'neutral',
            },
            'summary' => $summary,
            'passed_count' => collect($rules)->where('status', 'passed')->count(),
            'failed_count' => collect($rules)->where('status', 'failed')->count(),
            'review_count' => collect($rules)->where('status', 'review')->count(),
            'rules' => $rules,
            'sources' => $sources,
        ];
    }

    /**
     * @return array{
     *     has_ocr: bool,
     *     text: string,
     *     normalized: string,
     *     fields: array<string, list<string>>,
     *     sources: list<array<string, mixed>>
     * }
     */
    private function corpus(Application $application): array
    {
        $texts = [];
        $fields = [];
        $sources = [];

        foreach ($application->documents as $document) {
            if ($this->shouldSkipDocument($document)) {
                continue;
            }

            $ocr = $document->ocrResult;
            $raw = trim((string) ($ocr?->raw_text ?? ''));
            $extracted = $ocr?->fields
                ? $ocr->fields
                    ->map(fn ($field) => ['key' => $field->field_key, 'value' => $field->displayValue()])
                    ->filter(fn (array $field) => filled($field['value']))
                    ->values()
                : collect();

            $hasText = $raw !== '' || $extracted->isNotEmpty();

            $sources[] = [
                'id' => $document->id,
                'requirement_name' => $document->requirement_name,
                'has_text' => $hasText,
                'status' => $ocr?->status ?? 'pending',
                'status_label' => $hasText
                    ? 'Text extracted'
                    : ($ocr?->status === 'failed' ? 'OCR failed' : 'Not scanned'),
            ];

            if ($raw !== '') {
                $texts[] = $raw;
            }

            foreach ($extracted as $field) {
                $fields[$field['key']][] = (string) $field['value'];
            }
        }

        $text = trim(implode("\n", $texts));

        return [
            'has_ocr' => $text !== '' || $fields !== [],
            'text' => $text,
            'normalized' => $this->normalize($text),
            'fields' => $fields,
            'sources' => $sources,
        ];
    }

    private function shouldSkipDocument(DocumentSubmission $document): bool
    {
        return in_array($document->verification?->status, [
            DocumentVerificationStatus::Rejected,
            DocumentVerificationStatus::RevisionRequested,
        ], true);
    }

    /**
     * @param  array{text: string, normalized: string, fields: array<string, list<string>>}  $corpus
     * @return array<string, mixed>
     */
    private function checkRule(ProgramEligibilityRule $rule, array $corpus): array
    {
        $result = filled($rule->field)
            ? $this->checkStructured($rule, $corpus)
            : $this->checkFromLabel($rule, $corpus);

        return $this->ruleResult($rule, $result['status'], $result['detail'], $result['evidence'], 'ocr');
    }

    /**
     * @param  list<array<string, mixed>>  $priorRecords
     * @return array<string, mixed>
     */
    private function ruleResult(
        ProgramEligibilityRule $rule,
        string $status,
        string $detail,
        ?string $evidence,
        string $checkMode = 'ocr',
        array $priorRecords = [],
    ): array {
        return [
            'id' => $rule->id,
            'label' => $rule->label,
            'field' => $rule->field,
            'operator' => $rule->operator,
            'value' => $rule->value,
            'check_mode' => $checkMode,
            'check_mode_label' => $checkMode === 'manual' ? 'Manual check' : 'OCR check',
            'status' => $status,
            'status_label' => match ($status) {
                'passed' => 'Met',
                'failed' => 'Not met',
                default => $checkMode === 'manual' ? 'Needs manual check' : 'Needs review',
            },
            'detail' => $detail,
            'evidence' => $evidence,
            'prior_records' => $priorRecords,
            'shows_prior_records' => $this->looksLikePriorAssistance($rule->label),
        ];
    }

    public function isManual(ProgramEligibilityRule $rule): bool
    {
        if ($rule->check_mode === 'manual') {
            return true;
        }

        if ($rule->check_mode === 'ocr' && filled($rule->field)) {
            return false;
        }

        return $this->looksLikeManualLabel($rule->label);
    }

    /**
     * @return array<string, mixed>
     */
    private function manualResult(ProgramEligibilityRule $rule, Application $application): array
    {
        $prior = $this->looksLikePriorAssistance($rule->label)
            ? $this->priorAssistance($application)
            : [];

        $receivedThisYear = collect($prior)->contains(
            fn (array $record) => $record['received'] && $record['in_current_academic_year']
        );

        $detail = match (true) {
            $prior === [] && $this->looksLikePriorAssistance($rule->label) => 'No other application for this program was found. Confirm from office records that the applicant has not already received this assistance for the current academic year.',
            $receivedThisYear => 'A prior application for this program was found this academic year. Confirm whether assistance was already received before marking this rule.',
            $prior !== [] => 'Earlier applications for this program were found. Confirm whether any assistance was received this academic year.',
            default => 'This rule cannot be confirmed from scanned documents. Mark Met or Not met after your review.',
        };

        return $this->ruleResult($rule, 'review', $detail, null, 'manual', $prior);
    }

    private function looksLikeManualLabel(string $label): bool
    {
        $normalized = $this->normalize($label);

        foreach ($this->labelChecks() as $check) {
            if (($check['manual'] ?? false) === true && $this->textContainsAny($normalized, $check['needles'])) {
                return true;
            }
        }

        return false;
    }

    private function looksLikePriorAssistance(string $label): bool
    {
        $normalized = $this->normalize($label);

        return $this->textContainsAny($normalized, [
            'has not received',
            'not previously received',
            'same program',
            'current academic year',
            'already received',
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function priorAssistance(Application $application): array
    {
        $yearStart = Carbon::create(now()->month >= 6 ? now()->year : now()->year - 1, 6, 1)->startOfDay();

        return Application::query()
            ->where('applicant_id', $application->applicant_id)
            ->where('assistance_program_id', $application->assistance_program_id)
            ->where('id', '!=', $application->id)
            ->whereNotIn('status', [
                ApplicationStatus::Draft->value,
                ApplicationStatus::Cancelled->value,
                ApplicationStatus::Rejected->value,
            ])
            ->latest('submitted_at')
            ->get()
            ->map(function (Application $row) use ($yearStart) {
                $when = $row->submitted_at ?? $row->created_at;
                $received = in_array($row->status, [
                    ApplicationStatus::Approved,
                    ApplicationStatus::ScheduledForRelease,
                    ApplicationStatus::Released,
                    ApplicationStatus::Completed,
                ], true);

                return [
                    'id' => $row->id,
                    'application_no' => $row->application_no,
                    'status' => $row->status->value,
                    'status_label' => $row->status->label(),
                    'status_tone' => $row->status->tone(),
                    'submitted_at' => gov_date($when),
                    'in_current_academic_year' => $when?->gte($yearStart) ?? false,
                    'received' => $received,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array{text: string, normalized: string, fields: array<string, list<string>>}  $corpus
     * @return array{status: string, detail: string, evidence: ?string}
     */
    private function checkStructured(ProgramEligibilityRule $rule, array $corpus): array
    {
        $field = (string) $rule->field;
        $operator = $rule->operator ?: 'contains';
        $expected = $rule->value;

        if ($field === 'age') {
            return $this->checkAge($operator, $expected, $corpus);
        }

        $values = $this->fieldValues($field, $corpus);

        if ($operator === 'present') {
            if ($values !== []) {
                return [
                    'status' => 'passed',
                    'detail' => $this->fieldLabel($field).' was found in the scanned documents.',
                    'evidence' => $values[0],
                ];
            }

            if ($field !== 'document_text' && $this->textContainsAny($corpus['normalized'], OcrFields::aliases($field))) {
                return [
                    'status' => 'passed',
                    'detail' => $this->fieldLabel($field).' appears in the scanned document text.',
                    'evidence' => null,
                ];
            }

            if ($field === 'document_text' && $corpus['text'] !== '') {
                return [
                    'status' => 'passed',
                    'detail' => 'Text was extracted from the verification documents.',
                    'evidence' => $this->snippet($corpus['text'], Str::limit($corpus['text'], 40, '')),
                ];
            }

            return [
                'status' => 'failed',
                'detail' => $this->fieldLabel($field).' was not found in the scanned documents.',
                'evidence' => null,
            ];
        }

        if (! filled($expected)) {
            return [
                'status' => 'review',
                'detail' => 'This rule has no value to check against the scanned documents.',
                'evidence' => null,
            ];
        }

        if (in_array($operator, ['min', 'max'], true)) {
            return $this->checkNumeric($field, $operator, $expected, $values, $corpus);
        }

        $haystacks = $field === 'document_text'
            ? [$corpus['text']]
            : array_merge($values, [$corpus['text']]);

        $matched = $this->matchesValue($haystacks, $expected, $operator === 'equals', $field);

        if ($operator === 'not_contains') {
            return $matched
                ? [
                    'status' => 'failed',
                    'detail' => 'Scanned documents contain "'.$expected.'", which this rule does not allow.',
                    'evidence' => $this->snippet($corpus['text'], $expected) ?? $expected,
                ]
                : [
                    'status' => 'passed',
                    'detail' => 'Scanned documents do not contain "'.$expected.'".',
                    'evidence' => null,
                ];
        }

        if ($matched) {
            return [
                'status' => 'passed',
                'detail' => 'Found "'.$expected.'" in the scanned documents.',
                'evidence' => $this->snippet($corpus['text'], $expected) ?? $expected,
            ];
        }

        return [
            'status' => 'failed',
            'detail' => '"'.$expected.'" was not found in the scanned verification documents.',
            'evidence' => null,
        ];
    }

    /**
     * @param  array{text: string, normalized: string, fields: array<string, list<string>>}  $corpus
     * @return array{status: string, detail: string, evidence: ?string}
     */
    private function checkFromLabel(ProgramEligibilityRule $rule, array $corpus): array
    {
        $label = $this->normalize($rule->label);

        foreach ($this->labelChecks() as $check) {
            if (! $this->textContainsAny($label, $check['needles'])) {
                continue;
            }

            if (($check['manual'] ?? false) === true) {
                return [
                    'status' => 'review',
                    'detail' => $check['detail'],
                    'evidence' => null,
                ];
            }

            $search = $check['search'];
            $values = [];
            foreach ($check['fields'] ?? [] as $key) {
                $values = array_merge($values, $corpus['fields'][$key] ?? []);
            }

            if ($this->matchesValue(array_merge($values, [$corpus['text']]), $search, false)) {
                $found = $this->firstFound($corpus, is_array($search) ? $search : [$search]);

                return [
                    'status' => 'passed',
                    'detail' => $check['passed'],
                    'evidence' => $this->snippet($corpus['text'], $found) ?? $found,
                ];
            }

            return [
                'status' => 'review',
                'detail' => $check['failed'],
                'evidence' => null,
            ];
        }

        $keywords = $this->labelKeywords($rule->label);
        if ($keywords === []) {
            return [
                'status' => 'review',
                'detail' => 'This rule cannot be checked automatically from the scanned documents.',
                'evidence' => null,
            ];
        }

        $found = array_values(array_filter(
            $keywords,
            fn (string $word) => str_contains($corpus['normalized'], $word)
        ));

        if (count($found) >= max(1, (int) ceil(count($keywords) * 0.6))) {
            return [
                'status' => 'passed',
                'detail' => 'Keywords from this rule appear in the scanned documents.',
                'evidence' => $this->snippet($corpus['text'], $found[0] ?? null),
            ];
        }

        return [
            'status' => 'review',
            'detail' => 'Not enough wording from this rule was found in the scanned documents. Staff should review it.',
            'evidence' => null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function labelChecks(): array
    {
        return [
            [
                'needles' => ['has not received', 'not previously received', 'same program', 'current academic year'],
                'manual' => true,
                'detail' => 'Prior assistance cannot be confirmed from the uploaded documents. Staff should review records.',
            ],
            [
                'needles' => ['outside the', 'located outside'],
                'manual' => true,
                'detail' => 'Whether the school is outside the applicant’s barangay cannot be confirmed from OCR alone.',
            ],
            [
                'needles' => ['immediate family', 'next of kin', 'family member'],
                'manual' => true,
                'detail' => 'Family relationship cannot be confirmed automatically from the scanned documents.',
            ],
            [
                'needles' => ['non-student', 'non student'],
                'manual' => true,
                'detail' => 'Beneficiary type is not printed on the scanned documents in a way OCR can confirm.',
            ],
            [
                'needles' => ['nabua', 'resident', 'residency'],
                'search' => ['nabua'],
                'fields' => ['address', 'barangay', 'municipality'],
                'passed' => 'Residency in Nabua appears in the scanned documents.',
                'failed' => 'The scanned documents do not clearly show residency in Nabua. Staff should review them.',
            ],
            [
                'needles' => ['enroll', 'currently enrolled'],
                'search' => ['enrolled', 'enrollment', 'currently enrolled', 'certificate of enrollment'],
                'fields' => ['school_name', 'student_number', 'year_level'],
                'passed' => 'Enrollment evidence appears in the scanned documents.',
                'failed' => 'The scanned documents do not clearly show that the applicant is currently enrolled.',
            ],
            [
                'needles' => ['tuition', 'assessment'],
                'search' => ['tuition', 'assessment', 'statement of account', 'outstanding balance'],
                'fields' => ['amount', 'school_name'],
                'passed' => 'A tuition or assessment document appears in the scanned files.',
                'failed' => 'No tuition assessment was clearly found in the scanned documents.',
            ],
            [
                'needles' => ['medical', 'diagnosis', 'hospital', 'medical need'],
                'search' => ['diagnosis', 'medical certificate', 'hospital', 'physician'],
                'fields' => ['diagnosis', 'hospital'],
                'passed' => 'Medical evidence appears in the scanned documents.',
                'failed' => 'The scanned documents do not clearly show a current medical need.',
            ],
            [
                'needles' => ['emergency', 'crisis', 'calamity', 'fire', 'flood'],
                'search' => ['emergency', 'fire', 'flood', 'calamity', 'incident', 'disaster'],
                'fields' => [],
                'passed' => 'Emergency or incident wording appears in the scanned documents.',
                'failed' => 'The scanned documents do not clearly show an emergency or crisis.',
            ],
            [
                'needles' => ['food', 'indigency', 'hardship'],
                'search' => ['indigency', 'indigent', 'food'],
                'fields' => [],
                'passed' => 'Food or indigency evidence appears in the scanned documents.',
                'failed' => 'The scanned documents do not clearly show food-related hardship.',
            ],
            [
                'needles' => ['livelihood', 'proposal'],
                'search' => ['livelihood', 'proposal', 'sari-sari', 'business'],
                'fields' => [],
                'passed' => 'Livelihood proposal wording appears in the scanned documents.',
                'failed' => 'The scanned documents do not clearly show a livelihood proposal.',
            ],
            [
                'needles' => ['student'],
                'search' => ['student', 'school', 'enrolled', 'enrollment'],
                'fields' => ['school_name', 'student_number', 'year_level'],
                'passed' => 'Student evidence appears in the scanned documents.',
                'failed' => 'The scanned documents do not clearly show that the applicant is a student.',
            ],
        ];
    }

    /**
     * @param  array{fields: array<string, list<string>>, text: string}  $corpus
     * @return list<string>
     */
    private function fieldValues(string $field, array $corpus): array
    {
        if ($field === 'document_text') {
            return $corpus['text'] !== '' ? [$corpus['text']] : [];
        }

        if ($field === 'municipality') {
            return array_values(array_filter(array_merge(
                $corpus['fields']['municipality'] ?? [],
                $corpus['fields']['address'] ?? [],
            )));
        }

        return array_values($corpus['fields'][$field] ?? []);
    }

    /**
     * @param  list<string>  $haystacks
     * @param  string|list<string>  $expected
     */
    private function matchesValue(array $haystacks, string|array $expected, bool $strictEquals, ?string $field = null): bool
    {
        $needles = is_array($expected) ? $expected : [$expected];

        foreach ($haystacks as $haystack) {
            if (! filled($haystack)) {
                continue;
            }

            foreach ($needles as $needle) {
                if ($field === 'date_of_birth' && $this->extractor->sameCalendarDay((string) $needle, (string) $haystack)) {
                    return true;
                }

                if ($strictEquals) {
                    [$status] = $this->extractor->compare((string) $needle, (string) $haystack, $field);
                    if (in_array($status, ['matched', 'manual'], true)) {
                        return true;
                    }
                }

                if ($this->contains($this->normalize((string) $haystack), $this->normalize((string) $needle))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $values
     * @param  array{text: string}  $corpus
     * @return array{status: string, detail: string, evidence: ?string}
     */
    private function checkNumeric(string $field, string $operator, string $expected, array $values, array $corpus): array
    {
        $threshold = $this->number($expected);
        $candidates = $values !== [] ? $values : [$corpus['text']];
        $numbers = [];

        foreach ($candidates as $candidate) {
            $number = $this->number((string) $candidate);
            if ($number !== null) {
                $numbers[] = $number;
            }
        }

        if ($numbers === [] || $threshold === null) {
            return [
                'status' => 'review',
                'detail' => 'A numeric value for this rule could not be read from the scanned documents.',
                'evidence' => null,
            ];
        }

        $ok = $operator === 'min'
            ? collect($numbers)->contains(fn (float $number) => $number >= $threshold)
            : collect($numbers)->contains(fn (float $number) => $number <= $threshold);

        $label = $operator === 'min' ? 'at least' : 'at most';

        return $ok
            ? [
                'status' => 'passed',
                'detail' => $this->fieldLabel($field).' in the documents is '.$label.' '.$expected.'.',
                'evidence' => (string) $numbers[0],
            ]
            : [
                'status' => 'failed',
                'detail' => $this->fieldLabel($field).' in the documents is not '.$label.' '.$expected.'.',
                'evidence' => (string) $numbers[0],
            ];
    }

    /**
     * @param  array{fields: array<string, list<string>>}  $corpus
     * @return array{status: string, detail: string, evidence: ?string}
     */
    private function checkAge(string $operator, ?string $expected, array $corpus): array
    {
        $ages = [];
        foreach ($corpus['fields']['date_of_birth'] ?? [] as $value) {
            try {
                $ages[] = Carbon::parse($value)->age;
            } catch (\Throwable) {
                continue;
            }
        }

        if ($ages === []) {
            return [
                'status' => 'review',
                'detail' => 'No birth date was extracted from the scanned documents.',
                'evidence' => null,
            ];
        }

        if ($operator === 'present') {
            return [
                'status' => 'passed',
                'detail' => 'Age can be computed from the scanned birth date.',
                'evidence' => (string) $ages[0],
            ];
        }

        $threshold = $this->number((string) $expected);
        if ($threshold === null) {
            return [
                'status' => 'review',
                'detail' => 'This age rule has no number to compare.',
                'evidence' => null,
            ];
        }

        $age = $ages[0];
        $ok = match ($operator) {
            'min' => $age >= $threshold,
            'max' => $age <= $threshold,
            'equals' => $age === (int) $threshold,
            default => false,
        };

        return $ok
            ? [
                'status' => 'passed',
                'detail' => 'Age from the scanned birth date is '.$age.'.',
                'evidence' => (string) $age,
            ]
            : [
                'status' => 'failed',
                'detail' => 'Age from the scanned birth date is '.$age.', which does not meet this rule.',
                'evidence' => (string) $age,
            ];
    }

    /**
     * @param  list<string>  $needles
     */
    private function textContainsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if ($this->contains($haystack, $this->normalize($needle))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{normalized: string}  $corpus
     * @param  list<string>  $needles
     */
    private function firstFound(array $corpus, array $needles): ?string
    {
        foreach ($needles as $needle) {
            if ($this->contains($corpus['normalized'], $this->normalize($needle))) {
                return $needle;
            }
        }

        return $needles[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private function labelKeywords(string $label): array
    {
        $stop = [
            'must', 'have', 'has', 'the', 'and', 'for', 'this', 'that', 'with', 'from',
            'applicant', 'program', 'current', 'able', 'present', 'valid', 'other',
            'their', 'been', 'who', 'are', 'was',
        ];

        return array_values(array_filter(
            explode(' ', $this->normalize($label)),
            fn (string $word) => strlen($word) >= 4 && ! in_array($word, $stop, true)
        ));
    }

    private function snippet(string $text, ?string $needle): ?string
    {
        if (! filled($needle) || $text === '') {
            return null;
        }

        $pos = mb_stripos($text, $needle);
        if ($pos === false) {
            return null;
        }

        $start = max(0, $pos - 40);
        $chunk = trim(mb_substr($text, $start, mb_strlen($needle) + 80));

        return ($start > 0 ? '…' : '').$chunk.($start + mb_strlen($needle) + 80 < mb_strlen($text) ? '…' : '');
    }

    private function number(string $value): ?float
    {
        if (! preg_match('/-?\d+(?:[.,]\d+)?/', $value, $matches)) {
            return null;
        }

        return (float) str_replace(',', '', $matches[0]);
    }

    private function fieldLabel(string $field): string
    {
        foreach (self::fieldCatalog() as $item) {
            if ($item['key'] === $field) {
                return $item['label'];
            }
        }

        return OcrFields::label($field);
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
