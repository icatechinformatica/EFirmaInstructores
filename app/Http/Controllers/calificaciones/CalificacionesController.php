<?php

namespace App\Http\Controllers\calificaciones;

use PDF;
use App\tbl_cursos;
use App\instructores;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class CalificacionesController extends Controller {

    function __construct() {
        session_start();
    }

    public function index(Request $request) {
        $message = NULL;
        if(session('message')) $message = session('message');

        $clave = $request->clave;
        if($clave == null) {
            if(session('clave')) $clave = session('clave');
        }
        $curso = tbl_cursos::where('clave', '=', $clave)->first();

        $fecha_hoy = date("d-m-Y");
        $fecha_valida = NULL;
        $alumnos = [];
        $denegado = '';
        if ($curso) {
            if ($curso->id_instructor != Auth::user()->id_sivyc) {
                if (Auth::user()->unidad == 1) $fecha_penultimo = date("Y-m-d", strtotime($curso->termino . "- 3 days"));
                else $fecha_penultimo = date("Y-m-d", strtotime($curso->termino . "- 1 days"));
                $fecha_valida =  strtotime($fecha_hoy) - strtotime($fecha_penultimo);

                if ($curso->turnado == "UNIDAD" and $curso->status != "REPORTADO" and $curso->status != "CANCELADO") {
                    $alumnos = DB::connection('pgsql')->table('tbl_inscripcion as i')->select('i.id', 'i.matricula', 'i.alumno', 'i.calificacion', 'f.folio')
                        ->leftJoin('tbl_folios as f', function ($join) {
                            $join->on('f.id', '=', 'i.id_folio');
                        })
                        ->where('i.id_curso', $curso->id)->where('i.status', 'INSCRITO')->orderby('i.alumno')->get();

                    if ($fecha_valida < 0) $message = "No prodece el registro de calificaciones, la fecha de termino del curso es el $curso->termino.";
                } else $message = "El Curso fué $curso->status y turnado a $curso->turnado.";

                if(count($alumnos)==0 AND !$message) $message = "El curso no tiene alumnos registrados. ";
                else $_SESSION['id_curso'] = $curso->id;
            } else {
                $denegado = 'denegado';
            }
        }// else $message = "Clave inválida.";

        return view('layouts.calificaciones.agregarCalificaciones', compact('clave', 'curso', 'alumnos', 'message', 'fecha_valida', 'denegado'));
    }

    public function update(Request $request) {
        $id_curso = $_SESSION['id_curso'];
        $clave = $request->clave;
        if($request->calificacion ){
            foreach($request->calificacion as $key=>$val){
                if(!is_numeric($val) OR $val<6 )  $val = "NP";
                $result = DB::connection('pgsql')
                    ->table('tbl_inscripcion')
                    ->where('id_curso',$id_curso)
                    ->where('id', $key)
                    ->update(['calificacion' => $val,'iduser_updated'=>Auth::user()->id]);
            }
            if($result) $message = "Calificaciones guardadas exitosamente!";
        }else $message = "No existen cambios que guardar.";

        return redirect('/Calificaciones/inicio')->with(['message'=>$message, 'clave'=>$clave]);
    }

    public function calificacionEnviar(Request $request) {
        $clave = $request->clave3;
        if($clave) {
            $curso = DB::connection('pgsql')->table('tbl_cursos')->select(
                'tbl_cursos.*',
                DB::raw('right(clave,4) as grupo'),
                DB::raw("to_char(inicio, 'DD/MM/YYYY') as fechaini"),
                DB::raw("to_char(termino, 'DD/MM/YYYY') as fechafin"),
                'u.plantel'
            )->where('clave',$clave);
            $curso = $curso->leftjoin('tbl_unidades as u','u.unidad','tbl_cursos.unidad')->first();
            if($curso) {
                tbl_cursos::where('id', $curso->id)->update(['calif_finalizado' => true]);
                return redirect()->route('calificaciones.inicio')->with('success', 'CALIFICACIONES ENVIADAS EXITOSAMENTE!');
            } else return "Curso no v&aacute;lido para esta Unidad";
        }
        return "Clave no v&aacute;lida";
    }

    public function calificaciones(Request $request) {
        $clave = $request->get('clavePDF');
        if($clave) {
            $curso = DB::connection('pgsql')->table('tbl_cursos')->select(
                'tbl_cursos.*',
                DB::raw('right(clave,4) as grupo'),
                DB::raw("to_char(inicio, 'DD/MM/YYYY') as fechaini"),
                DB::raw("to_char(termino, 'DD/MM/YYYY') as fechafin"),
                'u.plantel'
            )->where('clave',$clave);
            // if($_SESSION['unidades']) $curso = $curso->whereIn('u.ubicacion',$_SESSION['unidades']);
            $curso = $curso->leftjoin('tbl_unidades as u','u.unidad','tbl_cursos.unidad')->first();
            if($curso) {
                $consec_curso = $curso->id_curso;
                $fecha_termino = $curso->inicio;
                $alumnos = DB::connection('pgsql')->table('tbl_inscripcion as i')->select(
                        'i.matricula',
                        'i.alumno',
                        'i.calificacion'
                    )->where('i.id_curso',$curso->id)
                    ->where('i.status','INSCRITO')
                    ->groupby('i.matricula','i.alumno','i.calificacion')
                    ->orderby('i.alumno')
                    ->get();
                if(count($alumnos)==0){
                    return "NO HAY ALUMNOS INSCRITOS";
                    exit;
                }
                $consec = 1;
                $pdf = PDF::loadView('layouts.calificaciones.pdfCalificaciones', compact('curso','alumnos','consec'));
                $pdf->setPaper('Letter', 'landscape');
                $file = "CALIFICACIONES_$clave.PDF";
                return $pdf->stream($file);
            } else return "Curso no v&aacute;lido para esta Unidad";
        }
        return "Clave no v&aacute;lida";
    }
}
