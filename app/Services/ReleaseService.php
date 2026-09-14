<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Mail\ReleaseScheduledMail;
use App\Models\Application;
use App\Models\AssistanceRelease;
use App\Models\ReleaseSchedule;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReleaseService
{
    public function __construct(
        private ApplicationService $applications,
        private NotificationService $notifications,
        private AuditService $audit,
    ) {}

    public function schedule(Application $application, User $user, array $data): ReleaseSchedule
    {
        if ($application->status !== ApplicationStatus::Approved && $application->status !== ApplicationStatus::ScheduledForRelease) {
            throw ValidationException::withMessages([
                'application' => 'Only approved applications can be scheduled for release.',
            ]);
        }

        $schedule = $application->releaseSchedules()->create([
            'release_date' => $data['release_date'],
            'release_location' => $data['release_location'],
            'release_method' => $data['release_method'],
            'status' => 'scheduled',
            'scheduled_by' => $user->id,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->applications->changeStatus(
            $application,
            ApplicationStatus::ScheduledForRelease,
            $user,
            'Release scheduled on '.gov_date($schedule->release_date).' at '.$schedule->release_location.'.'
        );

        $this->notifications->notifyApplicant(
            $application,
            'Release scheduled',
            'Assistance for application '.$application->application_no.' is scheduled on '.gov_date($schedule->release_date).' at '.$schedule->release_location.'. Method: '.$schedule->methodLabel().'.',
            'info'
        );

        $this->emailReleaseSchedule($application, $schedule);

        return $schedule;
    }

    public function reschedule(ReleaseSchedule $schedule, User $user, array $data): ReleaseSchedule
    {
        $schedule->loadMissing(['application.applicant.user', 'application.program']);

        if ($schedule->status === 'completed') {
            throw ValidationException::withMessages([
                'schedule' => 'A completed release cannot be rescheduled.',
            ]);
        }

        $application = $schedule->application;

        if ($application->status !== ApplicationStatus::ScheduledForRelease) {
            throw ValidationException::withMessages([
                'schedule' => 'Only scheduled applications can be rescheduled.',
            ]);
        }

        $schedule->update([
            'release_date' => $data['release_date'],
            'release_location' => $data['release_location'],
            'release_method' => $data['release_method'],
            'notes' => $data['notes'] ?? $schedule->notes,
            'scheduled_by' => $user->id,
        ]);

        $schedule->refresh();

        $this->applications->changeStatus(
            $application,
            ApplicationStatus::ScheduledForRelease,
            $user,
            'Release rescheduled to '.gov_date($schedule->release_date).' at '.$schedule->release_location.'.'
        );

        $this->notifications->notifyApplicant(
            $application,
            'Release rescheduled',
            'The release for application '.$application->application_no.' was moved to '.gov_date($schedule->release_date).' at '.$schedule->release_location.'. Method: '.$schedule->methodLabel().'.',
            'info'
        );

        $this->emailReleaseSchedule($application, $schedule, rescheduled: true);

        return $schedule;
    }

    public function recordRelease(Application $application, User $user, array $data): AssistanceRelease
    {
        $application->loadMissing('latestSchedule');

        if ($application->status !== ApplicationStatus::ScheduledForRelease) {
            throw ValidationException::withMessages([
                'application' => 'Record the actual release only after this application has been scheduled.',
            ]);
        }

        $schedule = $application->latestSchedule;

        if (! $schedule || $schedule->status === 'completed') {
            throw ValidationException::withMessages([
                'application' => 'This application has no open release schedule.',
            ]);
        }

        $amount = $data['amount'] ?? $application->approved_amount;

        $release = $application->releases()->create([
            'release_schedule_id' => $data['release_schedule_id'] ?? $application->latestSchedule?->id,
            'amount' => $amount,
            'released_at' => $data['released_at'] ?? now(),
            'released_by' => $user->id,
            'reference_no' => $this->applications->generateNumber(gov('release_prefix', 'REL'), 'assistance_releases', 'reference_no'),
            'verification_code' => strtoupper(Str::random(10)),
            'remarks' => $data['remarks'] ?? null,
        ]);

        if ($release->release_schedule_id) {
            ReleaseSchedule::query()->whereKey($release->release_schedule_id)->update(['status' => 'completed']);
        }

        $this->applications->changeStatus($application, ApplicationStatus::Released, $user, 'Assistance released. Reference '.$release->reference_no.'.', 'released');

        $this->notifications->notifyApplicant(
            $application,
            'Assistance released',
            'Assistance for application '.$application->application_no.' has been released. Reference number: '.$release->reference_no.'. Amount: '.peso($amount, false).'.',
            'success'
        );

        return $release;
    }

    public function verifyClaim(string $lookup, User $user, string $method = 'reference_no'): array
    {
        $query = AssistanceRelease::query()->with(['application.applicant', 'application.program']);

        $release = match ($method) {
            'application_no' => $query->whereHas('application', fn ($q) => $q->where('application_no', $lookup))->latest('id')->first(),
            'qr', 'verification_code' => $query->where('verification_code', strtoupper($lookup))->first(),
            default => $query->where('reference_no', $lookup)->first(),
        };

        if (! $release) {
            $result = [
                'valid' => false,
                'result' => 'invalid',
                'message' => 'No matching release record was found for the information provided.',
                'release' => null,
            ];
            $this->audit->log('updated', 'Failed release verification lookup: '.$lookup, null, null, $user);

            return $result;
        }

        $already = $release->application->status === ApplicationStatus::Completed;

        $release->verifications()->create([
            'verified_by' => $user->id,
            'verified_at' => now(),
            'result' => $already ? 'already_claimed' : 'valid',
            'lookup_method' => $method,
            'remarks' => $already ? 'Release previously completed.' : 'Claim verified.',
        ]);

        if (! $already) {
            $this->applications->changeStatus($release->application, ApplicationStatus::Completed, $user, 'Claim verified and assistance marked completed.');
        }

        $this->audit->log('verified', 'Verified release '.$release->reference_no, $release->application, $release, $user);

        return [
            'valid' => true,
            'result' => $already ? 'already_claimed' : 'valid',
            'message' => $already
                ? 'This assistance has already been verified and completed.'
                : 'Beneficiary identity and release record verified. Application marked completed.',
            'release' => $release,
        ];
    }

    private function emailReleaseSchedule(Application $application, ReleaseSchedule $schedule, bool $rescheduled = false): void
    {
        $application->loadMissing(['applicant.user', 'program']);

        $email = $application->applicant?->email
            ?: $application->applicant?->user?->email;

        if (! $email) {
            return;
        }

        try {
            Mail::to($email)->send(new ReleaseScheduledMail($application, $schedule, $rescheduled));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
}
