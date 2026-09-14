<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProgramCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        $categories = ProgramCategory::query()->withCount('programs')->orderBy('sort_order')->get();

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'group' => $c->group,
                'group_label' => $c->groupLabel(),
                'programs_count' => $c->programs_count,
                'is_active' => $c->is_active,
                'description' => $c->description,
            ])->values(),
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
}
