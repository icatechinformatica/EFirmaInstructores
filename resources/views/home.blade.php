{{-- @extends('layouts.app') --}}
@extends('adminlte::page')

@section('title', 'Menu')

@section('css')
    <style>
        .colorTop {
            background-color: #541533;
        }
        .colorTexto {
            color: #541533;
            font-size: 50px;
        }
    </style>
@endsection

@section('content')
    <div class="container">
        <div class="row justify-content-center pt-5">

            <div class="col-12 d-flex justify-content-center">
                {{-- <img src="{{ asset('img/icatech-imagen.png') }}" class="img-fluid"> --}}
                <span class="colorTexto font-weight-bold">EFIRMA</span>
            </div>

            <div class="col-12 d-flex justify-content-center mt-4">
                <h2><strong>Sistema Para Instructores</strong></h2>
            </div>
        </div>
    </div>
@endsection
