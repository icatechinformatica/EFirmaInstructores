<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    // return view('auth/login');

    return redirect('login');
});

Auth::routes();

Route::get('/home', 'HomeController@index')->name('home');

Route::get('/Registro/inicio', 'RegistroController@index')->name('registro.inicio');
Route::post('/Registro/enviar', 'RegistroController@store')->name('registro.enviar');

Route::get('/Usuarios/inicio', 'UsuariosController@index')->name('usuarios.inicio');
Route::post('/Usuarios/modificar', 'UsuariosController@update')->name('usuarios.modificar');

// agregar rol
Route::get('/Roles/inicio', 'RolesController@index')->name('roles.inicio');
Route::post('/Roles/guardar', 'RolesController@store')->name('roles.store');

// permisos
Route::get('/Permisos/inicio', 'PermisosController@index')->name('permisos.inicio');
Route::post('/Permisos/guardar', 'PermisosController@store')->name('permisos.store');

// calificaciones
Route::get('/Calificaciones/inicio', 'calificaciones\CalificacionesController@index')->name('calificaciones.inicio');
Route::post('/Calificaciones/guardar', 'calificaciones\CalificacionesController@update')->name('calificaciones.guardar');
Route::post('/Calificaciones/pdf', 'calificaciones\CalificacionesController@calificaciones')->name('calificaciones.pdf');
Route::post('/Calificaciones/enviar', 'calificaciones\CalificacionesController@calificacionEnviar')->name('calificacion.enviar');

// lista de asistencia
Route::get('/Asistencia/inicio', 'asistencia\AsistenciaController@index')->name('asistencia.inicio');
Route::post('/Asistencia/guardar', 'asistencia\AsistenciaController@update')->name('asistencia.guardar');
Route::post('/Asistencia/pdf', 'asistencia\AsistenciaController@asistenciaPdf')->name('asistencia.pdf');
Route::post('/Asistencia/enviar', 'asistencia\AsistenciaController@asistenciaEnviar')->name('asistencia.enviar');

// agregar documento para firmar
Route::get('/AddDocumentfirma/inicio', 'firmaElectronica\AddDocumentFirmaController@index')->name('addDocument.inicio');
Route::post('/AddDocumentfirma/buscar', 'firmaElectronica\AddDocumentFirmaController@search')->name('addDocument.buscar');
Route::post('/AddDocumentfirma/guardar', 'firmaElectronica\AddDocumentFirmaController@save')->name('addDocument.guardar');

// firma electronica
Route::get('/firma/inicio', 'firmaElectronica\FirmaController@index')->name('firma.inicio');
Route::post('/firma/update', 'firmaElectronica\FirmaController@update')->name('firma.update');
Route::post('/firma/sellar', 'firmaElectronica\FirmaController@sellar')->name('firma.sellar');
Route::post('/firma/generar', 'firmaElectronica\FirmaController@generarPDF')->name('firma.generarPdf');
Route::post('/firma/cancelar', 'FirmaElectronica\FirmaController@cancelarDocumento')->name('firma.cancelar');
Route::post('/firma/token', 'firmaElectronica\FirmaController@generarToken')->name('firma.token');


// By Jose Luis Moreno Arcos
Route::get('/Reporte/inicio', 'reporte_fot\ReporteController@index')->name('reporte.inicio');
Route::post('/Reporte/enviofotos', 'reporte_fot\ReporteController@catch_fotos')->name('reporte.fotos');
Route::post('/Reporte/enviar', 'reporte_fot\ReporteController@reporteenviar')->name('reporte.enviar');
Route::post('/Reporte/pdf', 'reporte_fot\ReporteController@repofotoPdf')->name('reporte.pdf');
Route::post('/Reporte/ordenar', 'reporte_fot\ReporteController@ordenar_fotos')->name('ordenar.fotos');


// Reset Password
Route::post('/reset-password-modal', 'Auth\LoginController@resetPasswordModal')->name('reset.password.modal');
Route::post('/get-telefono-by-email', 'Auth\LoginController@getTelefonoByEmail')->name('get.telefono.by.email');
