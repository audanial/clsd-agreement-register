<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'is_institute', 'sort_order', 'is_active'])]
class Campus extends Model
{
    protected function casts(): array
    {
        return [
            'is_institute' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }
}
