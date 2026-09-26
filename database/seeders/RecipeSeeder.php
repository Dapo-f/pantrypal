<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RecipeSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'username' => 'demo',
            'email' => 'demo@example.com',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);

        $dinner = Category::where('name', 'Dinner')->first();

        $recipe = new Recipe([
            'category_id' => $dinner->id,
            'title' => 'Jollof Rice',
            'slug' => Str::slug('Jollof Rice'),
            'description' => 'Classic West African rice dish.',
            'instructions' => 'Blend tomatoes and pepper. Fry the blend in oil with onions. Add rice and stock. Simmer until rice is cooked.',
            'prep_time' => 45,
            'servings' => 4,
        ]);
        $recipe->user_id = $user->id;
        $recipe->save();

        $ingredients = [
            'rice' => '3 cups',
            'tomato' => '4 large',
            'onion' => '2 medium',
            'pepper' => '2 pieces',
            'olive oil' => '1/4 cup',
        ];

        foreach ($ingredients as $name => $quantity) {
            $ingredient = Ingredient::where('name', $name)->first();
            $recipe->ingredients()->attach($ingredient->id, ['quantity' => $quantity]);
        }
    }
}
