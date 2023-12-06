<?php

namespace App\Http\Controllers\evidencia;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class EvidenciaController extends Controller
{
    function __construct() {
        $this->mes = ["01" => "ENERO", "02" => "FEBRERO", "03" => "MARZO", "04" => "ABRIL", "05" => "MAYO", "06" => "JUNIO", "07" => "JULIO", "08" => "AGOSTO", "09" => "SEPTIEMBRE", "10" => "OCTUBRE", "11" => "NOVIEMBRE", "12" => "DICIEMBRE"];
    }

    // 7X-21-ARFT-EXT-0006
    public function index(Request $request) {
        $message = NULL;
        $clave = $request->clave;
        if ($clave != null) session(['claveAsis' => $clave]);
        else $clave = session('claveAsis');
        $curso = tbl_cursos::where('clave', '=', $clave)->first();

        $dias = $dias_agenda = $alumnos = [];
        $fecha_valida = NULL;
        $fecha_hoy = date("d-m-Y");

        if ($curso) {

            $agenda = agenda::Where('id_curso', $curso->folio_grupo)->Select('start')->Get();
            foreach ($agenda as $item) {
                $temporal = explode(' ', $item->start);
                array_push($dias_agenda, $temporal[0]);
            }

            if ($curso->curp == Auth::user()->curp) {
                $inicio = $curso->inicio;
                $termino = $curso->termino;
                for ($i = $inicio; $i <= $termino; $i = date("Y-m-d", strtotime($i . "+ 1 days"))) {
                    foreach($dias_agenda as $moist) {
                        if($i == $moist) {
                            array_push($dias, $i);
                        }
                    }
                }

                if (Auth::user()->unidad == 1) $fecha_penultimo = date("Y-m-d", strtotime($curso->termino . "- 3 days"));
                else $fecha_penultimo = date("Y-m-d", strtotime($curso->termino . "- 1 days"));
                $fecha_valida = strtotime($fecha_hoy) - strtotime($fecha_penultimo);

                // if ($fecha_valida < 0) $message = 'noProcede';

                if ($curso->turnado == "UNIDAD" and $curso->status != "REPORTADO" and $curso->status != "CANCELADO") {
                    $alumnos = DB::connection('pgsql')->table('tbl_inscripcion as i')->select(
                            'i.id',
                            'i.matricula',
                            'i.alumno',
                            'i.calificacion',
                            'f.folio',
                            'i.asistencias'
                        )->leftJoin('tbl_folios as f', function ($join) {
                            $join->on('f.id', '=', 'i.id_folio');
                        })->where('i.id_curso', $curso->id)
                            ->where('i.status', 'INSCRITO')
                            ->orderby('i.alumno')->get();

                    foreach ($alumnos as $key => $value) {
                        $value->asistencias = json_decode($value->asistencias, true);
                    }
                } else $message = 'noDisponible';

            } else $message = 'denegado';

        }
        return view('layouts.asistencia.registrarAsistencias', compact('clave', 'curso', 'dias', 'alumnos', 'message'));
    }
}
