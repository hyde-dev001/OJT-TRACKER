<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Requirement extends Model
{
    use HasFactory;

    protected $fillable = [
        'internship_id',
        'title',
        'description',
        'due_date',
        'is_required',
        'status',
        'student_notes',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'is_required' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function internship(): BelongsTo
    {
        return $this->belongsTo(Internship::class);
    }

    public function isOverdue(): bool
    {
        return $this->due_date?->isBefore(today()) && $this->status !== 'completed';
    }

    public function complete(): bool
    {
        if ($this->status !== 'incomplete') {
            return false;
        }

        return $this->update(['status' => 'completed', 'completed_at' => now()]);
    }

    public function incomplete(): bool
    {
        if ($this->status !== 'completed') {
            return false;
        }

        return $this->update(['status' => 'incomplete', 'completed_at' => null]);
    }
}
