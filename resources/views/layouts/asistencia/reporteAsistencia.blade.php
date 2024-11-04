<html>

<head>
    <title>LISTA DE ASISTENCIA</title>
    <style>
        body {
            font-family: sans-serif;
        }

        @page {
            margin: 100px 25px 170px 25px;
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

        table #curso {
            font-size: 8px;
            padding: 10px;
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
            bottom: -170px;
            height: 150px;
            width: 100%;
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
            margin-left: 10px;
            margin-right: 20px;
        }

        .tab2 {
            margin-left: 10px;
            margin-right: 60px;
        }

    </style>
</head>

<body>
    {{-- <header>
        <img src="img/reportes/sep.png" alt='sep' width="12%" style='position:fixed; left:0; margin: -70px 0 0 20px;' />
        <h6>SUBSECRETAR&Iacute;A DE EDUCACI&Oacute;N E INVESTIGACI&Oacute;N TECNOL&Oacute;GICAS</h6>
        <h6>DIRECCI&Oacute;N GENERAL DE CENTROS DE FORMACI&Oacute;N PARA EL TRABAJO</h6>
        <h6>LISTA DE ASISTENCIA</h6>
        <h6>(LAD-04)</h6>

    </header> --}}
    {!! $header !!}
    {!! $body_html !!}
    {{-- ELIMINAR DESPUES DEL 01/01/2025 --}}
    {{-- @if (isset($meses))
        @foreach ($meses as $key => $mes)
            @php
                $consec = 1;
            @endphp
            <table class="tabla">
                <thead>
                    <tr>
                        <td
                            @if (explode('-', $mes['ultimoDia'])[2] == 28)
                                colspan="33"
                            @elseif (explode('-', $mes['ultimoDia'])[2] == 29)
                                colspan="34"
                            @elseif (explode('-', $mes['ultimoDia'])[2] == 30)
                                colspan="35"
                            @else
                                colspan="36"
                            @endif
                            >
                            <div id="curso">
                                UNIDAD DE CAPACITACI&Oacute;N:
                                <span class="tab">{{ $curso->plantel }} {{ $curso->unidad }}</span>
                                CLAVE CCT: <span class="tab">{{ $curso->cct }}</span>
                                CICLO ESCOLAR: <span class="tab">{{ $curso->ciclo }}</span>
                                GRUPO: <span class="tab">{{ $curso->grupo }}</span>
                                MES: <span class="tab">{{$mes['mes']}}</span>
                                A&Ntilde;O: &nbsp;&nbsp;{{ $mes['year'] }}
                                <br />
                                AREA: <span class="tab1">{{ $curso->area }}</span>
                                ESPECIALIDAD: <span class="tab1">{{ $curso->espe }}</span>
                                CURSO: <span class="tab1"> {{ $curso->curso }}</span>
                                CLAVE: &nbsp;&nbsp; {{ $curso->clave }}
                                <br />
                                FECHA INICIO: <span class="tab1"> {{ $curso->fechaini }}</span>
                                FECHA TERMINO: <span class="tab1"> {{ $curso->fechafin }}</span>
                                HORARIO: <span class="tab2"> {{ $curso->dia }} DE {{ $curso->hini }} A {{ $curso->hfin }}</span>
                                CURP: &nbsp;&nbsp;{{ $curso->curp }}
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <th
                            @if (explode('-', $mes['ultimoDia'])[2] == 28)
                                colspan="33"
                            @elseif (explode('-', $mes['ultimoDia'])[2] == 29)
                                colspan="34"
                            @elseif (explode('-', $mes['ultimoDia'])[2] == 30)
                                colspan="35"
                            @else
                                colspan="36"
                            @endif
                            style="border-left: white; border-right: white;">
                        </th>
                    </tr>
                    <tr>
                        <th width="15px" rowspan="2">N<br />U<br />M</th>
                        <th width="100px" rowspan="2">N&Uacute;MERO DE <br />CONTROL</th>
                        <th width="280px">NOMBRE DEL ALUMNO</th>
                        @foreach ($mes['dias'] as $keyD => $dia)
                            <th width="10px" rowspan="2"><b>{{ $keyD +1 }}</b></th>
                        @endforeach
                        <th colspan="2"><b>TOTAL</b></th>
                    </tr>
                    <tr>
                        <th>PRIMER APELLIDO/SEGUNDO APELLIDO/NOMBRE(S)</th>
                        <th> A </th>
                        <th> I </th>
                    </tr>
                </thead>

                <tbody>
                    @foreach ($alumnos as $a)
                        @php
                            $tAsis = 0;
                            $tFalta = 0;
                        @endphp
                        <tr>
                            <td>{{ $consec++ }}</td>
                            <td>{{ $a->matricula }}</td>
                            <td>{{ $a->alumno }}</td>
                            @foreach ($mes['dias'] as $dia)
                                <td>
                                    @if ($a->asistencias != null)
                                        @foreach ($a->asistencias as $asistencia)
                                            @if ($asistencia['fecha'] == $dia && $asistencia['asistencia'] == true)
                                                <strong>*</strong>
                                                @php
                                                    $tAsis++
                                                @endphp
                                            @elseif($asistencia['fecha'] == $dia && $asistencia['asistencia'] == false)
                                                x
                                                @php
                                                    $tFalta++
                                                @endphp
                                            @endif
                                        @endforeach
                                    @endif
                                </td>
                            @endforeach
                            <td>{{$tAsis}}</td>
                            <td>{{$tFalta}}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                </tfoot>
            </table>
            @if ($key < count($meses) - 1)
                <p style="page-break-before: always;"></p>
            @endif
        @endforeach
    @else
        {{ 'El Curso no tiene registrado la fecha de inicio y de termino' }}
    @endif --}}
</body>

</html>
