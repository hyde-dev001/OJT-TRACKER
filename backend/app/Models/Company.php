<?php

namespace App\Models;

use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'account_holder_id',
    ];

    public function accountHolder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'account_holder_id');
    }

    public function internships(): HasMany
    {
        return $this->hasMany(Internship::class);
    }
}
