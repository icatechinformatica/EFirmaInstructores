@extends('adminlte::page')

@section('title', 'Firmar Electronicamente')

@section('css')
    <style>
        .colorTop {
            background-color: #541533;
        }

    </style>
    <link rel="stylesheet" type="text/css" href="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/plugins/bootstrap-4.3.1/css/bootstrap.min.css" />
    <link rel="stylesheet" type="text/css" href="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/plugins/jasny-bootstrap4/css/jasny-bootstrap.min.css" />

@endsection

@section('content')
    <div class="d-none" id="vHTMLSignature"></div>
    <input class="d-none" id="token" name="token" type="text" value="{{$token}}">

    <div class="container-fluid pt-3 px-0 py-0 mx-0 my-0">
        <div class="row">
            <div class="col">
                @if ($message = Session::get('warning'))
                    <div class="alert alert-info">
                        <p>{{ $message }}</p>
                    </div>
                @endif

                @if ($message = Session::get('danger'))
                    <div class="alert alert-danger">
                        <p>{{ $message }}</p>
                    </div>
                @endif
            </div>
        </div>

        <form action="{{ route('firma.inicio') }}" method="get">
            <div class="row d-flex align-items-center py-2">
                <div class="col">
                    <select class="custom-select" id="tipo_documento" name="tipo_documento">
                        <option value="" selected>Todos los documentos</option>
                        <option {{$tipo_documento == 'Contrato' ? 'selected' : ''}} value="Contrato">Contrato</option>
                        <option {{$tipo_documento == 'Lista de asistencia' ? 'selected' : ''}} value="Lista de asistencia">Lista de asistencia</option>
                        <option {{$tipo_documento == 'Lista de calificaciones' ? 'selected' : ''}} value="Lista de calificaciones">Lista de calificaciones</option>
                        <option {{$tipo_documento == 'Reporte fotografico' ? 'selected' : ''}} value="Reporte fotografico">Reporte fotográfico</option>
                    </select>
                </div>
                <div class="col">
                    <button type="submit" class="btn btn-info">Buscar</button>
                </div>
            </div>
        </form>

        <div class="card">
            <div class="card-header"><div style="display: inline-block;">Mis documentos</div><div style="display: inline-block; margin-left: 70%;"> CURP: {{$curp}}</div></div>
            <div class="card-body px-0">

                <div class="row">
                    <div class="col">
                        {{-- encabezado --}}
                        <nav>
                            <div class="nav nav-tabs nav-fill" id="nav-tab" role="tablist">
                                <a class="nav-item nav-link active" id="nav-home-tab" data-toggle="tab" href="#nav-home" role="tab" aria-controls="nav-home" aria-selected="true">Por Firmar</a>
                                <a class="nav-item nav-link" id="nav-firmados-tab" data-toggle="tab" href="#nav-firmados" role="tab" aria-controls="nav-firmados" aria-selected="false">Firmados</a>
                                <a class="nav-item nav-link" id="nav-validados-tab" data-toggle="tab" href="#nav-validados" role="tab" aria-controls="nav-validados" aria-selected="false">Sellados</a>
                                <a style="color: #FF5733" class="nav-item nav-link" id="nav-cancelados-tab" data-toggle="tab" href="#nav-cancelados" role="tab" aria-controls="nav-cancelados" aria-selected="false">Cancelados</a>
                            </div>
                        </nav>

                        {{-- contenido --}}
                        <div class="tab-content py-3 px-sm-0" id="nav-tabContent">
                            {{-- Por Firmar --}}
                            <div class="tab-pane fade show active" id="nav-home" role="tabpanel" aria-labelledby="nav-home-tab">
                                @if ($docsFirmar != "[]")
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Nombre del documento</th>
                                                    <th scope="col">Ver documento</th>
                                                    <th scope="col">Firmantes</th>
                                                    <th scope="col">Creado</th>
                                                    <th scope="col">Cancelar</th>
                                                    <th scope="col">Firmar</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                @foreach ($docsFirmar as $key => $docFirmar)
                                                    @php
                                                        $firmantes = [];
                                                        $nameArchivo = '';
                                                        $obj = json_decode($docFirmar->obj_documento, true);
                                                        $nameArchivo = $obj['archivo']['_attributes']['nombre_archivo'];

                                                        foreach ($obj['firmantes']['firmante'][0] as $value) {
                                                            // $firmantes = $firmantes.$value['_attributes']['nombre_firmante'].'(), ';
                                                            if($value['_attributes']['curp_firmante'] == $curp){
                                                                $curp = $value['_attributes']['curp_firmante'];
                                                            }

                                                            if(empty($value['_attributes']['firma_firmante'])){
                                                                // $firmantes = $firmantes.$value['_attributes']['nombre_firmante'].' (NO), ';
                                                                array_push($firmantes, $value['_attributes']['nombre_firmante'].' (NO)');
                                                            } else {
                                                                // $firmantes = $firmantes.$value['_attributes']['nombre_firmante'].' (SI), ';
                                                                array_push($firmantes, $value['_attributes']['nombre_firmante'].' (SI)');
                                                            }
                                                        }
                                                        // $firmantes = substr($firmantes, 0, -2);
                                                    @endphp
                                                    <tr>
                                                        <td><small>{{$nameArchivo}}</small></td>
                                                        <td>

                                                            @switch($docFirmar->tipo_archivo)
                                                                @case('Lista de asistencia')
                                                                    <form id="frmPdf" action="{{route('asistencia.pdf')}}" method="post" target="_blank">
                                                                        @csrf
                                                                        <input class="d-none" type="text" name="clave2" value="{{$docFirmar->numero_o_clave}}">
                                                                        <button type="submit" class="btn mr-2"> <img class="rounded" src="{{ asset('img/pdf.png') }}" alt="{{ asset('img/pdf.png') }}" width="30px" height="30px"></button>
                                                                    </form>
                                                                @break
                                                                @case('Lista de calificaciones')
                                                                    <form id="frmPdf" action="{{route('calificaciones.pdf')}}" method="post" target="_blank">
                                                                        @csrf
                                                                        <input class="d-none" type="text" name="clavePDF" value="{{$docFirmar->numero_o_clave}}">
                                                                        <button type="submit" class="btn mr-2"> <img class="rounded" src="{{ asset('img/pdf.png') }}" alt="{{ asset('img/pdf.png') }}" width="30px" height="30px"></button>
                                                                    </form>
                                                                @break
                                                                @case('Reporte fotografico')
                                                                    <form id="frmPdf" action="{{route('reporte.pdf')}}" method="post" target="_blank">
                                                                        @csrf
                                                                        <input class="d-none" type="text" name="clave_curso" value="{{$docFirmar->numero_o_clave}}">
                                                                        <button type="submit" class="btn mr-2"> <img class="rounded" src="{{ asset('img/pdf.png') }}" alt="{{ asset('img/pdf.png') }}" width="30px" height="30px"></button>
                                                                    </form>
                                                                @break
                                                                @default {{-- Contratos --}}
                                                                    <button type="button" onclick="descargarDocumento('{{$docFirmar->id}}')" class="btn mr-2"><img class="rounded" src="{{ asset('img/pdf.png') }}" alt="{{ asset('img/pdf.png') }}" width="30px" height="30px"></button>
                                                                @break
                                                            @endswitch
                                                        </td>
                                                        <td>
                                                            <small>
                                                                @foreach ($firmantes as $item)
                                                                    <p class="my-0">{{$item}}</p>
                                                                @endforeach
                                                            </small>
                                                        </td>
                                                        <td><small>{{$docFirmar->created_at->format('d-m-Y')}}</small></td>
                                                        <td>
                                                            {{-- <button class="btn btn-outline-danger" type="button" onclick="cancelarDocumento('{{$docFirmar->id}}', '{{$nameArchivo}}', '{{$docFirmar->tipo_archivo}}', '{{$docFirmar->numero_o_clave}}')">Cancelar</button> --}}
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-outline-primary" href="#" data-toggle="modal" data-target="#mdlLoadViewSignature" onclick="abriModal('{{$key}}')">firmar</button>
                                                        </td>
                                                        <input class="d-none" value="{{$docFirmar->id}}" name="idFile{{$key}}" id="idFile{{$key}}" type="text">
                                                        <input class="d-none" value="{{$docFirmar->cadena_original}}" name="cadena{{$key}}" id="cadena{{$key}}" type="text">
                                                        <input class="d-none" value="{{$docFirmar->base64xml}}" name="xml{{$key}}" id="xml{{$key}}" type="text">
                                                        <input class="d-none" value="{{$curp}}" name="curp{{$key}}" id="curp{{$key}}" type="text">
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="row mt-5">
                                        <div class="col d-flex justify-content-center">
                                            <strong>Sin documentos por firmar</strong>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Firmados --}}
                            <div class="tab-pane fade" id="nav-firmados" role="tabpanel" aria-labelledby="nav-home-tab">
                                @if ($docsFirmados != "[]")
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Nombre del documento</th>
                                                    <th scope="col">Ver documento</th>
                                                    <th scope="col">Firmantes</th>
                                                    <th scope="col">Creado</th>
                                                    <th scope="col">Cancelar</th>
                                                    <th scope="col">Validar</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                @foreach ($docsFirmados as $docFirmado)
                                                    @php
                                                        $sendValidation = true;
                                                        $firmantes = [];
                                                        $nameArchivo = '';
                                                        $obj = json_decode($docFirmado->obj_documento, true);
                                                        $obj2 = json_decode($docFirmado->obj_documento_interno, true);
                                                        $nameArchivo = $obj['archivo']['_attributes']['nombre_archivo'];

                                                        foreach ($obj['firmantes']['firmante'][0] as $value) {
                                                            if(empty($value['_attributes']['firma_firmante'])){
                                                                $sendValidation = false;
                                                                array_push($firmantes, $value['_attributes']['nombre_firmante'].' (NO)');
                                                                // $firmantes = $firmantes.$value['_attributes']['nombre_firmante'].' (NO) ';
                                                            } else {
                                                                array_push($firmantes, $value['_attributes']['nombre_firmante'].' (SI)');
                                                                // $firmantes = $firmantes.$value['_attributes']['nombre_firmante'].' (SI) ';
                                                            }
                                                        }
                                                        // $firmantes = substr($firmantes, 0, -2);
                                                    @endphp

                                                    <tr>
                                                        <td><small>{{$nameArchivo}}</small></td>
                                                        <td>
                                                            @switch($docFirmado->tipo_archivo)
                                                                @case('Lista de asistencia')
                                                                    <form id="frmPdf" action="{{route('asistencia.pdf')}}" method="post" target="_blank">
                                                                        @csrf
                                                                        <input class="d-none" type="text" name="clave2" value="{{$docFirmado->numero_o_clave}}">
                                                                        <button type="submit" class="btn mr-2"> <img class="rounded" src="{{ asset('img/pdf.png') }}" alt="{{ asset('img/pdf.png') }}" width="30px" height="30px"></button>
                                                                    </form>
                                                                @break
                                                                @case('Lista de calificaciones')
                                                                    <form id="frmPdf" action="{{route('calificaciones.pdf')}}" method="post" target="_blank">
                                                                        @csrf
                                                                        <input class="d-none" type="text" name="clavePDF" value="{{$docFirmado->numero_o_clave}}">
                                                                        <button type="submit" class="btn mr-2"> <img class="rounded" src="{{ asset('img/pdf.png') }}" alt="{{ asset('img/pdf.png') }}" width="30px" height="30px"></button>
                                                                    </form>
                                                                @break
                                                                @case('Reporte fotografico')
                                                                    <form id="frmPdf" action="{{route('reporte.pdf')}}" method="post" target="_blank">
                                                                        @csrf
                                                                        <input class="d-none" type="text" name="clave_curso" value="{{$docFirmado->numero_o_clave}}">
                                                                        <button type="submit" class="btn mr-2"> <img class="rounded" src="{{ asset('img/pdf.png') }}" alt="{{ asset('img/pdf.png') }}" width="30px" height="30px"></button>
                                                                    </form>
                                                                @break
                                                                @default {{-- Contratos --}}
                                                                    <button type="button" onclick="descargarDocumento('{{$docFirmado->id}}')" class="btn mr-2"><img class="rounded" src="{{ asset('img/pdf.png') }}" alt="{{ asset('img/pdf.png') }}" width="30px" height="30px"></button>
                                                                @break
                                                            @endswitch
                                                            {{-- <a href="{{ $docFirmado->link_pdf }}" target="_blank" rel="{{ $docFirmado->link_pdf }}">
                                                                <img class="rounded" src="{{ asset('img/pdf.png') }}" alt="{{ asset('img/pdf.png') }}" width="30px" height="30px">
                                                            </a> --}}
                                                        </td>
                                                        <td>
                                                            <small>
                                                                @foreach ($firmantes as $item)
                                                                    <p class="my-0">{{$item}}</p>
                                                                @endforeach
                                                            </small>
                                                        </td>
                                                        <td><small>{{$docFirmado->created_at->format('d-m-Y')}}</small></td>
                                                        <td>
                                                            {{-- <button type="button" onclick="cancelarDocumento('{{$docFirmado->id}}', '{{$nameArchivo}}', '{{$docFirmado->tipo_archivo}}', '{{$docFirmado->numero_o_clave}}')" class="btn btn-outline-danger">Cancelar</button> --}}
                                                        </td>
                                                        <td>
                                                            {{-- @if ($obj2['emisor']['_attributes']['email'] == $email) --}}
                                                            @if ($sendValidation)
                                                                Listo para Sellado
                                                            @else
                                                                Faltan Firmas
                                                            @endif
                                                            {{-- @else
                                                                No disponible
                                                            @endif --}}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="row mt-3">
                                        <div class="col d-flex justify-content-center">
                                            <strong>Sin Documentos Firmados</strong>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- Validados --}}
                            <div class="tab-pane fade" id="nav-validados" role="tabpanel" aria-labelledby="nav-home-tab">
                                @if ($docsValidados != "[]")
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Nombre del documento</th>
                                                    <th scope="col">Firmantes</th>
                                                    <th scope="col">Creado</th>
                                                    <th scope="col">Validado</th>
                                                    <th scope="col">Cancelar</th>
                                                    <th scope="col">Descargar</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                @foreach ($docsValidados as $docValidado)
                                                    @php
                                                        $firmantes = [];
                                                        $nameArchivo = '';
                                                        $obj = json_decode($docValidado->obj_documento, true);
                                                        $nameArchivo = $obj['archivo']['_attributes']['nombre_archivo'];

                                                        foreach ($obj['firmantes']['firmante'][0] as $value) {
                                                            // $firmantes = $firmantes.$value['_attributes']['nombre_firmante'].', ';
                                                            array_push($firmantes, $value['_attributes']['nombre_firmante']);
                                                        }
                                                        // $firmantes = substr($firmantes, 0, -2);
                                                    @endphp
                                                    <tr>
                                                        <td><small>{{$nameArchivo}}</small></td>
                                                        <td>
                                                            <small>
                                                                @foreach ($firmantes as $item)
                                                                    <p class="my-0">{{$item}}</p>
                                                                @endforeach
                                                            </small>
                                                        </td>
                                                        <td><small>{{$docValidado->created_at->format('d-m-Y')}}</small></td>
                                                        <td><small>{{$docValidado->fecha_sellado}}</small></td>
                                                        <td>
                                                            {{-- <button type="button" onclick="cancelarDocumento('{{$docValidado->id}}', '{{$nameArchivo}}', '{{$docValidado->tipo_archivo}}', '{{$docValidado->numero_o_clave}}')" class="btn btn-outline-danger">Cancelar</button> --}}
                                                        </td>
                                                        <td>
                                                            @switch($docValidado->tipo_archivo)
                                                                @case('Lista de asistencia')
                                                                    <form id="frmPdf" action="{{route('asistencia.pdf')}}" method="post" target="_blank">
                                                                        @csrf
                                                                        <input class="d-none" type="text" name="clave2" value="{{$docValidado->numero_o_clave}}">
                                                                        <button type="submit" class="btn mr-2"> <img class="rounded" src="{{ asset('img/pdf.png') }}" alt="{{ asset('img/pdf.png') }}" width="30px" height="30px"></button>
                                                                    </form>
                                                                @break
                                                                @case('Lista de calificaciones')
                                                                    <form id="frmPdf" action="{{route('calificaciones.pdf')}}" method="post" target="_blank">
                                                                        @csrf
                                                                        <input class="d-none" type="text" name="clavePDF" value="{{$docValidado->numero_o_clave}}">
                                                                        <button type="submit" class="btn mr-2"> <img class="rounded" src="{{ asset('img/pdf.png') }}" alt="{{ asset('img/pdf.png') }}" width="30px" height="30px"></button>
                                                                    </form>
                                                                @break
                                                                @case('Reporte fotografico')
                                                                    <form id="frmPdf" action="{{route('reporte.pdf')}}" method="post" target="_blank">
                                                                        @csrf
                                                                        <input class="d-none" type="text" name="clave_curso" value="{{$docValidado->numero_o_clave}}">
                                                                        <button type="submit" class="btn mr-2"> <img class="rounded" src="{{ asset('img/pdf.png') }}" alt="{{ asset('img/pdf.png') }}" width="30px" height="30px"></button>
                                                                    </form>
                                                                @break
                                                                @default {{-- Contratos --}}
                                                                    <button type="button" onclick="descargarDocumento('{{$docValidado->id}}')" class="btn mr-2"><img class="rounded" src="{{ asset('img/pdf.png') }}" alt="{{ asset('img/pdf.png') }}" width="30px" height="30px"></button>
                                                                @break
                                                            @endswitch
                                                            {{-- <button type="button" onclick="descargarDocumento('{{$docValidado->id}}')" class="btn btn-outline-success">Descargar</button> --}}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="row mt-3">
                                        <div class="col d-flex justify-content-center">
                                            <strong>Sin Documentos Validados</strong>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            {{-- cancelados --}}
                            <div class="tab-pane fade" id="nav-cancelados" role="tabpanel" aria-labelledby="nav-home-tab">
                                @if ($docsCancelados != "[]")
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th scope="col">Nombre del documento</th>
                                                    <th scope="col">Firmantes</th>
                                                    <th scope="col">Creado</th>
                                                    <th scope="col">Cancelado</th>
                                                    <th scope="col">Motivo</th>
                                                    <th scope="col">Canceló</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                @foreach ($docsCancelados as $docCancelado)
                                                    @php
                                                        $firmantes = [];
                                                        $nameArchivo = '';
                                                        $obj = json_decode($docCancelado->obj_documento, true);
                                                        $objCancelado = json_decode($docCancelado->cancelacion, true);
                                                        $nameArchivo = $obj['archivo']['_attributes']['nombre_archivo'];

                                                        foreach ($obj['firmantes']['firmante'][0] as $value) {
                                                            // $firmantes = $firmantes.$value['_attributes']['nombre_firmante'].', ';
                                                            array_push($firmantes, $value['_attributes']['nombre_firmante']);
                                                        }
                                                        // $firmantes = substr($firmantes, 0, -2);
                                                    @endphp
                                                    <tr>
                                                        <td><small>{{$nameArchivo}}</small></td>
                                                        <td><small>
                                                                @foreach ($firmantes as $item)
                                                                    <p class="my-0">{{$item}}</p>
                                                                @endforeach
                                                            </small>
                                                        </td>
                                                        <td><small>{{$docCancelado->created_at->format('d-m-Y')}}</small></td>
                                                        @if(isset($obCancelado['fecha']))
                                                            <td><small>{{$objCancelado['fecha']}}</small></td>
                                                            <td><small>{{$objCancelado['motivo']}}</small></td>
                                                            <td><small>{{$objCancelado['correo']}}</small></td>
                                                        @endif
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <div class="row mt-3">
                                        <div class="col d-flex justify-content-center">
                                            <strong>Sin Documentos Cancelados</strong>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal -->
            <div class="modal fade" id="modalCancel" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <form id="formCancel" action="{{ route('firma.cancelar') }}" method="post">
                            @csrf
                            <div class="modal-header">
                                <h5 class="modal-title" id="exampleModalLongTitle"></h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="form-group">
                                    <label for="motivo">Motivo de Cancelación</label>
                                    <textarea class="form-control" name="motivo" id="motivo" rows="4"></textarea>
                                </div>
                                <input class="d-none" type="text" name="txtIdCancel" id="txtIdCancel">
                                <input class="d-none" type="text" name="txtTipo" id="txtTipo">
                                <input class="d-none" type="text" name="txtClave" id="txtClave">
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                                <button type="submit" class="btn btn-primary">Cancelar Documento</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <form id="formUpdate" action="{{route('firma.update')}}" method="post">
                @csrf
                <input class="d-none" id="fechaFirmado" name="fechaFirmado" type="text">
                <input class="d-none" id="serieFirmante" name="serieFirmante" type="text">
                <input class="d-none" id="firma" name="firma" type="text">
                <input class="d-none" id="curp" name="curp" type="text">
                <input class="d-none" id="idFile" name="idFile" type="text">
                <input class="d-none" id="certificado" name="certificado" type="text">
            </form>

            <form id="formSellar" action="{{route('firma.sellar')}}" method="post">
                @csrf
                <input class="d-none" id="txtIdFirmado" name="txtIdFirmado" type="text">
            </form>

            <form id="formGenerarPDF" action="{{route('firma.generarPdf')}}" method="post" target="_blank">
                @csrf
                <input class="d-none" id="txtIdGenerar" name="txtIdGenerar" type="text">
            </form>


        </div>
    </div>

