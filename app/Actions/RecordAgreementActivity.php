<?php

namespace App\Actions;

use App\Models\Agreement;
use App\Models\AgreementActivity;

class RecordAgreementActivity
{
    /**
     * Record an activity against an agreement.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function __invoke(Agreement $agreement, string $type, string $description, ?array $meta = null): void
    {
        AgreementActivity::create([
            'agreement_id' => $agreement->id,
            'user_id' => auth()->id(),
            'type' => $type,
            'description' => $description,
            'meta' => $meta,
        ]);
    }
}
