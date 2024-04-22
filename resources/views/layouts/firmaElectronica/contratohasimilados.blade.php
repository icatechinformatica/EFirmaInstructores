<!DOCTYPE html>
<html lang="en">
<head>
    <style>
        body{
            font-family: sans-serif;
        }
        @page {
            margin: 30px 60px, 60px, 60px;
        }
        header { position: fixed;
            left: 0px;
            top: -155px;
            right: 0px;
            height: 50px;
            background-color: #ddd;
            text-align: center;
        }
        header h1{
            margin: 1px 0;
        }
        header h2{
            margin: 0 0 1px 0;
        }
        footer {
            position: fixed;
            left: 0px;
            bottom: 0px;
            right: 0px;
            height: 10px;
            text-align: center;
        }
        footer .page:after {
            content: counter(page);
        }
        footer table {
            width: 100%;
        }
        footer p {
            text-align: right;
        }
        footer .izq {
            text-align: left;
        }
        table, td {
                  border:0px solid black;
                }
        table {
            border-collapse:collapse;
            width:100%;
        }
        td {
            padding:0px;
        }
        .page-number:after {
            float: right;
            font-size: 10px;
            /* display: inline-block; */
            content: "Pagina " counter(page) " de 5";
        }
        .link {
            position: fixed;
            left: 0px;
            top: 8px;
            font-size: 7px;
            text-align: left;
        }
    </style>
</head>
    <body>
        <footer>
            <div class="page-number"></div>
        </footer>
        <div class= "container g-pt-30" style="font-size: 12px; margin-bottom: 25px;" >
            <div id="content">
                {!! $body_html !!}
                <table>
                    <tr>
                        <td colspan="2"><p align="center"><b>"ICATECH"</b></p></td>
                        <td colspan="2"><p align="center"><b>"PRESTADOR DE SERVICIOS"</b></p></td>
                    </tr>
                    <tr>
                        <td colspan="2"><div align="center"><br><br></td></div>
                        <td colspan="2"><div align="center"><br><br></td></div>
                    </tr>
                    <tr>
                        <td colspan="2"><div align="center"><b>{{$director->nombre}} {{$director->apellidoPaterno}} {{$director->apellidoMaterno}}</b></td></div>
                        <td colspan="2"><div align="center"><b>C. {{$nomins}}</b></td></div>
                    </tr>
                    <tr>
                        <td colspan="2"><div align="center"><b>{{$director->puesto}} DE CAPACITACIÓN {{$data_contrato->unidad_capacitacion}}</b></td></div>
                        <td colspan="2"><div align="center"></td></div>
                    </tr>
                </table>
                <p align="center"><b>"TESTIGOS"</b></p>
                <br><br><br><br>
                <table>
                    <tr>
                        <td colspan="2"><p align="center"></p></td>
                        <td colspan="2"><p align="center"></p></td>
                    </tr>
                    <tr>
                        <td colspan="2"><div align="center"><b>{{$testigo1->nombre}} {{$testigo1->apellidoPaterno}} {{$testigo1->apellidoMaterno}}</b></td></div>
                        <td colspan="2"><div align="center"><b>{{$testigo3->nombre}} {{$testigo3->apellidoPaterno}} {{$testigo3->apellidoMaterno}}</b></td></div>
                    </tr>
                    <tr>
                        <td colspan="2"><div align="center"><b>{{$testigo1->puesto}}</b></td></div>
                        <td colspan="2"><div align="center"><b>{{$testigo3->puesto}}</b></td></div>
                    </tr>
                </table>
                <br>
                <div align=justify>
                    <small  style="font-size: 10px;">Las Firmas que anteceden corresponden al Contrato de prestación de servicios profesionales en su modalidad de @if($data->tipo_curso=='CURSO') horas curso @else   certificación extraordinaria @endif No. {{$data_contrato->numero_contrato}}, que celebran por una parte el Instituto de Capacitación y Vinculación Tecnológica del Estado de Chiapas, representado por el (la) C. {{$director->nombre}} {{$director->apellidoPaterno}} {{$director->apellidoMaterno}}, {{$director->puesto}} DE CAPACITACIÓN {{$data_contrato->unidad_capacitacion}}, y el (la) C. {{$nomins}}, en el Municipio de {{$data_contrato->municipio}}.</small>
                </div>
            </div>
        </div>
    </body>
</html>

<script src="https://code.jquery.com/jquery-3.4.1.slim.min.js" integrity="sha384-J6qa4849blE2+poT4WnyKhv5vZF5SrPo0iEjwBvKU7imGFAV0wwj1yYfoRSJoZ+n" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.4.1/js/bootstrap.min.js" integrity="sha384-wfSDF2E50Y2D1uUdj0O3uMBJnjuUD4Ih7YwaYd1iqfktj0Uod8GCExl3Og8ifwB6" crossorigin="anonymous"></script>