@endsection

@section('js')

    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/plugins/jquery-3.4.1/jquery-3.4.1.min.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/plugins/bootstrap-4.3.1/js/bootstrap.min.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/plugins/jasny-bootstrap4/js/jasny-bootstrap.min.js"></script>

    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/sjcl.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/sha1_002.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/llave.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/jsbn.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/jsbn2.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/rsa.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/rsa2.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/base64_002.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/crypto-1.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/asn1hex-1.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/rsasign-1.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/x509-1.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/pbkdf2.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/tripledes_002.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/aes.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/rc2.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/asn1.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/base64.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/hex_002.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/yahoo-min.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/hex.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/base64x-1.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/x64-core.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/tripledes.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/core.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/md5.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/sha1.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/sha256.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/ripemd160.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/sha512.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/enc-base64.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/hmac.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/pbkdf2_002.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/cipher-core.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/asn1-1.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/rsapem-1.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-sat/keyutil-1.js"></script>

    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/forge-0.7.1/forge-0.7.1.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-scg/mistake.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-scg/validate.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-scg/access.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-scg/dataSign.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/utilities-scg/dataTransportSign.js"></script>
    <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/signedjs-2.1/signature-spv021_doctos.js"></script>
    {{-- <script src="https://firmaelectronica.shyfpchiapas.gob.mx:8443/tools/library/signedjs-2.1/signature-spv021_doctos-prueba.js"></script> --}}
    <script>
        var cadena = '', xmlBase64 = '', curp = '', idFile = '';
        $(document).ready(function() {
            $('#btnsignature').attr('onclick', 'firmar();');
        });

        function abriModal(key) {
            $('#vHTMLSignature').removeClass('d-none');
            cadena = $('#cadena'+ key).val();
            // xmlBase64 = $('#xml'+ key).val();
            curp = $('#curp'+ key).val();
            idFile = $('#idFile' + key).val();
        }

        function firmar() {
            var response = firmarDocumento($('#token').val());
            console.log(response)
            if(response.codeResponse == '401') {
                generarToken().then((value) => {
                    response = firmarDocumento(value);
                    continueProcess(response);
                }).catch((error) => {
                    continueProcess(response);
                });
            } else {
                continueProcess(response);
            }
        }

        function firmarDocumento(token) {
            // var vresponseSignature = sign(cadena, curp, $('#txtpassword').val(), '30', token);
            var vresponseSignature = sign(cadena, curp, $('#txtpassword').val(), '88', token);
            // RAQN770121HDFMZV08
            console.log(curp)
            return vresponseSignature;
        }

        function generarToken() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    type: 'POST',
                    url: "{{ url('/firma/token') }}",
                    data: {
                        'nombre': '',
                        'key': '',
                        '_token': $("meta[name='csrf-token']").attr("content"),
                    },
                    success: function(result) {
                        resolve(result);

                    },
                    error: function(jqXHR, textStatus) {
                        reject('error');
                    }
                });
            })
        }

        function continueProcess(response) {
            if (response.statusResponse) {
                $('#fechaFirmado').val(response.date);
                $('#serieFirmante').val(response.certifiedSeries)
                $('#firma').val(response.sign);
                $('#curp').val(curp);
                $('#certificado').val(response.certificated)
                $('#idFile').val(idFile);
                $('#formUpdate').submit();
            } else {
                confirm(response.messageResponse + ' ' + response.descriptionResponse)
                location.reload;
            }
        }

        function validardocumento(id) {
            if (confirm("¿Está seguro de enviar a validación el documento?") == true) {
                $('#txtIdFirmado').val(id);
                $('#formSellar').submit();
            }
        }

        function descargarDocumento(id) {
            $('#txtIdGenerar').val(id);
            $('#formGenerarPDF').submit();
        }

        function cancelarDocumento(id, name, tipo, clave) {
            $('#exampleModalLongTitle').html('Cancelar ' + name);
            $('#txtIdCancel').val(id);
            $('#txtTipo').val(tipo);
            $('#txtClave').val(clave);
            $('#modalCancel').modal('toggle');
        }

        $('#btnLista').click(function () {
                $('#frmPdf').attr('target', "_blanck");
                $('#clave2').attr('value', $('#frmPdf'));
                $('#frmPdf').submit();

        });

    </script>

@endsection
