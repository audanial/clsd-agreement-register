<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'short_name', 'country_id'])]
class Partner extends Model
{
    use SoftDeletes;

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function agreements(): HasMany
    {
        return $this->hasMany(Agreement::class);
    }
}
