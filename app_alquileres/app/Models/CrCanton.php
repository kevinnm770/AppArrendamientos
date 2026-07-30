<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrCanton extends Model
{
    public $timestamps = false;

    protected $fillable = ['province_code', 'code', 'name'];

    public function province()
    {
        return $this->belongsTo(CrProvince::class, 'province_code', 'code');
    }

    public function districts()
    {
        return $this->hasMany(CrDistrict::class, 'canton_code', 'code')
            ->whereColumn('cr_districts.province_code', 'cr_cantons.province_code');
    }
}
