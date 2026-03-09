<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lessor extends Model
{
    protected $fillable = [
        'user_id',
        'legal_name',
        'commercial_name',
        'identification_type',
        'id_number',
        'phone',
        'email',
        'address',
        'province',
        'canton',
        'district',
        'barrio',
        'other_signs',
        'economic_activity_code',
        'crlibre_username',
        'crlibre_password',
        'crlibre_session_key',
        'crlibre_session_obtained_at',
        'certificate_code',
        'certificate_pin',
        'certificate_uploaded_at',
        'hacienda_username',
        'hacienda_password',
        'hacienda_access_token',
        'hacienda_refresh_token',
        'hacienda_token_expires_at',
        'hacienda_refresh_expires_at',
    ];

    protected $hidden = [
        'crlibre_password',
        'crlibre_session_key',
        'certificate_pin',
        'hacienda_password',
        'hacienda_access_token',
        'hacienda_refresh_token',
    ];

    protected function casts(): array
    {
        return [
            'crlibre_password' => 'encrypted',
            'crlibre_session_key' => 'encrypted',
            'certificate_pin' => 'encrypted',
            'hacienda_password' => 'encrypted',
            'hacienda_access_token' => 'encrypted',
            'hacienda_refresh_token' => 'encrypted',
            'crlibre_session_obtained_at' => 'datetime',
            'certificate_uploaded_at' => 'datetime',
            'hacienda_token_expires_at' => 'datetime',
            'hacienda_refresh_expires_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function agreements()
    {
        return $this->hasMany(Agreement::class);
    }

    public function hasCrLibreAccount(): bool
    {
        return filled($this->crlibre_username) && filled($this->crlibre_password);
    }

    public function hasUploadedCertificate(): bool
    {
        return filled($this->certificate_code);
    }
}
