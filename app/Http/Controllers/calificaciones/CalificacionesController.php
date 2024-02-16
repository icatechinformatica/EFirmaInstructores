<?php

namespace App\Http\Controllers\calificaciones;


use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Http\Request;
use App\DocumentosFirmar;
use PHPQRCode\QRcode;
use App\instructores;
use App\Tokens_icti;
use App\tbl_cursos;
use Carbon\Carbon;
use DateInterval;
use DateTime;
use PDF;
class CalificacionesController extends Controller {

    function __construct() {
        session_start();
    }

    public function index(Request $request) {
        $message = NULL;
        $procesoPago = FALSE;
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
            if ($curso->id_instructor == Auth::user()->id_sivyc) {
                if (Auth::user()->unidad == 1) $fecha_penultimo = date("Y-m-d", strtotime($curso->termino . "- 3 days"));
                else $fecha_penultimo = date("Y-m-d", strtotime($curso->termino . "- 1 days"));
                $fecha_valida =  strtotime($fecha_hoy) - strtotime($fecha_penultimo);

                if ($curso->status_curso == "AUTORIZADO") {
                    $alumnos = DB::connection('pgsql')->table('tbl_inscripcion as i')->select('i.id', 'i.matricula', 'i.alumno', 'i.calificacion', 'f.folio')
                        ->leftJoin('tbl_folios as f', function ($join) {
                            $join->on('f.id', '=', 'i.id_folio');
                        })
                        ->where('i.id_curso', $curso->id)->where('i.status', 'INSCRITO')->orderby('i.alumno')->get();

                    if ($fecha_valida < 0) $message = "No prodece el registro de calificaciones, la fecha de termino del curso es el $curso->termino.";
                } else $message = "El Curso fué $curso->status.";

                if(count($alumnos)==0 AND !$message) $message = "El curso no tiene alumnos registrados. ";
                else $_SESSION['id_curso'] = $curso->id;

                $pagos=DB::Connection('pgsql')->Table('pagos')->Where('id_curso',$curso->id)->Value('status_recepcion');
                if(!is_null($pagos) && in_array($pagos, ['VALIDADO', 'En Espera'])) {
                    $procesoPago = TRUE;
                }
            } else {
                $denegado = 'denegado';
            }
        }// else $message = "Clave inválida.";

        return view('layouts.calificaciones.agregarCalificaciones', compact('clave', 'curso', 'alumnos', 'message', 'fecha_valida', 'denegado','procesoPago'));
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
            //inicio generacion de cadena unica
            $info = DB::Connection('pgsql')->Table('tbl_cursos')->Select('tbl_unidades.*','tbl_cursos.clave','tbl_cursos.nombre','tbl_cursos.curp','instructores.correo')
                ->Join('tbl_unidades','tbl_unidades.unidad','tbl_cursos.unidad')
                ->join('instructores','instructores.id','tbl_cursos.id_instructor')
                ->Where('tbl_cursos.clave',$clave)
                ->First();

            $body = $this->create_body($clave,$info); //creacion de body

            $nameFileOriginal = 'Lista de calificaciones '.$info->clave.'.pdf';
            $numOficio = 'RESD-05-'.$info->clave;
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

            if($dataFirmante->curp == null)
            {
                return redirect()->route('firma.inicio')->with('Danger', 'Error: La curp de un firmante no se encuentra');
            }

            if($dataFirmante == null){
                return redirect()->route('firma.inicio')->with('danger', 'NO SE ENCONTRARON DATOS DEL FIRMANTE AL REALIZAR LA CONSULTA');
            }
            ##Incapacidad
            $val_inca = $this->valid_incapacidad($dataFirmante);
            if ($val_inca != null) {
                $dataFirmante = $val_inca;
            }

