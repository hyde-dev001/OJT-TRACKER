<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use InvalidArgumentException;

class WorkLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'internship_id',
        'work_date',
        'time_in',
        'time_out',
        'break_minutes',
        'rendered_minutes',
        'accomplishment_summary',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'break_minutes' => 'integer',
            'rendered_minutes' => 'integer',
        ];
    }

    public function internship(): BelongsTo
    {
        return $this->belongsTo(Internship::class);
    }

    public static function calculateRenderedMinutes(string $timeIn, string $timeOut, int $breakMinutes): int
    {
        $start = CarbonImmutable::createFromFormat('H:i', $timeIn);
        $end = CarbonImmutable::createFromFormat('H:i', $timeOut);

        if (! $start || ! $end || $end->lessThanOrEqualTo($start)) {
            throw new InvalidArgumentException('Time out must be after time in.');
        }

        $sessionMinutes = $start->diffInMinutes($end);

        if ($breakMinutes < 0 || $breakMinutes >= $sessionMinutes) {
            throw new InvalidArgumentException('Break minutes must be within the work session.');
        }

        return $sessionMinutes - $breakMinutes;
    }

}
