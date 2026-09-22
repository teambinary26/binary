<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProgramCategory;
use App\Services\AuditService;
use App\Support\CamData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = ProgramCategory::query()->withCount('programs')->orderBy('sort_order')->paginate(15);

        return Inertia::render('Admin/Categories/Index', [
            'categories' => CamData::paginator($categories, fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'group' => $c->group,
                'group_label' => $c->groupLabel(),
                'programs_count' => $c->programs_count,
                'is_active' => $c->is_active,
                'description' => $c->description,
                'can_delete' => $c->programs_count === 0,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'group' => ['required', 'in:student,general'],
            'description' => ['nullable', 'string'],
        ]);
        $data['slug'] = Str::slug($data['name']);
        $data['sort_order'] = ProgramCategory::query()->max('sort_order') + 1;
        ProgramCategory::query()->create($data);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, ProgramCategory $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'group' => ['required', 'in:student,general'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(ProgramCategory $category, AuditService $audit): RedirectResponse
    {
        if ($category->programs()->exists()) {
            return back()->with('error', 'This category cannot be deleted while programs still use it.');
        }

        $name = $category->name;
        $category->delete();
        $audit->log('deleted', 'Deleted program category '.$name);

        return back()->with('success', 'Category deleted.');
    }
}
