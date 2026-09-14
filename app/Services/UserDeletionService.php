<?php

namespace App\Services;

use App\Exceptions\CouldNotDeleteUserException;
use App\Models\Announcement;
use App\Models\Applicant;
use App\Models\Application;
use App\Models\ApplicationApproval;
use App\Models\ApplicationEvaluation;
use App\Models\AssistanceRelease;
use App\Models\ReleaseSchedule;
use App\Models\ReleaseVerification;
use App\Models\User;
use App\Support\DocumentFiles;
use Illuminate\Support\Facades\DB;

class UserDeletionService
{
    public function delete(User $user, User $actor): void
    {
        if ($user->is($actor)) {
            throw new CouldNotDeleteUserException('You cannot delete your own account.');
        }

        $user->loadMissing(['role', 'applicant.applications.documents', 'applicant.applications.releases']);

        if ($user->isAdmin()) {
            $otherAdminsExist = User::query()
                ->whereKeyNot($user->id)
                ->whereHas('role', fn ($query) => $query->where('slug', 'administrator'))
                ->exists();

            if (! $otherAdminsExist) {
                throw new CouldNotDeleteUserException('The last administrator account cannot be deleted.');
            }
        }

        if ($user->isApplicant()) {
            $this->deleteApplicantUser($user);

            return;
        }

        $this->assertStaffHasNoRestrictedRecords($user);

        $user->delete();
    }

    private function deleteApplicantUser(User $user): void
    {
        $applicant = $user->applicant;
        $directories = [];

        if ($applicant) {
            $directories = $this->applicationDocumentDirectories($applicant);
        }

        DB::transaction(function () use ($user, $applicant) {
            if ($applicant) {
                $this->purgeApplicantRecords($applicant);
            }

            $user->delete();
        });

        foreach ($directories as $directory) {
            DocumentFiles::deleteDirectory($directory);
        }
    }

    private function purgeApplicantRecords(Applicant $applicant): void
    {
        $applications = $applicant->applications()
            ->with(['releases', 'releaseSchedules'])
            ->get();

        foreach ($applications as $application) {
            $this->purgeApplicationRecords($application);
        }
    }

    private function purgeApplicationRecords(Application $application): void
    {
        foreach ($application->releases as $release) {
            $release->verifications()->delete();
            $release->delete();
        }

        $application->releaseSchedules()->delete();
        $application->delete();
    }

    /**
     * @return list<string>
     */
    private function applicationDocumentDirectories(Applicant $applicant): array
    {
        return $applicant->applications
            ->map(fn (Application $application) => 'documents/'.$application->id)
            ->unique()
            ->values()
            ->all();
    }

    private function assertStaffHasNoRestrictedRecords(User $user): void
    {
        $linked = ApplicationEvaluation::query()->where('evaluator_id', $user->id)->exists()
            || ApplicationApproval::query()->where('officer_id', $user->id)->exists()
            || ReleaseSchedule::query()->where('scheduled_by', $user->id)->exists()
            || AssistanceRelease::query()->where('released_by', $user->id)->exists()
            || ReleaseVerification::query()->where('verified_by', $user->id)->exists()
            || Announcement::query()->where('author_id', $user->id)->exists();

        if ($linked) {
            throw new CouldNotDeleteUserException(
                'This staff account cannot be deleted because it is linked to evaluations, approvals, releases, or announcements.'
            );
        }
    }
}
