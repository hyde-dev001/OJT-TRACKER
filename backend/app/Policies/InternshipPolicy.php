<?php

namespace App\Policies;

use App\Models\Internship;
use App\Models\User;

class InternshipPolicy
{
    public function view(User $user, Internship $internship): bool
    {
        return $internship->student_id === $user->id;
    }

    public function update(User $user, Internship $internship): bool
    {
        return $this->view($user, $internship);
    }
}
