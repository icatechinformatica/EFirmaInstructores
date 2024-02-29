<?php

namespace App\Http\Controllers\reporte_fot;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\instructores;
use App\tbl_cursos;
use BaconQrCode\Renderer\Color\Rgb;
use PDF;

use Illuminate\Support\Facades\Http;
use Spatie\ArrayToXml\ArrayToXml;
use App\DocumentosFirmar;
use App\Tokens_icti;
use PHPQRCode\QRcode;
use Carbon\Carbon;
use DateInterval;
use DateTime;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ReporteController extends Controller
{

    function __construct() {
        // $this->path_pdf = "/DTA/solicitud_folios/";
        $this->path_files = env("APP_URL").'/storage/uploadFiles';
    }


    public function index(Request $request){
        // $path_files = $this->path_files;
        $path_files = 'https://www.sivyc.icatech.gob.mx/storage/uploadFiles';
        $message = null;
        $curso = null;
        $unidad = null;
        $mensaje_retorno = "";
        $status_documento = "";
        $status_firma = "";
        $array_fotos = [];
        $clave = $request->clave;

        if ($clave != null) session(['claveRepo' => $clave]);
        else $clave = session('claveRepo');

        #Consulta general
        $curso = tbl_cursos::where('clave', '=', $clave)->first();
        // $tbl_firmas = DocumentosFirmar::select('status')->where('numero_o_clave', $clave)
        // ->where('nombre_archivo', '=', 'Reporte fotografico '.$clave.'.pdf')->first();
        $tbl_firmas = DocumentosFirmar::where('numero_o_clave', $clave)
        ->where('nombre_archivo', '=', 'Reporte fotografico ' . $clave . '.pdf')->pluck('status');
        if(isset($tbl_firmas[0])){
            if($tbl_firmas[0] == 'VALIDADO'){
                $status_firma = "VALIDADO";
            }else if($tbl_firmas[0] == 'EnFirma'){
                $status_firma = "PENDIENTE";
            }
        }

        ##Validar fechas de termino de curso
        //2024-04-22
        $firma_activa = '';
        if ($curso) {
            $fecha_actual = Carbon::now();
            $termino_curso = Carbon::createFromFormat('Y-m-d', $curso->termino);

            if ($fecha_actual->gte($termino_curso)) {$firma_activa = 'ACTIVO';}
            else {$firma_activa = 'INACTIVO';}
        }


        //Reporte fotografico 2B-23-ADMI-CAE-0200.pdf
        #Validamos si existen
        if (isset($curso->evidencia_fotografica['observacion_reporte'])){
            $mensaje_retorno = $curso->evidencia_fotografica['observacion_reporte'];
            // return redirect()->route('reporte.inicio')->with('alert', 'EL CURSO YA ESTA REPORTADO Y/O CANCELADO');
        }
        if (isset($curso->evidencia_fotografica['status_validacion'])){
            $status_documento = $curso->evidencia_fotografica['status_validacion'];
        }

        if (isset($curso->evidencia_fotografica['url_fotos'])){
            $array_fotos = $curso->evidencia_fotografica['url_fotos'];
        }

        if ($curso) {

            $unidad = DB::connection('pgsql')->table('tbl_unidades')->select('dunidad')
            ->where('unidad', $curso->unidad)->first();

            if ($curso->curp == Auth::user()->curp) {

                if ($curso->status_curso == "AUTORIZADO") {
                    $message = 'ok';
                } else $message = 'noDisponible';

            } else $message = 'denegado';
        }

        return view('layouts.reporte_fot.agregarEvidenciaFot', compact('curso', 'message', 'clave', 'unidad', 'mensaje_retorno',
                                                            'status_documento', 'array_fotos', 'path_files', 'status_firma','firma_activa'));
    }

    protected function img_upload($img, $id, $nom, $anio)
    {
        // # nuevo nombre del archivo
        $tamanio = $img->getSize();
        $extensionFile = $img->getClientOriginalExtension();
        $imgFile = trim($nom . "_" . $id . '.' .$extensionFile);
        $directorio = '/' . $anio . '/evidenciafotos/' . $id . '/'.$imgFile;
        $img->storeAs('/uploadFiles/'.$anio.'/evidenciafotos/'.$id, $imgFile);
        $imgUrl = Storage::url('/uploadFiles' . $directorio);
        return [$imgUrl, $directorio];
    }

    ##Obtenemos imagenes, las guardamos y las enviamos al endpoint sivyc
    public function catch_fotos(Request $request) {
        #Generamos Token
        $token = Str::random(32);

        // Guardar el token en la base de datos
        DB::connection('pgsql')->table('tokens_sendimg')->insert([
            'token' => $token,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $imagenes = $request->file('imagenes');
        $idcurso = $request->id_curso;

        if(count($imagenes) >= 2 && count($imagenes) <= 3){}
        else{throw new \Exception('Se deben agregar 2 a 3 imagenes');}
        if(empty($idcurso) && !$request->hasFile('imagenes')){throw new \Exception('Faltan Valores');}

        ##Obtenemos las rutas de las imagenes de la bd y el id del curso
        $db_consulta = tbl_cursos::select('evidencia_fotografica', DB::raw('EXTRACT(YEAR FROM inicio) as anio'))->where('id', $idcurso)->first();

        #Ejecutamos el guardado de imagenes en Instructores
        $this->proces_img_local($imagenes, $idcurso, $db_consulta);

        // Construir el cuerpo de la solicitud multipart
        $body = [
            'idcurso' => (string)$idcurso,
            'imagenes' => [],
        ];

        if (is_array($imagenes)) {
            for ($i=0; $i < count($imagenes); $i++) {
                // Agregar cada imagen al array 'imagenes'
                $body['imagenes'][] = [
                    'name' => 'imagenes[]',
                    'contents' => file_get_contents($imagenes[$i]),
                    'filename' => $imagenes[$i]->getClientOriginalName()
                ];
            }
        }
        $client = new Client();
        //http://localhost:8080/api/v1/catchimg
        //https://sivyc.icatech.gob.mx/api/v1/catchimg
        $response = $client->request('POST', 'https://sivyc.icatech.gob.mx/api/v1/catchimg', [
            'headers' => [
                'Accept' => 'multipart/form-data',
                'Authorization' => 'Bearer ' . $token,
            ],
            'multipart' => $body['imagenes'],
            'query' => ['idcurso' => $body['idcurso']]
        ]);

        $respuesta = $response->getBody()->getContents();
        $decodedResponse = json_decode($respuesta, true); //decodificamos la respuesta json

        return response()->json(['respuesta' => $decodedResponse]);
    }


    #Procesar imagenes localmente para los instructores
    public function proces_img_local($imagenesArray, $idcurso, $db_consulta) {
        $imagenes = $imagenesArray;
        $id_curso = $idcurso;
        $anio = $db_consulta->anio;

        #Eliminar fotos si esque lo hay
        if(isset($db_consulta->evidencia_fotografica['url_fotos'])){
            $array_fotosbd = $db_consulta->evidencia_fotografica['url_fotos'];
            if(count($array_fotosbd) > 0){
                for ($i=0; $i < count($array_fotosbd); $i++) {
                    $filePath = 'uploadFiles'.$array_fotosbd[$i];
                    if (Storage::exists($filePath)) {
                        Storage::delete($filePath);
                    } else { return response()->json(['mensaje' => "¡Error!, Documento no encontrado instructores->".$filePath]); }
                }
            }
        }
        ##Agregamos las fotos
        $arrayUrlFotos = [];
        try {
            for ($i=0; $i < count($imagenes); $i++) {
                $url_foto = $this->img_upload($imagenes[$i], $id_curso, 'foto'.($i+1), $anio);
                array_push($arrayUrlFotos, $url_foto[1]);
            }
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Error al intentar guardar la imagen instructores: ' . $th->getMessage()]);
        }

    }


    #Envio de informacion de envio de reporte
    public function reporteenviar (Request $request) {
        #Generamos xml desde Instructores.
        // $fotosbd = $md5bd = [];
        $clave =  $request->claveg;

        ##BUSQUEDA DEL CURSO
        $cursoBD = tbl_cursos::select('id','evidencia_fotografica', 'turnado', 'status', 'status_curso')->where('clave', '=', $clave)->first();
        if($cursoBD->status_curso == "AUTORIZADO"){
        }else{
            return redirect()->route('reporte.inicio')->with('alert', 'EL CURSO NO ESTÁ CANCELADO');
        }

        ### GENERACION DE XML CON FOTOS
        if(isset($cursoBD->evidencia_fotografica['url_fotos']) && isset($cursoBD->evidencia_fotografica['md5_fotos'])){
            $fotosbd = $cursoBD->evidencia_fotografica['url_fotos'];
            $md5bd = $cursoBD->evidencia_fotografica['md5_fotos'];
            if (count($fotosbd) > 0 && count($md5bd) > 0) {
                #Guardamos los datos con fotos
                try {
                    $resul = $this->generar_xml($cursoBD->id, $fotosbd, $md5bd);
                    if ($resul == "EXITOSO") {
                        #Ejecutamos la generacion de XML
                        $curso = tbl_cursos::find($cursoBD->id);
                        $json = $curso->evidencia_fotografica;
                        $json['status_validacion'] = 'ENVIADO';
                        $json['observacion_reporte'] = '';
                        $json['fecha_envio'] = date('Y-m-d');
                        $curso->evidencia_fotografica = $json;
                        $curso->save();
                        return redirect()->route('reporte.inicio')->with('success', "DATOS ENVIADOS CON ÉXITO");
                    }else{
                        return redirect()->route('reporte.inicio')->with('success', $resul);
                    }

                } catch (\Throwable $th) {
                    return redirect()->route('reporte.inicio')->with('alert', 'error'.$th->getMessage());
                }

            }else{
                return redirect()->route('reporte.inicio')->with('alert', '¡ERROR AL ENVIAR! NO EXISTEN EVIDENCIAS FOTOGRAFICAS.');
            }

        }else{
            return redirect()->route('reporte.inicio')->with('alert', '¡ERROR! NO EXISTEN EVIDENCIAS FOTOGRAFICAS');
        }


    }

    #Generar reporte pdf
    public function repofotoPdf(Request $request){
        $clave = $request->clave_curso;
        // $path_files = $this->path_files;
        $path_files = 'https://www.sivyc.icatech.gob.mx/storage/uploadFiles';
        $array_fotos = [];
        $fecha_gen = '';

        ###Fechas en caso de que ya este en la bd
        $meses = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];

        ##Obtenemos el registro firmado con la clave
        // $consulta_firma = DocumentosFirmar::where('numero_o_clave', $clave)
        // ->where('tipo_archivo', 'Reporte fotografico')->first();

        ##Consulta del curso
        $cursopdf = tbl_cursos::select('nombre', 'curso', 'tcapacitacion', 'inicio', 'termino', 'evidencia_fotografica', 'curp',
        'clave', 'hini', 'hfin', 'tbl_cursos.unidad', 'uni.dunidad', 'uni.ubicacion', 'uni.direccion', 'uni.municipio')
        ->join('tbl_unidades as uni', 'uni.unidad', 'tbl_cursos.unidad')
        ->where('clave', '=', $clave)->first();

        ##Validacion de fechas
        // if ($consulta_firma != null) {
        //     $documento_firmado = json_decode($consulta_firma->obj_documento, true);
        // }
        // if (isset($documento_firmado['firmantes']['firmante'][0][0]['_attributes']['fecha_firmado_firmante'])) {
        //     $fech_firma_firmante = $documento_firmado['firmantes']['firmante'][0][0]['_attributes']['fecha_firmado_firmante'];
        //     $partes = explode('T', $fech_firma_firmante);
        //     $fecha_part = $partes[0];
        //     [$aniof, $mesf, $diaf] = explode('-', $fecha_part);
        //     $fecha_gen = $diaf. ' de '.$meses[$mesf-1].' de '.$aniof;

        // }else{
        //     $dia = date('d'); $mes = date('m'); $anio = date('Y');
        //     $dia = ($dia) < 10 ? '0'.$dia : $dia;
        //     $fecha_gen = $dia.' de '.$meses[$mes-1].' de '.$anio;

        // }

         if (isset($cursopdf->evidencia_fotografica["fecha_envio"])) {
             $fechapdf = $cursopdf->evidencia_fotografica["fecha_envio"];
             $fechaCarbon = Carbon::createFromFormat('Y-m-d', $fechapdf);
             $dia = ($fechaCarbon->day) < 10 ? '0'.$fechaCarbon->day : $fechaCarbon->day;
             $fecha_gen = $dia.' DE '.$meses[$fechaCarbon->month-1].' DE '.$fechaCarbon->year;
         }else{
            $fechaActual = Carbon::now();
            $dia = $fechaActual->day;
            $mes = $fechaActual->month;
            $anio = $fechaActual->year;

            $dia = ($dia) <= 9 ? '0'.$dia : $dia;
            $fecha_gen = $dia.' DE '.$meses[$mes-1].' DE '.$anio;
         }

        #Distintivo
        $leyenda = DB::connection('pgsql')->table('tbl_instituto')->value('distintivo');


        ##procesaro imagenes
        if (isset($cursopdf->evidencia_fotografica['url_fotos'])){
            $array_fotos = $cursopdf->evidencia_fotografica['url_fotos'];
        }

        ##Procesar imagenes
        $base64Images = [];
        $environment = config('app.env');

        if ($environment === 'local') {
            ##Local
            foreach ($array_fotos as $url) {
                $imageContent = file_get_contents("https://www.sivyc.icatech.gob.mx/storage/uploadFiles{$url}");
                $base64 = base64_encode($imageContent);
                $base64Images[] = $base64;
            }
        } else {
            ##Produccion
            foreach ($array_fotos as $url) {
                $imageContent = file_get_contents(storage_path("app/public/uploadFiles".$url));
                $base64 = base64_encode($imageContent);
                $base64Images[] = $base64;
            }
        }

        $pdf = PDF::loadView('layouts.reporte_fot.pdfEvidenciaFot', compact('cursopdf', 'leyenda', 'fecha_gen', 'base64Images', 'path_files'));
        $pdf->setPaper('Letter', 'portrait');
        $file = "REPORTE_FOTOGRAFICO$clave.PDF";
        return $pdf->stream($file);
    }


    #Generar xml
    public function generar_xml($id_curso, $fotosbd, $md5bd) {
        $info = DB::connection('pgsql')->Table('tbl_cursos')->Select('tbl_unidades.*','tbl_cursos.clave','tbl_cursos.nombre','tbl_cursos.curp','instructores.correo', 'tbl_cursos.id_unidad')
                ->Join('tbl_unidades','tbl_unidades.unidad','tbl_cursos.unidad')
                ->join('instructores','instructores.id','tbl_cursos.id_instructor')
                ->Where('tbl_cursos.id', $id_curso)
                ->First();

        $body = $this->create_body($id_curso, $info); //creacion de body

        $nameFileOriginal = 'Reporte fotografico '.$info->clave.'.pdf';
        $numOficio = 'REPORTE-FOTOGRAFICO-'.$info->clave;
        $numFirmantes = '2';

        $arrayFirmantes = [];
        $arrayFotosbd = $fotosbd;
        $arrayMd5bd = $md5bd;
        $numAnexos = (string)count($arrayFotosbd);

        ##OBTENEMOS DATOS DEL ACADEMICO
        $dataFirmante = DB::connection('pgsql')->Table('tbl_organismos AS org')
        ->Select('fun.id as id_fun','org.id', 'fun.nombre AS funcionario','fun.curp', 'us.name',
        'fun.cargo','fun.correo', 'us.puesto', 'fun.incapacidad')
            ->join('tbl_funcionarios AS fun', 'fun.id_org','org.id')
            ->join('tbl_cursos as tc', 'tc.id_unidad','org.id_unidad')
            ->join('users as us', 'us.email','fun.correo')
            ->where('org.nombre', 'LIKE', '%ACADEMICO%')
            ->where('tc.id_unidad', '=', $info->id_unidad)
            ->where('fun.activo', '=', 'true')
            ->first();

        if($dataFirmante == null){
            return "NO SE ENCONTRON DATOS DEL ACADEMICO!";
        }

        $val_incap = $this->valid_incapacidad($dataFirmante);
        if ($val_incap != null) {
            $dataFirmante = $val_incap;
        }

        // dd($dataFirmante);

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

        ## Anexos
        $anexos = [];
        for ($i = 0; $i < count($arrayFotosbd); $i++) {
            $nombreAnexo = basename($arrayFotosbd[$i]);
            $nombreArchivo = $nombreAnexo;

            $md5Anexo = $arrayMd5bd[$i];

            $tmp_anexo = ['_attributes' =>
                [
                    'nombre_anexo' => $nombreArchivo,
                    'md5_anexo' => $md5Anexo,
                ]
            ];
            $anexos[] = $tmp_anexo;
        }

        ### XML CON FOTOS
        $ArrayXml = [
            'emisor' => [
                '_attributes' => [
                    'nombre_emisor' => $dataFirmante->name,
                    'cargo_emisor' => $dataFirmante->puesto,
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
            'anexos' => [
                '_attributes' => [
                    'num_anexos' => $numAnexos
                ],
                'anexo' => $anexos
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
                'asunto_docto' => 'Reporte fotografico',
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

            $dataInsert = DocumentosFirmar::Where('numero_o_clave',$info->clave)->Where('tipo_archivo','Reporte fotografico')->First();
            if(is_null($dataInsert)) {
                $dataInsert = new DocumentosFirmar();
            }
            $dataInsert->obj_documento_interno = json_encode($ArrayXml);
            $dataInsert->obj_documento = json_encode($ArrayXml);
            $dataInsert->status = 'EnFirma';
            $dataInsert->cadena_original = $response->json()['cadenaOriginal'];
            $dataInsert->tipo_archivo = 'Reporte fotografico';
            $dataInsert->numero_o_clave = $info->clave;
            $dataInsert->nombre_archivo = $nameFileOriginal;
            $dataInsert->documento = $result;
            $dataInsert->documento_interno = $result;
            $dataInsert->save();
            return "EXITOSO";

        } else {
            return "ERROR AL ENVIAR Y VALIDAR. INTENTE NUEVAMENTE EN UNOS MINUTOS";
        }
    }

    ## VALIDACIÓN DE LA INCAPACIDAD DEL FIRMANTE
    private function valid_incapacidad($dataFirmante){
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
                return "LA ESTRUCTURA DEL JSON DE LA INCAPACIDAD NO ES VALIDA!";
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
                    $dataIncapacidad = DB::connection('pgsql')->Table('tbl_organismos AS org')
                    ->Select('org.id', 'fun.nombre AS funcionario','fun.curp', 'us.name',
                    'fun.cargo','fun.correo', 'us.puesto', 'fun.incapacidad')
                    ->join('tbl_funcionarios AS fun', 'fun.id','org.id')
                    ->join('users as us', 'us.email','fun.correo')
                    ->where('fun.id', $id_firmante)
                    ->first();

                    if ($dataIncapacidad != null) {$result = $dataIncapacidad;}
                    else{return "NO SE ENCONTRON DATOS DE LA PERSONA QUE TOMARÁ EL LUGAR DEL ACADEMICO!";}
                }else{
                    ##Historial
                    $fecha_busqueda = 'Ini:'. $fecha_ini .'/Fin:'. $fecha_fin .'/IdFun:'. $id_firmante;
                    $clave_ar = array_search($fecha_busqueda, $historial);

                    if($clave_ar === false){ ##No esta en el historial entonces guardamos
                        $historial[] = $fecha_busqueda;
                        ##guardar en la bd el nuevo array en el campo historial del json
                        try {
                            $jsonHistorial = json_encode($historial);
                            DB::connection('pgsql')->update('UPDATE tbl_funcionarios SET incapacidad = jsonb_set(incapacidad, \'{historial}\', ?) WHERE id = ?', [$jsonHistorial, $dataFirmante->id_fun]);
                        } catch (\Throwable $th) {
                            return "Error: " . $th->getMessage();
                        }

                    }
                }
            }

        }
        return $result;
    }

    #Crear Cuerpo
    private function create_body($id, $firmantes) {
        #Distintivo
        $leyenda = DB::connection('pgsql')->table('tbl_instituto')->value('distintivo');

        $curso = DB::connection('pgsql')->Table('tbl_cursos')->select(
            'tbl_cursos.*',
            'inicio',
            'termino',
            DB::raw("to_char(inicio, 'DD/MM/YYYY') as fechaini"),
            DB::raw("to_char(termino, 'DD/MM/YYYY') as fechafin"),
            'u.dunidad',
            'u.municipio',
            'u.unidad',
            'u.ubicacion',
            'u.direccion'
            )->where('tbl_cursos.id',$id);
        $curso = $curso->leftjoin('tbl_unidades as u','u.unidad','tbl_cursos.unidad')->first();

        ##Procesar dirección de unidad
        $direcSinAsteriscos = ' ';
        $direccion = $curso->direccion;
        if (!empty($direccion)) {
            $direcSinAsteriscos = str_replace('*', ' ', $direccion);
        }

        ##Procesar fecha del envio de documento
        $meses = ['ENERO', 'FEBRERO', 'MARZO', 'ABRIL', 'MAYO', 'JUNIO', 'JULIO', 'AGOSTO', 'SEPTIEMBRE', 'OCTUBRE', 'NOVIEMBRE', 'DICIEMBRE'];

        $fechaActual = Carbon::now();
        $dia = $fechaActual->day;
        $mes = $fechaActual->month;
        $anio = $fechaActual->year;

        $dia = ($dia) <= 9 ? '0'.$dia : $dia;
        $fecha_gen = $dia.' DE '.$meses[$mes-1].' DE '.$anio;


        $valid_accionmovil = ($curso->unidad != $curso->ubicacion) ? ', ACCIÓN MOVIL '.$curso->unidad : ' ';

        $body = $leyenda."\n".
        "\n REPORTE FOTOGRÁFICO DEL INSTRUCTOR\n".
        "\n UNIDAD DE CAPACITACIÓN ".$curso->ubicacion. $valid_accionmovil.
        "\n ".mb_strtoupper($curso->municipio, 'UTF-8').", CHIAPAS. A ".$fecha_gen.".\n";

        $body .= "\n CURSO: ". $curso->curso.
        "\n TIPO: ". $curso->tcapacitacion.
        "\n FECHA DE INICIO: ". $curso->fechaini.
        "\n FECHA DE TÉRMINO: ". $curso->fechafin.
        "\n CLAVE: ". $curso->clave.
        "\n HORARIO: ". $curso->hini. ' A '. $curso->hfin.
        "\n NOMBRE DEL TITULAR DE LA U.C: ". $curso->dunidad.
        "\n NOMBRE DEL INSTRUCTOR: ". $curso->nombre."\n".
        "\n ".$direcSinAsteriscos;

        return $body;
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

    ## obtener posiciones y ordernar
    public function ordenar_fotos(Request $request){
        $array_orden = $request->array_orden;
        // $array_orden = [0,1,2];
        // $array_fotos = $request->array_fotos;
        $id_curso = $request->id_curso;

        ##Hacemos una consulta de fotos y md5
        $consultaBD = tbl_cursos::select('evidencia_fotografica')->where('id', '=', $id_curso)->first();
        if ($consultaBD == null) {
            return response()->json(['status' => 400, 'message' => 'Hubo un error al realizar la consulta a la base de datos']);
        }

        $array_fotos = $consultaBD->evidencia_fotografica['url_fotos'];
        $array_md5 = $consultaBD->evidencia_fotografica['md5_fotos'];

        $orden_md5 = $orden_fotos = [];

        for ($i=0; $i < count($array_fotos); $i++) {
            $foto = $array_fotos[intval($array_orden[$i])-1];
            $md5 = $array_md5[intval($array_orden[$i])-1];
            array_push($orden_fotos, $foto);
            array_push($orden_md5, $md5);
        }

        ##Validamos si el tamaño del array nuevo es igual al anterior
        if (count($array_fotos) != count($orden_fotos)) {
            return response()->json(['status' => 400, 'message' => 'Valida que el orden sea el correcto']);
        }

        ##faltan guardar los array de fotos y md5
        try {
            $curso = tbl_cursos::find($id_curso);
            $json = $curso->evidencia_fotografica;
            $json['url_fotos'] = $orden_fotos;
            $json['md5_fotos'] = $orden_md5;
            $curso->evidencia_fotografica = $json;
            $curso->save();
            return response()->json(['status' => 200, 'message' => 'Imagenes ordenadas correctamente']);
        } catch (\Throwable $th) {
            return response()->json(['status' => 500, 'message' => 'Hubo un error al intentar insertar en la base de datos']);
        }
    }

}
