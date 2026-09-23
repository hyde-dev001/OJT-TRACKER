<?php

namespace App\Policies;

use App\Models\Internship;
use App\Models\Requirement;
use App\Models\User;

class RequirementPolicy
{
    public function view(User $user, Requirement $requirement): bool
    {
        return $this->ownsInternship($user, $requirement->internship);
    }

    public function create(User $user, Internship $internship): bool
    {
        return $this->ownsInternship($user, $internship);
    }

    public function update(User $user, Requirement $requirement): bool
    {
        return $this->ownsInternship($user, $requirement->internship);
    }

    public function delete(User $user, Requirement $requirement): bool
    {
        return $this->ownsInternship($user, $requirement->internship);
    }

    public function complete(User $user, Requirement $requirement): bool
    {
        return $this->ownsInternship($user, $requirement->internship);
    }

    public function incomplete(User $user, Requirement $requirement): bool
    {
        return $this->ownsInternship($user, $requirement->internship);
    }

    private function ownsInternship(User $user, Internship $internship): bool
    {
        return $internship->student_id === $user->id;
    }
}
