<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    /**
     * Iniciar sesión y devolver token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'identificador' => 'required|string', // puede ser email o número de usuario
            'password' => 'required',
        ]);

        // Buscar por email o por numero_usuario
        $user = User::where('email', $request->identificador)
            ->orWhere('numero_usuario', $request->identificador)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'identificador' => ['Las credenciales son incorrectas.'],
            ]);
        }

        // Verificar si el usuario está activo
        if (!$user->activo) {
            return response()->json(['error' => 'Usuario inactivo. Contacta al administrador.'], 403);
        }

        // Revocar tokens anteriores
        $user->tokens()->delete();

        $token = $user->createToken('pos-mobile')->plainTextToken;

        return response()->json([
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->only(['id', 'name', 'email', 'telefono', 'numero_usuario', 'rol', 'activo']),
            'empresa' => $user->empresa ? [
                'id' => $user->empresa->id,
                'nombre' => $user->empresa->nombre,
                'logo_url' => $user->empresa->logo_url,
                'colores' => $user->empresa->colores,
                'configuracion' => $user->empresa->configuracion,
            ] : null,
            'licencia' => [
                'tipo' => $user->licencia_tipo,
                'fecha_inicio' => $user->licencia_fecha_inicio,
                'fecha_fin' => $user->licencia_fecha_fin,
            ],
        ]);
    }

    /**
     * Cerrar sesión (revocar token).
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Sesión cerrada']);
    }

    /**
     * Obtener datos del usuario autenticado.
     */
    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate(['name' => 'sometimes|string|max:120', 'telefono' => 'nullable|string|max:30']);
        $request->user()->update($data);
        return response()->json(['message' => 'Perfil actualizado', 'user' => $request->user()->fresh()->only(['id', 'name', 'email', 'telefono', 'numero_usuario', 'rol'])]);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate(['password_actual' => 'required|string', 'password_nueva' => 'required|string|min:8|confirmed']);
        $user = $request->user();
        if (!Hash::check($data['password_actual'], $user->password)) {
            throw ValidationException::withMessages(['password_actual' => ['La contraseña actual es incorrecta.']]);
        }
        $user->forceFill(['password' => Hash::make($data['password_nueva'])])->save();
        $user->tokens()->delete();
        return response()->json(['message' => 'Contraseña actualizada. Inicia sesión de nuevo.']);
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);
        Password::sendResetLink($request->only('email'));
        return response()->json(['message' => 'Si el correo está registrado, recibirás instrucciones para restablecer la contraseña.']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate(['token' => 'required|string', 'email' => 'required|email', 'password' => 'required|string|min:8|confirmed']);
        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
            $user->tokens()->delete();
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }
        return response()->json(['message' => 'Contraseña restablecida.']);
    }

    public function permissions(Request $request)
    {
        $role = $request->user()->rol;
        $admin = in_array($role, ['admin', 'superadmin'], true);
        return response()->json(['role' => $role, 'capabilities' => [
            'pos.sell' => true, 'sales.read' => true, 'catalog.read' => true,
            'catalog.write' => $admin, 'reports.read' => $admin, 'reports.share' => $admin,
            'users.manage' => $role === 'superadmin', 'companies.manage' => $role === 'superadmin', 'settings.manage' => $admin,
        ]]);
    }
}
