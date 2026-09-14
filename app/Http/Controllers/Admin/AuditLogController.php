<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Support\CamData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function index(Request $request): Response
    {
        $logs = AuditLog::query()
            ->with(['user', 'application'])
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $search = '%'.$request->string('q').'%';
                $q->where(function ($query) use ($search) {
                    $query->where('description', 'like', $search)
                        ->orWhere('ip_address', 'like', $search)
                        ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $search));
                });
            })
            ->latest('created_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Admin/Audit/Index', [
            'logs' => CamData::paginator($logs, fn ($log) => [
                'id' => $log->id,
                'user' => $log->user?->name ?? 'System',
                'action' => $log->action,
                'application_no' => $log->application?->application_no,
                'created_at' => gov_datetime($log->created_at),
                'ip_address' => $log->ip_address,
                'description' => $log->description,
            ]),
            'filters' => $request->only(['q', 'action']),
            'actions' => ['created', 'updated', 'verified', 'rejected', 'approved', 'released', 'deleted', 'login', 'logout'],
        ]);
    }
}
