<?php

namespace App\Policies;

use App\Models\Submission;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SubmissionPolicy
{
    /**
     * Determine whether the user can view any submissions.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->canAccessPortal();
    }

    /**
     * Determine whether the user can view the submission.
     */
    public function view(User $user, Submission $submission): Response
    {
        if (! $user->is_active) {
            return Response::deny();
        }

        if ($user->isAdmin() || $user->isLegal()) {
            return Response::allow();
        }

        if ($user->isRequester()) {
            return $submission->created_by === $user->id
                ? Response::allow()
                : Response::denyAsNotFound();
        }

        return Response::deny();
    }

    /**
     * Determine whether the user can view and download the submission's
     * document version history. The owning requester and Legal/Admin share
     * the same history (V1 spec §5.2), so this mirrors view(), including the
     * not-found denial for other requesters.
     */
    public function viewDocuments(User $user, Submission $submission): Response
    {
        return $this->view($user, $submission);
    }

    /**
     * Determine whether the user can create submissions.
     */
    public function create(User $user): bool
    {
        return $user->is_active && $user->isRequester();
    }

    /**
     * Determine whether the user can update the submission.
     */
    public function update(User $user, Submission $submission): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the submission.
     */
    public function delete(User $user, Submission $submission): bool
    {
        return false;
    }
}
