<?php

namespace App\Http\Controllers\firmaElectronica;


// use QrCode;
use setasign\fpdi\Fpdi;
use App\DocumentosFirmar;
use Illuminate\Http\Request;
use Spatie\ArrayToXml\ArrayToXml;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\tbl_cursos;
use App\Tokens_icti;
use App\Models\contratos;
use App\Models\contrato_directorio;
use App\Models\directorio;
use App\Models\especialidad_instructor;
// use BaconQrCode\Encoder\QrCode;
use Illuminate\Support\Facades\Http;
use Vyuldashev\XmlToArray\XmlToArray;
use Illuminate\Support\Facades\Storage;
use \setasign\Fpdi\PdfParser\StreamReader;
use PDF;
use PHPQRCode\QRcode;
use Carbon\Carbon;

// use SimpleSoftwareIO\QrCode\Facades\QrCode;

class FirmaController extends Controller {

    // php artisan serve --port=8001
    public function index(Request $request) {
        $curp = Auth::user()->curp;
        // $curpUser = DB::connection('pgsql')->Table('instructores')->Select('curp')
        //     ->Where('id', Auth::user()->id_sivyc)
        //     ->First();

        $docsFirmar1 = DocumentosFirmar::where('status','!=','CANCELADO')
            ->whereRaw("EXISTS(SELECT TRUE FROM jsonb_array_elements(obj_documento->'firmantes'->'firmante'->0) x
                WHERE x->'_attributes'->>'curp_firmante' IN ('".$curp."')
                AND x->'_attributes'->>'firma_firmante' is null)");
            // ->orderBy('id', 'desc')->get();

        $docsFirmados1 = DocumentosFirmar::where('status', 'EnFirma')
            ->where(function ($query) use ($curp) {
                $query->whereRaw("EXISTS(SELECT TRUE FROM jsonb_array_elements(obj_documento->'firmantes'->'firmante'->0) x
                    WHERE x->'_attributes'->>'curp_firmante' IN ('".$curp."')
                    AND x->'_attributes'->>'firma_firmante' <> '')")
                ->orWhere(function($query1) use ($curp) {
                    $query1->where('obj_documento_interno->emisor->_attributes->email', $curp)
                            ->where('status', 'EnFirma');
                });
            });
            // ->orderBy('id', 'desc')->get();

        $docsValidados1 = DocumentosFirmar::where('status', 'VALIDADO')
            ->where(function ($query) use ($curp) {
                $query->whereRaw("EXISTS(SELECT TRUE FROM jsonb_array_elements(obj_documento->'firmantes'->'firmante'->0) x
                    WHERE x->'_attributes'->>'curp_firmante' IN ('".$curp."'))")
                ->orWhere(function($query1) use ($curp) {
                    $query1->where('obj_documento_interno->emisor->_attributes->email', $curp)
                            ->where('status', 'VALIDADO');
                });
            });
            // ->orderBy('id', 'desc')->get();

        $docsCancelados1 = DocumentosFirmar::where('status', 'CANCELADO')
            ->where(function ($query) use ($curp) {
                $query->whereRaw("EXISTS(SELECT TRUE FROM jsonb_array_elements(obj_documento->'firmantes'->'firmante'->0) x
                    WHERE x->'_attributes'->>'curp_firmante' IN ('".$curp."'))")
                ->orWhere(function($query1) use ($curp) {
                    $query1->where('obj_documento_interno->emisor->_attributes->email', $curp)
                            ->where('status', 'CANCELADO');
                });
            });
            // ->orderBy('id', 'desc')->get();

        $tipo_documento = $request->tipo_documento;
        // if ($tipo_documento != null) {
            session(['tipo' => $tipo_documento]);
        // }
        $tipo_documento = session('tipo');

        if($tipo_documento == null) {
            $docsFirmar = $docsFirmar1->orderBy('id', 'desc')->get();
            $docsFirmados = $docsFirmados1->orderBy('id', 'desc')->get();
            $docsValidados = $docsValidados1->orderBy('id', 'desc')->get();
            $docsCancelados = $docsCancelados1->orderBy('id', 'desc')->get();
        } else {
            $docsFirmar = $docsFirmar1->where('tipo_archivo', $tipo_documento)->orderBy('id', 'desc')->get();
            $docsFirmados = $docsFirmados1->where('tipo_archivo', $tipo_documento)->orderBy('id', 'desc')->get();
            $docsValidados = $docsValidados1->where('tipo_archivo', $tipo_documento)->orderBy('id', 'desc')->get();
            $docsCancelados = $docsCancelados1->where('tipo_archivo', $tipo_documento)->orderBy('id', 'desc')->get();
        }

        foreach ($docsFirmar as $value) {
            $value->base64xml = base64_encode($value->documento);
        }

        $getToken = Tokens_icti::all()->last();
        if (!isset($token)) {// no hay registros
            $token = $this->generarToken($request);
        }
        $token = $getToken->token;

        return view('layouts.firmaElectronica.firmaElectronica', compact('docsFirmar', 'curp', 'docsFirmados', 'docsValidados', 'docsCancelados', 'tipo_documento', 'token'));
    }

