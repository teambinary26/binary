<?php

namespace App\Enums;

enum DocumentVerificationStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
    case RevisionRequested = 'revision_requested';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Verification',
            self::Verified => 'Verified',
            self::Rejected => 'Rejected',
            self::RevisionRequested => 'Revision Requested',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Pending => 'info',
            self::Verified => 'success',
            self::Rejected => 'danger',
            self::RevisionRequested => 'warning',
        };
    }
}
