<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Patient extends Model
{
    use HasFactory;

    public const ZONE_IN_AREA = 'in_area';
    public const ZONE_OUT_AREA = 'out_area';

    protected $fillable = [
        'hn',
        'national_id',
        'name',
        'dob',
        'phone',
        'address',
        'sub_district',
        'district',
        'province',
        'zone',
    ];

    protected function casts(): array
    {
        return [
            'dob' => 'date',
        ];
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function isInArea(): bool
    {
        return $this->zone === self::ZONE_IN_AREA;
    }
}