    public function update(Request $request) {
        $documento = DocumentosFirmar::where('id', $request->idFile)->first();

        $obj_documento = json_decode($documento->obj_documento, true);
        // $obj_documento_interno = json_decode($documento->obj_documento_interno, true);

        if (empty($obj_documento['archivo']['_attributes']['md5_archivo'])) {
            $obj_documento['archivo']['_attributes']['md5_archivo'] = $documento->md5_file;
        }

        foreach ($obj_documento['firmantes']['firmante'][0] as $key => $value) {
            if ($value['_attributes']['curp_firmante'] == $request->curp) {
                $value['_attributes']['fecha_firmado_firmante'] = $request->fechaFirmado;
                $value['_attributes']['no_serie_firmante'] = $request->serieFirmante;
                $value['_attributes']['firma_firmante'] = $request->firma;
                $value['_attributes']['certificado'] = $request->certificado;
                $obj_documento['firmantes']['firmante'][0][$key] = $value;
            }
        }

        $array = XmlToArray::convert($documento->documento);
        $array['DocumentoChis']['firmantes'] = $obj_documento['firmantes'];

        ##By Jose Luis Moreno/ Creamos nuevo array para ordenar el xml
        $ArrayXml['emisor'] = $obj_documento['emisor'];

        if(isset($obj_documento['receptores'])){
            $ArrayXml['receptores'] = $obj_documento['receptores'];
        }

        $ArrayXml["archivo"] = $obj_documento['archivo'];

        if(isset($obj_documento['anexos'])){
            $ArrayXml["anexos"] = $obj_documento['anexos'];
        }

        $ArrayXml["firmantes"] = $obj_documento['firmantes'];

        $obj_documento = $ArrayXml;

        $result = ArrayToXml::convert($obj_documento, [
            'rootElementName' => 'DocumentoChis',
            '_attributes' => [
                'version' => $array['DocumentoChis']['_attributes']['version'],
                'fecha_creacion' => $array['DocumentoChis']['_attributes']['fecha_creacion'],
                'no_oficio' => $array['DocumentoChis']['_attributes']['no_oficio'],
                'dependencia_origen' => $array['DocumentoChis']['_attributes']['dependencia_origen'],
                'asunto_docto' => $array['DocumentoChis']['_attributes']['asunto_docto'],
                'tipo_docto' => $array['DocumentoChis']['_attributes']['tipo_docto'],
                'xmlns' => 'http://firmaelectronica.chiapas.gob.mx/GCD/DoctoGCD',
            ],
        ]);

        DocumentosFirmar::where('id', $request->idFile)
            ->update([
                'obj_documento' => json_encode($obj_documento),
                // 'obj_documento_interno' => json_encode($obj_documento_interno),
                'documento' => $result,
                // 'documento_interno' => $result2
            ]);

        return redirect()->route('firma.inicio')->with('warning', 'Documento firmado exitosamente!');
    }

    public function sellar(Request $request) {
        $documento = DocumentosFirmar::where('id', $request->txtIdFirmado)->first();
        $xmlBase64 = base64_encode($documento->documento);

        $getToken = Tokens_icti::all()->last();

        $response = $this->sellarFile($xmlBase64, $getToken->token);
        if ($response->json() == null) {
            $request = new Request();
            $token = $this->generarToken($request);
            $response = $this->sellarFile($xmlBase64, $token);
        }

        if ($response->json()['status'] == 1) { //exitoso
            $decode = base64_decode($response->json()['xml']);
            DocumentosFirmar::where('id', $request->txtIdFirmado)
                ->update([
                    'status' => 'VALIDADO',
                    'uuid_sellado' => $response->json()['uuid'],
                    'fecha_sellado' => $response->json()['fecha_Sellado'],
                    'documento' => $decode,
                    'cadena_sello' => $response->json()['cadenaSello']
                ]);
            return redirect()->route('firma.inicio')->with('warning', 'Documento validado exitosamente!');
        } else {
            return redirect()->route('firma.inicio')->with('danger', 'Ocurrio un error al sellar el documento, por favor intente de nuevo');
        }
    }

