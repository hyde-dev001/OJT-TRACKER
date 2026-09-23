<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkLogResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'internship_id' => $this->internship_id,
            'work_date' => $this->work_date?->toDateString(),
            'time_in' => substr((string) $this->time_in, 0, 5),
            'time_out' => substr((string) $this->time_out, 0, 5),
            'break_minutes' => $this->break_minutes,
            'rendered_minutes' => $this->rendered_minutes,
            'accomplishment_summary' => $this->accomplishment_summary,
            'status' => $this->status,
        ];
    }
}
