<?php

namespace App\Support;

use App\Models\AssistanceProgram;
use App\Models\ProgramFormField;
use Illuminate\Validation\Rule;

class ProgramFormFieldRules
{
    /**
     * @return list<string>
     */
    public static function studentFieldNames(): array
    {
        return [
            'school_name',
            'course_or_program',
            'year_level',
            'education_level',
            'student_id_no',
            'student_number',
            'student_id',
            'grade_level',
            'tuition_amount',
            'term',
            'supplies_needed',
            'school_location',
            'usual_transport',
            'estimated_daily_fare',
        ];
    }

    public static function isStudentField(string $name): bool
    {
        return in_array($name, self::studentFieldNames(), true);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(AssistanceProgram $program, string $prefix = 'answers.', ?string $beneficiaryType = null): array
    {
        $program->loadMissing('formFields');
        $requireStudentFields = $beneficiaryType !== 'non_student';

        $rules = [];
        if ($prefix === 'answers.') {
            $rules['answers'] = ['nullable', 'array'];
        }

        foreach ($program->formFields as $field) {
            $required = $field->is_required && ($requireStudentFields || ! self::isStudentField($field->name));
            $rules[$prefix.$field->name] = self::forField($field, $required);
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(AssistanceProgram $program, string $prefix = 'answers.'): array
    {
        $program->loadMissing('formFields');
        $messages = [];

        foreach ($program->formFields as $field) {
            $key = $prefix.$field->name;
            $messages[$key.'.required'] = $field->label.' is required.';
            $messages[$key.'.numeric'] = $field->label.' must be a number.';
            $messages[$key.'.date'] = $field->label.' must be a valid date.';
            $messages[$key.'.in'] = 'Please choose a valid option for '.$field->label.'.';
            $messages[$key.'.max'] = $field->label.' is too long.';
        }

        return $messages;
    }

    /**
     * @return list<mixed>
     */
    public static function forField(ProgramFormField $field, ?bool $required = null): array
    {
        $rules = [($required ?? $field->is_required) ? 'required' : 'nullable'];

        return array_merge($rules, match ($field->type) {
            'number' => ['numeric'],
            'date' => ['date'],
            'textarea' => ['string', 'max:5000'],
            'select' => filled($field->options)
                ? [Rule::in($field->options)]
                : ['string', 'max:255'],
            default => ['string', 'max:255'],
        });
    }
}