    public function generarPDF(Request $request) {
        $documento = DocumentosFirmar::where('id', $request->txtIdGenerar)->Where('tipo_archivo','Contrato')->first();
        $contrato = new contratos();
        $data_contrato = contratos::SELECT('contratos.*')
                        ->JOIN('folios', 'folios.id_folios', 'contratos.id_folios')
                        ->JOIN('tbl_cursos','tbl_cursos.id','folios.id_cursos')
                        ->WHERE('tbl_cursos.clave', '=', $documento->numero_o_clave)
                        ->FIRST();

        $data = $contrato::SELECT('folios.id_folios','folios.importe_total','tbl_cursos.id','tbl_cursos.horas','tbl_cursos.fecha_apertura',
                                    'tbl_cursos.tipo_curso','tbl_cursos.espe','tbl_cursos.unidad', 'tbl_cursos.clave','tbl_cursos.inicio','instructores.nombre','instructores.apellidoPaterno',
                                    'instructores.apellidoMaterno','tbl_cursos.instructor_tipo_identificacion','tbl_cursos.instructor_folio_identificacion',
                                    'instructores.rfc','tbl_cursos.modinstructor','instructores.curp','instructores.domicilio','tabla_supre.fecha_validacion')
                            ->WHERE('folios.id_folios', '=', $data_contrato->id_folios)
                            ->LEFTJOIN('folios', 'folios.id_folios', '=', 'contratos.id_folios')
                            ->LEFTJOIN('tabla_supre', 'tabla_supre.id', '=', 'folios.id_supre')
                            ->LEFTJOIN('tbl_cursos', 'tbl_cursos.id', '=', 'folios.id_cursos')
                            ->LEFTJOIN('instructores', 'instructores.id', '=', 'tbl_cursos.id_instructor')
                            ->FIRST();

        $uni = DB::Connection('pgsql')->Table('tbl_unidades')->SELECT('ubicacion')->WHERE('unidad', '=', $data->unidad)->FIRST();
        $funcionarios = $this->funcionarios($uni->ubicacion);
                            //nomes especialidad
        $especialidad = especialidad_instructor::SELECT('especialidades.nombre')
                                                ->WHERE('especialidad_instructores.id', '=', $data_contrato->instructor_perfilid)
                                                ->LEFTJOIN('especialidades', 'especialidades.id', '=', 'especialidad_instructores.especialidad_id')
                                                ->FIRST();

        $fecha_act = new Carbon('23-06-2022');
        $fecha_fir = new Carbon($data_contrato->fecha_firma);
        $nomins = $data->nombre . ' ' . $data->apellidoPaterno . ' ' . $data->apellidoMaterno;

        $cantidad = $this->numberFormat($data_contrato->cantidad_numero);
        $monto = explode(".",strval($data_contrato->cantidad_numero));

        $date = strtotime($data_contrato->fecha_firma);
        $D = date('d', $date);
        $M = $this->toMonth(date('m', $date));
        $Y = date("Y", $date);

        $body = json_decode($documento->obj_documento_interno);
        $body_html = $body->body;


        if ($data->modinstructor == 'HONORARIOS') {
            $pdf = PDF::loadView('layouts.firmaElectronica.contratohonorarios', compact('data_contrato','data','nomins','D','M','Y','monto','especialidad','cantidad','fecha_act','fecha_fir','body_html','funcionarios'));
        }else {
            $pdf = PDF::loadView('layouts.firmaElectronica.contratohasimilados', compact('data_contrato','data','nomins','D','M','Y','monto','especialidad','cantidad','fecha_act','fecha_fir','body_html','funcionarios'));;
        }

        return $pdf->stream("Contrato-Instructor-$data_contrato->numero_contrato.pdf");
    }

    public function cancelarDocumento(Request $request) {
        $date = date('Y-m-d H:i:s');

        if ($request->motivo != null) {
            $data = [
                'usuario' => 'instructor',
                'id' => Auth::user()->id,
                'motivo' => $request->motivo,
                'fecha' => $date,
                'correo' => Auth::user()->email
            ];

            DocumentosFirmar::where('id', $request->txtIdCancel)
                ->update([
                    'status' => 'CANCELADO',
                    'cancelacion' => $data
                ]);
            tbl_cursos::where('clave', $request->txtClave)
                ->update(
                    $request->txtTipo == 'Lista de asistencia'
                        ? ['asis_finalizado' => false]
                        : ($request->txtTipo == 'Lista de calificaciones'
                            ?  ['calif_finalizado' => false]
                            : [])
                );
            return redirect()->route('firma.inicio')->with('warning', 'Documento cancelado exitosamente!');
        } else {
            return redirect()->route('firma.inicio')->with('danger', 'Debe ingresar el motivo de cancelación');
        }
    }

