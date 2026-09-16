<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\SystemNotification;
use App\Support\CamData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = SystemNotification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return Inertia::render('Applicant/Notifications', [
            'notifications' => CamData::paginator($notifications, fn ($n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'type' => $n->type,
                'unread' => $n->isUnread(),
                'created_at' => gov_datetime($n->created_at),
                'application_id' => $n->application_id,
            ]),
        ]);
    }

    public function markRead(Request $request, SystemNotification $notification): RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
        $notification->update(['read_at' => now()]);

        if ($notification->application_id) {
            $application = $notification->application;

            if ($application?->canBeEditedByApplicant()) {
                return redirect()->route('applicant.apply.documents', $application);
            }

            return redirect()->route('applicant.applications.show', $application);
        }

        return back();
    }
}
