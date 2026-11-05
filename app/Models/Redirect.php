<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Old URL → new URL (301) or gone (410). from_path is stored decoded, starting with "/". */
class Redirect extends Model
{
    protected $guarded = ['id'];
}
