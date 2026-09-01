<?php

namespace App\Services;

use App\Models\LogAuditoria;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditoriaService
{
    /**
     * Registrar una acción en la auditoría.
     *
     * Las acciones realizadas por superadmin no se registran.
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
            $usuario = $request->user();

            if (!$usuario && $usuarioId) {
                $usuario = User::find($usuarioId);
            }

            // Superadmin queda excluido de la auditoría.
            if ($usuario && $this->esSuperAdmin($usuario)) {
                return null;
            }

            if ($empresaId === null && $usuario) {
                $empresaId = $usuario->empresa_id;
            }

            if ($usuarioId === null && $usuario) {
                $usuarioId = $usuario->id;
            }

            $datosAntes = $this->sanitizarDatos($datosAntes);
            $datosDespues = $this->sanitizarDatos($datosDespues);

            return LogAuditoria::create([
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'accion' => $accion,
                'tabla' => $tabla ?: 'sistema',
                'registro_id' => $registroId,
                'datos_antes' => $datosAntes,
                'datos_despues' => $datosDespues,
                'ip' => $this->obtenerIp($request),
                'user_agent' => $this->obtenerUserAgent($request),
            ]);
        } catch (Throwable $e) {
            // La auditoría nunca debe romper la operación principal.
            Log::error('Error al registrar auditoría', [
                'accion' => $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Registrar una acción utilizando directamente un usuario.
     */
    public function registrarUsuario(
        ?User $usuario,
        string $accion,
        ?string $tabla = null,
        ?int $registroId = null,
        ?array $datosAntes = null,
        ?array $datosDespues = null,
        ?Request $request = null
    ): ?LogAuditoria {
        try {
            if ($usuario && $this->esSuperAdmin($usuario)) {
                return null;
            }

            $datosAntes = $this->sanitizarDatos($datosAntes);
            $datosDespues = $this->sanitizarDatos($datosDespues);

            return LogAuditoria::create([
                'empresa_id' => $usuario?->empresa_id,
                'usuario_id' => $usuario?->id,
                'accion' => $accion,
                'tabla' => $tabla ?: 'sistema',
                'registro_id' => $registroId,
                'datos_antes' => $datosAntes,
                'datos_despues' => $datosDespues,
                'ip' => $request ? $this->obtenerIp($request) : null,
                'user_agent' => $request ? $this->obtenerUserAgent($request) : null,
            ]);
        } catch (Throwable $e) {
            Log::error('Error al registrar auditoría por usuario', [
                'accion' => $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'usuario_id' => $usuario?->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Registrar una acción del sistema.
     *
     * Puede utilizarse para acciones sin autenticación.
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
            if ($usuarioId !== null) {
                $usuario = User::find($usuarioId);

                if ($usuario && $this->esSuperAdmin($usuario)) {
                    return null;
                }
            }

            $datosAntes = $this->sanitizarDatos($datosAntes);
            $datosDespues = $this->sanitizarDatos($datosDespues);

            return LogAuditoria::create([
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'accion' => $accion,
                'tabla' => $tabla ?: 'sistema',
                'registro_id' => $registroId,
                'datos_antes' => $datosAntes,
                'datos_despues' => $datosDespues,
                'ip' => $request ? $this->obtenerIp($request) : null,
                'user_agent' => $request ? $this->obtenerUserAgent($request) : null,
            ]);
        } catch (Throwable $e) {
            Log::error('Error al registrar auditoría del sistema', [
                'accion' => $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Determinar si un usuario es superadmin.
     */
    private function esSuperAdmin(User $usuario): bool
    {
        return strtolower(trim((string) $usuario->rol)) === 'superadmin';
    }

    /**
     * Obtener IP.
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
     * Obtener User-Agent.
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
     * Sanitizar información sensible.
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
            'authorization',
            'bearer_token',
            'secret',
            'client_secret',
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

            if (in_array(
                $claveNormalizada,
                $camposSensibles,
                true
            )) {
                $resultado[$clave] = '[OCULTO]';
                continue;
            }

            if (is_array($valor)) {
                $resultado[$clave] =
                    $this->sanitizarRecursivo(
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