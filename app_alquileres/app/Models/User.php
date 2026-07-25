<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Lessor;
use App\Models\Roomer;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'role',
        'profile_photo_path',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function lessor()
    {
        return $this->hasOne(Lessor::class);
    }

    public function roomer()
    {
        return $this->hasOne(Roomer::class);
    }

    // Helpers (muy recomendados)
    public function isLessor(): bool
    {
        return $this->lessor()->exists();
    }

    public function isRoomer(): bool
    {
        return $this->roomer()->exists();
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->profile_photo_path
            ? asset('storage/'.$this->profile_photo_path)
            : asset('storage/profiles_images/UserProfile_default.png');
    }
}