            //Llenado de funcionarios firmantes
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
                    'asunto_docto' => 'Registro de evalucación por subobjetivos RESD-05',
                    'tipo_docto' => 'OFC',
                    'xmlns' => 'http://firmaelectronica.chiapas.gob.mx/GCD/DoctoGCD',
                ],
            ]);

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
                $dataInsert = DocumentosFirmar::Where('numero_o_clave',$info->clave)->Where('tipo_archivo','Lista de calificaciones')->First();
                if(is_null($dataInsert)) {
                    $dataInsert = new DocumentosFirmar();
                }
                $dataInsert->obj_documento = json_encode($ArrayXml);
                $dataInsert->obj_documento_interno = json_encode($ArrayXml);
                $dataInsert->status = 'EnFirma';
                // $dataInsert->link_pdf = $urlFile;
                $dataInsert->cadena_original = $response->json()['cadenaOriginal'];
                $dataInsert->tipo_archivo = 'Lista de calificaciones';
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

    private function create_body($clave, $firmantes) {
        $curso = DB::Connection('pgsql')->table('tbl_cursos')->select(
            'tbl_cursos.*',
            DB::raw('right(clave,4) as grupo'),
            DB::raw("to_char(inicio, 'DD/MM/YYYY') as fechaini"),
            DB::raw("to_char(termino, 'DD/MM/YYYY') as fechafin"),
            'u.plantel'
        )->where('tbl_cursos.clave',$clave);
        // if($_SESSION['unidades']) $curso = $curso->whereIn('u.ubicacion',$_SESSION['unidades']);
        $curso = $curso->leftjoin('tbl_unidades as u','u.unidad','tbl_cursos.unidad')->first();
        if($curso) {
            $consec_curso = $curso->id_curso;
            $fecha_termino = $curso->inicio;
            $alumnos = DB::Connection('pgsql')->table('tbl_inscripcion as i')->select(
                    'i.matricula',
                    'i.alumno',
                    'i.calificacion'
                )->where('i.id_curso',$curso->id)
                ->where('i.status','INSCRITO')
                ->groupby('i.matricula','i.alumno','i.calificacion')
                ->orderby('i.alumno')
                ->get();
            $consec = 1;
            $body = "SUBSECRETARÍA DE EDUCACIÓN E INVESTIGACIÓN TECNOLÓGICAS \n".
            "DIRECCIÓN GENERAL DE CENTROS DE FORMACIÓN PARA EL TRABAJO \n".
            "REGISTRO DE EVALUACIÓN POR SUBOBJETIVOS \n".
            "(RESD-05) ".
            "UNIDAD DE CAPACITACIÓN: ". $curso->plantel. ' '.   $curso->unidad. ' CLAVE CCT: '. $curso->cct. ' AREA: '. $curso->area. ' ESPECIALIDAD: '. $curso->espe.
            "\n CURSO: ". $curso->curso. ' CLAVE: '. $curso->clave. ' CICLO ESCOLAR: '. $curso->ciclo. ' FECHA INICIO: '. $curso->fechaini. ' FECHA TERMINO: '. $curso->fechafin.
            "\n GRUPO: ". $curso->grupo. ' HORARIO: '. $curso->dia. ' DE '. $curso->hini. ' A '. $curso->hfin. ' CURP: '. $curso->curp.
            "\n NUM NúMERO DE CONTROL NOMBRE DEL ALUMNO PRIMER APELLIDO/SEGUNDO APELLIDO/NOMBRE(S) CLAVE DE CADA SUBOBJETIVO RESULTADO RESULTADO FINAL";
                    foreach ($alumnos as $a) {
                        $body = $body. "\n". ($consec++). ' '. $a->matricula. ' '. $a->alumno. ' '. $a->calificacion;
                    }
            return $body;
        } else return "Curso no válido para esta Unidad";
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
                return redirect()->route('firma.inicio')->with('Danger', 'LA ESTRUCTURA DEL JSON DE LA INCAPACIDAD NO ES VALIDA!');
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
