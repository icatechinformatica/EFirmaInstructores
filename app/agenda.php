<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class agenda extends Model
{
    protected $connection = "pgsql";

    protected $table = 'agenda';
}
