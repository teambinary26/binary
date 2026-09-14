<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Support\CamData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationStatusController extends Controller
{
    public function show(Request $request): Response
    {
        $application = null;
        $searched = $request->filled('application_no');

        if ($searched) {
            $data = $request->validate([
                'application_no' => ['required', 'string', 'max:50'],
            ]);

            $match = Application::query()
                ->with(['program.category', 'applicant', 'statusHistory'])
                ->where('application_no', strtoupper(trim($data['application_no'])))
                ->first();

            if ($match) {
                $application = CamData::applicationRow($match);
            }
        }

        return Inertia::render('Public/Status', [
            'application' => $application,
            'searched' => $searched,
            'filters' => [
                'application_no' => $request->string('application_no')->toString(),
            ],
        ]);
    }
}
