@extends('adminlte::page')

@section('title', 'Reporte Fotografico')

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

        .fondo_info{
            background-color: #ece6e6;
        }

        #imageContainer img {
            width: 300px;
            height: 300px;
            margin: 5px; /* Ajusta el espacio entre las imágenes según tu preferencia */
            border: 2px solid #3e3c3c; /* Añade un borde para separar las imágenes */
            border-radius: 7px; /* Opcional: agrega esquinas redondeadas */
        }

        .foto img {
            width: 300px;
            height: 300px;
            margin: 5px;
            border: 2px solid #3e3c3c;
            border-radius: 7px;
        }

        #loader-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5); /* Fondo semi-transparente */
            z-index: 9999; /* Asegura que esté por encima de otros elementos */
            display: none; /* Ocultar inicialmente */
        }

        #loader {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 60px;
            height: 60px;
            border: 6px solid #fff;
            border-top: 6px solid #621132;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: translate(-50%, -50%) rotate(0deg);
            }
            100% {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }


    </style>
@endsection

@section('content')

    <div class="container-fluid pt-4">
        {{-- Loader --}}
        <div id="loader-overlay">
            <div id="loader"></div>
        </div>

        @if ($messages = Session::get('success'))
            <div class="alert alert-success">
                <p>{{ $messages }}</p>
            </div>
        @endif
        @if ($messages = Session::get('alert'))
            <div class="alert alert-warning">
                <p>{{ $messages }}</p>
            </div>
        @endif
        @if ($mensaje_retorno != '' && $status_documento == 'RETORNADO')
            <div class="alert alert-danger">
                <p>{{ $mensaje_retorno }}</p>
            </div>
        @endif
        @if ($status_firma != '')
            <div class="alert alert-info">
                <p>{{$status_firma}}</p>
            </div>
        @endif

        {{ Form::open(['route' => 'reporte.inicio', 'method' => 'get', 'id'=>'frm']) }}
        {{csrf_field()}}

        <div class="card">
            <div class="card-header">Reporte Fotografico</div>
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
                    @if ($message == 'denegado')
                        <div class="alert alert-warning mt-4">
                            <p>Acceso denegado. El curso le pertenece a otro instructor</p>
                        </div>
                    @elseif($message == 'noDisponible')
                        <div class="alert alert-warning mt-4">
                            <p>El Curso fué {{$curso->status}} y turnado a {{$curso->turnado}}.</p>
                        </div>
                    @elseif($message == 'ok')

                            <div class="row fondo_info mt-3 p-3 col-12">
                                <div class="col-6">
                                    <div class="form-group">
                                        <b>CURSO: </b>{{ $curso->curso }}
                                    </div>
                                    <div class="form-group">
                                        <b>TIPO: </b>{{ $curso->tcapacitacion }}
                                    </div>
                                    <div class="form-group">
                                        <b>FECHA DE INICIO: </b> {{ \Carbon\Carbon::createFromFormat('Y-m-d', $curso->inicio)->format('d/m/Y') }}
                                    </div>
                                    <div class="form-group">
                                        <b>FECHA DE TERMINO: </b> {{ \Carbon\Carbon::createFromFormat('Y-m-d', $curso->termino)->format('d/m/Y') }}
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-group">
                                        <b>CLAVE: </b>{{ $curso->clave }}
                                    </div>
                                    <div class="form-group">
                                        <b>HORARIO: </b>{{ $curso->hini }} A {{ $curso->hfin }}
                                    </div>
                                    <div class="form-group">
                                        <b>TITULAR DE LA UNIDAD: </b>{{ $unidad->dunidad}}
                                    </div>
                                    <div class="form-group">
                                        <b>INSTRUCTOR: </b>{{ $curso->nombre }}
                                    </div>
                                </div>
                            </div>

                            {{-- Mostrar imagenes --}}
                            @if (count($array_fotos) > 0)
                            <div class="col-12 mt-2 row">
                                @foreach ($array_fotos as $foto)
                                    <div class="d-flex foto">
                                        <img class="" src="{{$path_files.$foto}}" alt="Foto">
                                    </div>
                                @endforeach
                            </div>
                            @endif

                            {{-- Cargar y visualizar imagen --}}
                            <span class="badge badge-light mt-3">3 FOTOGRAFÍAS</span>

                            <div class="col-12">
                                <!-- Input para seleccionar imágenes -->
                                <input type="file" id="inputFile" accept="image/*" multiple>
                            </div>
                            <div class="col-12 mt-2">
                                <!-- Contenedor para mostrar imágenes cargadas -->
                                <div class="d-flex" id="imageContainer"></div>
                            </div>

                            {{-- boton generar pdf --}}
                            <div class="col-8">
                                <button id="btnGenerar" type="button" class="btn btn-info mt-1" onclick="">GENERAR PDF</button>
                                @if ($status_documento == "" || $status_documento == 'RETORNADO')
                                <button id="btnSaveImg" type="button" class="btn btn-info mt-1" onclick="enviarImgServ({{$curso->id}})">GUARDAR FOTOS</button>
                                <button id="btnEnviar" type="button" class="btn btn-danger mt-1" onclick="">ENVIAR</button>
                                @endif
                            </div>


                            {{-- <div class="row">
                                <input class="d-none" type="text" id="asis_finalizado" value="{{$curso->asis_finalizado}}">
                                <div class="col d-flex justify-content-end mt-2">
                                    <button id="btnLista" type="button" class="btn btn-outline-info mr-2">GENERAR LISTA DE ASISTENCIA</button>
                                    @if (!$curso->asis_finalizado)
                                        <button id="btnGuardar" type="button" class="btn btn-outline-success mr-2">GUARDAR ASISTENCIAS</button>
                                        <button id="btnEnviar" type="button" class="btn btn-outline-danger">ENVIAR A UNIDAD</button>
                                    @endif
                                </div>
                            </div> --}}

                    @else
                        <div class="alert alert-success mt-4">
                            <p>No hay registros por mostrar</p>
                        </div>
                    @endif

                @endif
            </div>
        </div>
        {!! Form::close() !!}

        <form id="frmPdf" action="" method="post">
            @csrf
            <input type="hidden" name="clave_curso" value="{{$clave}}">
            {{-- <input class="d-none" type="text" name="clave2" id="clave2" value="{{$clave}}"> --}}
        </form>
        <form id="frmEnviar" action="{{route('reporte.enviar')}}" method="post">
            @csrf
            {{-- <input class="d-none" type="text" name="clave3" id="clave3" value="{{$clave}}"> --}}
            <input type="hidden" name="claveg" value="{{$clave}}">
        </form>
    </div>

