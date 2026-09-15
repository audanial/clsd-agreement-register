<?php

namespace App\Actions;

use App\Models\Submission;
use App\Models\SubmissionActivity;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateSubmission
{
    public function __construct(private AuthFactory $auth) {}

    /**
     * Create a submission and its first audit event as one atomic operation.
     *
     * The owner is derived from the authenticated session; no caller-supplied
     * user, status, timestamp, or agreement link is accepted.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(array $input): Submission
    {
        $user = $this->auth->user();

        if ($user === null) {
            throw new AuthorizationException('No authenticated user.');
        }

        Gate::forUser($user)->authorize('create', Submission::class);

        // "Not sure" may arrive as a blank string from the future portal form:
        // treat empty / whitespace-only agreement_type as unset (null), exactly
        // like the explicit null, so the nullable in-rule validation applies.
        if (array_key_exists('agreement_type', $input) && is_string($input['agreement_type'])
            && trim($input['agreement_type']) === '') {
            $input['agreement_type'] = null;
        }

        $validated = Validator::make($input, [
            'title' => ['required', 'string', 'max:255'],
            'campus_id' => [
                'required',
                'integer',
                Rule::exists('campuses', 'id')
                    ->where('is_active', true)
                    ->whereNot('code', 'TBD'),
            ],
            'partner_name' => ['required', 'string', 'max:255'],
            'agreement_type' => ['nullable', Rule::in(Submission::AGREEMENT_TYPES)],
            'purpose' => ['required', 'string', 'max:5000'],
        ])->validate();

        return DB::transaction(function () use ($user, $validated) {
            $submission = new Submission;
            $submission->fill($validated);
            $submission->created_by = $user->id;
            $submission->status = Submission::STATUS_PENDING;
            $submission->submitted_at = now();
            $submission->save();

            app(RecordSubmissionActivity::class)(
                $submission,
                $user,
                SubmissionActivity::TYPE_SUBMISSION_CREATED,
                'Submission created',
            );

            return $submission;
        });
    }
}
