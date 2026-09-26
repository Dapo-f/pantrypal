<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use Illuminate\Database\Seeder;

class IngredientSeeder extends Seeder
{
    public function run(): void
    {
        $ingredients = [
            'chicken', 'beef', 'rice', 'onion', 'garlic', 'tomato',
            'pepper', 'salt', 'egg', 'flour', 'butter', 'milk',
            'cheese', 'potato', 'carrot', 'olive oil', 'ginger',
            'pasta', 'beans', 'fish',
        ];

        foreach ($ingredients as $name) {
            Ingredient::create(['name' => $name]);
        }
    }
}