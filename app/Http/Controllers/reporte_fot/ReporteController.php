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
        $path_files = $this->path_files;
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
                $status_firma =  'El reporte de evidencias de este curso ya se encuentra firmado electrónicamente.';
            }else if($tbl_firmas[0] == 'EnFirma'){
                $status_firma =  'Pendiente por firmar';
            }
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

                    ##Verificamos si el archivo no ha sido firmado electronicamente
                    $message = 'ok';

                } else $message = 'noDisponible';

            } else $message = 'denegado';
        }

        return view('layouts.reporte_fot.agregarEvidenciaFot', compact('curso', 'message', 'clave', 'unidad', 'mensaje_retorno',
                                                            'status_documento', 'array_fotos', 'path_files', 'status_firma'));
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

        if(count($imagenes) != 3){throw new \Exception('Se deben agregar 3 imagenes');}
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
                $url_foto = $this->img_upload($imagenes[$i], $id_curso, 'foto'.$i, $anio);
                array_push($arrayUrlFotos, $url_foto[1]);
            }
        } catch (\Throwable $th) {
            return response()->json(['message' => 'Error al intentar guardar la imagen instructores: ' . $th->getMessage()]);
        }

    }


    #Envio de informacion de envio de reporte
    public function reporteenviar (Request $request) {
        #Generamos xml desde Instructores.
        $clave =  $request->claveg;
        $cursoBD = tbl_cursos::select('id','evidencia_fotografica', 'turnado', 'status', 'status_curso')->where('clave', '=', $clave)->first();
        if($cursoBD->status_curso == "AUTORIZADO"){
        }else{
            return redirect()->route('reporte.inicio')->with('alert', 'EL CURSO YA ESTA REPORTADO Y/O CANCELADO');
        }

        if(isset($cursoBD->evidencia_fotografica['url_fotos']) && isset($cursoBD->evidencia_fotografica['md5_fotos'])){
            $fotosbd = $cursoBD->evidencia_fotografica['url_fotos'];
            $md5bd = $cursoBD->evidencia_fotografica['md5_fotos'];
            if (count($fotosbd) > 0 && count($md5bd) > 0) {
                #Guardamos los datos
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
                        return redirect()->route('reporte.inicio')->with('success', "DATOS ENVIADOS CON EXITO");
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
            return redirect()->route('reporte.inicio')->with('alert', '¡ERROR AL ENVIAR! NO EXISTEN EVIDENCIAS FOTOGRAFICAS.');
        }


    }

    #Generar reporte pdf
    public function repofotoPdf(Request $request){

        $path_files = $this->path_files;
        $array_fotos = [];
        #Distintivo
        $leyenda = DB::connection('pgsql')->table('tbl_instituto')->value('distintivo');


        $clave = $request->clave_curso;
        $cursopdf = tbl_cursos::select('nombre', 'curso', 'tcapacitacion', 'inicio', 'termino', 'evidencia_fotografica',
        'clave', 'hini', 'hfin', 'tbl_cursos.unidad', 'uni.dunidad', 'uni.ubicacion', 'uni.direccion', 'uni.municipio')
        ->join('tbl_unidades as uni', 'uni.unidad', 'tbl_cursos.unidad')
        ->where('clave', '=', $clave)->first();

        if (isset($cursopdf->evidencia_fotografica['url_fotos'])){
            $array_fotos = $cursopdf->evidencia_fotografica['url_fotos'];
        }

        ###Fechas en caso de que ya este en la bd
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        if (isset($cursopdf->evidencia_fotografica["fecha_envio"])) {
            $fechapdf = $cursopdf->evidencia_fotografica["fecha_envio"];
            $fechaCarbon = Carbon::createFromFormat('Y-m-d', $fechapdf);
            $dia = ($fechaCarbon->day) < 10 ? '0'.$fechaCarbon->day : $fechaCarbon->day;
            $fecha_gen = $dia.' de '.$meses[$fechaCarbon->month-1].' de '.$fechaCarbon->year;
        }else{
            #Unidad de capacitacion
            $dia = date('d'); $mes = date('m'); $anio = date('Y');
            $dia = ($dia) < 10 ? '0'.$dia : $dia;
            $fecha_gen = $dia.' de '.$meses[$mes-1].' de '.$anio;
        }

        $base64Images = [];
        foreach ($array_fotos as $url) {
            // $imageContent = file_get_contents('/storage/uploadFiles'.$url);
            $imageContent = file_get_contents(storage_path("app/public/uploadFiles".$url));
            $base64 = base64_encode($imageContent);
            $base64Images[] = $base64;
        }

        $pdf = PDF::loadView('layouts.reporte_fot.pdfEvidenciaFot', compact('cursopdf', 'leyenda', 'fecha_gen', 'base64Images', 'path_files'));
        $pdf->setPaper('Letter', 'portrait');
        $file = "REPORTE_FOTOGRAFICO$clave.PDF";
        return $pdf->stream($file);
    }


    #Generar xml
    public function generar_xml($id_curso, $fotosbd, $md5bd) {
        $info = DB::connection('pgsql')->Table('tbl_cursos')->Select('tbl_unidades.*','tbl_cursos.clave','tbl_cursos.nombre','tbl_cursos.curp','instructores.correo')
                ->Join('tbl_unidades','tbl_unidades.unidad','tbl_cursos.unidad')
                ->join('instructores','instructores.id','tbl_cursos.id_instructor')
                ->Where('tbl_cursos.id', $id_curso)
                ->First();

        $body = $this->create_body($id_curso, $info); //creacion de body
        $body = str_replace(["\r", "\n", "\f"], ' ', $body);

        $nameFileOriginal = 'Reporte fotografico '.$info->clave.'.pdf';
        $numOficio = 'REPORTE-'.$info->clave;
        $numFirmantes = '2';

        $arrayFirmantes = [];
        $arrayAnexos = [];
        $arrayFotosbd = $fotosbd;
        $arrayMd5bd = $md5bd;
        $numAnexos = (string)count($arrayFotosbd);

        // $dataFirmante = DB::connection('pgsql')->Table('tbl_organismos AS org')->Select('org.id','fun.nombre AS funcionario','fun.curp','fun.cargo','fun.correo','org.nombre')
        //                     ->Join('tbl_funcionarios AS fun','fun.id','org.id')
        //                     ->Where('org.id', Auth::user()->id_organismo)
        //                     ->Where('org.nombre', 'LIKE', 'DEPARTAMENTO ACADEMICO%')
        //                     ->OrWhere('org.id_parent', Auth::user()->id_organismo)
        //                     // ->Where('org.nombre', 'NOT LIKE', 'CENTRO%')
        //                     ->Where('org.nombre', 'LIKE', 'DEPARTAMENTO ACADEMICO%')
        //                     ->First();


        $dataFirmante = DB::connection('pgsql')->Table('tbl_organismos AS org')->Select('org.id', 'fun.nombre AS funcionario','fun.curp',
        'fun.cargo','fun.correo', 'us.name', 'us.puesto')
            ->join('tbl_funcionarios AS fun', 'fun.id','org.id')
            ->join('users as us', 'us.email','fun.correo')
            ->where('org.nombre', 'ILIKE', 'DELEGACIÓN ADMINISTRATIVA UC '.$info->ubicacion.'%')
            ->first();
        if($dataFirmante == null){
            return "NO SE ENCONTRON DATOS DEL FIRMANTE";
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

        ## Anexos
        $anexos = [];
        for ($i = 0; $i < count($arrayFotosbd); $i++) {
            $nombreAnexo = basename($arrayFotosbd[$i]);
            ##Partimos la cadena a partir del punto
            $partes = explode(".", $nombreAnexo);
            $nombreArchivo = $partes[0].'.pdf';

            $md5Anexo = $arrayMd5bd[$i];

            $tmp_anexo = ['_attributes' =>
                [
                    'nombre_anexo' => $nombreArchivo,
                    'md5_anexo' => $md5Anexo,
                ]
            ];
            $anexos[] = $tmp_anexo;
        }

        // dd($arrayFirmantes);
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
            // $urlFile = $this->uploadFileServer($request->file('doc'), $nameFileOriginal);
            // $urlFile = $this->uploadFileServer($request->file('doc'), $nameFile);
            // $datas = explode('*',$urlFile);


            $dataInsert = DocumentosFirmar::Where('numero_o_clave',$info->clave)->Where('tipo_archivo','Reporte fotografico')->First();
            if(is_null($dataInsert)) {
                $dataInsert = new DocumentosFirmar();
            }
            $dataInsert->obj_documento_interno = json_encode($ArrayXml);
            $dataInsert->obj_documento = json_encode($ArrayXml);
            $dataInsert->status = 'EnFirma';
            // $dataInsert->link_pdf = $urlFile;
            $dataInsert->cadena_original = $response->json()['cadenaOriginal'];
            $dataInsert->tipo_archivo = 'Reporte fotografico';
            $dataInsert->numero_o_clave = $info->clave;
            $dataInsert->nombre_archivo = $nameFileOriginal;
            $dataInsert->documento = $result;
            $dataInsert->documento_interno = $result;
            // $dataInsert->md5_file = $md5;
            $dataInsert->save();

            // return redirect()->route('reporte.inicio')->with('success', 'Reporte fotografico Validado Exitosamente!');
            return "EXITOSO";
        } else {
            return "ERROR AL ENVIAR Y VALIDAR. INTENTE NUEVAMENTE EN UNOS MINUTOS";
            // return redirect()->route('reporte.inicio')->with('alert', 'Hubo un Error al Validar. Intente Nuevamente en unos Minutos.');
        }
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
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $dia = date('d'); $mes = date('m'); $anio = date('Y');
        $dia = ($dia) < 10 ? '0'.$dia : $dia;
        $fecha_gen = $dia.' de '.$meses[$mes-1].' de '.$anio;


        $valid_accionmovil = ($curso->unidad != $curso->ubicacion) ? ', Acción Movil '.$curso->unidad : ' ';

        $body = $leyenda."\n\n".'REPORTE FOTOGRÁFICO DEL INSTRUCTOR'."\n".
        'Unidad de Capacitación '.$curso->municipio. $valid_accionmovil."\n".
        $curso->municipio.', Chiapas. A '.$fecha_gen.'.'."\n\n";

        $body .= 'CURSO: '. $curso->curso."\n".
        ' TIPO: '. $curso->tcapacitacion. ' FECHA DE INICIO: '. $curso->fechaini. ' FECHA DE TÉRMINO: '. $curso->fechafin."\n".
        ' CLAVE: '. $curso->clave. ' HORARIO: '. $curso->hini. ' A '. $curso->hfin."\n".
        ' NOMBRE DEL TITULAR DE LA U.C: '. $curso->dunidad.' NOMBRE DEL INSTRUCTOR: '. $curso->nombre."\n\n".
        $direcSinAsteriscos;

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

}
