@extends('layouts.theme.vlayout2025')
@section('title', 'REPORTE FOTOGRAFICO | SIVyC Icatech')
@section('content_script_css')
    <style>
        /* body {
            font-family: sans-serif;
            margin-top: 15%;
            margin-bottom: 4%;
        } */

        /* @page {
            margin: 35px 30px 150px 30px;
        } */

        /* header {
            position: fixed;
            left: 0px;
            top: -80px;
            right: 0px;
            text-align: center;
        } */

        /* header h6 {
            height: 0;
            line-height: 14px;
            padding: 8px;
            margin: 0;
        } */

        /* table #curso {
            font-size: 8px;
            padding: 10px;
            line-height: 18px;
            text-align: justify;
        } */

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
            /* padding: 3px; */
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

        /* footer {
            position: fixed;
            left: 0px;
            bottom: -170px;
            height: 150px;
            width: 100%;
        } */

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

        /* by Jose Luis Moreno */
        .estilo_tabla {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            /* text-align: center; */
            /* margin-top: 20px; */
        }

        .estilo_colum {
            border: 1px solid #ddd;
            padding: 3px;
            /* text-align: left; */
            font-size: 12px;
        }

        /* .direccion {
            top: 1.3cm;
            text-align: left;
            position: absolute;
            bottom: 60px;
            left: 20px;
            font-size: 8px;
            color:#FFF;
            font-weight: bold;
            line-height: 1;
        } */


    </style>
    @php $reporte_fotografico = true; @endphp
@endsection
@section('content')
    {{-- <header>
        <img src="img/instituto_oficial.png" alt="Logo Izquierdo" width="30%" style="position:fixed; left:0; top:0;" />
        <img src="img/chiapas.png" alt="Logo Derecho" width="25%" style="position:fixed; right:0; top:0;" />
    </header> --}}
    {{-- {!! $body['header'] !!} --}}
    {!! $body['body'] !!}
        <br>
        {{-- Mostrar imagenes --}}
        @if (count($base64Images) > 0)
            <div class="" style="text-align: center;">
                @foreach($base64Images as $key => $base64)
                        {{-- <img style="width: 350px; height: 350px; margin: 5px;" src="data:image/jpeg;base64,{{$base64}}" alt="Foto"> --}}
                        {{-- <img style="width: 600px; height: 600px;" src="data:image/jpeg;base64,{{$base64}}" alt="Foto"> --}}
                        @if ($key != (count($base64Images)-1))
                            <div style="page-break-after: always;">
                                <img style="width: auto; height: auto; max-width: 100%; max-height:100%;" src="data:image/jpeg;base64,{{$base64}}" alt="Foto">
                            </div>
                        @else
                            <div style="">
                                <img style="width: auto; height: auto; max-width: 100%; max-height:100%;" src="data:image/jpeg;base64,{{$base64}}" alt="Foto">
                            </div>
                        @endif
                @endforeach
            </div>
        @endif
@endsection
@section('script_content_js')
