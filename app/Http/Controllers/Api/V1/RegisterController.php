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

    /**
     * Versión vigente del texto legal.
     *
     * Si actualizas los T&C, cambia esta constante Y la de
     * App\Models\User::VERSION_TERMINOS.
     */
    private const VERSION_TERMINOS = '2026-09-19';

    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    private function generarPasswordAleatoria(): string
    {
        $mayusculas = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
        $minusculas = 'abcdefghijkmnopqrstuvwxyz';
        $numeros    = '23456789';

        $password = [
            $mayusculas[random_int(0, strlen($mayusculas) - 1)],
            $minusculas[random_int(0, strlen($minusculas) - 1)],
            $numeros[random_int(0, strlen($numeros) - 1)],
        ];

        $todos = $mayusculas . $minusculas . $numeros;

        while (count($password) < 12) {
            $password[] = $todos[random_int(0, strlen($todos) - 1)];
        }

        shuffle($password);

        return implode('', $password);
    }

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
            'mac_address' => ['required', 'string', 'max:45'],
            'rfc' => ['nullable', 'string', 'max:20'],

            // 🆕 UBICACIÓN (opcional)
            'latitud' => ['nullable', 'numeric', 'between:-90,90'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180'],
            'precision_metros' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'ubicacion_provider' => ['nullable', 'string', 'max:30'],

            // ✅ T&C — obligatorios
            'terminos_aceptados' => ['required', 'accepted'],
            'terminos_version' => ['required', 'string', 'max:50'],
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
        // CONTRASEÑA ALEATORIA
        // ----------------------------------------------------------
        $passwordPlano = $this->generarPasswordAleatoria();

        // ----------------------------------------------------------
        // ✅ T&C: preparar evidencia del consentimiento
        // ----------------------------------------------------------
        $terminosAceptados = (bool) $data['terminos_aceptados'];
        $terminosVersion = trim($data['terminos_version']);
        $terminosAceptadosAt = now();
        $terminosIp = $ip;
        $terminosUserAgent = substr(
            (string) $request->userAgent(),
            0,
            255
        );

        // ----------------------------------------------------------
        // CREAR TODO EN UNA TRANSACCIÓN
        // ----------------------------------------------------------
        try {
            $resultado = DB::transaction(function () use (
                $data,
                $email,
                $mac,
                $empresaNombre,
                $ip,
                $passwordPlano,
                $terminosAceptados,
                $terminosVersion,
                $terminosAceptadosAt,
                $terminosIp,
                $terminosUserAgent
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
                        'mesas_activas' => true,
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
                    'password' => Hash::make($passwordPlano),
                    'telefono' => $data['telefono'] ?? null,
                    'numero_usuario' => $numeroUsuario,
                    'empresa_id' => $empresa->id,
                    'rol' => 'vendedor',
                    'activo' => true,
                    'mac_address' => $mac,
                    'mac_vinculada' => true,
                    'origen_registro' => 'app',
                    'requiere_cambio_password' => true,

                    // ✅ T&C
                    'terminos_aceptados' => $terminosAceptados,
                    'terminos_version' => $terminosVersion,
                    'terminos_aceptados_at' => $terminosAceptadosAt,
                ]);

                // ----------------------------------------------
                // REGISTRO DE PRUEBA (anti-abuso + T&C)
                // ----------------------------------------------
                RegistroPrueba::create([
                    'email' => $email,
                    'ip' => $ip,
                    'mac_address' => $mac,
                    'empresa_nombre' => $empresaNombre,
                    'empresa_id' => $empresa->id,
                    'user_id' => $user->id,
                    'estado' => 'aprobado',

                    // ✅ T&C — evidencia completa
                    'terminos_aceptados' => $terminosAceptados,
                    'terminos_version' => $terminosVersion,
                    'terminos_aceptados_at' => $terminosAceptadosAt,
                    'terminos_ip' => $terminosIp,
                    'terminos_user_agent' => $terminosUserAgent,
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
                    'terminos_version' => $terminosVersion,
                    'terminos_aceptados_at' => $terminosAceptadosAt->toIso8601String(),
                ],
                $empresa->id,
                $user->id
            );

            Log::info('Registro de prueba creado.', [
                'empresa_id' => $empresa->id,
                'user_id' => $user->id,
                'numero_usuario' => $numeroUsuario,
                'mac' => $mac,
                'terminos_version' => $terminosVersion,
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

                    // ✅ T&C
                    'terminos_aceptados' => (bool) $user->terminos_aceptados,
                    'terminos_version' => $user->terminos_version,
                    'terminos_aceptados_at' => $user->terminos_aceptados_at?->toIso8601String(),
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
                    'password_generica' => $passwordPlano,
                    'mensaje' => 'Esta es tu contraseña temporal. '
                        . 'Cópiala en un lugar seguro. '
                        . 'Debes cambiarla desde Configuración → Usuario.',
                ],
                'terminos' => [
                    'aceptados' => true,
                    'version' => $terminosVersion,
                    'aceptados_at' => $terminosAceptadosAt->toIso8601String(),
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