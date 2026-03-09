<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Roomer extends Model
{
    protected $fillable = ['user_id', 'legal_name', 'id_number', 'phone'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function agreements()
    {
        return $this->hasMany(Agreement::class);
    }
}
