<?php

namespace App\Http\Controllers\Auth;

use App\User;
use Illuminate\Http\Request;
use App\Services\WhatsAppService;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Providers\RouteServiceProvider;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Auth\AuthenticatesUsers;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = RouteServiceProvider::HOME;

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function getTelefonoByEmail(Request $request)
    {
        $mirror = DB::Table('users')->Where('users.email', $request->email)->first();
        if ($mirror) {
            $user = DB::Connection('pgsql')->Table('instructores')->Where('curp', $mirror->curp)->value('telefono');
        } else {
            $user = null;
        }

        return response()->json(['telefono' => $user ? $user : '']);
    }

    public function resetPasswordModal(Request $request)
    {
        $request->validate([
            'email' => [
                'required',
                'email',
                'exists:users,email'
            ],
            'resetTelefono' => 'required'
        ], [
            'email.exists' => 'El correo proporcionado para restablecer la contraseña es erróneo o el usuario no está activo.',
        ]);

        $user = User::where('email', $request->email)->first();

        // Generate a new random password
        $newPassword = \Str::random(6);
        $user->password = \Hash::make($newPassword);

        //mensaje via whatsapp
        $infowhats = [
            'nombre' => $user->name,
            'correo' => $user->email,
            'pwd' => $newPassword,
            'telefono' => $request->resetTelefono,
        ];

        $response = $this->whatsapp_restablecer_usuario_msg($infowhats, app(WhatsAppService::class));

        // Check for WhatsApp sending errors in the response
        if (isset($response['status']) && $response['status'] === false) {
            return back()->with('error', 'Error al enviar mensaje de WhatsApp: ' . ($response['message'] ?? 'Error desconocido'));
        }

        $user->save();

        return back()->with('success', 'Tu contraseña ha sido restablecida. Se ha enviado un mensaje de WhatsApp con tu nueva contraseña.');
    }

     private function whatsapp_restablecer_usuario_msg($instructor, WhatsAppService $whatsapp)
    {
        $plantilla = DB::Connection('pgsql')->Table('tbl_wsp_plantillas')->Where('nombre', 'restablecer_pwd_sivyc')->First();

        // Reemplazar variables en plantilla
        $mensaje = str_replace(
            ['{{nombre}}', '{{correo}}', '{{pwd}}','\n'],
            [$instructor['nombre'], $instructor['correo'], $instructor['pwd'],"\n"],
            $plantilla->plantilla
        );

         $callback = $whatsapp->cola($instructor['telefono'], $mensaje, $plantilla->prueba);

        return $callback;
    }
}
