<?php

namespace App\Enums;

enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Accepted = 'accepted';
    case Submitted = 'submitted';
    case UnderVerification = 'under_verification';
    case Incomplete = 'incomplete';
    case UnderEvaluation = 'under_evaluation';
    case ForApproval = 'for_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ForRevision = 'for_revision';
    case ScheduledForRelease = 'scheduled_for_release';
    case Released = 'released';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Accepted => 'Accepted',
            self::Submitted => 'Submitted',
            self::UnderVerification => 'Under Verification',
            self::Incomplete => 'Incomplete',
            self::UnderEvaluation => 'Under Evaluation',
            self::ForApproval => 'For Approval',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
            self::ForRevision => 'For Revision',
            self::ScheduledForRelease => 'Scheduled for Release',
            self::Released => 'Released',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Draft => 'Application has been started but not yet submitted.',
            self::Accepted => 'The office has accepted your application. You may now complete the requirements in the applicant portal.',
            self::Submitted => 'Application has been received by the office.',
            self::UnderVerification => 'Submitted documents are being verified by authorized staff.',
            self::Incomplete => 'One or more requirements are missing or insufficient.',
            self::UnderEvaluation => 'Application is being evaluated against program eligibility.',
            self::ForApproval => 'Evaluation is complete. The application is waiting for a final approval decision.',
            self::Approved => 'Application has been approved for assistance.',
            self::Rejected => 'Application was not approved.',
            self::ForRevision => 'The office asked you to replace one or more documents. After you upload the replacements, the application returns to verification.',
            self::ScheduledForRelease => 'Assistance release has been scheduled.',
            self::Released => 'Assistance has been released to the beneficiary.',
            self::Completed => 'Assistance process has been completed and recorded.',
            self::Cancelled => 'Application was cancelled.',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Accepted, self::Submitted, self::UnderVerification, self::UnderEvaluation, self::ForApproval, self::ScheduledForRelease => 'info',
            self::Incomplete, self::ForRevision => 'warning',
            self::Approved, self::Released, self::Completed => 'success',
            self::Rejected, self::Cancelled => 'danger',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Rejected, self::Cancelled], true);
    }

    public function isActive(): bool
    {
        return ! in_array($this, [self::Draft, self::Completed, self::Rejected, self::Cancelled], true);
    }

    /**
     * Statuses that count as the applicant's current program (blocks applying to another).
     *
     * @return list<string>
     */
    public static function currentValues(): array
    {
        return collect(self::cases())
            ->reject(fn (self $status) => $status->isTerminal())
            ->map(fn (self $status) => $status->value)
            ->values()
            ->all();
    }

    /**
     * @return list<self>
     */
    public static function processingQueue(): array
    {
        return [
            self::Submitted,
            self::UnderVerification,
            self::Incomplete,
            self::UnderEvaluation,
            self::ForApproval,
            self::ForRevision,
        ];
    }

    /**
     * @return list<self>
     */
    public static function timeline(): array
    {
        return [
            self::Submitted,
            self::UnderVerification,
            self::UnderEvaluation,
            self::ForApproval,
            self::Approved,
            self::ScheduledForRelease,
            self::Released,
            self::Completed,
        ];
    }

    public function timelineIndex(): int
    {
        $index = array_search($this, self::timeline(), true);

        if ($index === false) {
            return match ($this) {
                self::Draft => -1,
                self::Accepted => 0,
                self::Incomplete => 1,
                self::ForRevision => 2,
                self::Rejected => 4,
                self::Cancelled => -1,
                default => 0,
            };
        }

        return $index;
    }

    /**
     * @return list<self>
     */
    public static function casesForFilter(): array
    {
        return self::cases();
    }
}
