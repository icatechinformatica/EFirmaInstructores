<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class tbl_cursos extends Model {

    protected $connection = "pgsql";

    protected $table = 'tbl_cursos';

    protected $casts = ['evidencia_fotografica' => 'json'];

}
