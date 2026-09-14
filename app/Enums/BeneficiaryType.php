<?php

namespace App\Enums;

enum BeneficiaryType: string
{
    case Student = 'student';
    case NonStudent = 'non_student';
    case Both = 'both';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Student',
            self::NonStudent => 'Non-Student',
            self::Both => 'Students and Non-Students',
        };
    }
}
