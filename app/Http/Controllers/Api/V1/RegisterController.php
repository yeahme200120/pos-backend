<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\RegistroPrueba;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class RegisterController extends Controller
{
    private const DIAS_PRUEBA = 7;

    private const PASSWORD_GENERICA = 'pos2026';

    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * Registrar una nueva empresa con licencia de prueba.
     *
     * SOLO desde la app móvil.
     */
    public function register(Request $request)
    {
        // ----------------------------------------------------------
        // RATE LIMIT
        // ----------------------------------------------------------
        $ip = $request->ip();
        $rateLimitKey = 'register:' . $ip;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            return response()->json([
                'success' => false,
                'error' => 'RATE_LIMIT',
                'message' => 'Demasiados intentos. Intenta más tarde.',
            ], 429);
        }

        RateLimiter::hit($rateLimitKey, 3600);

        // ----------------------------------------------------------
        // VALIDACIÓN
        // ----------------------------------------------------------
        $data = $request->validate([
            'empresa_nombre' => ['required', 'string', 'max:255'],
            'nombre' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'mac_address' => ['required', 'string', 'max:45'],
            'rfc' => ['nullable', 'string', 'max:20'],
        ]);

        $email = strtolower(trim($data['email']));
        $mac = strtoupper(trim($data['mac_address']));
        $empresaNombre = trim($data['empresa_nombre']);

        // ----------------------------------------------------------
        // VERIFICAR EMAIL ÚNICO
        // ----------------------------------------------------------
        $existeEmail = User::withTrashed()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();

        if ($existeEmail) {
            $this->auditoriaService->registrarSistema(
                'registro.rechazado.email_duplicado',
                'users',
                null,
                null,
                [
                    'email' => $email,
                    'ip' => $ip,
                    'mac' => $mac,
                ],
                null,
                null,
                $request
            );

            return response()->json([
                'success' => false,
                'error' => 'EMAIL_EXISTS',
                'message' => 'Este correo ya está registrado. '
                    . 'Si ya tienes cuenta, inicia sesión.',
            ], 422);
        }

        // ----------------------------------------------------------
        // VERIFICAR EMPRESA ÚNICA
        // ----------------------------------------------------------
        $existeEmpresa = Empresa::withTrashed()
            ->whereRaw('LOWER(nombre) = ?', [strtolower($empresaNombre)])
            ->exists();

        if ($existeEmpresa) {
            $this->auditoriaService->registrarSistema(
                'registro.rechazado.empresa_duplicada',
                'empresas',
                null,
                null,
                [
                    'empresa_nombre' => $empresaNombre,
                    'email' => $email,
                    'ip' => $ip,
                    'mac' => $mac,
                ],
                null,
                null,
                $request
            );

            return response()->json([
                'success' => false,
                'error' => 'EMPRESA_YA_REGISTRADA',
                'message' => 'Esta empresa ya está registrada. '
                    . 'Contacta al administrador del sistema.',
            ], 422);
        }

        // ----------------------------------------------------------
        // VERIFICAR QUE LA EMPRESA NO TENGA YA UN USUARIO
        // ----------------------------------------------------------
        // Regla de negocio (spec §1.2 y §2):
        //   Desde la app solo se permite 1 usuario por empresa.
        //
        // NOTA: Si la empresa no existía (validación anterior),
        // esta condición nunca puede cumplirse hoy. Se conserva
        // por defensa en profundidad para el día en que se permita
        // registrar sobre empresas existentes.
        // ----------------------------------------------------------
        $empresaExistente = Empresa::withTrashed()
            ->whereRaw('LOWER(nombre) = ?', [strtolower($empresaNombre)])
            ->first();

        if ($empresaExistente) {
            $usuariosDeEmpresa = User::withTrashed()
                ->where('empresa_id', $empresaExistente->id)
                ->count();

            if ($usuariosDeEmpresa >= 1) {
                $this->auditoriaService->registrarSistema(
                    'registro.rechazado.empresa_con_usuarios',
                    'empresas',
                    $empresaExistente->id,
                    null,
                    [
                        'empresa_nombre' => $empresaNombre,
                        'usuarios_actuales' => $usuariosDeEmpresa,
                        'email' => $email,
                        'ip' => $ip,
                        'mac' => $mac,
                    ],
                    $empresaExistente->id,
                    null,
                    $request
                );

                return response()->json([
                    'success' => false,
                    'error' => 'EMPRESA_CON_USUARIOS',
                    'message' => 'Esta empresa ya tiene un usuario registrado. '
                        . 'Contacta al administrador del sistema.',
                ], 422);
            }
        }

        // ----------------------------------------------------------
        // VERIFICAR MAC ÚNICA
        // ----------------------------------------------------------
        $existeMac = RegistroPrueba::where('mac_address', $mac)
            ->whereIn('estado', ['pendiente', 'aprobado'])
            ->exists();

        if ($existeMac) {
            $this->auditoriaService->registrarSistema(
                'registro.rechazado.mac_duplicada',
                'users',
                null,
                null,
                [
                    'mac' => $mac,
                    'ip' => $ip,
                ],
                null,
                null,
                $request
            );

            return response()->json([
                'success' => false,
                'error' => 'MAC_ALREADY_USED',
                'message' => 'Este dispositivo ya creó una cuenta de prueba.',
            ], 422);
        }

        // ----------------------------------------------------------
        // CREAR TODO EN UNA TRANSACCIÓN
        // ----------------------------------------------------------
        try {
            $resultado = DB::transaction(function () use (
                $data,
                $email,
                $mac,
                $empresaNombre,
                $ip
            ) {
                // ----------------------------------------------
                // EMPRESA
                // ----------------------------------------------
                $empresa = Empresa::create([
                    'nombre' => $empresaNombre,
                    'rfc' => $data['rfc'] ?? null,
                    'email_contacto' => $email,
                    'telefono' => $data['telefono'] ?? null,
                    'configuracion' => [
                        'cajas_activas' => true,
                        'mesas_activas' => false,
                    ],
                    'colores' => [
                        'primary' => '#1E293B',
                        'secondary' => '#108981',
                        'background' => '#f3f4f6',
                        'text' => '#FFFFFF',
                        'text_navbar' => '#FFFFFF',
                        'menu_hover' => '#2d3748',
                    ],
                    'activo' => true,
                    'licencia_tipo' => 'semana',
                    'licencia_fecha_inicio' => now(),
                    'licencia_fecha_fin' => now()->addDays(self::DIAS_PRUEBA),
                    'licencia_activa' => true,
                    'licencia_ultima_validacion' => now(),
                ]);

                // ----------------------------------------------
                // NÚMERO DE USUARIO
                // ----------------------------------------------
                $nextId = (User::withTrashed()->max('id') ?? 0) + 1;
                $numeroUsuario = User::generarNumeroUsuario($nextId);

                // ----------------------------------------------
                // USUARIO
                // ----------------------------------------------
                $user = User::create([
                    'name' => trim($data['nombre']),
                    'email' => $email,
                    'password' => Hash::make(self::PASSWORD_GENERICA),
                    'telefono' => $data['telefono'] ?? null,
                    'numero_usuario' => $numeroUsuario,
                    'empresa_id' => $empresa->id,
                    'rol' => 'vendedor',
                    'activo' => true,
                    'mac_address' => $mac,
                    'mac_vinculada' => true,
                    'origen_registro' => 'app',
                    'requiere_cambio_password' => true,
                ]);

                // ----------------------------------------------
                // REGISTRO DE PRUEBA (anti-abuso)
                // ----------------------------------------------
                RegistroPrueba::create([
                    'email' => $email,
                    'ip' => $ip,
                    'mac_address' => $mac,
                    'empresa_nombre' => $empresaNombre,
                    'empresa_id' => $empresa->id,
                    'user_id' => $user->id,
                    'estado' => 'aprobado',
                ]);

                return [
                    'empresa' => $empresa,
                    'user' => $user,
                    'numeroUsuario' => $numeroUsuario,
                ];
            });

            /** @var Empresa $empresa */
            $empresa = $resultado['empresa'];
            /** @var User $user */
            $user = $resultado['user'];
            $numeroUsuario = $resultado['numeroUsuario'];

            // ----------------------------------------------
            // TOKEN SANCTUM
            // ----------------------------------------------
            $token = $user->createToken('pos-mobile')->plainTextToken;

            // ----------------------------------------------
            // AUDITORÍA
            // ----------------------------------------------
            $this->auditoriaService->registrar(
                $request,
                'registro.creado',
                'users',
                $user->id,
                null,
                [
                    'empresa_id' => $empresa->id,
                    'empresa_nombre' => $empresa->nombre,
                    'email' => $email,
                    'mac' => $mac,
                    'ip' => $ip,
                    'licencia_tipo' => 'semana',
                    'licencia_fin' => $empresa->licencia_fecha_fin?->toISOString(),
                ],
                $empresa->id,
                $user->id
            );

            Log::info('Registro de prueba creado.', [
                'empresa_id' => $empresa->id,
                'user_id' => $user->id,
                'numero_usuario' => $numeroUsuario,
                'mac' => $mac,
            ]);

            // ----------------------------------------------
            // RESPUESTA
            // ----------------------------------------------
            return response()->json([
                'success' => true,
                'access_token' => $token,
                'token_type' => 'Bearer',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'telefono' => $user->telefono,
                    'numero_usuario' => $user->numero_usuario,
                    'rol' => $user->rol,
                    'activo' => (bool) $user->activo,
                    'requiere_cambio_password' => true,
                ],
                'empresa' => [
                    'id' => $empresa->id,
                    'nombre' => $empresa->nombre,
                    'rfc' => $empresa->rfc,
                    'logo_url' => $empresa->logo_url,
                    'colores' => $empresa->colores,
                    'configuracion' => $empresa->configuracion,
                    'activo' => (bool) $empresa->activo,
                ],
                'licencia' => [
                    'tipo' => 'semana',
                    'fecha_inicio' => $empresa->licencia_fecha_inicio?->toISOString(),
                    'fecha_fin' => $empresa->licencia_fecha_fin?->toISOString(),
                    'activa' => true,
                    'vigente' => true,
                    'en_gracia' => false,
                    'permanente' => false,
                    'dias_restantes' => self::DIAS_PRUEBA,
                    'dias_vencidos' => 0,
                    'licencia_activa' => true,
                    'empresa_id' => $empresa->id,
                ],
                'fecha_comercial' => now()->toDateString(),
                'credenciales_iniciales' => [
                    'numero_usuario' => $numeroUsuario,
                    'password_generica' => self::PASSWORD_GENERICA,
                    'mensaje' => 'Guarda estas credenciales. '
                        . 'Cambia la contraseña en Configuración → Usuario.',
                ],
            ], 201);
        } catch (Throwable $e) {
            Log::error('Error al registrar prueba.', [
                'email' => $email,
                'empresa' => $empresaNombre,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => 'REGISTER_ERROR',
                'message' => 'No fue posible completar el registro.',
            ], 500);
        }
    }

    /**
     * Verificar si un email está disponible.
     */
    public function checkEmail(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = strtolower(trim($data['email']));

        $existe = User::withTrashed()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->exists();

        return response()->json([
            'disponible' => !$existe,
        ]);
    }

    /**
     * Verificar si una empresa está disponible.
     */
    public function checkEmpresa(Request $request)
    {
        $data = $request->validate([
            'empresa_nombre' => ['required', 'string', 'max:255'],
        ]);

        $nombre = strtolower(trim($data['empresa_nombre']));

        $existe = Empresa::withTrashed()
            ->whereRaw('LOWER(nombre) = ?', [$nombre])
            ->exists();

        return response()->json([
            'disponible' => !$existe,
        ]);
    }
}