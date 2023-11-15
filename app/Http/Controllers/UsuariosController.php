<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UsuariosController extends Controller {

    public function index(Request $request) {
        if($request->busqueda != null) {
            $users = DB::table('users')->where('users.id', '!=', 1)
                ->where('users.email', '=', $request->busqueda)
                ->leftJoin('organos', 'users.id_area', '=', 'organos.id')
                ->leftJoin('organos as org', 'users.id_organo', '=', 'org.id')
                ->select('users.*', 'organos.descripcion', 'org.descripcion as descripcion2')->get();
        } else {
            /*$users = DB::table('users')->where('users.id', '!=', 1)->leftJoin('organos', 'users.id_area', '=', 'organos.id')
                ->leftJoin('organos as org', 'users.id_organo', '=', 'org.id')
                ->select('users.*', 'organos.descripcion', 'org.descripcion as descripcion2')->get(); */
            $users = DB::table('users')->get();
        }

        $roles = DB::table('roles')->where('id', '!=', 5)->get();
        $permisos = DB::table('model_has_roles')->get();

        return view('layouts.inicioUsuarios', compact('users', 'roles', 'permisos'));
    }

    public function update(Request $request) {
        DB::table('model_has_roles')->where('model_id', '=', $request->id)->delete();
        if ($request->permisos != null) {
            foreach ($request->permisos as $value) {
                DB::table('model_has_roles')->insert([
                    'role_id' => $value,
                    'model_type' => 'App\User',
                    'model_id' => $request->id,
                ]);
            }
        }

        return redirect()->route('usuarios.inicio')->with('success', 'SE GUARDARON LOS CAMBIOS CORRECTAMENTE');
    }

}
