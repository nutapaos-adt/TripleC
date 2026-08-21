<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CaseType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function visitRules(): HasMany
    {
        return $this->hasMany(VisitRule::class);
    }

    public function activeVisitRule(): ?VisitRule
    {
        return $this->visitRules()->where('is_active', true)->latest()->first();
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }
}