@endsection

@section('js')
    <script>
        //Generar PDF
        $('#btnGenerar').click(function () {
            if(confirm("¿Está seguro de generar el documento PDF?")==true){
                $('#frmPdf').attr('action', "{{route('reporte.pdf')}}");
                $('#frmPdf').attr('target', "_blank");
                $('#frmPdf').attr('method', "post");
                $('#frmPdf').submit();
            }
        });
        //Enviar datos para firmado
        $('#btnEnviar').click(function () {
            //Agregar validacion de que ya sepa que se ha generado el pdf de lo contrario notificarle en este apartado
            let pdfGenerado =  true;
            if (pdfGenerado) {
                if(confirm('¿Está seguro de enviar el reporte fotografico? \n Una vez enviado ya no podrá hacer cambios.') == true) {
                    $('#frmEnviar').submit();
                }
            } else {
                $('#frmEnviar').submit();
            }

        });

        //CODIGO PARA VISUALIZAR LAS IMAGENES
        // Variable Global
        var imagenesGlobal = "";
        var numImagenesG = "";

        document.getElementById('inputFile').addEventListener('change', function (event) {
        const input = event.target;
        imagenesGlobal = input; //Agregamos datos a la variable global
        const cantidad_imagen = event.target.files.length
        numImagenesG = cantidad_imagen;
        const imageContainer = document.getElementById('imageContainer');
        // console.log(event.target.files.length);
        if (cantidad_imagen != 3) {
            alert("DEBE CARGAR 3 FOTOGRAFÍAS");
            return;
        }
        // Limpiar el contenedor de imágenes antes de agregar nuevas
        imageContainer.innerHTML = '';

        // Recorrer cada archivo seleccionado
        for (const file of input.files) {
            const reader = new FileReader();

            reader.onload = function (e) {
                // Crear elemento de imagen
                const img = document.createElement('img');
                img.src = e.target.result;
                img.style.maxWidth = '100%';

                // Crear contenedor para la imagen y el botón
                const container = document.createElement('div');
                container.appendChild(img);

                // Agregar contenedor al contenedor principal
                imageContainer.appendChild(container);
            };
            reader.readAsDataURL(file);
        }
    });

    //GUARDADO DE IMAGENES
    function enviarImgServ(id_c) {
        loader('show');
        if (numImagenesG != 3) {
            alert("DEBE CARGAR 3 FOTOGRAFÍAS");
            loader('hide');
            return;
        }
        let id_curso =  id_c;
        let input = imagenesGlobal; //Agregamos al input lo que contiene la variable global
        const formData = new FormData();

        for (const file of input.files) {
            formData.append('imagenes[]', file);
            formData.append('id_curso', id_curso);
        }
        fetch('/Reporte/enviofotos', {
            method: 'POST',
            body: formData,
            headers: {
            'X-CSRF-TOKEN': $("meta[name='csrf-token']").attr("content")
            },
        })
        .then(response => response.json())
        .then(data => {
            console.log(data.respuesta);
            if (data.respuesta.status == 'success') {
                alert('Operación exitosa');
                location.reload();
            }
        })
        .catch(error => console.error('Error:', error));

    }

    //LOADER DE CARGANDO
    function loader(make) {
        if(make == 'hide') make = 'none';
        if(make == 'show') make = 'block';
        document.getElementById('loader-overlay').style.display = make;
    }
    </script>
@endsection
