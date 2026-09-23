<?php

namespace App\Policies;

use App\Models\Internship;
use App\Models\User;
use App\Models\WorkLog;

class WorkLogPolicy
{
    public function view(User $user, WorkLog $workLog): bool
    {
        return $this->ownsInternship($user, $workLog->internship);
    }

    public function create(User $user, Internship $internship): bool
    {
        return $internship->student_id === $user->id;
    }

    public function update(User $user, WorkLog $workLog): bool
    {
        return $this->ownsInternship($user, $workLog->internship);
    }

    public function delete(User $user, WorkLog $workLog): bool
    {
        return $this->update($user, $workLog);
    }

    private function ownsInternship(User $user, Internship $internship): bool
    {
        return $internship->student_id === $user->id;
    }
}
