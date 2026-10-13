<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
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
            'status' => $this->status,
            'completed_at' => $this->completed_at?->toISOString(),
            'submission_status' => $this->submission_status,
            'proof_available' => $this->proof_path !== null,
            'submission_comment' => $this->submission_comment,
            'review_comment' => $this->review_comment,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'reviewed_at' => $this->reviewed_at?->toISOString(),
            'is_overdue' => $this->status !== 'completed'
                && $this->due_date?->isBefore(today()) === true,
        ];
    }
}
