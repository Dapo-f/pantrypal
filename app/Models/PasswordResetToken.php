<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'expires_at'])]
class PasswordResetToken extends Model
{
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}