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

    public const STATUS_ACTIVE_DUTY = 'active_duty';
    public const STATUS_ACTIVE_DUTY_FAMILY = 'active_duty_family';
    public const STATUS_CIVILIAN = 'civilian';

    public const GENDER_MALE = 'male';
    public const GENDER_FEMALE = 'female';

    protected $fillable = [
        'hn',
        'national_id',
        'name',
        'dob',
        'gender',
        'phone',
        'address',
        'sub_district',
        'district',
        'province',
        'zone',
        'status',
        'military_unit',
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
