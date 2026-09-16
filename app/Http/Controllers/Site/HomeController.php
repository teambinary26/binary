<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AssistanceProgram;
use App\Support\CamData;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $programs = AssistanceProgram::query()->with('category')->orderBy('sort_order')->paginate(15);
        $announcements = Announcement::query()->published()->latest('published_at')->limit(4)->get();

        $codes = [
            ['Student Assistance', 'Educational and financial assistance for qualified students.', 'EDU-001'],
            ['Medical Assistance', 'Financial support for eligible medical and healthcare-related expenses.', 'MED-001'],
            ['Emergency Assistance', 'Support for qualified individuals experiencing urgent financial difficulties.', 'EMG-001'],
            ['Livelihood Assistance', 'Programs intended to help eligible beneficiaries establish or improve livelihood opportunities.', 'LIV-001'],
            ['Other Assistance', 'Other government-supported financial assistance programs including food and burial assistance.', 'FOD-001'],
        ];

        $quickPrograms = AssistanceProgram::query()
            ->whereIn('code', collect($codes)->pluck(2))
            ->get()
            ->keyBy('code');

        return Inertia::render('Public/Home', [
            'programs' => CamData::paginator($programs, fn ($p) => CamData::program($p)),
            'announcements' => $announcements->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->title,
                'type_label' => $item->typeLabel(),
                'excerpt' => \Illuminate\Support\Str::limit(strip_tags($item->body), 140),
                'published_at' => gov_date($item->published_at),
            ])->values(),
            'quick' => collect($codes)->map(function ($row) use ($quickPrograms) {
                $program = $quickPrograms->get($row[2]);

                return [
                    'title' => $row[0],
                    'text' => $row[1],
                    'slug' => $program?->slug,
                ];
            })->values(),
        ]);
    }

    public function howToApply(): Response
    {
        return Inertia::render('Public/HowToApply');
    }

    public function requirements(): Response
    {
        $programs = AssistanceProgram::query()->with(['category', 'requirements'])->orderBy('sort_order')->paginate(10);

        return Inertia::render('Public/Requirements', [
            'programs' => CamData::paginator($programs, fn ($p) => CamData::program($p, true)),
        ]);
    }

    public function contact(): Response
    {
        return Inertia::render('Public/Contact');
    }
}
