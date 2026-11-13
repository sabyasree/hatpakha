<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** D179: a player's report of a wrong word, clue or riddle in the games. */
class WordReport extends Model
{
    protected $guarded = ['id'];
}
