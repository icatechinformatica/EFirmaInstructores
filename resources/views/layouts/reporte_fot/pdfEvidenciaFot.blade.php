<html>

<head>
    <title>REPORTE FOTOGRAFICO</title>
    <style>
        body {
            font-family: sans-serif;
            /* margin: 35px 30px 40px 30px; */
            margin-top: 20%;
            /* margin-bottom: -40px; */
        }

        @page {
            margin: 35px 30px 40px 30px;
        }

        header {
            position: fixed;
            left: 0px;
            top: -80px;
            right: 0px;
            text-align: center;
        }

        header h6 {
            height: 0;
            line-height: 14px;
            padding: 8px;
            margin: 0;
        }

        main {
            padding: 0;
            margin: 0;
            margin-top: 0px;
        }

        .tabla {
            border-collapse: collapse;
            width: 100%;
        }

        .tabla tr td,
        .tabla tr th {
            font-size: 8px;
            border: gray 1px solid;
            text-align: center;
            padding: 3px;
        }

        footer {
            position: fixed;
            left: 0px;
            bottom: -170px;
            height: 150px;
            width: 100%;
        }

        footer .page:after {
            content: counter(page, sans-serif);
        }



        /* by Jose Luis Moreno */
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            /* text-align: center; */
            /* margin-top: 20px; */
        }

        th, td {
            border: 1px solid #ddd;
            padding: 5px;
            /* text-align: left; */
            font-size: 12px;
        }
        .direccion {
            top: 1.3cm;
            text-align: left;
            position: absolute;
            bottom: 60px;
            left: 20px;
            font-size: 8px;
            color:#FFF;
            font-weight: bold;
            line-height: 1;
        }

    </style>
</head>


<body>
    <header>
        <img src="img/instituto_oficial.png" alt="Logo Izquierdo" width="30%" style="position:fixed; left:0; top:0;" />
        <img src="img/chiapas.png" alt="Logo Derecho" width="25%" style="position:fixed; right:0; top:8px;" />
    </header>
    <footer>
        <div style="position: relative; top:-76%">
            <img style="position: absolute;" src="img/formatos/footer_vertical.jpeg" width="100%">
            @if ($cursopdf)
                @php $direccion = explode("*", $cursopdf->direccion);  @endphp
                <p class='direccion'><b>@foreach($direccion as $point => $ari)@if($point != 0)<br> @endif {{$ari}}@endforeach</b></p>
            @endif
        </div>
    </footer>
    <div style="margin-top: -13%;">
        <h6 style="text-align: center;">{{isset($leyenda) ? $leyenda : ''}}</h6>
    </div>
    <div style="text-align: center;">
        <span style="text-align: center;">REPORTE FOTOGRÁFICO DEL INSTRUCTOR</span>
    </div>
    {{-- Lugar --}}
    <div style="text-align: right;">
        <p style="">Unidad de Capacitación {{ucfirst(strtolower($cursopdf->ubicacion))}}
        @if ($cursopdf->ubicacion != $cursopdf->unidad)
        , Accion Movil {{ucfirst(strtolower($cursopdf->unidad))}}.
        @else

        @endif
        </p>
        <p>{{ucfirst(strtolower($cursopdf->unidad))}}, Chiapas. A {{$fecha_gen}}.</p>
    </div>
    {{-- tabla --}}
    @if ($cursopdf)
        <table border="1" class="" width="100%">
            <tbody>
                <tr>
                    <td colspan="2"><b>CURSO: </b>{{$cursopdf->curso}}</td>
                </tr>
                <tr>
                    <td colspan="2"><b>TIPO: </b>{{$cursopdf->tcapacitacion}}</td>
                </tr>
                <tr>
                    <td><b>FECHA DE INICIO: </b>{{$cursopdf->inicio}}</td>
                    <td><b>FECHA DE TERMINO: </b>{{$cursopdf->termino}}</td>
                </tr>
                <tr>
                    <td><b>CLAVE: </b>{{$cursopdf->clave}}</td>
                    <td><b>HORARIO: </b>{{$cursopdf->hini. ' A '. $cursopdf->hfin}}</td>
                </tr>
                <tr>
                    <td><b>NOMBRE DEL TITULAR DE LA U.C: </b>{{$cursopdf->dunidad}}</td>
                    <td><b>NOMBRE DEL INSTRUCTOR: </b>{{$cursopdf->nombre}}</td>
                </tr>
            </tbody>
        </table>
        <br>
        <br>
        <br>
        <br>
        {{-- Mostrar imagenes --}}
        @if (count($base64Images) > 0)
            <div class="" style="text-align: center;">
                @foreach($base64Images as $base64)
                    <img style="width: 350px; height: 350px; margin: 5px;" src="data:image/jpeg;base64,{{$base64}}" alt="Foto">
                @endforeach
            </div>
        @endif

    @endif
</body>

</html>
