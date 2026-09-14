<?php

namespace App\Support;

use Illuminate\Support\Str;

class OcrFields
{
    /**
     * @return list<array{key: string, label: string, aliases: list<string>}>
     */
    public static function catalog(): array
    {
        return [
            ['key' => 'full_name', 'label' => 'Name', 'aliases' => ['student name', 'full name', 'applicant name', 'patient name', 'given names', 'last name', 'name']],
            ['key' => 'date_of_birth', 'label' => 'Birth Date', 'aliases' => ['date of birth', 'petsa ng kapanganakan', 'petsang kapanganakan', 'birth date', 'birthday', 'dob']],
            ['key' => 'school_name', 'label' => 'School', 'aliases' => ['school name', 'school', 'university', 'institution']],
            ['key' => 'course_or_program', 'label' => 'Course / Program', 'aliases' => ['course', 'program', 'degree', 'strand']],
            ['key' => 'year_level', 'label' => 'School Year / Level', 'aliases' => ['school year', 'year level', 'grade', 'year']],
            ['key' => 'student_number', 'label' => 'Student Number', 'aliases' => ['student number', 'student no', 'id number']],
            ['key' => 'address', 'label' => 'Address', 'aliases' => ['address', 'residence', 'home address']],
            ['key' => 'barangay', 'label' => 'Barangay', 'aliases' => ['barangay', 'brgy']],
            ['key' => 'hospital', 'label' => 'Hospital', 'aliases' => ['hospital', 'clinic', 'medical center']],
            ['key' => 'diagnosis', 'label' => 'Diagnosis', 'aliases' => ['diagnosis', 'impression', 'condition']],
            ['key' => 'amount', 'label' => 'Amount', 'aliases' => ['amount', 'total', 'balance', 'tuition']],
        ];
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_column(self::catalog(), 'key');
    }

    public static function label(string $key): string
    {
        foreach (self::catalog() as $field) {
            if ($field['key'] === $key) {
                return $field['label'];
            }
        }

        return Str::headline($key);
    }

    /**
     * @return list<string>
     */
    public static function aliases(string $key): array
    {
        foreach (self::catalog() as $field) {
            if ($field['key'] === $key) {
                return $field['aliases'];
            }
        }

        return [$key];
    }

    /**
     * @return list<string>
     */
    public static function defaultsForName(?string $name): array
    {
        $haystack = Str::lower((string) $name);

        return match (true) {
            str_contains($haystack, 'enrollment') => ['full_name', 'school_name', 'course_or_program', 'year_level'],
            str_contains($haystack, 'school id') => ['full_name', 'school_name', 'student_number'],
            str_contains($haystack, 'residency') || str_contains($haystack, 'barangay') => ['full_name', 'address', 'barangay'],
            str_contains($haystack, 'medical certificate') => ['full_name', 'diagnosis'],
            str_contains($haystack, 'hospital') || str_contains($haystack, 'bill') => ['full_name', 'hospital', 'amount'],
            str_contains($haystack, 'statement') || str_contains($haystack, 'tuition') || str_contains($haystack, 'assessment') => ['full_name', 'school_name', 'amount'],
            str_contains($haystack, 'valid id') || str_contains($haystack, 'identification') => ['full_name', 'date_of_birth'],
            default => ['full_name'],
        };
    }

    public static function typeForName(?string $name): string
    {
        $haystack = Str::lower((string) $name);

        return match (true) {
            str_contains($haystack, 'enrollment') => 'certificate_of_enrollment',
            str_contains($haystack, 'school id') => 'school_id',
            str_contains($haystack, 'residency') => 'proof_of_residency',
            str_contains($haystack, 'barangay') => 'barangay_clearance',
            str_contains($haystack, 'medical certificate') => 'medical_certificate',
            str_contains($haystack, 'hospital') || str_contains($haystack, 'bill') => 'hospital_bill',
            str_contains($haystack, 'statement') || str_contains($haystack, 'tuition') || str_contains($haystack, 'assessment') => 'statement_of_account',
            str_contains($haystack, 'valid id') || str_contains($haystack, 'identification') => 'valid_id',
            default => Str::slug((string) $name, '_'),
        };
    }

    /**
     * @return array<string, list<string>>
     */
    public static function typeKeywords(): array
    {
        return [
            'valid_id' => ['valid id', 'philippine identification', 'philippine national id', 'pambansang pagkakakilanlan', 'national id', 'driver', 'passport', 'umid', 'sss', 'philhealth'],
            'school_id' => ['school id', 'student id', 'student number'],
            'certificate_of_enrollment' => ['certificate of enrollment', 'currently enrolled', 'registrar', 'enrolled'],
            'proof_of_residency' => ['proof of residency', 'resident of', 'residency'],
            'barangay_clearance' => ['barangay clearance', 'barangay certificate', 'punong barangay'],
            'medical_certificate' => ['medical certificate', 'diagnosis', 'physician', 'license no'],
            'hospital_bill' => ['hospital bill', 'statement of account', 'hospital'],
            'statement_of_account' => ['statement of account', 'tuition', 'assessment', 'outstanding balance'],
        ];
    }
}
