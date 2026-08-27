<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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
}
