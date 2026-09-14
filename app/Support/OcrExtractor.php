<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Str;

class OcrExtractor
{
    public function value(string $text, string $key, ?string $expected = null): ?string
    {
        if ($key === 'full_name') {
            $composed = $this->composeName($text);
            if ($composed) {
                return $composed;
            }
        }

        $labeled = $this->labeledValue($text, OcrFields::aliases($key));
        if ($labeled) {
            return $labeled;
        }

        if ($key === 'date_of_birth') {
            $date = $this->firstDate($text);
            if ($date) {
                return $date;
            }
        }

        if ($expected && $this->contains($this->normalize($text), $this->normalize($expected))) {
            return $expected;
        }

        if ($key === 'full_name' && $expected && $this->tokenCoverage($expected, $text) >= 80) {
            return $expected;
        }

        if ($key === 'date_of_birth' && $expected) {
            foreach ($this->dateVariants($expected) as $variant) {
                if ($this->contains($this->normalize($text), $this->normalize($variant))) {
                    return $variant;
                }
            }
        }

        return null;
    }

    /**
     * @return array{0: string, 1: int}
     */
    public function compare(?string $expected, ?string $extracted, ?string $key = null): array
    {
        if (! filled($expected) && ! filled($extracted)) {
            return ['missing', 0];
        }

        if (! filled($extracted)) {
            return ['missing', 0];
        }

        if (! filled($expected)) {
            return ['extracted', 100];
        }

        if ($key === 'date_of_birth') {
            if ($this->sameCalendarDay($expected, $extracted)) {
                return ['matched', 100];
            }

            if ($this->dateParts($expected) && $this->datePartCandidates($extracted) !== []) {
                return ['mismatch', 0];
            }
        }

        $left = $this->normalize($expected);
        $right = $this->normalize($extracted);

        if ($left === $right || $this->contains($left, $right) || $this->contains($right, $left)) {
            return ['matched', 100];
        }

        if (in_array($key, ['full_name', 'school_name', 'address'], true)) {
            $score = $this->tokenScore($expected, $extracted);

            return $score >= 80
                ? ['matched', $score]
                : ['mismatch', $score];
        }

        similar_text($left, $right, $percent);
        $score = (int) round($percent);

        return $score >= 80
            ? ['matched', $score]
            : ['mismatch', $score];
    }

    private function composeName(string $text): ?string
    {
        $last = $this->labeledValue($text, ['last name', 'apelyido', 'surname', 'family name']);
        $given = $this->labeledValue($text, ['given names', 'given name', 'first name', 'mga pangalan']);
        $middle = $this->labeledValue($text, ['middle name', 'gitnang apelyido', 'gitnang pangalan']);

        $parts = array_filter([$given, $middle, $last], fn (?string $part) => filled($part));

        return $parts === [] ? null : implode(' ', $parts);
    }

    /**
     * @param  list<string>  $aliases
     */
    private function labeledValue(string $text, array $aliases): ?string
    {
        $aliases = collect($aliases)
            ->filter()
            ->sortByDesc(fn (string $alias) => strlen($alias))
            ->values()
            ->all();

        $lines = preg_split('/\r\n|\n|\r/', $text) ?: [];

        foreach ($aliases as $alias) {
            $pattern = '/'.preg_quote($alias, '/').'\s*[:\-]\s*(.+)/i';
            if (preg_match($pattern, $text, $matches)) {
                $line = trim(preg_split('/\r\n|\n|\r/', $matches[1])[0] ?? '');
                if ($this->isValue($line)) {
                    return $this->cleanValue($line);
                }
            }
        }

        foreach ($lines as $index => $line) {
            $normalizedLine = $this->normalize($line);

            foreach ($aliases as $alias) {
                $normalizedAlias = $this->normalize($alias);

                if ($normalizedAlias === '' || strlen($normalizedAlias) < 4) {
                    continue;
                }

                if ($normalizedLine !== $normalizedAlias && ! str_contains($normalizedLine, $normalizedAlias)) {
                    continue;
                }

                $next = trim($lines[$index + 1] ?? '');
                if ($this->isValue($next) && ! $this->looksLikeLabel($next)) {
                    return $this->cleanValue($next);
                }
            }
        }

        return null;
    }

