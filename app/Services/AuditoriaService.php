<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\LogAuditoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditoriaService
{
    /**
     * Registra una acción realizada desde una petición HTTP.
     *
     * $empresaId representa la empresa AFECTADA por la operación.
     * $usuarioId representa al ACTOR que realizó la operación.
     *
     * El superadmin también queda registrado.
     */
    public function registrar(
        Request $request,
        string $accion,
        ?string $tabla = null,
        ?int $registroId = null,
        ?array $datosAntes = null,
        ?array $datosDespues = null,
        ?int $empresaId = null,
        ?int $usuarioId = null
    ): ?LogAuditoria {
        try {
            $usuarioAutenticado = $request->user();

            /*
             * El actor real siempre es el usuario autenticado.
             * No permitimos que el controlador falsifique usuario_id.
             */
            if ($usuarioAutenticado) {
                $usuario = $usuarioAutenticado;
                $usuarioId = (int) $usuario->id;
            } elseif ($usuarioId !== null) {
                $usuario = User::find($usuarioId);
            } else {
                $usuario = null;
            }

            /*
             * Si no se especificó empresa afectada, usamos la empresa
             * del actor cuando exista.
             */
            if ($empresaId === null && $usuario) {
                $empresaId = $usuario->empresa_id !== null
                    ? (int) $usuario->empresa_id
                    : null;
            }

            /*
             * Usuarios normales únicamente pueden registrar acciones
             * dentro de su propia empresa.
             *
             * Superadmin puede actuar sobre cualquier empresa.
             */
            if (
                $usuario &&
                !$this->esSuperAdmin($usuario) &&
                $empresaId !== null &&
                (int) $usuario->empresa_id !== (int) $empresaId
            ) {
                Log::warning('Intento de auditoría fuera de empresa.', [
                    'usuario_id' => $usuario->id,
                    'empresa_usuario' => $usuario->empresa_id,
                    'empresa_solicitada' => $empresaId,
                    'accion' => $accion,
                    'tabla' => $tabla,
                    'registro_id' => $registroId,
                ]);

                return null;
            }

            /*
             * Si existe empresa afectada, debe existir realmente.
             */
            if ($empresaId !== null) {
                if (!Empresa::query()->whereKey($empresaId)->exists()) {
                    Log::warning(
                        'Empresa inexistente al registrar auditoría.',
                        [
                            'empresa_id' => $empresaId,
                            'usuario_id' => $usuarioId,
                            'accion' => $accion,
                        ]
                    );

                    return null;
                }
            }

            $datosAntes = $this->sanitizarDatos($datosAntes);
            $datosDespues = $this->sanitizarDatos($datosDespues);

            return LogAuditoria::create([
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'accion' => trim($accion),
                'tabla' => $tabla ? trim($tabla) : 'sistema',
                'registro_id' => $registroId,
                'datos_antes' => $datosAntes,
                'datos_despues' => $datosDespues,
                'ip' => $this->obtenerIp($request),
                'user_agent' => $this->obtenerUserAgent($request),
            ]);
        } catch (Throwable $e) {
            Log::error('Error al registrar auditoría.', [
                'accion' => $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Registra una acción utilizando directamente un usuario.
     *
     * $usuario es el ACTOR.
     *
     * $empresaId representa la empresa afectada y puede ser diferente
     * a la empresa del actor únicamente cuando el actor es superadmin.
     */
    public function registrarUsuario(
        ?User $usuario,
        string $accion,
        ?string $tabla = null,
        ?int $registroId = null,
        ?array $datosAntes = null,
        ?array $datosDespues = null,
        ?Request $request = null,
        ?int $empresaId = null
    ): ?LogAuditoria {
        try {
            if ($empresaId === null && $usuario) {
                $empresaId = $usuario->empresa_id !== null
                    ? (int) $usuario->empresa_id
                    : null;
            }

            if (
                $usuario &&
                !$this->esSuperAdmin($usuario) &&
                $empresaId !== null &&
                (int) $usuario->empresa_id !== (int) $empresaId
            ) {
                Log::warning(
                    'Intento de registrar auditoría fuera de empresa.',
                    [
                        'usuario_id' => $usuario->id,
                        'empresa_usuario' => $usuario->empresa_id,
                        'empresa_solicitada' => $empresaId,
                        'accion' => $accion,
                    ]
                );

                return null;
            }

            if ($empresaId !== null) {
                if (!Empresa::query()->whereKey($empresaId)->exists()) {
                    return null;
                }
            }

            return LogAuditoria::create([
                'empresa_id' => $empresaId,
                'usuario_id' => $usuario?->id,
                'accion' => trim($accion),
                'tabla' => $tabla ? trim($tabla) : 'sistema',
                'registro_id' => $registroId,
                'datos_antes' => $this->sanitizarDatos($datosAntes),
                'datos_despues' => $this->sanitizarDatos($datosDespues),
                'ip' => $request
                    ? $this->obtenerIp($request)
                    : null,
                'user_agent' => $request
                    ? $this->obtenerUserAgent($request)
                    : null,
            ]);
        } catch (Throwable $e) {
            Log::error('Error al registrar auditoría por usuario.', [
                'accion' => $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'usuario_id' => $usuario?->id,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Registra una acción del sistema.
     *
     * Puede utilizarse cuando no existe usuario autenticado.
     */
    public function registrarSistema(
        string $accion,
        ?string $tabla = null,
        ?int $registroId = null,
        ?array $datosAntes = null,
        ?array $datosDespues = null,
        ?int $empresaId = null,
        ?int $usuarioId = null,
        ?Request $request = null
    ): ?LogAuditoria {
        try {
            $usuario = null;

            if ($usuarioId !== null) {
                $usuario = User::find($usuarioId);

                if (!$usuario) {
                    Log::warning(
                        'Usuario inexistente al registrar auditoría del sistema.',
                        [
                            'usuario_id' => $usuarioId,
                            'empresa_id' => $empresaId,
                            'accion' => $accion,
                        ]
                    );

                    return null;
                }

                if ($empresaId === null) {
                    $empresaId = $usuario->empresa_id !== null
                        ? (int) $usuario->empresa_id
                        : null;
                }

                if (
                    !$this->esSuperAdmin($usuario) &&
                    $empresaId !== null &&
                    (int) $usuario->empresa_id !== (int) $empresaId
                ) {
                    Log::warning(
                        'Auditoría del sistema fuera de empresa.',
                        [
                            'usuario_id' => $usuario->id,
                            'empresa_usuario' => $usuario->empresa_id,
                            'empresa_solicitada' => $empresaId,
                            'accion' => $accion,
                        ]
                    );

                    return null;
                }
            }

            if ($empresaId !== null) {
                if (!Empresa::query()->whereKey($empresaId)->exists()) {
                    return null;
                }
            }

            return LogAuditoria::create([
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'accion' => trim($accion),
                'tabla' => $tabla ? trim($tabla) : 'sistema',
                'registro_id' => $registroId,
                'datos_antes' => $this->sanitizarDatos($datosAntes),
                'datos_despues' => $this->sanitizarDatos($datosDespues),
                'ip' => $request
                    ? $this->obtenerIp($request)
                    : null,
                'user_agent' => $request
                    ? $this->obtenerUserAgent($request)
                    : null,
            ]);
        } catch (Throwable $e) {
            Log::error('Error al registrar auditoría del sistema.', [
                'accion' => $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Determina si un usuario es superadmin.
     */
    public function esSuperAdmin(User $usuario): bool
    {
        return strtolower(trim((string) $usuario->rol)) === 'superadmin';
    }

    /**
     * Determina si un usuario tiene permisos administrativos.
     */
    public function esAdministrador(User $usuario): bool
    {
        return in_array(
            strtolower(trim((string) $usuario->rol)),
            ['admin', 'superadmin'],
            true
        );
    }

    /**
     * Obtiene IP.
     */
    private function obtenerIp(Request $request): ?string
    {
        try {
            return $request->ip();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Obtiene User-Agent.
     */
    private function obtenerUserAgent(Request $request): ?string
    {
        try {
            $userAgent = $request->userAgent();

            if ($userAgent === null) {
                return null;
            }

            return mb_substr($userAgent, 0, 255);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Sanitiza datos sensibles.
     */
    private function sanitizarDatos(?array $datos): ?array
    {
        if ($datos === null) {
            return null;
        }

        $camposSensibles = [
            'password',
            'password_confirmation',
            'current_password',
            'password_actual',
            'password_nueva',
            'new_password',
            'old_password',

            'token',
            'access_token',
            'refresh_token',
            'remember_token',
            'api_token',
            'auth_token',
            'authentication_token',
            'authorization',
            'bearer_token',

            'secret',
            'client_secret',
            'client_secret_key',
            'private_key',
            'private_key_id',

            'api_key',
            'apikey',
            'x_api_key',

            'cookie',
            'set_cookie',
            'session',
            'session_id',

            'credit_card',
            'card_number',
            'cvv',
            'cvc',
        ];

        return $this->sanitizarRecursivo(
            $datos,
            $camposSensibles
        );
    }

    /**
     * Sanitización recursiva.
     */
    private function sanitizarRecursivo(
        array $datos,
        array $camposSensibles
    ): array {
        $resultado = [];

        foreach ($datos as $clave => $valor) {
            $claveNormalizada = strtolower(trim((string) $clave));

            $claveNormalizada = str_replace(
                ['-', ' ', '.'],
                '_',
                $claveNormalizada
            );

            if (in_array(
                $claveNormalizada,
                $camposSensibles,
                true
            )) {
                $resultado[$clave] = '[OCULTO]';
                continue;
            }

            if (is_array($valor)) {
                $resultado[$clave] = $this->sanitizarRecursivo(
                    $valor,
                    $camposSensibles
                );

                continue;
            }

            $resultado[$clave] = $valor;
        }

        return $resultado;
    }
}