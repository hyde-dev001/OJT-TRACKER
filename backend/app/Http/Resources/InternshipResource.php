<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InternshipResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'required_minutes' => $this->required_minutes,
            'required_hours' => round($this->required_minutes / 60, 2),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'work_days' => $this->work_days ?? [],
            'expected_daily_minutes' => $this->expected_daily_minutes,
            'expected_hours_per_day' => $this->expected_daily_minutes === null
                ? null
                : round($this->expected_daily_minutes / 60, 2),
            'status' => $this->status,
            'progress' => $this->progressSummary(),
        ];
    }
}