    private function firstDate(string $text): ?string
    {
        if (preg_match('/\b(?:january|february|march|april|may|june|july|august|september|october|november|december)\s+\d{1,2},?\s+\d{4}\b/i', $text, $matches)) {
            return $matches[0];
        }

        if (preg_match('/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/', $text, $matches)) {
            return $matches[0];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function dateVariants(string $value): array
    {
        $date = $this->parseDate($value);

        if (! $date) {
            return [$value];
        }

        return array_values(array_unique([
            $date->format('Y-m-d'),
            $date->format('m/d/Y'),
            $date->format('d/m/Y'),
            $date->format('F j, Y'),
            $date->format('F d, Y'),
            $date->format('j F Y'),
            $date->format('d F Y'),
            $date->format('M j, Y'),
            $date->format('M d, Y'),
        ]));
    }

    public function sameCalendarDay(string $expected, string $extracted): bool
    {
        $expectedParts = $this->dateParts($expected);

        if (! $expectedParts) {
            return false;
        }

        foreach ($this->datePartCandidates($extracted) as $parts) {
            if ($this->partsMatch($expectedParts, $parts)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{year: int, month: int, day: int}|null
     */
    private function dateParts(string $value): ?array
    {
        $date = $this->parseDate($this->cleanDateString($value));

        if (! $date) {
            return null;
        }

        return [
            'year' => (int) $date->year,
            'month' => (int) $date->month,
            'day' => (int) $date->day,
        ];
    }

    /**
     * @return list<array{year: int, month: int, day: int}>
     */
    private function datePartCandidates(string $value): array
    {
        $value = $this->cleanDateString($value);
        $candidates = [];

        $direct = $this->dateParts($value);
        if ($direct) {
            $candidates[] = $direct;
        }

        foreach ($this->numericDateParts($value) as $parts) {
            $candidates[] = $parts;
        }

        $unique = [];
        foreach ($candidates as $parts) {
            $key = $parts['year'].'-'.$parts['month'].'-'.$parts['day'];
            $unique[$key] = $parts;
        }

        return array_values($unique);
    }

    /**
     * @return list<array{year: int, month: int, day: int}>
     */
    private function numericDateParts(string $value): array
    {
        $candidates = [];

        if (preg_match('/^(?<year>\d{4})[\/\-\. ](?<month>\d{1,2})[\/\-\. ](?<day>\d{1,2})$/', $value, $matches)) {
            $this->pushValidDate($candidates, (int) $matches['year'], (int) $matches['month'], (int) $matches['day']);
        }

        if (preg_match('/^(?<left>\d{1,2})[\/\-\. ](?<right>\d{1,2})[\/\-\. ](?<year>\d{2,4})$/', $value, $matches)) {
            $year = $this->normalizeYear($matches['year']);
            $left = (int) $matches['left'];
            $right = (int) $matches['right'];

            $this->pushValidDate($candidates, $year, $left, $right);
            $this->pushValidDate($candidates, $year, $right, $left);
        }

        return $candidates;
    }

    /**
     * @param  list<array{year: int, month: int, day: int}>  $candidates
     */
    private function pushValidDate(array &$candidates, int $year, int $month, int $day): void
    {
        if (! checkdate($month, $day, $year)) {
            return;
        }

        $candidates[] = [
            'year' => $year,
            'month' => $month,
            'day' => $day,
        ];
    }

    /**
     * @param  array{year: int, month: int, day: int}  $left
     * @param  array{year: int, month: int, day: int}  $right
     */
    private function partsMatch(array $left, array $right): bool
    {
        return $left['year'] === $right['year']
            && $left['month'] === $right['month']
            && $left['day'] === $right['day'];
    }

    private function normalizeYear(string $year): int
    {
        $year = (int) $year;

        return $year < 100 ? 2000 + $year : $year;
    }

    private function cleanDateString(string $value): string
    {
        $value = trim($value);
        $value = str_replace(['.', ','], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return trim($value);
    }

    private function parseDate(string $value): ?Carbon
    {
        $value = $this->cleanDateString($value);

        if ($value === '') {
            return null;
        }

        $formats = [
            'Y-m-d',
            'Y/m/d',
            'Y m d',
            'F j Y',
            'F d Y',
            'M j Y',
            'M d Y',
            'j F Y',
            'd F Y',
            'j M Y',
            'd M Y',
            'm/d/Y',
            'd/m/Y',
            'm-d-Y',
            'd-m-Y',
        ];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (\Throwable) {
                continue;
            }

            if ($date instanceof Carbon) {
                return $date->startOfDay();
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function tokenScore(string $expected, string $extracted): int
    {
        $left = $this->tokens($expected);
        $right = $this->tokens($extracted);

        if ($left === [] || $right === []) {
            return 0;
        }

        $overlap = count(array_intersect($left, $right));

        return (int) round(100 * ($overlap / max(count($left), count($right))));
    }

    public function tokenCoverage(string $expected, string $haystack): int
    {
        $left = $this->tokens($expected);
        $right = $this->tokens($haystack);

        if ($left === []) {
            return 0;
        }

        $overlap = count(array_intersect($left, $right));

        return (int) round(100 * ($overlap / count($left)));
    }

    /**
     * @return list<string>
     */
    private function tokens(string $value): array
    {
        return array_values(array_filter(
            explode(' ', $this->normalize($value)),
            fn (string $token) => strlen($token) > 1
        ));
    }

    private function isValue(string $value): bool
    {
        $value = trim($value);

        return $value !== '' && ! preg_match('/^[:\-]+$/', $value);
    }

    private function looksLikeLabel(string $line): bool
    {
        $normalized = $this->normalize($line);

        foreach (['last name', 'given name', 'middle name', 'date of birth', 'birth date', 'address', 'digital id', 'tirahan', 'apelyido', 'pangalan'] as $label) {
            if (str_contains($normalized, $label)) {
                return true;
            }
        }

        return false;
    }

    private function cleanValue(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
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
