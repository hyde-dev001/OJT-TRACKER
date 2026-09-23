<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'internship_id',
        'title',
        'description',
        'due_date',
        'status',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function internship(): BelongsTo
    {
        return $this->belongsTo(Internship::class);
    }

    public function start(): bool
    {
        return $this->status === 'to_do'
            && $this->update(['status' => 'in_progress']);
    }

    public function complete(): bool
    {
        return $this->status === 'in_progress'
            && $this->update([
                'status' => 'completed',
                'completed_at' => now(),
            ]);
    }
}
