<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'group', 'value', 'description'])]
class Setting extends Model
{
    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
