<?php

namespace App\Actions;

use App\Models\Submission;
use App\Models\SubmissionActivity;
use App\Models\User;

class RecordSubmissionActivity
{
    /**
     * Record an activity against a submission.
     *
     * @param  array<string, mixed>|null  $meta
     */
    public function __invoke(Submission $submission, User $actor, string $type, string $description, ?array $meta = null): SubmissionActivity
    {
        return SubmissionActivity::create([
            'submission_id' => $submission->id,
            'user_id' => $actor->id,
            'type' => $type,
            'description' => $description,
            'meta' => $meta,
        ]);
    }
}
