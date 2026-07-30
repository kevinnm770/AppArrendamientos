<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrDistrict extends Model
{
    public $timestamps = false;

    protected $fillable = ['province_code', 'canton_code', 'code', 'name'];
}
