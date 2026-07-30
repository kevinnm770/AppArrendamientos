<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrProvince extends Model
{
    public $incrementing = false;
    protected $keyType = 'string';
    protected $primaryKey = 'code';
    public $timestamps = false;

    protected $fillable = ['code', 'name'];

    public function cantons()
    {
        return $this->hasMany(CrCanton::class, 'province_code', 'code');
    }
}
