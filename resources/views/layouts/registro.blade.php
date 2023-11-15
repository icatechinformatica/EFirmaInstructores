{{-- @extends('layouts.app') --}}
@extends('adminlte::page')

@section('title', 'Agregar Usuarios')

@section('css')
    <style>
        .colorTop {
            background-color: #541533;
        }
    </style>
@endsection

@section('content')

    <div class="container-fluid pt-5">
        @if ($message = Session::get('success'))
            <div class="alert alert-success">
                <p>{{ $message }}</p>
            </div>
        @endif

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">{{ __('Registrarse') }}</div>

                    <div class="card-body">
                        <form method="POST" action="{{ route('registro.enviar') }}">
                            @csrf

                            <div class="form-group row">
                                <label for="name" class="col-md-4 col-form-label text-md-right">{{ __('Nombre') }}</label>

                                <div class="col-md-6">
                                    <input id="name" type="text" class="form-control @error('name') is-invalid @enderror"
                                        name="name" value="{{ old('name') }}" required autocomplete="name" autofocus>

                                    @error('name')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="curp" class="col-md-4 col-form-label text-md-right">{{ __('CURP') }}</label>

                                <div class="col-md-6">
                                    <input id="curp" type="text" class="form-control @error('curp') is-invalid @enderror"
                                        name="curp" value="{{ old('curp') }}" required autocomplete="curp" autofocus>

                                    @error('curp')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            {{-- organo --}}
                            {{-- <div class="form-group row">
                                <label for="Organo"
                                    class="col-md-4 col-form-label text-md-right">{{ __('Organo') }}</label>
                                <div class="col-md-6">
                                    <select name="organo" id="organo" class="custom-select">
                                        <option value="">--SELECCIONAR--</option>
                                        @foreach ($organos as $organo)
                                            @if ($organo->id_parent > 9)
                                                @foreach ($organos as $item)
                                                    @if ($item->id == $organo->id_parent)
                                                        <option value="{{ $organo->id }}">{{ $organo->descripcion }} -
                                                            {{ $item->descripcion }}</option>
                                                    @endif
                                                @endforeach
                                            @else
                                                <option value="{{ $organo->id }}">{{ $organo->descripcion }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>
                            </div> --}}

                            {{-- organo --}}
                            <div class="form-group row">
                                <label for="rol" class="col-md-4 col-form-label text-md-right">{{ __('Rol') }}</label>
                                <div class="col-md-6">
                                    <select name="rol" id="rol" class="custom-select">
                                        <option value="">--SELECCIONAR--</option>
                                        <option value="3">Instructor</option>
                                        {{-- <option value="1">Directores</option>
                                        <option value="2">Validadores</option>
                                        <option value="3">Direccion General</option> --}}
                                    </select>
                                </div>
                                <div class="col">
                                    <div class="form-group">
                                        <div class="custom-control custom-checkbox">
                                            {{-- <input type="checkbox" value="Areas" class="custom-control-input settings"
                                                name="unidades[]" id="Areas">
                                            <label class="custom-control-label"
                                                for="Areas" value="instructor">Instructor</label> --}}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- telefono --}}
                            <div class="form-group row">
                                <label for="telefono"
                                    class="col-md-4 col-form-label text-md-right">{{ __('Telefono') }}</label>

                                <div class="col-md-6">
                                    <input id="telefono" type="text"
                                        class="form-control @error('telefono') is-invalid @enderror" name="telefono"
                                        value="{{ old('telefono') }}" required autocomplete="telefono" autofocus>

                                    @error('telefono')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="email"
                                    class="col-md-4 col-form-label text-md-right">{{ __('Correo electronico') }}</label>

                                <div class="col-md-6">
                                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                                        name="email" value="{{ old('email') }}" required autocomplete="email">

                                    @error('email')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="password"
                                    class="col-md-4 col-form-label text-md-right">{{ __('Contraseña') }}</label>

                                <div class="col-md-6">
                                    <input id="password" type="password"
                                        class="form-control @error('password') is-invalid @enderror" name="password"
                                        required autocomplete="new-password">

                                    @error('password')
                                        <span class="invalid-feedback" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>

                            <div class="form-group row">
                                <label for="password-confirm"
                                    class="col-md-4 col-form-label text-md-right">{{ __('Confirmar contraseña') }}</label>

                                <div class="col-md-6">
                                    <input id="password-confirm" type="password" class="form-control"
                                        name="password_confirmation" required autocomplete="new-password">
                                </div>
                            </div>

                            <div class="form-group row mb-0">
                                <div class="col-md-6 offset-md-4">
                                    <button type="submit" class="btn btn-primary">
                                        {{ __('Registrarme') }}
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

@endsection
