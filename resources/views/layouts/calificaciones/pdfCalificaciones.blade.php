<html>

<head>
    <title>LISTA DE CALIFICACIONES</title>
    <style>
        body {
            font-family: sans-serif;
        }

        @page {
            margin: 200px 25px 0px 25px;
        }

        header {
            position: fixed;
            left: 0px;
            top: -185px;
            right: 0px;
            text-align: center;
        }

        header h6 {
            height: 0;
            line-height: 14px;
            padding: 8px;
            margin: 0;
        }

        header #curso {
            margin-top: 20px;
            font-size: 8px;
            border: 1px solid gray;
            padding: 12px;
            line-height: 18px;
            text-align: justify;
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

        .tab {
            margin-left: 10px;
            margin-right: 50px;
        }

        .tab1 {
            margin-left: 15px;
            margin-right: 50px;
        }

        .tab2 {
            margin-left: 5px;
            margin-right: 20px;
        }

        footer {
            position: fixed;
            left: 0px;
            bottom: -100px;
            height: 150px;
            width: 100%;
            font-size: 10px;
        }
        footer .page:after {
            content: counter(page, sans-serif);
        }

        .tablaf {
            border-collapse: collapse;
            width: 100%;
        }

        .tablaf tr td {
            font-size: 9px;
            text-align: center;
            padding: 3px;
        }

        .tab {
            margin-left: 20px;
            margin-right: 50px;
        }

        .tab1 {
            margin-left: 3px;
            margin-right: 18px;
        }

        .tab2 {
            margin-left: 10px;
            margin-right: 60px;
        }
        .page-break {
            page-break-after: always;
        }
        .page-break-non {
            page-break-after: avoid;
        }

    </style>
</head>

<body>
    {!! $header !!}
    {!! $body !!}
    {{-- <header>
        <img src="img/reportes/sep.png" alt='sep' width="12%"
            style='position:fixed; left:0; margin: -170px 0 0 20px;' />
        <h6>SUBSECRETAR&Iacute;A DE EDUCACI&Oacute;N E INVESTIGACI&Oacute;N TECNOL&Oacute;GICAS</h6>
        <h6>DIRECCI&Oacute;N GENERAL DE CENTROS DE FORMACI&Oacute;N PARA EL TRABAJO</h6>
        <h6>REGISTRO DE EVALUACI&Oacute;N POR SUBOBJETIVOS</h6>
        <h6>(RESD-05)</h6>
        <div id="curso">
            UNIDAD DE CAPACITACI&Oacute;N: <span class="tab">{{ $curso->plantel }} {{ $curso->unidad }}</span>
            CLAVE CCT: <span class="tab">{{ $curso->cct }}</span>
            AREA: <span class="tab">{{ $curso->area }}</span>
            ESPECIALIDAD: &nbsp;&nbsp;{{ $curso->espe }}
            <br />
            CURSO: <span class="tab1">{{ $curso->curso }}</span>
            CLAVE: <span class="tab1">{{ $curso->clave }}</span>
            CICLO ESCOLAR: <span class="tab1">{{ $curso->ciclo }}</span>
            FECHA INICIO: <span class="tab1"> {{ $curso->fechaini }}</span>
            FECHA TERMINO: &nbsp;&nbsp; {{ $curso->fechafin }}
            <br />
            GRUPO: <span class="tab2">{{ $curso->grupo }}</span>
            HORARIO: <span class="tab2"> {{ $curso->dia }} DE {{ $curso->hini }} A {{ $curso->hfin }}</span>
            CURP: &nbsp;&nbsp;{{ $curso->curp }}
        </div>
    </header> --}}

    {{--<main>
         <table class="tabla">
            <thead>
                <tr>
                    <th width="15px" rowspan="2">N<br />U<br />M</th>
                    <th width="90px" rowspan="2">N&Uacute;MERO DE <br />CONTROL</th>
                    <th width="300px">NOMBRE DEL ALUMNO</th>
                    <th colspan="17" width="380"><b>CLAVE DE CADA SUBOBJETIVO</b></th>
                    <th rowspan="2"><b>RESULTADO FINAL</b></th>

                </tr>
                <tr>
                    <th>PRIMER APELLIDO/SEGUNDO APELLIDO/NOMBRE(S)</th>
                    <th colspan="17">RESULTADO</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($alumnos as $a)
                    <tr>
                        <td>{{ $consec++ }}</td>
                        <td>{{ $a->matricula }}</td>
                        <td>{{ $a->alumno }}</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td>{{ $a->calificacion }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
            </tfoot>
        </table>
    </main> --}}
</body>

</html>
