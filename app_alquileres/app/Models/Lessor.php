<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lessor extends Model
{
    protected $fillable = ['user_id','legal_name','id_number','phone','address'];

    public function agreements()
    {
        return $this->hasMany(Agreement::class);
    }
}
