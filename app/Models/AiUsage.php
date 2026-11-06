<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiUsage extends Model
{
    protected $guarded = ['id'];

    public static function spentThisMonthUsd(): float
    {
        return (float) static::where('created_at', '>=', now()->startOfMonth())->sum('cost_usd');
    }
}