    public function generarToken(Request $request) {
        $resToken = Http::withHeaders([
            'Accept' => 'application/json'
        ])->post('https://interopera.chiapas.gob.mx/gobid/api/AppAuth/AppTokenAuth', [
            'nombre' => 'SISTEM_INSTRUC',
            'key' => '7339F037-D329-4165-A1C9-45FAA99D5FD9'
            // 'nombre' => 'FirmaElectronica',
            // 'key' => '19106D6F-E91F-4C20-83F1-1700B9EBD553'
        ]);

        $token = $resToken->json();
        Tokens_icti::create([
            'token' => $token
        ]);

        return $token;
    }

    public function sellarFile($xml, $token) {
        $response1 = Http::withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$token
        ])->post('https://api.firma.chiapas.gob.mx/FEA/v2/NotariaXML/sellarXML', [
            'xml_Firmado' => $xml
        ]);
        //https://apiprueba.firma.chiapas.gob.mx/FEA/v2/NotariaXML/sellarXML
        return $response1;
    }

    protected function toMonth($m)
    {
        switch ($m) {
            case 1:
                return "Enero";
            break;
            case 2:
                return "Febrero";
            break;
            case 3:
                return "Marzo";
            break;
            case 4:
                return "Abril";
            break;
            case 5:
                return "Mayo";
            break;
            case 6:
                return "Junio";
            break;
            case 7:
                return "Julio";
            break;
            case 8:
                return "Agosto";
            break;
            case 9:
                return "Septiembre";
            break;
            case 10:
                return "Octubre";
            break;
            case 11:
                return "Noviembre";
            break;
            case 12:
                return "Diciembre";
            break;


        }
    }

    protected function numberFormat($numero)
    {
        $part = explode(".", $numero);
        $part[0] = number_format($part['0']);
        $cadwell = implode(".", $part);
        return ($cadwell);
    }

    public function funcionarios($unidad) {
        $query = clone $direc = clone $ccp1 = clone $ccp2 = clone $delegado = clone $academico = clone $vinculacion = clone $destino = DB::Connection('pgsql')->Table('tbl_organismos AS o')->Select('f.nombre','f.cargo')
            ->Join('tbl_funcionarios AS f', 'f.id_org', 'o.id')
            ->Where('f.activo', 'true');

        $direc = $direc->Join('tbl_unidades AS u', 'u.id', 'o.id_unidad')
            ->Where('o.id_parent',1)
            ->Where('u.unidad', $unidad)
            ->First();

        $destino = $destino->Where('o.id',13)->First();
        $ccp1 = $ccp1->Where('o.id',1)->First();
        $ccp2 = $ccp2->Where('o.id',12)->First();
        $delegado = $delegado->Join('tbl_unidades AS u', 'u.id', 'o.id_unidad')
            ->Where('o.nombre','LIKE','DELEG%')
            ->Where('u.unidad', $unidad)
            ->First();

        $academico = $academico->Join('tbl_unidades AS u', 'u.id', 'o.id_unidad')
            ->Where('f.cargo','LIKE','%ACADÉMICO%')
            ->Where('u.unidad', $unidad)
            ->First();

        $vinculacion = $vinculacion->Join('tbl_unidades AS u', 'u.id', 'o.id_unidad')
            ->Where('f.cargo','LIKE','%VINCULACIÓN%')
            ->Where('u.unidad', $unidad)
            ->First();

        $funcionarios = [
            'director' => $direc->nombre,
            'directorp' => $direc->cargo,
            'destino' => $destino->nombre,
            'destinop' => $destino->cargo,
            'ccp1' => $ccp1->nombre,
            'ccp1p' => $ccp1->cargo,
            'ccp2' => $ccp2->nombre,
            'ccp2p' => $ccp2->cargo,
            'delegado' => $delegado->nombre,
            'delegadop' => $delegado->cargo,
            'academico' => $academico->nombre,
            'academicop' => $academico->cargo,
            'elabora' => strtoupper(Auth::user()->name),
            'elaborap' => strtoupper(Auth::user()->puesto)
        ];

        return $funcionarios;
    }


}
