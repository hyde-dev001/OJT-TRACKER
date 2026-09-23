<?php

namespace App\Policies;

use App\Models\Internship;
use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    public function view(User $user, Task $task): bool
    {
        return $this->ownsInternship($user, $task->internship);
    }

    public function create(User $user, Internship $internship): bool
    {
        return $this->ownsInternship($user, $internship);
    }

    public function update(User $user, Task $task): bool
    {
        return $this->ownsInternship($user, $task->internship);
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->ownsInternship($user, $task->internship);
    }

    public function start(User $user, Task $task): bool
    {
        return $this->ownsInternship($user, $task->internship);
    }

    public function complete(User $user, Task $task): bool
    {
        return $this->ownsInternship($user, $task->internship);
    }

    private function ownsInternship(User $user, Internship $internship): bool
    {
        return $internship->student_id === $user->id;
    }
}
