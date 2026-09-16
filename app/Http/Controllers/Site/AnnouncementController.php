<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Support\CamData;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        $announcements = Announcement::query()->published()->latest('published_at')->paginate(10);

        return Inertia::render('Public/Announcements/Index', [
            'announcements' => CamData::paginator($announcements, fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'type_label' => $item->typeLabel(),
                'excerpt' => \Illuminate\Support\Str::limit(strip_tags($item->body), 220),
                'published_at' => gov_datetime($item->published_at),
            ]),
        ]);
    }

    public function show(Announcement $announcement): Response
    {
        abort_unless($announcement->is_published, 404);

        return Inertia::render('Public/Announcements/Show', [
            'announcement' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'type_label' => $announcement->typeLabel(),
                'body' => $announcement->body,
                'published_at' => gov_datetime($announcement->published_at),
            ],
        ]);
    }
}
