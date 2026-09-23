<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequirementResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'internship_id' => $this->internship_id,
            'title' => $this->title,
            'description' => $this->description,
            'due_date' => $this->due_date?->toDateString(),
            'is_required' => $this->is_required,
            'status' => $this->status,
            'student_notes' => $this->student_notes,
            'completed_at' => $this->completed_at?->toISOString(),
            'is_overdue' => $this->isOverdue(),
        ];
    }
}
