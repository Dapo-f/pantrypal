<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImportMealDbRecipes extends Command
{
    protected $signature = 'app:import-mealdb-recipes';
    protected $description = 'Import a batch of real recipes from TheMealDB into the local database';

    public function handle()
    {
        $systemUser = User::firstOrCreate(
            ['email' => 'mealdb-import@system.local'],
            [
                'username' => 'MealDB Import',
                'password' => Str::random(32),
                'email_verified_at' => now(),
            ]
        );

        $categories = ['Chicken', 'Beef', 'Seafood', 'Vegetarian', 'Dessert', 'Pasta'];

        foreach ($categories as $mealDbCategory) {
            $this->info("Fetching category: {$mealDbCategory}");

            $response = Http::get('https://www.themealdb.com/api/json/v1/1/filter.php', [
                'c' => $mealDbCategory,
            ]);

            $meals = $response->json('meals') ?? [];

            foreach ($meals as $mealSummary) {
                $this->importSingleMeal($mealSummary['idMeal'], $systemUser);
                usleep(300000); // 0.3 second pause between requests
            }
        }

        $this->info('Import complete.');
    }

    protected function importSingleMeal(string $mealId, User $systemUser)
    {
        $response = Http::get('https://www.themealdb.com/api/json/v1/1/lookup.php', [
            'i' => $mealId,
        ]);

        $meal = $response->json('meals.0');

        if (!$meal) {
            return;
        }

        if (Recipe::where('title', $meal['strMeal'])->exists()) {
            $this->info("Skipping (already exists): {$meal['strMeal']}");
            return;
        }

        $category = Category::firstOrCreate(
            ['name' => $meal['strCategory']],
            ['slug' => Str::slug($meal['strCategory'])]
        );

        $recipe = new Recipe([
            'category_id' => $category->id,
            'title' => $meal['strMeal'],
            'slug' => Str::slug($meal['strMeal']) . '-' . Str::random(6),
            'description' => $meal['strArea'] . ' dish.',
            'instructions' => $meal['strInstructions'],
            'image_path' => $meal['strMealThumb'],
            'prep_time' => 30,
            'servings' => 4,
        ]);
        $recipe->user_id = $systemUser->id;
        $recipe->save();

        for ($i = 1; $i <= 20; $i++) {
            $ingredientName = $meal["strIngredient{$i}"] ?? null;
            $measure = $meal["strMeasure{$i}"] ?? null;

            if (empty($ingredientName) || trim($ingredientName) === '') {
                continue;
            }

            $ingredient = Ingredient::firstOrCreate([
                'name' => strtolower(trim($ingredientName)),
            ]);

            if ($recipe->ingredients()->where('ingredient_id', $ingredient->id)->exists()) {
                continue;
            }

            $recipe->ingredients()->attach($ingredient->id, [
                'quantity' => trim($measure ?? ''),
            ]);
        }
        $this->info("Imported: {$meal['strMeal']}");
    }
}
