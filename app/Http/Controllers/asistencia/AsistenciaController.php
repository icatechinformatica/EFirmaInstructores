<?php

namespace App\Http\Controllers\asistencia;

use PDF;
use App\tbl_cursos;
use App\agenda;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\tbl_inscripcion;
use DateInterval;
use DateTime;
use Illuminate\Support\Facades\Auth;

class AsistenciaController extends Controller
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
        // dd('a');
        $dias = $dias_agenda = $dias_agenda_raw = $alumnos = [];
        $fecha_valida = NULL;
        $fecha_hoy = date("d-m-Y");

        if ($curso) {

            $agenda = agenda::Where('id_curso', $curso->folio_grupo)->Select('start','end')->GroupBy('start','end')->Get();
            foreach ($agenda as $key => $item) {

                $temporal_start = explode(' ', $item->start);
                $temporal_end = explode(' ', $item->end);
                $start_date = new DateTime($temporal_start[0]);
                $end_date = new DateTime($temporal_end[0]);

                while ($start_date <= $end_date) {
                    array_push($dias_agenda_raw, $start_date->format('Y-m-d'));
                    $start_date->modify('+1 day');
                }
            }

            foreach($dias_agenda_raw as $moist) {
                if(!in_array($moist, $dias_agenda)) {
                    array_push($dias_agenda, $moist);
                }
            }

            if ($curso->curp != Auth::user()->curp) {
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

                if ($curso->status_curso == "AUTORIZADO") {
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

    public function update(Request $request) {
        $message = '';
        $fechas = $request->fechas;
        $alumnos = $request->alumnos;
        $asistencias = $request->asistencias;

        if ($asistencias != null) {
            foreach ($alumnos as $alumno) {
                $asisAlumno = [];
                foreach ($fechas as $fecha) {
                    $bandera = false;
                    foreach ($asistencias as $asistencia) {
                        if ($alumno == explode(' ', $asistencia)[0] && $fecha == explode(' ', $asistencia)[1]) $bandera = true;
                    }
                    if ($bandera) {
                        $temp = [
                            'fecha' => $fecha,
                            'asistencia' => true
                        ];
                    } else {
                        $temp = [
                            'fecha' => $fecha,
                            'asistencia' => false
                        ];
                    }
                    array_push($asisAlumno, $temp);
                }
                // se actualiza el alumno en la bd
                tbl_inscripcion::where('id', '=', $alumno)->update(['asistencias' => $asisAlumno]);
                $message = 'Las asistencias se guardaron exitosamente';
            }
        } else $message = 'Debe marcar los checks en la fecha que los alumnos asistieron a la capacitación';

        // return redirect('/Asistencia/inicio')->with(['message'=>$message]);
        return redirect()->route('asistencia.inicio')->with('success', 'ASISTENCIAS GUARDADAS EXITOSAMENTE!');
    }

    public function asistenciaPdf(Request $request) {
        $clave = $request->clave2;

        if ($clave) {
            // $curso = tbl_cursos::where('clave', '=', $clave)->first();
            $curso = DB::connection('pgsql')->table('tbl_cursos')->select(
                'tbl_cursos.*',
                DB::raw('right(clave,4) as grupo'),
                'inicio',
                'termino',
                DB::raw("to_char(inicio, 'DD/MM/YYYY') as fechaini"),
                DB::raw("to_char(termino, 'DD/MM/YYYY') as fechafin"),
                'u.plantel',
                )->where('clave',$clave);
            $curso = $curso->leftjoin('tbl_unidades as u','u.unidad','tbl_cursos.unidad')->first();
            if ($curso) {
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
                    if (!$alumnos) return "NO HAY ALUMNOS INSCRITOS";

                    foreach ($alumnos as $key => $value) {
                        $value->asistencias = json_decode($value->asistencias, true);
                    }
                    $mes = $this->mes;
                    $consec = 1;
                    if ($curso->inicio and $curso->termino) {
                        $inicio = explode('-', $curso->inicio); $inicio[2] = '01';
                        $termino = explode('-', $curso->termino); $termino[2] = '01';
                        $meses = $this->verMeses(array($inicio[0].'-'.$inicio[1].'-'.$inicio[2], $termino[0].'-'.$termino[1].'-'.$termino[2]));

                    } else  return "El Curso no tiene registrado la fecha de inicio y de termino";

                    // tbl_cursos::where('id', $curso->id)->update(['asis_finalizado' => true]);

                    $pdf = PDF::loadView('layouts.asistencia.reporteAsistencia', compact('curso', 'alumnos', 'mes', 'consec', 'meses'));
                    $pdf->setPaper('Letter', 'landscape');
                    $file = "ASISTENCIA_$clave.PDF";
                    return $pdf->stream($file);

                    // if ($fecha_valida < 0) $message = "No prodece el registro de calificaciones, la fecha de termino del curso es el $curso->termino.";
                } // else $message = "El Curso fué $curso->status y turnado a $curso->turnado.";
            }
        }
    }

    public function asistenciaEnviar(Request $request) {
        $clave = $request->clave3;
        if ($clave) {
            // $curso = tbl_cursos::where('clave', '=', $clave)->first();
            $curso = DB::connection('pgsql')->table('tbl_cursos')->select(
                'tbl_cursos.*',
                DB::raw('right(clave,4) as grupo'),
                'inicio',
                'termino',
                DB::raw("to_char(inicio, 'DD/MM/YYYY') as fechaini"),
                DB::raw("to_char(termino, 'DD/MM/YYYY') as fechafin"),
                'u.plantel',
                )->where('clave',$clave);
            $curso = $curso->leftjoin('tbl_unidades as u','u.unidad','tbl_cursos.unidad')->first();
            if ($curso) {
                if ($curso->turnado == "UNIDAD" and $curso->status != "REPORTADO" and $curso->status != "CANCELADO") {
                    tbl_cursos::where('id', $curso->id)->update(['asis_finalizado' => true]);
                    return redirect()->route('asistencia.inicio')->with('success', 'ASISTENCIAS ENVIADAS EXITOSAMENTE!');
                }
                return redirect()->route('asistencia.inicio')->with('alert', 'EL ENVIO DE ASISTENCIAS FUE ABORTADO. EL CURSO YA ESTA REPORTADO Y/O CANCELADO!');
            }
            return redirect()->route('asistencia.inicio')->with('alert', 'EL ENVIO DE ASISTENCIAS FUE ABORTADO, INTENTELO DE NUEVO MAS TARDE');
        }
    }

    function verMeses($a) {
        $f1 = new DateTime($a[0]);
        $f2 = new DateTime($a[1]);

        // obtener la diferencia de fechas
        $d = $f1->diff($f2);
        $difmes =  $d->format('%m');
        $messs = $this->mes;

        $meses = [];
        $temp = [
            'fecha' => $f1->format('Y-m-d'),
            'ultimoDia' => date("Y-m-t", strtotime($f1->format('Y-m-d'))),
            'mes' => $messs[$f1->format('m')],
            'year' => $f1->format('Y'),
            'dias' => $this->getDays($f1->format('Y-m-d'), date("Y-m-t", strtotime($f1->format('Y-m-d'))))
        ];
        array_push($meses, $temp);

        $impf = $f1;
        for ($i = 1; $i <= $difmes; $i++) {
            // despliega los meses
            $impf->add(new DateInterval('P1M'));
            $temp = [
                'fecha' => $impf->format('Y-m-d'),
                'ultimoDia' => date("Y-m-t", strtotime($impf->format('Y-m-d'))),
                'mes' => $messs[$f1->format('m')],
                'year' => $impf->format('Y'),
                'dias' => $this->getDays($impf->format('Y-m-d'), date("Y-m-t", strtotime($impf->format('Y-m-d'))))
            ];
            array_push($meses, $temp);
        }
        return $meses;
    }

    function getDays($dateInicio, $dateFinal) {
        $dias = [];
        for ($i = $dateInicio; $i <= $dateFinal; $i = date("Y-m-d", strtotime($i . "+ 1 days"))) {
            array_push($dias, $i);
        }
        return $dias;
    }
}
