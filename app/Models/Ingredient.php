<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
class Ingredient extends Model
{
    use HasFactory;

    public function recipes()
    {
        return $this->belongsToMany(Recipe::class, 'ingredient_recipe')
            ->withPivot('quantity')
            ->withTimestamps();
    }
}