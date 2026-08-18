<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'agreement_id', 'uploaded_by', 'document_type', 'original_filename', 'storage_path', 'mime_type', 'file_size',
])]
class AgreementFile extends Model
{
    use SoftDeletes;

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
        ];
    }

    public function agreement(): BelongsTo
    {
        return $this->belongsTo(Agreement::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
