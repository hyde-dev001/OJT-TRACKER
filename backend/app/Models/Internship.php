<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Internship extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'required_minutes',
        'start_date',
        'end_date',
        'work_days',
        'expected_daily_minutes',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'required_minutes' => 'integer',
            'start_date' => 'date',
            'end_date' => 'date',
            'work_days' => 'array',
            'expected_daily_minutes' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(WorkLog::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(Requirement::class);
    }

    /** @return array<string, int|float> */
    public function progressSummary(): array
    {
        $completedMinutes = (int) $this->workLogs()
            ->where('status', 'completed')
            ->sum('rendered_minutes');
        $requiredMinutes = (int) $this->required_minutes;
        $remainingMinutes = max(0, $requiredMinutes - $completedMinutes);
        $percentage = $requiredMinutes > 0
            ? min(100, (int) round(($completedMinutes / $requiredMinutes) * 100))
            : 0;

        return [
            'completed_minutes' => $completedMinutes,
            'completed_hours' => round($completedMinutes / 60, 2),
            'required_minutes' => $requiredMinutes,
            'required_hours' => round($requiredMinutes / 60, 2),
            'remaining_minutes' => $remainingMinutes,
            'remaining_hours' => round($remainingMinutes / 60, 2),
            'percentage' => $percentage,
        ];
    }
}
