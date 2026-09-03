<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Iniciar sesión.
     *
     * Permite:
     * - numero_usuario
     * - email
     *
     * La licencia SIEMPRE se obtiene desde empresas.
     */
    public function login(Request $request)
    {
        $request->validate([
            'identificador' => [
                'required',
                'string',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $identificador = trim(
            (string) $request->input('identificador')
        );

        /*
         * IMPORTANTE:
         * Nunca aplicar trim() a la contraseña.
         */
        $password = (string) $request->input('password');

        if ($identificador === '') {
            throw ValidationException::withMessages([
                'identificador' => [
                    'Ingresa tu número de usuario o correo.',
                ],
            ]);
        }

        if ($password === '') {
            throw ValidationException::withMessages([
                'password' => [
                    'Ingresa tu contraseña.',
                ],
            ]);
        }

        /*
         * Detectar si el identificador es correo.
         */
        $esEmail = filter_var(
            $identificador,
            FILTER_VALIDATE_EMAIL
        ) !== false;

        /*
         * Buscar usuario.
         *
         * IMPORTANTE:
         * No aplicar strtolower directamente al campo de BD.
         * LOWER(email) permite mantener el login por correo
         * independientemente de mayúsculas/minúsculas.
         */
        if ($esEmail) {
            $user = User::query()
                ->whereRaw(
                    'LOWER(email) = ?',
                    [
                        strtolower($identificador),
                    ]
                )
                ->first();
        } else {
            $user = User::query()
                ->where(
                    'numero_usuario',
                    $identificador
                )
                ->first();
        }

        /*
         * Usuario inexistente.
         */
        if (!$user) {
            app(AuditoriaService::class)
                ->registrarSistema(
                    'login.fallido',
                    'users',
                    null,
                    null,
                    [
                        'motivo' =>
                            'usuario_no_encontrado',
                        'identificador' =>
                            $identificador,
                        'tipo_identificador' =>
                            $esEmail
                                ? 'email'
                                : 'numero_usuario',
                    ],
                    null,
                    null,
                    $request
                );

            throw ValidationException::withMessages([
                'identificador' => [
                    'Número de usuario o correo incorrectos.',
                ],
            ]);
        }

        /*
         * Usuario inactivo.
         */
        if (!$this->isUserActive($user)) {
            app(AuditoriaService::class)
                ->registrarSistema(
                    'login.fallido',
                    'users',
                    $user->id,
                    null,
                    [
                        'motivo' =>
                            'usuario_inactivo',
                        'identificador' =>
                            $identificador,
                    ],
                    $user->empresa_id,
                    $user->id,
                    $request
                );

            return response()->json([
                'success' => false,
                'error' =>
                    'usuario_inactivo',
                'message' =>
                    'El usuario está inactivo.',
                'errors' => [
                    'identificador' => [
                        'El usuario está inactivo.',
                    ],
                ],
            ], 403);
        }

        /*
         * Obtener empresa.
         *
         * La empresa es ahora la propietaria
         * de la licencia.
         */
        $empresa = $user->empresa;

        if (!$empresa) {
            app(AuditoriaService::class)
                ->registrarSistema(
                    'login.fallido',
                    'users',
                    $user->id,
                    null,
                    [
                        'motivo' =>
                            'empresa_no_asignada',
                        'identificador' =>
                            $identificador,
                    ],
                    $user->empresa_id,
                    $user->id,
                    $request
                );

            return response()->json([
                'success' => false,
                'error' =>
                    'empresa_no_asignada',
                'message' =>
                    'El usuario no tiene una empresa asignada.',
                'errors' => [
                    'identificador' => [
                        'El usuario no tiene una empresa asignada.',
                    ],
                ],
            ], 403);
        }

        /*
         * Empresa inactiva.
         */
        if (!$this->isCompanyActive($empresa)) {
            app(AuditoriaService::class)
                ->registrarSistema(
                    'login.fallido',
                    'users',
                    $user->id,
                    null,
                    [
                        'motivo' =>
                            'empresa_inactiva',
                        'identificador' =>
                            $identificador,
                    ],
                    $empresa->id,
                    $user->id,
                    $request
                );

            return response()->json([
                'success' => false,
                'error' =>
                    'empresa_inactiva',
                'message' =>
                    'La empresa está inactiva.',
                'errors' => [
                    'identificador' => [
                        'La empresa está inactiva.',
                    ],
                ],
            ], 403);
        }

        /*
         * Contraseña.
         */
        if (
            !Hash::check(
                $password,
                (string) $user->password
            )
        ) {
            app(AuditoriaService::class)
                ->registrarSistema(
                    'login.fallido',
                    'users',
                    $user->id,
                    null,
                    [
                        'motivo' =>
                            'password_incorrecta',
                        'identificador' =>
                            $identificador,
                    ],
                    $empresa->id,
                    $user->id,
                    $request
                );

            throw ValidationException::withMessages([
                'password' => [
                    'Número de usuario o contraseña incorrectos.',
                ],
            ]);
        }

        /*
         * ==========================================================
         * LICENCIA DE EMPRESA
         * ==========================================================
         *
         * NO se bloquea el login por licencia vencida.
         *
         * Esto es intencional:
         *
         * Flutter necesita poder iniciar sesión para posteriormente
         * consultar /licencia/estado.
         *
         * Las operaciones del POS serán protegidas por CheckLicense.
         */

        $licencia = $this->buildLicenseData($empresa);

        /*
         * Revocar tokens anteriores.
         */
        $user->tokens()->delete();

        /*
         * Crear nuevo token Sanctum.
         */
        $token = $user
            ->createToken('pos-mobile')
            ->plainTextToken;

        /*
         * Datos seguros del usuario.
         */
        $userData = $user->only([
            'id',
            'name',
            'email',
            'telefono',
            'numero_usuario',
            'rol',
            'activo',
        ]);

        /*
         * Datos de empresa.
         */
        $empresaData = [
            'id' =>
                $empresa->id,

            'nombre' =>
                $empresa->nombre,

            'logo_url' =>
                $empresa->logo_url,

            'colores' =>
                $this->decodeJson(
                    $empresa->colores
                ),

            'configuracion' =>
                $this->decodeJson(
                    $empresa->configuracion
                ),

            'activo' =>
                (bool) $empresa->activo,
        ];

        /*
         * Auditoría.
         */
        app(AuditoriaService::class)->registrar(
            $request,
            'login.exitoso',
            'users',
            $user->id,
            null,
            [
                'identificador' =>
                    $identificador,

                'tipo_identificador' =>
                    $esEmail
                        ? 'email'
                        : 'numero_usuario',

                'empresa_id' =>
                    $empresa->id,

                'licencia_tipo' =>
                    $empresa->licencia_tipo,
            ],
            $empresa->id,
            $user->id
        );

        return response()->json([
            'success' => true,

            'access_token' =>
                $token,

            'token_type' =>
                'Bearer',

            'user' =>
                $userData,

            'empresa' =>
                $empresaData,

            /*
             * Se conserva exactamente la estructura
             * "licencia" para no romper Flutter.
             */
            'licencia' =>
                $licencia,
        ], 200);
    }

    /**
     * Cerrar sesión.
     */
    public function logout(Request $request)
    {
        $user = $request->user();

        if ($user) {
            app(AuditoriaService::class)->registrar(
                $request,
                'logout',
                'users',
                $user->id,
                null,
                [
                    'resultado' =>
                        'sesion_cerrada',
                ],
                $user->empresa_id,
                $user->id
            );

            $token = $user->currentAccessToken();

            if ($token) {
                $token->delete();
            }
        }

        return response()->json([
            'success' => true,
            'message' =>
                'Sesión cerrada correctamente.',
        ], 200);
    }

    /**
     * Obtener usuario autenticado.
     *
     * IMPORTANTE:
     * La respuesta incluye la empresa y licencia.
     */
    public function user(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Usuario no autenticado.',
            ], 401);
        }

        $empresa = $user->empresa;

        app(AuditoriaService::class)->registrar(
            $request,
            'usuario.consultado',
            'users',
            $user->id,
            null,
            null,
            $user->empresa_id,
            $user->id
        );

        if (!$empresa) {
            return response()->json([
                'success' => false,
                'error' =>
                    'empresa_no_asignada',
                'message' =>
                    'El usuario no tiene una empresa asignada.',
            ], 403);
        }

        $userData = $user->only([
            'id',
            'name',
            'email',
            'telefono',
            'numero_usuario',
            'rol',
            'activo',
        ]);

        return response()->json([
            'success' => true,

            'user' =>
                $userData,

            'empresa' => [
                'id' =>
                    $empresa->id,

                'nombre' =>
                    $empresa->nombre,

                'logo_url' =>
                    $empresa->logo_url,

                'colores' =>
                    $this->decodeJson(
                        $empresa->colores
                    ),

                'configuracion' =>
                    $this->decodeJson(
                        $empresa->configuracion
                    ),

                'activo' =>
                    (bool) $empresa->activo,
            ],

            'licencia' =>
                $this->buildLicenseData($empresa),
        ], 200);
    }

    /**
     * Actualizar perfil.
     */
    public function updateProfile(
        Request $request
    ) {
        $data = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:120',
            ],
            'telefono' => [
                'nullable',
                'string',
                'max:30',
            ],
        ]);

        if (array_key_exists('name', $data)) {
            $data['name'] = trim(
                (string) $data['name']
            );

            if ($data['name'] === '') {
                throw ValidationException::withMessages([
                    'name' => [
                        'El nombre no puede estar vacío.',
                    ],
                ]);
            }
        }

        if (array_key_exists('telefono', $data)) {
            $data['telefono'] = trim(
                (string) $data['telefono']
            );

            if ($data['telefono'] === '') {
                $data['telefono'] = null;
            }
        }

        $user = $request->user();

        $datosAntes = $user->only([
            'id',
            'name',
            'email',
            'telefono',
            'numero_usuario',
            'rol',
            'activo',
        ]);

        $user->update($data);

        $freshUser = $user->fresh();

        app(AuditoriaService::class)->registrar(
            $request,
            'perfil.actualizado',
            'users',
            $user->id,
            $datosAntes,
            $freshUser->only([
                'id',
                'name',
                'email',
                'telefono',
                'numero_usuario',
                'rol',
                'activo',
            ]),
            $user->empresa_id,
            $user->id
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Perfil actualizado correctamente.',
            'user' =>
                $freshUser->only([
                    'id',
                    'name',
                    'email',
                    'telefono',
                    'numero_usuario',
                    'rol',
                    'activo',
                ]),
        ], 200);
    }

    /**
     * Cambiar contraseña.
     */
    public function changePassword(
        Request $request
    ) {
        $data = $request->validate([
            'password_actual' => [
                'required',
                'string',
            ],
            'password_nueva' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'confirmed',
            ],
        ]);

        $user = $request->user();

        if (
            !Hash::check(
                $data['password_actual'],
                (string) $user->password
            )
        ) {
            app(AuditoriaService::class)->registrar(
                $request,
                'password.cambio_fallido',
                'users',
                $user->id,
                null,
                [
                    'motivo' =>
                        'password_actual_incorrecta',
                ],
                $user->empresa_id,
                $user->id
            );

            throw ValidationException::withMessages([
                'password_actual' => [
                    'La contraseña actual es incorrecta.',
                ],
            ]);
        }

        if (
            Hash::check(
                $data['password_nueva'],
                (string) $user->password
            )
        ) {
            throw ValidationException::withMessages([
                'password_nueva' => [
                    'La nueva contraseña debe ser diferente de la actual.',
                ],
            ]);
        }

        $user->forceFill([
            'password' =>
                Hash::make(
                    $data['password_nueva']
                ),
        ])->save();

        $user->tokens()->delete();

        app(AuditoriaService::class)->registrar(
            $request,
            'password.cambiada',
            'users',
            $user->id,
            null,
            [
                'resultado' =>
                    'correcto',
            ],
            $user->empresa_id,
            $user->id
        );

        return response()->json([
            'success' => true,
            'message' =>
                'Contraseña actualizada correctamente.',
        ], 200);
    }

    /**
     * Solicitar recuperación de contraseña.
     */
    public function forgotPassword(
        Request $request
    ) {
        $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
            ],
        ]);

        $email = strtolower(
            trim(
                (string) $request->input('email')
            )
        );

        Password::sendResetLink([
            'email' => $email,
        ]);

        app(AuditoriaService::class)
            ->registrarSistema(
                'password.recuperacion.solicitada',
                'users',
                null,
                null,
                [
                    'email' => $email,
                ],
                null,
                null,
                $request
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Si el correo existe, se enviaron instrucciones de recuperación.',
        ], 200);
    }

    /**
     * Restablecer contraseña.
     */
    public function resetPassword(
        Request $request
    ) {
        $data = $request->validate([
            'token' => [
                'required',
                'string',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'confirmed',
            ],
        ]);

        $email = strtolower(
            trim(
                (string) $data['email']
            )
        );

        $data['email'] = $email;

        $userRestablecido = null;

        $status = Password::reset(
            $data,
            function (
                User $user,
                string $password
            ) use (
                &$userRestablecido
            ) {
                $userRestablecido = $user;

                $user->forceFill([
                    'password' =>
                        Hash::make($password),
                ])->save();

                $user->tokens()->delete();
            }
        );

        if (
            $status !==
            Password::PASSWORD_RESET
        ) {
            app(AuditoriaService::class)
                ->registrarSistema(
                    'password.reset_fallido',
                    'users',
                    null,
                    null,
                    [
                        'email' =>
                            $email,
                        'motivo' =>
                            __($status),
                    ],
                    null,
                    null,
                    $request
                );

            throw ValidationException::withMessages([
                'email' => [
                    __($status),
                ],
            ]);
        }

        app(AuditoriaService::class)
            ->registrarUsuario(
                $userRestablecido,
                'password.restablecida',
                'users',
                $userRestablecido?->id,
                null,
                [
                    'resultado' =>
                        'correcto',
                ],
                $request
            );

        return response()->json([
            'success' => true,
            'message' =>
                'Contraseña restablecida correctamente.',
        ], 200);
    }

    /**
     * Permisos del usuario.
     */
    public function permissions(
        Request $request
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Usuario no autenticado.',
            ], 401);
        }

        $role = (string) $user->rol;

        $admin = in_array(
            $role,
            [
                'admin',
                'superadmin',
            ],
            true
        );

        $cajero = in_array(
            $role,
            [
                'cajero',
                'admin',
                'superadmin',
            ],
            true
        );

        app(AuditoriaService::class)->registrar(
            $request,
            'permisos.consultados',
            'users',
            $user->id,
            null,
            [
                'rol' => $role,
            ],
            $user->empresa_id,
            $user->id
        );

        return response()->json([
            'success' => true,
            'role' => $role,
            'capabilities' => [
                'pos.sell' => true,
                'sales.read' => true,
                'catalog.read' => true,

                'catalog.write' =>
                    $admin,

                'reports.read' =>
                    $admin,

                'reports.share' =>
                    $admin,

                'users.manage' =>
                    $role === 'superadmin',

                'companies.manage' =>
                    $role === 'superadmin',

                'settings.manage' =>
                    $admin,

                'cash.open' =>
                    $cajero,

                'cash.close' =>
                    $cajero,

                'tables.manage' =>
                    $cajero,
            ],
        ], 200);
    }

    /**
     * Construir información de licencia.
     *
     * LA FUENTE SIEMPRE ES empresas.
     */
    private function buildLicenseData($empresa): array
    {
        $tipo = $empresa->licencia_tipo;

        $inicio = $empresa->licencia_fecha_inicio;
        $fin = $empresa->licencia_fecha_fin;

        $permanente = $tipo === 'permanente';

        $vigente = false;
        $enGracia = false;
        $diasRestantes = null;
        $diasVencidos = 0;

        if ($empresa->licencia_activa) {
            if ($permanente) {
                $vigente = true;
                $diasRestantes = null;
            } elseif ($inicio && $fin) {
                $ahora = now();

                if ($ahora->lt($inicio)) {
                    $vigente = false;
                    $diasRestantes = $ahora->diffInDays(
                        $fin,
                        false
                    );
                } elseif ($ahora->lte($fin)) {
                    $vigente = true;
                    $diasRestantes = $ahora->diffInDays(
                        $fin,
                        false
                    );
                } else {
                    $diasVencidos = $fin->diffInDays(
                        $ahora
                    );

                    $enGracia = $diasVencidos <= 3;

                    if ($enGracia) {
                        $vigente = false;
                    }
                }
            }
        }

        return [
            /*
             * Campos originales para compatibilidad
             * con Flutter.
             */
            'tipo' =>
                $tipo,

            'fecha_inicio' =>
                $inicio,

            'fecha_fin' =>
                $fin,

            /*
             * Estado ampliado.
             */
            'activa' =>
                $vigente || $enGracia,

            'vigente' =>
                $vigente,

            'en_gracia' =>
                $enGracia,

            'permanente' =>
                $permanente,

            'dias_restantes' =>
                $diasRestantes,

            'dias_vencidos' =>
                $diasVencidos,

            'licencia_activa' =>
                (bool) $empresa->licencia_activa,

            'empresa_id' =>
                $empresa->id,
        ];
    }

    /**
     * Determinar si el usuario está activo.
     */
    private function isUserActive(
        User $user
    ): bool {
        $value = $user->activo;

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(
                strtolower(
                    trim($value)
                ),
                [
                    '1',
                    'true',
                    'si',
                    'yes',
                ],
                true
            );
        }

        return false;
    }

    /**
     * Determinar si la empresa está activa.
     */
    private function isCompanyActive(
        mixed $empresa
    ): bool {
        if (!$empresa) {
            return false;
        }

        $value = $empresa->activo;

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            return in_array(
                strtolower(
                    trim($value)
                ),
                [
                    '1',
                    'true',
                    'si',
                    'yes',
                ],
                true
            );
        }

        return false;
    }

    /**
     * Decodificar JSON de empresa.
     */
    private function decodeJson(
        mixed $value
    ): mixed {
        if (!is_string($value)) {
            return $value;
        }

        $decoded = json_decode(
            $value,
            true
        );

        return json_last_error() === JSON_ERROR_NONE
            ? $decoded
            : $value;
    }
}