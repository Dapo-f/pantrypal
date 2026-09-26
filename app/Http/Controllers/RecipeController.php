<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Models\Ingredient;

class RecipeController extends Controller
{
    public function index(Request $request)
    {
        $recipes = Recipe::with(['user', 'category'])
            ->when($request->category_id, function ($query, $categoryId) {
                $query->where('category_id', $categoryId);
            })
            ->latest()
            ->paginate(12);

        return response()->json($recipes);
    }

    public function show(Recipe $recipe)
    {
        $recipe->load(['user', 'category', 'ingredients']);

        return response()->json($recipe);
    }

    public function search(Request $request)
    {
        $request->validate([
            'ingredients' => 'required|string',
        ]);

        $ingredientNames = array_map(function ($name) {
            return strtolower(trim($name));
        }, explode(',', $request->ingredients));

        $recipes = Recipe::with(['user', 'category', 'ingredients'])
            ->whereHas('ingredients', function ($query) use ($ingredientNames) {
                $query->whereIn('name', $ingredientNames);
            }, '=', count($ingredientNames))
            ->get();

        return response()->json($recipes);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'prep_time' => 'nullable|integer|min:0',
            'servings' => 'nullable|integer|min:1',
            'ingredients' => 'required|array|min:1',
            'ingredients.*.name' => 'required|string',
            'ingredients.*.quantity' => 'nullable|string',
        ]);

        $validatedData['slug'] = Str::slug($validatedData['title']) . '-' . Str::random(6);

        $recipe = new Recipe($validatedData);
        $recipe->user_id = $request->user()->id;
        $recipe->save();

        foreach ($request->ingredients as $item) {
            $ingredient = Ingredient::firstOrCreate(['name' => strtolower(trim($item['name']))]);
            $recipe->ingredients()->attach($ingredient->id, ['quantity' => $item['quantity'] ?? null]);
        }

        $recipe->load('ingredients');

        return response()->json($recipe, 201);
    }

    public function update(Request $request, Recipe $recipe)
    {
        if ($recipe->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $validatedData = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'sometimes|string',
            'prep_time' => 'nullable|integer|min:0',
            'servings' => 'nullable|integer|min:1',
            'ingredients' => 'sometimes|array|min:1',
            'ingredients.*.name' => 'required_with:ingredients|string',
            'ingredients.*.quantity' => 'nullable|string',
        ]);

        if (isset($validatedData['title'])) {
            $validatedData['slug'] = Str::slug($validatedData['title']) . '-' . Str::random(6);
        }

        $recipe->update($validatedData);

        if ($request->has('ingredients')) {
            $recipe->ingredients()->detach();

            foreach ($request->ingredients as $item) {
                $ingredient = Ingredient::firstOrCreate(['name' => strtolower(trim($item['name']))]);
                $recipe->ingredients()->attach($ingredient->id, ['quantity' => $item['quantity'] ?? null]);
            }
        }

        $recipe->load(['user', 'category', 'ingredients']);

        return response()->json($recipe);
    }

    public function destroy(Request $request, Recipe $recipe)
    {
        if ($recipe->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $recipe->delete();

        return response()->json(['message' => 'Recipe deleted successfully.']);
    }
}
