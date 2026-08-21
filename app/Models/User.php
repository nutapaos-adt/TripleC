<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_WARD_STAFF = 'ward_staff';
    public const ROLE_HOME_VISIT_TEAM = 'home_visit_team';
    public const ROLE_ADMIN = 'admin';

    public const ROLES = [
        self::ROLE_WARD_STAFF => 'พยาบาล/เจ้าหน้าที่หอผู้ป่วย',
        self::ROLE_HOME_VISIT_TEAM => 'ทีมเยี่ยมบ้าน',
        self::ROLE_ADMIN => 'แอดมิน/หัวหน้าแผนก',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(self::ROLE_ADMIN);
    }

    public function isHomeVisitTeam(): bool
    {
        return $this->hasRole(self::ROLE_HOME_VISIT_TEAM);
    }

    public function isWardStaff(): bool
    {
        return $this->hasRole(self::ROLE_WARD_STAFF);
    }

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? $this->role;
    }
}
