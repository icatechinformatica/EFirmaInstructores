<?php

namespace App\Http\Controllers\asistencia;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Spatie\ArrayToXml\ArrayToXml;
use App\DocumentosFirmar;
use Illuminate\Http\Request;
use App\Tokens_icti;
use App\tbl_inscripcion;
use App\tbl_cursos;
use Carbon\Carbon;
use DateInterval;
use App\agenda;
use DateTime;
use PDF;

class AsistenciaController extends Controller
{

    function __construct() {
        $this->mes = ["01" => "ENERO", "02" => "FEBRERO", "03" => "MARZO", "04" => "ABRIL", "05" => "MAYO", "06" => "JUNIO", "07" => "JULIO", "08" => "AGOSTO", "09" => "SEPTIEMBRE", "10" => "OCTUBRE", "11" => "NOVIEMBRE", "12" => "DICIEMBRE"];
    }

    // 7X-21-ARFT-EXT-0006
    public function index(Request $request) {
        $message = NULL;
        $procesoPago = FALSE;
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

                $pagos=DB::Connection('pgsql')->Table('pagos')->Where('id_curso',$curso->id)->Value('status_recepcion');
                if($pagos != null && in_array($pagos, ['VALIDADO', 'En Espera'])) {
                    $procesoPago = TRUE;
                }

            } else $message = 'denegado';

        }
        return view('layouts.asistencia.registrarAsistencias', compact('clave', 'curso', 'dias', 'alumnos', 'message','procesoPago'));
    }

    public function update(Request $request) {
        $message = '';
        $fechas = $request->fechas;
        $alumnos = $request->alumnos;
        $asistencias = $request->asistencias;
        $totalFechas = count($fechas);

        if ($asistencias != null) {
            foreach ($alumnos as $alumno) {
                $countAsistencia = 0;
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
                        $countAsistencia++;
                    } else {
                        $temp = [
                            'fecha' => $fecha,
                            'asistencia' => false
                        ];
                    }
                    array_push($asisAlumno, $temp);
                }

                // se actualiza el alumno en la bd
                $porcentajeAsistencias = ($countAsistencia / $totalFechas) * 100;
                $alumnoCheck = tbl_inscripcion::Where('id', $alumno)->First();
                $calif_finalizado = tbl_cursos::Where('id', $alumnoCheck->id_curso)->Value('calif_finalizado');

                if($porcentajeAsistencias < 80) {
                    if($alumnoCheck->calificacion != 'NP' && $alumnoCheck->calificacion != '0')
                    {
                        $message = 'El alumno '.$alumnoCheck->alumno.' tiene una calificación aprobatoria pero un porcentaje de asistencia reprobatorio. Por favor, revise nuevamente.';
                        return redirect()->route('asistencia.inicio')->with('Warning', $message);
                    }

                    if($calif_finalizado == FALSE || $alumnoCheck->calificacion == 'NP') {
                        tbl_inscripcion::where('id', $alumno)
                            ->update(['calificacion' => 'NP',
                                    'iduser_updated'=>Auth::user()->id,
                                    'asistencias' => $asisAlumno,
                                    'porcentaje_asis' => $porcentajeAsistencias]);
                    } else {
                        $message = 'No se le puede asignar un porcentaje reprobatorio al alumno '.$alumnoCheck->alumno.'. Se envio a unidad con calificación aprobatoria. Por favor, revise nuevamente.';
                        return redirect()->route('asistencia.inicio')->with('Warning', $message);
                    }
                }
                else {
                    tbl_inscripcion::where('id', '=', $alumno)
                    ->update(['asistencias' => $asisAlumno,
                              'porcentaje_asis' => $porcentajeAsistencias]);
                }
                $message = 'ASISTENCIAS GUARDADAS EXITOSAMENTE!';
            }
        } else $message = 'Debe marcar los checks en la fecha que los alumnos asistieron a la capacitación';

        // return redirect('/Asistencia/inicio')->with(['message'=>$message]);
        return redirect()->route('asistencia.inicio')->with('success', $message);
    }

    public function asistenciaPdf(Request $request) {
        $clave = $request->clave2;

        if ($clave) {
            // $curso = tbl_cursos::where('clave', '=', $clave)->first();
            $curso = DB::connection('pgsql')->table('tbl_cursos')->select(
                'tbl_cursos.status_curso',
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
            //inicio generacion de cadena unica
            $info = DB::Connection('pgsql')->Table('tbl_cursos')->Select('tbl_unidades.*','tbl_cursos.clave','tbl_cursos.nombre','tbl_cursos.curp','instructores.correo')
                ->Join('tbl_unidades','tbl_unidades.unidad','tbl_cursos.unidad')
                ->join('instructores','instructores.id','tbl_cursos.id_instructor')
                ->Where('tbl_cursos.clave',$clave)
                ->First();

            $body = $this->create_body($clave,$info); //creacion de body
            $body = str_replace(["\r", "\n", "\f"], ' ', $body);

            $nameFileOriginal = 'Lista de asistencia '.$info->clave.'.pdf';
            $numOficio = 'LAD-04-'.$info->clave;
            $numFirmantes = '2';

            $arrayFirmantes = [];

            $dataFirmante = DB::connection('pgsql')->Table('tbl_organismos AS org')
            ->Select('fun.id as id_fun','org.id', 'fun.nombre AS funcionario','fun.curp', 'us.name',
            'fun.cargo','fun.correo', 'us.puesto', 'fun.incapacidad')
                ->join('tbl_funcionarios AS fun', 'fun.id','org.id')
                ->join('users as us', 'us.email','fun.correo')
                ->where('org.nombre', 'LIKE', '%ACADEMICO%')
                ->where('org.nombre', 'LIKE', '%'.$info->ubicacion.'%')
                ->first();


            if($dataFirmante == null){
                return redirect()->route('firma.inicio')->with('danger', 'NO SE ENCONTRARON DATOS DEL FIRMANTE AL REALIZAR LA CONSULTA');
            }

            ##Incapacidad
            $val_inca = $this->valid_incapacidad($dataFirmante);
            if ($val_inca != null) {
                $dataFirmante = $val_inca;
            }

            $temp = ['_attributes' =>
            [
                'curp_firmante' => $info->curp,
                'nombre_firmante' => $info->nombre,
                'email_firmante' => $info->correo,
                'tipo_firmante' => 'FM'
            ]
            ];
            array_push($arrayFirmantes, $temp);

            $temp = ['_attributes' =>
                [
                    'curp_firmante' => $dataFirmante->curp,
                    'nombre_firmante' => $dataFirmante->funcionario,
                    'email_firmante' => $dataFirmante->correo,
                    'tipo_firmante' => 'FM'
                ]
            ];
            array_push($arrayFirmantes, $temp);

            $ArrayXml = [
                'emisor' => [
                    '_attributes' => [
                        'nombre_emisor' => Auth::user()->name,
                        'cargo_emisor' => 'Instructor Externo',
                        'dependencia_emisor' => 'Instituto de Capacitación y Vinculación Tecnológica del Estado de Chiapas'
                        // 'curp_emisor' => $dataEmisor->curp
                    ],
                ],
                'archivo' => [
                    '_attributes' => [
                        'nombre_archivo' => $nameFileOriginal
                        // 'md5_archivo' => $md5
                        // 'checksum_archivo' => utf8_encode($text)
                    ],
                    // 'cuerpo' => ['Por medio de la presente me permito solicitar el archivo '.$nameFile]
                    'cuerpo' => [$body]
                ],
                'firmantes' => [
                    '_attributes' => [
                        'num_firmantes' => $numFirmantes
                    ],
                    'firmante' => [
                        $arrayFirmantes
                    ]
                ],
            ];
            //Creacion de estampa de hora exacta de creacion
            $date = Carbon::now();
            $month = $date->month < 10 ? '0'.$date->month : $date->month;
            $day = $date->day < 10 ? '0'.$date->day : $date->day;
            $hour = $date->hour < 10 ? '0'.$date->hour : $date->hour;
            $minute = $date->minute < 10 ? '0'.$date->minute : $date->minute;
            $second = $date->second < 10 ? '0'.$date->second : $date->second;
            $dateFormat = $date->year.'-'.$month.'-'.$day.'T'.$hour.':'.$minute.':'.$second;

            $result = ArrayToXml::convert($ArrayXml, [
                'rootElementName' => 'DocumentoChis',
                '_attributes' => [
                    'version' => '2.0',
                    'fecha_creacion' => $dateFormat,
                    'no_oficio' => $numOficio,
                    'dependencia_origen' => 'Instituto de Capacitación y Vinculación Tecnológica del Estado de Chiapas',
                    'asunto_docto' => 'Lista de asistencia LAD-04',
                    'tipo_docto' => 'OFC',
                    'xmlns' => 'http://firmaelectronica.chiapas.gob.mx/GCD/DoctoGCD',
                ],
            ]);
            //Generacion de cadena unica mediante el ICTI
            $xmlBase64 = base64_encode($result);
            $getToken = Tokens_icti::all()->last();
            if ($getToken) {
                $response = $this->getCadenaOriginal($xmlBase64, $getToken->token);
                if ($response->json() == null) {
                    $token = $this->generarToken();
                    $response = $this->getCadenaOriginal($xmlBase64, $token);
                }
            } else {// no hay registros

                $token = $this->generarToken();
                $response = $this->getCadenaOriginal($xmlBase64, $token);
            }

            //Guardado de cadena unica
            if ($response->json()['cadenaOriginal'] != null) {

                $dataInsert = DocumentosFirmar::Where('numero_o_clave',$info->clave)->Where('tipo_archivo','Lista de asistencia')->First();
                if(is_null($dataInsert)) {
                    $dataInsert = new DocumentosFirmar();
                }
                $dataInsert->obj_documento = json_encode($ArrayXml);
                $dataInsert->obj_documento_interno = json_encode($ArrayXml);
                $dataInsert->status = 'EnFirma';
                // $dataInsert->link_pdf = $urlFile;
                $dataInsert->cadena_original = $response->json()['cadenaOriginal'];
                $dataInsert->tipo_archivo = 'Lista de asistencia';
                $dataInsert->numero_o_clave = $info->clave;
                $dataInsert->nombre_archivo = $nameFileOriginal;
                $dataInsert->documento = $result;
                $dataInsert->documento_interno = $result;
                // $dataInsert->md5_file = $md5;
                $dataInsert->save();

            } else {
                return redirect()->route('firma.inicio')->with('danger', 'Hubo un Error al Validar. Intente Nuevamente en unos Minutos.');
            }
            //termina generacion de cadena unica

            // $curso = tbl_cursos::where('clave', '=', $clave)->first();
            $curso = DB::connection('pgsql')->table('tbl_cursos')->select(
                'tbl_cursos.status_curso',
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
                if ($curso->status_curso == "AUTORIZADO") {
                    tbl_cursos::where('id', $curso->id)->update(['asis_finalizado' => true]);
                    return redirect()->route('asistencia.inicio')->with('success', 'ASISTENCIAS ENVIADAS EXITOSAMENTE!');
                }
                return redirect()->route('asistencia.inicio')->with('alert', 'EL ENVIO DE ASISTENCIAS FUE ABORTADO. EL CURSO YA ESTA REPORTADO Y/O CANCELADO!');
            }
            return redirect()->route('asistencia.inicio')->with('alert', 'EL ENVIO DE ASISTENCIAS FUE ABORTADO, INTENTELO DE NUEVO MAS TARDE');
        }
    }

    private function create_body($clave, $firmantes) {
        $curso = DB::Connection('pgsql')->Table('tbl_cursos')->select(
            'tbl_cursos.*',
            DB::raw('right(clave,4) as grupo'),
            'inicio',
            'termino',
            DB::raw("to_char(inicio, 'DD/MM/YYYY') as fechaini"),
            DB::raw("to_char(termino, 'DD/MM/YYYY') as fechafin"),
            'u.plantel',
            )->where('tbl_cursos.clave',$clave);
        $curso = $curso->leftjoin('tbl_unidades as u','u.unidad','tbl_cursos.unidad')->first();
        $alumnos = DB::Connection('pgsql')->Table('tbl_inscripcion as i')->select(
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

        $mes = $this->mes;
        $consec = 1;
        $inicio = explode('-', $curso->inicio); $inicio[2] = '01';
        $termino = explode('-', $curso->termino); $termino[2] = '01';
        $meses = $this->verMeses(array($inicio[0].'-'.$inicio[1].'-'.$inicio[2], $termino[0].'-'.$termino[1].'-'.$termino[2]));

        $body = "SUBSECRETARÍA DE EDUCACIÓN E INVESTIGACIÓN TECNOLÓGICAS \n".
        "DIRECCIÓN GENERAL DE CENTROS DE FORMACIÓN PARA EL TRABAJO \n".
        "LISTA DE ASISTENCIA \n".
        "(LAD-04) \n";

        foreach($meses as $key => $mes) {
            $consec = 1;
            $body = $body. 'UNIDAD DE CAPACITACIÓN: '. $curso->plantel. ' '.   $curso->unidad. ' CLAVE CCT: '. $curso->cct. ' CICLO ESCOLAR: '. $curso->ciclo. ' GRUPO: '. $curso->grupo. ' MES: '. $mes['mes'] . ' AÑO: '. $mes['year'].
            "\n AREA: ". $curso->area. ' ESPECIALIDAD: '. $curso->espe. ' CURSO: '. $curso->curso. ' CLAVE: '. $curso->clave.
            "\n FECHA INICIO: ". $curso->fechaini. ' FECHA TERMINO: '. $curso->fechafin. ' HORARIO: '. $curso->dia. ' DE '. $curso->hini. ' A '. $curso->hfin. ' CURP: '. $curso->curp.
            "NUM NÚMERO DE CONTROL NOMBRE DEL ALUMNO PRIMER APELLIDO/SEGUNDO APELLIDO/NOMBRE(S) \n";
            foreach($mes['dias'] as $keyD => $dia){
                $body = ' '. $body. ' '. ($keyD+1);
            }
            $body = $body. ' TOTAL '. ' A I ';
            foreach($alumnos as $a) {
                $tAsis = 0;
                $tFalta = 0;
                $body = $body . "\n". $consec++. ' '. $a->matricula. ' '. $a->alumno. ' ';
                foreach($mes['dias'] as $dia) {
                    if($a->asistencias != null) {
                        foreach($a->asistencias as $asistencia) {
                            if($asistencia['fecha'] == $dia && $asistencia['asistencia'] == true) {
                                $body = $body. '* ';
                                $tAsis++;
                            } else if($asistencia['fecha'] == $dia && $asistencia['asistencia'] == false) {
                                $body = $body. 'x ';
                                $tFalta++;
                            }
                        }
                    }
                }
                $body = $body. $tAsis. ' '. $tFalta. ' ';
            }
        }

        return $body;
    }

    ### BY JOSE LUIS / VALIDACIÓN DE INCAPACIDAD
    public function valid_incapacidad($dataFirmante){
        $result = null;

        $status_campos = false;
        if($dataFirmante->incapacidad != null){
            $dataArray = json_decode($dataFirmante->incapacidad, true);

            ##Validamos los campos json
            if(isset($dataArray['fecha_inicio']) && isset($dataArray['fecha_termino'])
            && isset($dataArray['id_firmante']) && isset($dataArray['historial'])){

                if($dataArray['fecha_inicio'] != '' && $dataArray['fecha_termino'] != '' && $dataArray['id_firmante'] != ''){
                    $fecha_ini = $dataArray['fecha_inicio'];
                    $fecha_fin = $dataArray['fecha_termino'];
                    $id_firmante = $dataArray['id_firmante'];
                    $historial = $dataArray['historial'];
                    $status_campos = true;
                }
            }else{
                return redirect()->route('firma.inicio')->with('danger', 'LA ESTRUCTURA DEL JSON DE LA INCAPACIDAD NO ES VALIDA!');
            }

            ##Validar si esta vacio
            if($status_campos == true){
                ##Validar las fechas
                $fechaActual = date("Y-m-d");
                $fecha_nowObj = new DateTime($fechaActual);
                $fecha_iniObj = new DateTime($fecha_ini);
                $fecha_finObj = new DateTime($fecha_fin);

                if($fecha_nowObj >= $fecha_iniObj && $fecha_nowObj <= $fecha_finObj){
                    ###Realizamos la consulta del nuevo firmante
                    $dataIncapacidad = DB::Connection('pgsql')->Table('tbl_organismos AS org')
                    ->Select('org.id', 'fun.nombre AS funcionario','fun.curp',
                    'fun.cargo','fun.correo', 'org.nombre', 'fun.incapacidad')
                    ->join('tbl_funcionarios AS fun', 'fun.id','org.id')
                    ->where('fun.id', $id_firmante)
                    ->first();

                    if ($dataIncapacidad != null) {$result = $dataIncapacidad;}
                    else{return redirect()->route('firma.inicio')->with('danger', 'NO SE ENCONTRON DATOS DE LA PERSONA QUE TOMARÁ EL LUGAR DEL ACADEMICO!');}

                }else{
                    ##Historial
                    $fecha_busqueda = 'Ini:'. $fecha_ini .'/Fin:'. $fecha_fin .'/IdFun:'. $id_firmante;
                    $clave_ar = array_search($fecha_busqueda, $historial);

                    if($clave_ar === false){ ##No esta en el historial entonces guardamos
                        $historial[] = $fecha_busqueda;
                        ##guardar en la bd el nuevo array en el campo historial del json
                        try {
                            $jsonHistorial = json_encode($historial);
                            DB::Connection('pgsql')->update('UPDATE tbl_funcionarios SET incapacidad = jsonb_set(incapacidad, \'{historial}\', ?) WHERE id = ?', [$jsonHistorial, $dataFirmante->id_fun]);
                        } catch (\Throwable $th) {
                            return redirect()->route('firma.inicio')->with('danger', 'Error: ' . $th->getMessage());
                        }

                    }
                }
            }

        }
        return $result;
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

    public function generarToken() {
        ##Producción
        $resToken = Http::withHeaders([
            'Accept' => 'application/json'
        ])->post('https://interopera.chiapas.gob.mx/gobid/api/AppAuth/AppTokenAuth', [
            'nombre' => 'SISTEM_INSTRUC',
            'key' => '7339F037-D329-4165-A1C9-45FAA99D5FD9'
        ]);

        ##Prueba
        // $resToken = Http::withHeaders([
        //     'Accept' => 'application/json'
        // ])->post('https://interopera.chiapas.gob.mx/gobid/api/AppAuth/AppTokenAuth', [
        //     'nombre' => 'FirmaElectronica',
        //     'key' => '19106D6F-E91F-4C20-83F1-1700B9EBD553'
        // ]);

        $token = $resToken->json();
        Tokens_icti::create([
            'token' => $token
        ]);
        return $token;
    }

    // obtener la cadena original
    public function getCadenaOriginal($xmlBase64, $token) {
        ##Produccion
        $response1 = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token,
        ])->post('https://api.firma.chiapas.gob.mx/FEA/v2/Tools/generar_cadena_original', [
            'xml_OriginalBase64' => $xmlBase64
        ]);

        ##Prueba
        // $response1 = Http::withHeaders([
        //     'Accept' => 'application/json',
        //     'Authorization' => 'Bearer '.$token,
        // ])->post('https://apiprueba.firma.chiapas.gob.mx/FEA/v2/Tools/generar_cadena_original', [
        //     'xml_OriginalBase64' => $xmlBase64
        // ]);

        return $response1;
    }
}
