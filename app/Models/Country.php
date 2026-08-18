<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'iso_code', 'is_domestic'])]
class Country extends Model
{
    protected function casts(): array
    {
        return [
            'is_domestic' => 'boolean',
        ];
    }

    public function partners(): HasMany
    {
        return $this->hasMany(Partner::class);
    }
}
