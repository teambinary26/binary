<?php

namespace App\Enums;

enum WorkflowStep: string
{
    case Verification = 'verification';
    case Evaluation = 'evaluation';
    case Approval = 'approval';

    public function label(): string
    {
        return match ($this) {
            self::Verification => 'Verification',
            self::Evaluation => 'Evaluation',
            self::Approval => 'Approval',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Verification => 'Verify submitted documents and requirements.',
            self::Evaluation => 'Evaluate application eligibility and recommend action.',
            self::Approval => 'Final approval or rejection of the application.',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Verification => '📋',
            self::Evaluation => '📊',
            self::Approval => '✅',
        };
    }

    public function permission(): string
    {
        return match ($this) {
            self::Verification => 'applications.verify',
            self::Evaluation => 'applications.evaluate',
            self::Approval => 'applications.approve',
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::Verification => 1,
            self::Evaluation => 2,
            self::Approval => 3,
        };
    }

    /**
     * @return list<ApplicationStatus>
     */
    public function queueStatuses(): array
    {
        return match ($this) {
            self::Verification => [
                ApplicationStatus::Submitted,
                ApplicationStatus::UnderVerification,
                ApplicationStatus::Incomplete,
                ApplicationStatus::ForRevision,
            ],
            self::Evaluation => [
                ApplicationStatus::UnderEvaluation,
            ],
            self::Approval => [
                ApplicationStatus::ForApproval,
            ],
        };
    }

    /**
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::Verification, self::Evaluation, self::Approval];
    }
}
