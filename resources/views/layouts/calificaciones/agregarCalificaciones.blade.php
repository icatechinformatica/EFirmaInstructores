@extends('adminlte::page')

@section('title', 'Registrar Calificaciones')

@section('css')
    <style>
        .colorTop {
            background-color: #541533;
        }

    </style>
@endsection

@section('content')

    <div class="container-fluid pt-4">
        @if ($message)
            <div class="row px-2">
                <div class="col-md-12 alert alert-success">
                    <p>{{ $message }}</p>
                </div>
            </div>
        @endif
        @if (isset($curso) && $curso->observacion_calificacion_rechazo != null && $curso->calif_finalizado == FALSE)
        <div class="alert alert-danger">
            <p>{{ $curso->observacion_calificacion_rechazo }}</p>
        </div>
        @endif

        {{ Form::open(['route' => 'calificaciones.inicio', 'method' => 'get', 'id'=>'frm']) }}
        {{csrf_field()}}

        <div class="card">
            <div class="card-header">Registrar Calificaciones</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-2">Clave de curso</div>
                    <div class="col-4">
                            {{ Form::text('clave', $clave, ['id'=>'clave', 'class' => 'form-control', 'placeholder' => 'CLAVE DEL CURSO', 'aria-label' => 'CLAVE DEL CURSO', 'required' => 'required', 'size' => 30]) }}
                    </div>
                    <div class="col">
                        {{ Form::button('Buscar', ['class' => 'btn btn-outline-primary', 'type' => 'submit']) }}
                    </div>
                </div>

                @if (isset($curso))
                    @if ($denegado == 'denegado')
                        <div class="alert alert-success mt-4">
                            <p>Acceso denegado. El curso le pertenece a otro instructor</p>
                        </div>
                    @else
                        @if ($procesoPago == TRUE)
                            <div class="alert alert-info mt-4">
                                <p>La información no es editable; ha sido validada.</p>
                            </div>
                        @endif
                        <div class="row bg-secondary mt-3" style="padding:20px">
                            <div class="form-group col-md-6">
                                CURSO: <b>{{ $curso->curso }}</b>
                            </div>
                            <div class="form-group col-md-4">
                                INSTRUCTOR: <b>{{ $curso->nombre }}</b>
                            </div>
                            <div class="form-group col-md-2">
                                DURACI&Oacute;N: <b>{{ $curso->dura }} hrs.</b>
                            </div>
                            <div class="form-group col-md-6">
                                ESPECIALIDAD: <b>{{ $curso->espe }}</b>
                            </div>
                            <div class="form-group col-md-6">
                                &Aacute;REA: <b>{{ $curso->area }}</b>
                            </div>
                            <div class="form-group col-md-6">
                                FECHAS DEL <b> {{ $curso->inicio }}</b> AL <b>{{ $curso->termino }}</b>
                            </div>
                            <div class="form-group col-md-4">
                                HORARIO: <b>{{ $curso->hini }} A {{ $curso->hfin }}</b>
                            </div>
                            <div class="form-group col-md-2">
                                CICLO: <b>{{ $curso->ciclo }}</b>
                            </div>
                        </div>

                        <div class="row">
                            <div class="table-responsive ">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th scope="col">N°</th>
                                            <th scope="col">MATR&Iacute;CULA</th>
                                            <th scope="col">ALUMNOS</th>
                                            <th scope="col" class="text-center" width="10%">FOLIO ASIGNADO</th>
                                            <th scope="col" class="text-center" width="10%">CALIFICACI&Oacute;N</th>
                                            <th scope="col" class="text-center" width="18%">OBSERVACION</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $cambios = false; ?>
                                        @foreach ($alumnos as $key => $a)
                                            <tr>
                                                <td>{{$key + 1}}</td>
                                                <td> {{ $a->matricula }} </td>
                                                <td> {{ $a->alumno }} </td>
                                                <td class="text-center">
                                                @if ($a->folio) {{ $a->folio }} @else
                                                        {{ 'NINGUNO' }} @endif
                                                </td>
                                                <td>
                                                    @if ((!$a->folio or $a->folio == '0') && ($a->porcentaje_asis >= 70 || !is_null($a->porcentaje_asis)))
                                                        <?php $cambios = true; ?>
                                                        {{ Form::text('calificacion[' . $a->id . ']', $a->calificacion, ['id' => $a->id, 'class' => 'form-control numero', 'required' => 'required', 'size' => 1]) }}
                                                    @else
                                                        {{ $a->calificacion }}
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($a->porcentaje_asis < 70)
                                                        La Asistencia es menor al 70%
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            @if (count($alumnos) > 0 and $fecha_valida >= 0)
                                                <td colspan="6" class="text-right">
                                                    <input class="d-none" type="text" id="calif_finalizado" value="{{$curso->calif_finalizado}}">
                                                    {{ Form::button('GENERAR LISTA DE CALIFICACIONES', ['id' => 'reporte', 'class' => 'btn btn-outline-info']) }}
                                                    @if (!$curso->calif_finalizado && !$procesoPago)
                                                        {{ Form::button('GUARDAR CAMBIOS', ['id' => 'guardar', 'class' => 'btn btn-outline-success']) }}
                                                        <button id="btnEnviar" type="button" class="btn btn-outline-danger">ENVIAR A UNIDAD</button>
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
        {!! Form::close() !!}

        <form id="formPDF" action="{{route('calificaciones.pdf')}}" method="post" target="_blanck">
            @csrf
            <input id="clavePDF" name="clavePDF" class="d-none" type="text" value="{{$clave}}">
        </form>
        <form id="frmEnviar" action="{{route('calificacion.enviar')}}" method="post">
            @csrf
            <input class="d-none" type="text" name="clave3" id="clave3" value="{{$clave}}">
        </form>
    </div>

@endsection

@section('js')
    <script language="javascript">
        $(document).ready(function(){
            $("#guardar").click(function(){
                if(confirm("¿Está seguro de guardar las calificaciones?")==true){
                    $('#frm').attr('action', "{{route('calificaciones.guardar')}}");
                    $('#frm').attr('method', "post");
                    $('#frm').submit();
                }
            });

            $('.numero').keyup(function (){
                this.value = (this.value + '').replace(/[^0-9NP]/g, '');
            });
        });

        $('#reporte').click(function () {
            var calif_finalizado = $('#calif_finalizado').val();
            if (!calif_finalizado) {
                    $('#formPDF').submit();
            } else {
                $('#formPDF').submit();
            }
        });

        $('#btnEnviar').click(function () {
            var asis_finalizado = $('#asis_finalizado').val();
            if (!asis_finalizado) {
                if(confirm('¿Esta seguro de enviar la lista de calificaciones? \n Ya no podra modificar las calificaciones despues.') == true) {
                    $('#btnGuardar').addClass('d-none');
                    $('#btnEnviar').addClass('d-none');
                    $('#frmEnviar').submit();
                }
            } else {
                $('#frmEnviar').submit();
            }

        });
    </script>
@endsection
