<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * List all categories (with children count).
     */
    public function index()
    {
        $categories = Category::withCount('courses')
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->withCount('courses')])
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    /**
     * Store a new category (admin only).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'icon' => 'nullable|string|max:100',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        $validated['slug'] = Str::slug($validated['name']);

        $category = Category::create($validated);

        return response()->json($category, 201);
    }

    /**
     * Update a category (admin only).
     */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'icon' => 'nullable|string|max:100',
            'parent_id' => 'nullable|exists:categories,id',
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $category->update($validated);

        return response()->json($category->fresh());
    }

    /**
     * Delete a category (admin only).
     */
    public function destroy(Category $category)
    {
        $category->delete();

        return response()->json(['message' => 'Categoría eliminada.']);
    }
}
