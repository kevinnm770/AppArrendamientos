<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasUuids;

    protected $fillable = [
        'name_file',
        'type',
        'weigth',
        'bucket',
    ];
}
