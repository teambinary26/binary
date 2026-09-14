<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Support\CamData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(): Response
    {
        $announcements = Announcement::query()->with('author')->latest()->paginate(15);

        return Inertia::render('Admin/Announcements/Index', [
            'announcements' => CamData::paginator($announcements, fn ($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'type' => $row->type,
                'type_label' => $row->typeLabel(),
                'is_published' => $row->is_published,
                'published_at' => $row->is_published ? gov_datetime($row->published_at) : 'Draft',
                'author' => $row->author?->name,
            ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Announcements/Form', [
            'announcement' => null,
            'types' => $this->types(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Announcement::query()->create($this->validated($request) + [
            'author_id' => $request->user()->id,
            'published_at' => $request->boolean('is_published') ? now() : null,
        ]);

        return redirect()->route('admin.announcements.index')->with('success', 'Announcement saved.');
    }

    public function edit(Announcement $announcement): Response
    {
        return Inertia::render('Admin/Announcements/Form', [
            'announcement' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'type' => $announcement->type,
                'body' => $announcement->body,
                'is_published' => $announcement->is_published,
            ],
            'types' => $this->types(),
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $data = $this->validated($request);
        $data['published_at'] = $request->boolean('is_published')
            ? ($announcement->published_at ?? now())
            : null;
        $announcement->update($data);

        return back()->with('success', 'Announcement updated.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $announcement->delete();

        return back()->with('success', 'Announcement deleted.');
    }

    private function types(): array
    {
        return [
            ['value' => 'program', 'label' => 'Program announcement'],
            ['value' => 'application_schedule', 'label' => 'Application schedule'],
            ['value' => 'release_schedule', 'label' => 'Release schedule'],
            ['value' => 'requirement', 'label' => 'Requirement change'],
            ['value' => 'notice', 'label' => 'Important notice'],
        ];
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'type' => ['required', 'in:program,application_schedule,release_schedule,requirement,notice'],
            'body' => ['required', 'string'],
            'is_published' => ['nullable', 'boolean'],
        ]);
        $data['is_published'] = $request->boolean('is_published');

        return $data;
    }
}
