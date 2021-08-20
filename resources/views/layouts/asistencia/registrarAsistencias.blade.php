@extends('adminlte::page')

@section('title', 'Registrar Asistencias')

@section('css')
    <style>
        .colorTop {
            background-color: #541533;
        }

        thead tr th { 
            position: sticky;
            top: 0;
            z-index: 10;
            background-color: #ffffff;
        }
        .table-responsive { 
            height:600px;
            overflow:scroll;
        } 

        .static {
            position: sticky;
            left: 0;
            z-index: 10;
            background-color: #ffffff;
        }
        

    </style>
@endsection

@section('content')
    
    <div class="container-fluid pt-4">
        @if ($message = Session::get('success'))
            <div class="alert alert-success">
                <p>{{ $message }}</p>
            </div>
        @endif

        {{ Form::open(['route' => 'asistencia.inicio', 'method' => 'get', 'id'=>'frm']) }}
        {{csrf_field()}}
        
        <div class="card">
            <div class="card-header">Registar Asistencia</div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-2">Clave de curso</div>
                    <div class="col-md-4">
                        {{ Form::text('clave', $clave, ['id'=>'clave', 'class' => 'form-control', 'placeholder' => 'CLAVE DEL CURSO', 'aria-label' => 'CLAVE DEL CURSO', 'required' => 'required', 'size' => 30]) }}
                    </div>
                    <div class="col">
                        {{ Form::button('Buscar', ['class' => 'btn btn-outline-primary', 'type' => 'submit']) }}
                    </div>
                </div>

                @if (isset($curso))
                    @if (count($alumnos) > 0)
                        <div class="row bg-secondary mt-3" style="padding:10px">
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
                            <div class="table-responsive">
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>Alumnos</th>
                                            @foreach ($dias as $dia)
                                                <th>{{$dia}}</th>
                                                <input class="d-none" type="text" name="fechas[]" value="{{$dia}}">
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($alumnos as $alumno)
                                            <input class="d-none" type="text" name="alumnos[]" value="{{$alumno->id}}">
                                            <tr>
                                                <td class="static">{{$alumno->alumno}}</td>
                                                @foreach ($dias as $dia)
                                                    <td>
                                                        <div class="custom-control custom-checkbox d-flex justify-content-center">
                                                            <input type="checkbox"
                                                                @if ($alumno->asistencias != null)
                                                                    @foreach ($alumno->asistencias as $asistencia)
                                                                        @if ($asistencia['fecha'] == $dia && $asistencia['asistencia'] == true)
                                                                            checked
                                                                        @endif
                                                                    @endforeach
                                                                @endif
                                                                value="{{ $alumno->id }} {{$dia}}"
                                                                class="custom-control-input" name="asistencias[]"
                                                                id="check + {{ $alumno->id }} + {{$dia}}">
                                                            <label class="custom-control-label"
                                                                for="check + {{ $alumno->id }} + {{$dia}}"></label>
                                                        </div> 
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col d-flex justify-content-end mt-2">
                                <button id="btnLista" type="button" class="btn btn-outline-info mr-2">GENERAR LISTA DE ASISTENCIA</button>
                                <button id="btnGuardar" type="button" class="btn btn-outline-success">GUARDAR ASISTENCIAS</button>
                            </div>
                        </div>
                    @else
                        <div class="alert alert-success mt-4">
                            <p>No hay alumnos registrados en el curso</p>
                        </div>
                    @endif
                    
                @endif
            </div>
        </div>
        {!! Form::close() !!}

        <form id="frmPdf" action="{{route('asistencia.pdf')}}" method="post">
            @csrf
            <input class="d-none" type="text" name="clave2" id="clave2" value="{{$clave}}">
        </form>
    </div>

@endsection

@section('js')
    <script>
        $('#btnGuardar').click(function () {
            if(confirm("¿Está seguro de guardar las asistencias?")==true){
                $('#frm').attr('action', "{{route('asistencia.guardar')}}");
                $('#frm').attr('method', "post"); 
                $('#frm').submit(); 
            }
        });

        $('#btnLista').click(function () {
            if(confirm('¿Esta seguro de generar la lista de asistencia?') == true) {
                // $('#frm').attr('action', "{{route('asistencia.pdf')}}");
                // $('#frm').attr('method', "post");
                $('#frmPdf').attr('target', "_blanck");  
                $('#frmPdf').submit(); 
            }
        });
    </script>
@endsection