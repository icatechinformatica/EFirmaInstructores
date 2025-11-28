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
use App\Services\CalificacionValidationService;


class AsistenciaController extends Controller
{

    function __construct()
    {
        $this->mes = ["01" => "ENERO", "02" => "FEBRERO", "03" => "MARZO", "04" => "ABRIL", "05" => "MAYO", "06" => "JUNIO", "07" => "JULIO", "08" => "AGOSTO", "09" => "SEPTIEMBRE", "10" => "OCTUBRE", "11" => "NOVIEMBRE", "12" => "DICIEMBRE"];
    }

    // 7X-21-ARFT-EXT-0006
    public function index(Request $request)
    {
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

            $agenda = agenda::Where('id_curso', $curso->folio_grupo)->Select('start', 'end')->GroupBy('start', 'end')->Get();
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

            foreach ($dias_agenda_raw as $moist) {
                if (!in_array($moist, $dias_agenda)) {
                    array_push($dias_agenda, $moist);
                }
            }

            if ($curso->curp == Auth::user()->curp) {
                $inicio = $curso->inicio;
                $termino = $curso->termino;
                for ($i = $inicio; $i <= $termino; $i = date("Y-m-d", strtotime($i . "+ 1 days"))) {
                    foreach ($dias_agenda as $moist) {
                        if ($i == $moist) {
                            array_push($dias, $i);
                        }
                    }
                }

                if (Auth::user()->unidad == 1) $fecha_penultimo = date("Y-m-d", strtotime($curso->termino . "- 3 days"));
                else $fecha_penultimo = date("Y-m-d", strtotime($curso->termino . "- 1 days"));
                $fecha_valida = strtotime($fecha_hoy) - strtotime($curso->termino);

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

                $pagos = DB::Connection('pgsql')->Table('pagos')->Where('id_curso', $curso->id)->Value('status_recepcion');
                if ($pagos != null && in_array($pagos, ['VALIDADO', 'En Espera'])) {
                    $procesoPago = TRUE;
                }
            } else $message = 'denegado';
        }
        return view('layouts.asistencia.registrarAsistencias', compact('clave', 'curso', 'dias', 'alumnos', 'message', 'procesoPago', 'fecha_valida'));
    }

    public function update(Request $request)
    {
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
                // $alumnoCheck = tbl_inscripcion::Where('id', $alumno)->First();
                // $calif_finalizado = tbl_cursos::Where('id', $alumnoCheck->id_curso)->Value('calif_finalizado');
                // $calif_alumno = tbl_inscripcion::select('calificacion', 'alumno')->Where('id', $alumno)->first();

                tbl_inscripcion::where('id', '=', $alumno)
                    ->update([
                        'asistencias' => $asisAlumno,
                        'porcentaje_asis' => $porcentajeAsistencias
                    ]);
                $message = 'ASISTENCIAS GUARDADAS EXITOSAMENTE!';
            }
        } else $message = 'Debe marcar los checks en la fecha que los alumnos asistieron a la capacitación';

        // return redirect('/Asistencia/inicio')->with(['message'=>$message]);
        return redirect()->route('asistencia.inicio')->with('success', $message);
    }

    public function asistenciaPdf(Request $request)
    {
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
            )->where('clave', $clave);
            $curso = $curso->leftjoin('tbl_unidades as u', 'u.unidad', 'tbl_cursos.unidad')->first();
            if ($curso) {
                if ($curso->status_curso == "AUTORIZADO") {
                    $documento = DocumentosFirmar::where('numero_o_clave', $clave)
                        ->WhereNotIn('status', ['CANCELADO', 'CANCELADO ICTI'])
                        ->Where('tipo_archivo', 'Lista de asistencia')
                        ->first();

                    if (is_null($documento)) {
                        $body = $this->create_body($clave);
                        $body_html = $body['body_html'];
                        $header = $body['header'];
                    } else {
                        $body = json_decode($documento->obj_documento_interno);
                        $body_html = $body->body_html;
                        $header = $body->header;
                    }

                    //-- ELIMINAR DESPUES DEL 01/01/2025 --
                    // $alumnos = DB::connection('pgsql')->table('tbl_inscripcion as i')->select(
                    //     'i.id',
                    //     'i.matricula',
                    //     'i.alumno',
                    //     'i.calificacion',
                    //     'f.folio',
                    //     'i.asistencias'
                    // )->leftJoin('tbl_folios as f', function ($join) {
                    //     $join->on('f.id', '=', 'i.id_folio');
                    // })->where('i.id_curso', $curso->id)
                    //     ->where('i.status', 'INSCRITO')
                    //     ->orderby('i.alumno')->get();
                    // if (!$alumnos) return "NO HAY ALUMNOS INSCRITOS";

                    // foreach ($alumnos as $key => $value) {
                    //     $value->asistencias = json_decode($value->asistencias, true);
                    // }
                    // $mes = $this->mes;
                    // $consec = 1;
                    // if ($curso->inicio and $curso->termino) {
                    //     $inicio = explode('-', $curso->inicio); $inicio[2] = '01';
                    //     $termino = explode('-', $curso->termino); $termino[2] = '01';
                    //     $meses = $this->verMeses(array($inicio[0].'-'.$inicio[1].'-'.$inicio[2], $termino[0].'-'.$termino[1].'-'.$termino[2]));

                    // } else  return "El Curso no tiene registrado la fecha de inicio y de termino";

                    // tbl_cursos::where('id', $curso->id)->update(['asis_finalizado' => true]);


                    $pdf = PDF::loadView('layouts.asistencia.reporteAsistencia', compact('body_html', 'header'));
                    $pdf->setPaper('Letter', 'landscape');
                    $file = "ASISTENCIA_$clave.PDF";
                    return $pdf->stream($file);

                    // if ($fecha_valida < 0) $message = "No prodece el registro de calificaciones, la fecha de termino del curso es el $curso->termino.";
                } // else $message = "El Curso fué $curso->status y turnado a $curso->turnado.";
            }
        }
    }

    public function asistenciaEnviar(Request $request)
    {
        $clave = $request->clave3;
        if ($clave) {
            // Validar asistencias usando el servicio
            $validationService = new CalificacionValidationService();
            $validacion = $validationService->validarAsistenciasParaEnvio($clave);

            // Si hay errores de validación, regresar con los mensajes
            if (!$validacion['valido']) {
                return redirect()
                    ->route('asistencia.inicio')
                    ->with([
                        'clave' => $clave,
                        'errores' => $validacion['errores'],
                        'message' => 'No se puede enviar la lista de asistencias. Se encontraron ' . $validacion['alumnos_con_error'] . ' problema(s) con los registros de asistencia.'
                    ]);
            }

            //inicio generacion de cadena unica
            $info = DB::Connection('pgsql')->Table('tbl_cursos')->Select('tbl_unidades.*', 'tbl_cursos.clave', 'tbl_cursos.nombre', 'tbl_cursos.curp', 'instructores.correo')
                ->Join('tbl_unidades', 'tbl_unidades.unidad', 'tbl_cursos.unidad')
                ->join('instructores', 'instructores.id', 'tbl_cursos.id_instructor')
                ->Where('tbl_cursos.clave', $clave)
                ->First();

            $body = $this->create_body($clave, $info); //creacion de body
            // $body = str_replace(["\r", "\n", "\f"], ' ', $body);

            $nameFileOriginal = 'Lista de asistencia ' . $info->clave . '.pdf';
            $numOficio = 'LAD-04-' . $info->clave;
            $numFirmantes = '2';

            $arrayFirmantes = [];

            $dataFirmante = DB::connection('pgsql')->Table('tbl_organismos AS org')
                ->Select(
                    'fun.id as id_fun',
                    'org.id',
                    'fun.nombre AS funcionario',
                    'fun.curp',
                    'fun.cargo',
                    'fun.correo',
                    'fun.incapacidad'
                )
                ->join('tbl_funcionarios AS fun', 'fun.id_org', 'org.id')
                // ->join('users as us', 'us.email','fun.correo')
                ->where('org.nombre', 'LIKE', '%ACADEMICO%')
                ->where('org.nombre', 'LIKE', '%' . $info->ubicacion . '%')
                ->Where('fun.titular', true)
                ->Where('fun.activo', 'true')
                ->first();


            if ($dataFirmante == null) {
                return back()->with('danger', 'NO SE ENCONTRARON DATOS DEL FIRMANTE AL REALIZAR LA CONSULTA');
            }

            if ($dataFirmante->curp == null) {
                return back()->with('Danger', 'Error: La curp de un firmante no se encuentra');
            }

            ##Incapacidad
            $val_inca = $this->valid_incapacidad($dataFirmante);
            if ($val_inca != null) {
                $dataFirmante = $val_inca;
            }

            $temp = [
                '_attributes' =>
                [
                    'curp_firmante' => $info->curp,
                    'nombre_firmante' => $info->nombre,
                    'email_firmante' => $info->correo,
                    'tipo_firmante' => 'FM'
                ]
            ];
            array_push($arrayFirmantes, $temp);

            $temp = [
                '_attributes' =>
                [
                    'curp_firmante' => $dataFirmante->curp,
                    'nombre_firmante' => $dataFirmante->funcionario,
                    'email_firmante' => $dataFirmante->correo,
                    'tipo_firmante' => 'FM'
                ]
            ];

            $firmante = ['nombre' => $dataFirmante->funcionario, 'curp' => $dataFirmante->curp, 'cargo' => $dataFirmante->cargo];
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
                'receptores' => [
                    'receptor' => [
                        '_attributes' => [
                            'nombre_receptor' => $dataFirmante->funcionario,
                            'cargo_receptor' => $dataFirmante->cargo,
                            'dependencia_receptor' => 'Instituto de Capacitación y Vinculación Tecnológica del Estado de Chiapas',
                            'tipo_receptor' => 'JDP'
                        ]
                    ]
                ],
                'archivo' => [
                    '_attributes' => [
                        'nombre_archivo' => $nameFileOriginal
                        // 'md5_archivo' => $md5
                        // 'checksum_archivo' => utf8_encode($text)
                    ],
                    // 'cuerpo' => ['Por medio de la presente me permito solicitar el archivo '.$nameFile]
                    'cuerpo' => [$body['body_xml']]
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

            array_pop($body);
            //Creacion de estampa de hora exacta de creacion
            $date = Carbon::now();
            $month = $date->month < 10 ? '0' . $date->month : $date->month;
            $day = $date->day < 10 ? '0' . $date->day : $date->day;
            $hour = $date->hour < 10 ? '0' . $date->hour : $date->hour;
            $minute = $date->minute < 10 ? '0' . $date->minute : $date->minute;
            $second = $date->second < 10 ? '0' . $date->second : $date->second;
            $dateFormat = $date->year . '-' . $month . '-' . $day . 'T' . $hour . ':' . $minute . ':' . $second;

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
            } else { // no hay registros

                $token = $this->generarToken();
                $response = $this->getCadenaOriginal($xmlBase64, $token);
            }

            //Guardado de cadena unica
            if ($response->json()['cadenaOriginal'] != null) {

                $dataInsert = DocumentosFirmar::Where('numero_o_clave', $info->clave)->Where('tipo_archivo', 'Lista de asistencia')->First();
                if (is_null($dataInsert)) {
                    $dataInsert = new DocumentosFirmar();
                }

                array_push($body, ['firmantes' => $firmante]);

                $dataInsert->obj_documento = json_encode($ArrayXml);
                $dataInsert->obj_documento_interno = json_encode($body);
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
            )->where('clave', $clave);
            $curso = $curso->leftjoin('tbl_unidades as u', 'u.unidad', 'tbl_cursos.unidad')->first();
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

    // private function verificacionAsistencias($clave)
    // {
    //     $message = NULL;
    //     $idCurso = DB::Connection('pgsql')->Table('tbl_cursos')->Where('clave', $clave)->Value('id');
    //     $listaAlumnos = DB::Connection('pgsql')->Table('tbl_inscripcion')->Select('asistencias', 'porcentaje_asis', 'calificacion', 'alumno', 'id_curso')
    //         ->Where('id_curso', $idCurso)
    //         ->Get();
    //     foreach ($listaAlumnos as $info) {
    //         $calif = intval($info->calificacion);
    //         if (is_null($info->asistencias)) {
    //             $message = 'Error: No se puede enviar a unidad, el alumno ' . $info->alumno . '. No tiene asistencias/inasistencias registradas. Por favor, revise nuevamente.';
    //             return redirect()->route('asistencia.inicio')->with('Warning', $message);
    //         } else if ($info->porcentaje_asis < 80 && is_numeric($info->calificacion) && $calif > 5) {
    //             $message = 'Error: No se puede enviar a unidad, el alumno ' . $info->alumno . '. tiene una calificación aprobatoria (' . $info->calificacion . ') pero un porcentaje de asistencia reprobatorio (' . $info->porcentaje_asis . '%). Por favor, revise nuevamente.';
    //         }

    //         if (!is_null($message)) {
    //             return redirect()->route('asistencia.inicio')->with('Warning', $message);
    //         }
    //     }
    //     return 'Exito';
    // }

    private function create_body($clave, $firmantes = null)
    {
        $curso = DB::Connection('pgsql')->Table('tbl_cursos')->select(
            'tbl_cursos.*',
            DB::raw('right(clave,4) as grupo'),
            'inicio',
            'termino',
            DB::raw("to_char(inicio, 'DD/MM/YYYY') as fechaini"),
            DB::raw("to_char(termino, 'DD/MM/YYYY') as fechafin"),
            'u.plantel',
        )->where('tbl_cursos.clave', $clave);
        $curso = $curso->leftjoin('tbl_unidades as u', 'u.unidad', 'tbl_cursos.unidad')->first();
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
        $inicio = explode('-', $curso->inicio);
        $inicio[2] = '01';
        $termino = explode('-', $curso->termino);
        $termino[2] = '01';
        $meses = $this->verMeses(array($inicio[0] . '-' . $inicio[1] . '-' . $inicio[2], $termino[0] . '-' . $termino[1] . '-' . $termino[2]));

        $array_html['header'] = '<header>
            <img src="img/reportes/sep.png" alt="sep" width="16%" style="position:fixed; left:0; margin: -70px 0 0 20px;" />
            <h6>SUBSECRETARÍA DE EDUCACIÓN E INVESTIGACIÓN TECNOLÓGICAS</h6>
            <h6>DIRECCIÓN GENERAL DE CENTROS DE FORMACIÓN PARA EL TRABAJO</h6>
            <h6>LISTA DE ASISTENCIA</h6>
            <h6>(LAD-04)</h6>
        </header>';
        $array_html['body_html'] = null;
        if (isset($meses)) {
            foreach ($meses as $key => $mes) {
                $consec = 0;
                $array_html['body_html'] = $array_html['body_html'] . '<table class="tabla">
                    <thead>
                        <tr>
                            <td ';
                if (explode('-', $mes['ultimoDia'])[2] == 28) {
                    $array_html['body_html'] = $array_html['body_html'] . 'colspan="33"';
                } elseif (explode('-', $mes['ultimoDia'])[2] == 29) {
                    $array_html['body_html'] = $array_html['body_html'] . 'colspan="34"';
                } elseif (explode('-', $mes['ultimoDia'])[2] == 30) {
                    $array_html['body_html'] = $array_html['body_html'] . 'colspan="35"';
                } else {
                    $array_html['body_html'] = $array_html['body_html'] . 'colspan="36"';
                }
                $array_html['body_html'] = $array_html['body_html'] . '>
                                <div id="curso">
                                    UNIDAD DE CAPACITACIÓN:
                                    <span class="tab">' . $curso->plantel . ' ' . $curso->unidad . '</span>
                                    CLAVE CCT: <span class="tab">' . $curso->cct . '</span>
                                    CICLO ESCOLAR: <span class="tab">' . $curso->ciclo . '</span>
                                    GRUPO: <span class="tab">' . $curso->folio_grupo . '</span>
                                    MES: <span class="tab">' . $mes['mes'] . '</span>
                                    AÑO: &nbsp;&nbsp;' . $mes['year'] . '
                                    <br />
                                    AREA: <span class="tab1">' . $curso->area . '</span>
                                    ESPECIALIDAD: <span class="tab1">' . $curso->espe . '</span>
                                    CURSO: <span class="tab1">' . $curso->curso . '</span>
                                    CLAVE: &nbsp;&nbsp;' . $curso->clave . '
                                    <br />
                                    FECHA INICIO: <span class="tab1">' . $curso->fechaini . '</span>
                                    FECHA TERMINO: <span class="tab1">' . $curso->fechafin . '</span>
                                    HORARIO: ' . $curso->dia . ' DE ' . $curso->hini . ' A ' . $curso->hfin . '&nbsp;&nbsp;&nbsp;
                                    CURP: &nbsp;&nbsp;' . $curso->curp . ' &nbsp;&nbsp;&nbsp;
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th ';
                if (explode('-', $mes['ultimoDia'])[2] == 28) {
                    $array_html['body_html'] = $array_html['body_html'] . 'colspan="33"';
                } elseif (explode('-', $mes['ultimoDia'])[2] == 29) {
                    $array_html['body_html'] = $array_html['body_html'] . 'colspan="34"';
                } elseif (explode('-', $mes['ultimoDia'])[2] == 30) {
                    $array_html['body_html'] = $array_html['body_html'] . 'colspan="35"';
                } else {
                    $array_html['body_html'] = $array_html['body_html'] . 'colspan="36"';
                }
                $array_html['body_html'] = $array_html['body_html'] . 'style="border-left: white; border-right: white;">
                            </th>
                        </tr>
                        <tr>
                            <th width="15px" rowspan="2">N<br />U<br />M</th>
                            <th width="100px" rowspan="2">NÚMERO DE <br />CONTROL</th>
                            <th width="280px">NOMBRE DEL ALUMNO</th>';
                foreach ($mes['dias'] as $keyD => $dia) {
                    $counting = $keyD + 1;
                    $array_html['body_html'] = $array_html['body_html'] . '<th width="10px" rowspan="2"><b>' . $counting . "</b></th>\n";
                }
                $array_html['body_html'] = $array_html['body_html'] . '<th colspan="2"><b>TOTAL</b></th>
                        </tr>
                        <tr>
                            <th>PRIMER APELLIDO/SEGUNDO APELLIDO/NOMBRE(S)</th>
                            <th> A </th>
                            <th> I </th>
                        </tr>
                    </thead>
                    <tbody>';
                $i = 16;
                foreach ($alumnos as $a) {
                    $tAsis = 0;
                    $tFalta = 0;
                    $consec++;
                    $array_html['body_html'] = $array_html['body_html'] . '<tr>
                            <td>' . $consec . '</td>
                            <td>' . $a->matricula . '</td>
                            <td>' . $a->alumno . '</td>';
                    foreach ($mes['dias'] as $dia) {
                        $array_html['body_html'] = $array_html['body_html'] . '<td>';
                        if ($a->asistencias != null) {
                            foreach ($a->asistencias as $asistencia) {
                                if ($asistencia['fecha'] == $dia && $asistencia['asistencia'] == true) {
                                    $array_html['body_html'] = $array_html['body_html'] . '<strong>*</strong>';
                                    $tAsis++;
                                } elseif ($asistencia['fecha'] == $dia && $asistencia['asistencia'] == false) {
                                    $array_html['body_html'] = $array_html['body_html'] . 'x';
                                    $tFalta++;
                                }
                            }
                        }
                        $array_html['body_html'] = $array_html['body_html'] . "</td>";
                    }
                    $array_html['body_html'] = $array_html['body_html'] . '<td>' . $tAsis . '</td>
                            <td>' . $tFalta . '</td>
                            </tr>';
                    if ($consec > $i && isset($alumnos[$consec]->alumno)) {
                        $array_html['body_html'] = $array_html['body_html'] . '</tbody>
                                </table>
                                <br><br><br>
                                <div class="page-break"></div>';
                        $i = $i + 15;

                        $array_html['body_html'] = $array_html['body_html'] . '<table class="tabla">
                                <thead>
                                    <tr>
                                        <td ';
                        if (explode('-', $mes['ultimoDia'])[2] == 28) {
                            $array_html['body_html'] = $array_html['body_html'] . 'colspan="33"';
                        } elseif (explode('-', $mes['ultimoDia'])[2] == 29) {
                            $array_html['body_html'] = $array_html['body_html'] . 'colspan="34"';
                        } elseif (explode('-', $mes['ultimoDia'])[2] == 30) {
                            $array_html['body_html'] = $array_html['body_html'] . 'colspan="35"';
                        } else {
                            $array_html['body_html'] = $array_html['body_html'] . 'colspan="36"';
                        }
                        $array_html['body_html'] = $array_html['body_html'] . '>
                                            <div id="curso">
                                                UNIDAD DE CAPACITACIÓN:
                                                <span class="tab">' . $curso->plantel . ' ' . $curso->unidad . '</span>
                                                CLAVE CCT: <span class="tab">' . $curso->cct . '</span>
                                                CICLO ESCOLAR: <span class="tab">' . $curso->ciclo . '</span>
                                                GRUPO: <span class="tab">' . $curso->grupo . '</span>
                                                MES: <span class="tab">' . $mes['mes'] . '</span>
                                                AÑO: &nbsp;&nbsp;' . $mes['year'] . '
                                                <br />
                                                AREA: <span class="tab1">' . $curso->area . '</span>
                                                ESPECIALIDAD: <span class="tab1">' . $curso->espe . '</span>
                                                CURSO: <span class="tab1">' . $curso->curso . '</span>
                                                CLAVE: &nbsp;&nbsp;' . $curso->clave . '
                                                <br />
                                                FECHA INICIO: <span class="tab1">' . $curso->fechaini . '</span>
                                                FECHA TERMINO: <span class="tab1">' . $curso->fechafin . '</span>
                                                HORARIO: ' . $curso->dia . ' DE ' . $curso->hini . ' A ' . $curso->hfin . '&nbsp;&nbsp;&nbsp;
                                                CURP: &nbsp;&nbsp;' . $curso->curp . ' &nbsp;&nbsp;&nbsp;
                                            </div>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th ';
                        if (explode('-', $mes['ultimoDia'])[2] == 28) {
                            $array_html['body_html'] = $array_html['body_html'] . 'colspan="33"';
                        } elseif (explode('-', $mes['ultimoDia'])[2] == 29) {
                            $array_html['body_html'] = $array_html['body_html'] . 'colspan="34"';
                        } elseif (explode('-', $mes['ultimoDia'])[2] == 30) {
                            $array_html['body_html'] = $array_html['body_html'] . 'colspan="35"';
                        } else {
                            $array_html['body_html'] = $array_html['body_html'] . 'colspan="36"';
                        }
                        $array_html['body_html'] = $array_html['body_html'] . 'style="border-left: white; border-right: white;">
                                        </th>
                                    </tr>
                                    <tr>
                                        <th width="15px" rowspan="2">N<br />U<br />M</th>
                                        <th width="100px" rowspan="2">NÚMERO DE <br />CONTROL</th>
                                        <th width="280px">NOMBRE DEL ALUMNO</th>';
                        foreach ($mes['dias'] as $keyD => $dia) {
                            $counting = $keyD + 1;
                            $array_html['body_html'] = $array_html['body_html'] . '<th width="10px" rowspan="2"><b>' . $counting . "</b></th>\n";
                        }
                        $array_html['body_html'] = $array_html['body_html'] . '<th colspan="2"><b>TOTAL</b></th>
                                    </tr>
                                    <tr>
                                        <th>PRIMER APELLIDO/SEGUNDO APELLIDO/NOMBRE(S)</th>
                                        <th> A </th>
                                        <th> I </th>
                                    </tr>
                                </thead>
                                <tbody>';
                    }
                }
                $array_html['body_html'] = $array_html['body_html'] . '</tbody>
                    <tfoot>
                    </tfoot>
                </table>
                <br><br><br>';
                if ($key < count($meses) - 1) {
                    $array_html['body_html'] = $array_html['body_html'] . '<p style="page-break-before: always;"></p>';
                }
            }
        } else {
            dd('El Curso no tiene registrado la fecha de inicio y de termino');
        }

        $array_html['body_xml'] = "SUBSECRETARÍA DE EDUCACIÓN E INVESTIGACIÓN TECNOLÓGICAS \n" .
            "DIRECCIÓN GENERAL DE CENTROS DE FORMACIÓN PARA EL TRABAJO \n" .
            "LISTA DE ASISTENCIA \n" .
            "(LAD-04) \n";

        foreach ($meses as $key => $mes) {
            $consec = 1;
            $array_html['body_xml'] = $array_html['body_xml'] . 'UNIDAD DE CAPACITACIÓN: ' . $curso->plantel . ' ' .   $curso->unidad . ' CLAVE CCT: ' . $curso->cct . ' CICLO ESCOLAR: ' . $curso->ciclo . ' GRUPO: ' . $curso->grupo . ' MES: ' . $mes['mes'] . ' AÑO: ' . $mes['year'] .
                "\n AREA: " . $curso->area . ' ESPECIALIDAD: ' . $curso->espe . ' CURSO: ' . $curso->curso . ' CLAVE: ' . $curso->clave .
                "\n FECHA INICIO: " . $curso->fechaini . ' FECHA TERMINO: ' . $curso->fechafin . ' HORARIO: ' . $curso->dia . ' DE ' . $curso->hini . ' A ' . $curso->hfin . ' CURP: ' . $curso->curp .
                "NUM NÚMERO DE CONTROL NOMBRE DEL ALUMNO PRIMER APELLIDO/SEGUNDO APELLIDO/NOMBRE(S) \n";
            foreach ($mes['dias'] as $keyD => $dia) {
                $array_html['body_xml'] = ' ' . $array_html['body_xml'] . ' ' . ($keyD + 1);
            }
            $array_html['body_xml'] = $array_html['body_xml'] . ' TOTAL ' . ' A I ';
            foreach ($alumnos as $a) {
                $tAsis = 0;
                $tFalta = 0;
                $array_html['body_xml'] = $array_html['body_xml'] . "\n" . $consec++ . ' ' . $a->matricula . ' ' . $a->alumno . ' ';
                foreach ($mes['dias'] as $dia) {
                    if ($a->asistencias != null) {
                        foreach ($a->asistencias as $asistencia) {
                            if ($asistencia['fecha'] == $dia && $asistencia['asistencia'] == true) {
                                $array_html['body_xml'] = $array_html['body_xml'] . '* ';
                                $tAsis++;
                            } else if ($asistencia['fecha'] == $dia && $asistencia['asistencia'] == false) {
                                $array_html['body_xml'] = $array_html['body_xml'] . 'x ';
                                $tFalta++;
                            }
                        }
                    }
                }
                $array_html['body_xml'] = $array_html['body_xml'] . $tAsis . ' ' . $tFalta . ' ';
            }
        }

        return $array_html;
    }

    ### BY JOSE LUIS / VALIDACIÓN DE INCAPACIDAD
    public function valid_incapacidad($dataFirmante)
    {
        $result = null;

        $status_campos = false;
        if ($dataFirmante->incapacidad != null) {
            $dataArray = json_decode($dataFirmante->incapacidad, true);

            ##Validamos los campos json
            if (
                isset($dataArray['fecha_inicio']) && isset($dataArray['fecha_termino'])
                && isset($dataArray['id_firmante']) && isset($dataArray['historial'])
            ) {

                if ($dataArray['fecha_inicio'] != '' && $dataArray['fecha_termino'] != '' && $dataArray['id_firmante'] != '') {
                    $fecha_ini = $dataArray['fecha_inicio'];
                    $fecha_fin = $dataArray['fecha_termino'];
                    $id_firmante = $dataArray['id_firmante'];
                    $historial = $dataArray['historial'];
                    $status_campos = true;
                }
            } else {
                // dd('[...]');
                // return redirect()->route('firma.inicio')->with('danger', 'LA ESTRUCTURA DEL JSON DE LA INCAPACIDAD NO ES VALIDA!');
            }

            ##Validar si esta vacio
            if ($status_campos == true) {
                ##Validar las fechas
                $fechaActual = date("Y-m-d");
                $fecha_nowObj = new DateTime($fechaActual);
                $fecha_iniObj = new DateTime($fecha_ini);
                $fecha_finObj = new DateTime($fecha_fin);

                if ($fecha_nowObj >= $fecha_iniObj && $fecha_nowObj <= $fecha_finObj) {
                    ###Realizamos la consulta del nuevo firmante
                    $dataIncapacidad = DB::Connection('pgsql')->Table('tbl_organismos AS org')
                        ->Select(
                            'org.id',
                            'fun.nombre AS funcionario',
                            'fun.curp',
                            'fun.cargo',
                            'fun.correo',
                            'org.nombre',
                            'fun.incapacidad'
                        )
                        ->join('tbl_funcionarios AS fun', 'fun.id', 'org.id')
                        ->where('fun.id', $id_firmante)
                        ->first();

                    if ($dataIncapacidad != null) {
                        $result = $dataIncapacidad;
                    } else {
                        return redirect()->route('firma.inicio')->with('danger', 'NO SE ENCONTRON DATOS DE LA PERSONA QUE TOMARÁ EL LUGAR DEL ACADEMICO!');
                    }
                } else {
                    // dd($dataArray);
                    ##Historial
                    $fecha_busqueda = 'Ini:' . $fecha_ini . '/Fin:' . $fecha_fin . '/IdFun:' . $id_firmante;
                    $clave_ar = array_search($fecha_busqueda, $historial);

                    if ($clave_ar === false) { ##No esta en el historial entonces guardamos
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

    function verMeses($a)
    {
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

    function getDays($dateInicio, $dateFinal)
    {
        $dias = [];
        for ($i = $dateInicio; $i <= $dateFinal; $i = date("Y-m-d", strtotime($i . "+ 1 days"))) {
            array_push($dias, $i);
        }
        return $dias;
    }

    public function generarToken()
    {
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
    public function getCadenaOriginal($xmlBase64, $token)
    {
        ##Produccion
        $response1 = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
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
