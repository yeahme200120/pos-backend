<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Illuminate\Support\Str;
use Throwable;

class LogoController extends Controller
{
    protected AuditoriaService $auditoria;

    public function __construct(AuditoriaService $auditoria)
    {
        $this->auditoria = $auditoria;
    }

    /**
     * Registrar auditoría sin permitir que un fallo
     * del servicio de auditoría afecte la operación principal.
     *
     * Se conservan los 8 parámetros utilizados actualmente
     * por AuditoriaService en este controlador.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues
    ): void {
        try {
            $usuario = $request->user();

            $datosAuditoria = $datosDespues ?? [];

            /*
             * Estos valores siempre deben provenir del usuario
             * autenticado y no de datos externos.
             */
            $datosAuditoria['empresa_id'] = $usuario?->empresa_id;
            $datosAuditoria['usuario_id'] = $usuario?->id;

            $this->auditoria->registrar(
                $request,
                $accion,
                'empresas',
                $registroId,
                $datosAntes,
                $datosAuditoria,
                $usuario?->empresa_id,
                $usuario?->id
            );
        } catch (Throwable $e) {
            /*
             * La auditoría nunca debe romper la operación principal.
             */
            Log::warning(
                'No fue posible registrar auditoría del logo.',
                [
                    'accion' => $accion,
                    'registro_id' => $registroId,
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );
        }
    }

    /**
     * Registrar errores de auditoría.
     *
     * Este método también es tolerante a fallos.
     */
    private function registrarAuditoriaError(
        Request $request,
        string $accion,
        array $datos = []
    ): void {
        try {
            $usuario = $request->user();

            $datosAuditoria = array_merge(
                $datos,
                [
                    'empresa_id' => $usuario?->empresa_id,
                    'usuario_id' => $usuario?->id,
                ]
            );

            $this->auditoria->registrar(
                $request,
                $accion,
                'empresas',
                $usuario?->empresa_id
                    ? (int) $usuario->empresa_id
                    : null,
                null,
                $datosAuditoria,
                $usuario?->empresa_id,
                $usuario?->id
            );
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible registrar auditoría del error del logo.',
                [
                    'accion' => $accion,
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );
        }
    }

    /**
     * Obtener la empresa del usuario autenticado.
     *
     * Centralizar esta validación evita repetir lógica
     * y mantiene la separación por empresa.
     */
    private function obtenerEmpresa(Request $request)
    {
        $usuario = $request->user();

        if (!$usuario) {
            return [
                'usuario' => null,
                'empresa' => null,
                'response' => response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado.',
                    'error_code' => 'LOGO_UNAUTHENTICATED',
                ], 401),
            ];
        }

        /*
         * La relación empresa se obtiene directamente desde
         * el usuario autenticado.
         */
        $empresa = $usuario->empresa;

        if (!$empresa) {
            return [
                'usuario' => $usuario,
                'empresa' => null,
                'response' => response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asociada.',
                    'error_code' => 'LOGO_EMPRESA_NO_ASOCIADA',
                ], 403),
            ];
        }

        return [
            'usuario' => $usuario,
            'empresa' => $empresa,
            'response' => null,
        ];
    }

    /**
     * Obtener la URL del logo de la empresa.
     */
    public function show(Request $request)
    {
        try {
            $contexto = $this->obtenerEmpresa($request);

            if ($contexto['response']) {
                return $contexto['response'];
            }

            $usuario = $contexto['usuario'];
            $empresa = $contexto['empresa'];

            $logoUrl = $empresa->logo_url;

            $this->registrarAuditoria(
                $request,
                'logo.consultado',
                (int) $empresa->id,
                null,
                [
                    'logo_url' => $logoUrl,
                    'tiene_logo' => !empty($empresa->logo),
                ]
            );

            return response()->json([
                'success' => true,
                'logo_url' => $logoUrl,
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Error al consultar logo de empresa.',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'logo.consulta_error',
                [
                    'error_code' => 'LOGO_CONSULTA_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible consultar el logo de la empresa.',
                'error_code' => 'LOGO_CONSULTA_ERROR',
            ], 500);
        }
    }

    /**
     * Subir y procesar el logo de la empresa.
     */
    public function upload(Request $request)
    {
        $contexto = $this->obtenerEmpresa($request);

        if ($contexto['response']) {
            return $contexto['response'];
        }

        $usuario = $contexto['usuario'];
        $empresa = $contexto['empresa'];

        try {
            /*
             * La validación de Laravel utiliza internamente
             * consultas/operaciones seguras y no concatena
             * valores del usuario en SQL.
             */
            $validated = $request->validate([
                'logo' => [
                    'required',
                    'file',
                    'image',
                    'mimes:jpeg,png,jpg,gif,svg',
                    'max:2048',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaError(
                $request,
                'logo.validacion_fallida',
                [
                    'error_code' => 'LOGO_VALIDATION_ERROR',
                    'errores' => $e->errors(),
                ]
            );

            /*
             * Se conserva el comportamiento estándar de Laravel:
             * respuesta 422 de validación.
             */
            throw $e;
        }

        $datosAntes = $empresa->toArray();

        $file = $validated['logo'];

        $imagenNueva = null;

        /*
         * Permite distinguir:
         *
         * false = todavía no se confirmó la actualización de BD.
         * true  = la BD ya apunta al nuevo archivo.
         *
         * Esto evita eliminar accidentalmente el logo nuevo
         * si ocurre un error después del update().
         */
        $baseDatosActualizada = false;

        try {
            $extension = strtolower(
                $file->getClientOriginalExtension()
            );

            /*
             * SVG:
             *
             * GD no procesa SVG correctamente, por lo que se
             * conserva el comportamiento existente y se almacena
             * directamente.
             */
            if ($extension === 'svg') {
                $imagenNueva = $file->store(
                    'logos/' . $empresa->id,
                    'public'
                );

                if (!$imagenNueva) {
                    throw new \RuntimeException(
                        'El almacenamiento del archivo SVG no devolvió una ruta válida.'
                    );
                }
            } else {
                /*
                 * Procesamiento de imágenes rasterizadas.
                 */
                $manager = new ImageManager(
                    new Driver()
                );

                $image = $manager->read($file);

                /*
                 * Mantener proporción con máximo de 300x200.
                 */
                $image->scale(
                    width: 300,
                    height: 200
                );

                /*
                 * UUID en lugar de uniqid() para evitar colisiones
                 * y generar nombres más robustos.
                 */
                $imagenNueva = 'logos/'
                    . $empresa->id
                    . '/'
                    . Str::uuid()->toString()
                    . '.'
                    . $extension;

                $contenidoImagen = (string) $image->encode();

                if ($contenidoImagen === '') {
                    throw new \RuntimeException(
                        'No fue posible codificar la imagen procesada.'
                    );
                }

                $guardado = Storage::disk('public')->put(
                    $imagenNueva,
                    $contenidoImagen
                );

                if (!$guardado) {
                    throw new \RuntimeException(
                        'No fue posible almacenar la imagen procesada.'
                    );
                }
            }

            /*
             * Guardamos el logo anterior antes de modificar la BD.
             */
            $logoAnterior = $empresa->logo;

            /*
             * update() de Eloquent utiliza binding de parámetros.
             * No se construye SQL concatenando datos externos.
             */
            $empresa->update([
                'logo' => $imagenNueva,
            ]);

            $baseDatosActualizada = true;

            $empresa->refresh();

            /*
             * Eliminar el logo anterior únicamente después
             * de confirmar que la BD apunta al nuevo archivo.
             */
            if (
                $logoAnterior
                && $logoAnterior !== $imagenNueva
            ) {
                try {
                    $eliminado = Storage::disk('public')->delete(
                        $logoAnterior
                    );

                    if (!$eliminado) {
                        Log::warning(
                            'El logo anterior no pudo ser eliminado del almacenamiento.',
                            [
                                'empresa_id' => $empresa->id,
                                'logo_anterior' => $logoAnterior,
                                'logo_nuevo' => $imagenNueva,
                            ]
                        );
                    }
                } catch (Throwable $e) {
                    /*
                     * No revertimos la BD por un problema
                     * secundario del almacenamiento.
                     */
                    Log::warning(
                        'No fue posible eliminar el logo anterior.',
                        [
                            'empresa_id' => $empresa->id,
                            'logo_anterior' => $logoAnterior,
                            'logo_nuevo' => $imagenNueva,
                            'error' => $e->getMessage(),
                            'exception' => get_class($e),
                        ]
                    );
                }
            }

            /*
             * Auditoría posterior a la operación confirmada.
             */
            $this->registrarAuditoria(
                $request,
                'logo.actualizado',
                (int) $empresa->id,
                [
                    'logo' => $logoAnterior,
                    'logo_url' => $datosAntes['logo_url']
                        ?? null,
                    'tiene_logo' => !empty($logoAnterior),
                ],
                [
                    'logo' => $empresa->logo,
                    'logo_url' => $empresa->logo_url,
                    'tiene_logo' => !empty($empresa->logo),
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Logo actualizado correctamente.',
                'logo_url' => $empresa->logo_url,
            ]);
        } catch (Throwable $e) {
            /*
             * Si la BD todavía NO fue actualizada, podemos eliminar
             * con seguridad el archivo recién creado.
             */
            if (
                $imagenNueva
                && !$baseDatosActualizada
            ) {
                try {
                    $eliminado = Storage::disk('public')->delete(
                        $imagenNueva
                    );

                    if (!$eliminado) {
                        Log::warning(
                            'El logo temporal no pudo ser eliminado.',
                            [
                                'empresa_id' => $empresa->id,
                                'logo' => $imagenNueva,
                            ]
                        );
                    }
                } catch (Throwable $deleteException) {
                    Log::warning(
                        'No fue posible eliminar logo temporal.',
                        [
                            'empresa_id' => $empresa->id,
                            'logo' => $imagenNueva,
                            'error' => $deleteException->getMessage(),
                            'exception' => get_class($deleteException),
                        ]
                    );
                }
            }

            /*
             * Si la BD YA fue actualizada, NO eliminamos
             * imagenNueva porque la empresa ya apunta a ella.
             *
             * En ese escenario solamente registramos el problema.
             */
            if (
                $imagenNueva
                && $baseDatosActualizada
            ) {
                Log::critical(
                    'Error después de actualizar el logo en BD. El archivo nuevo se conserva para evitar inconsistencia.',
                    [
                        'usuario_id' => $usuario->id,
                        'empresa_id' => $empresa->id,
                        'logo_nuevo' => $imagenNueva,
                        'error' => $e->getMessage(),
                        'exception' => get_class($e),
                    ]
                );
            } else {
                Log::error(
                    'Error al actualizar logo de empresa.',
                    [
                        'usuario_id' => $usuario->id,
                        'empresa_id' => $empresa->id,
                        'logo_nuevo' => $imagenNueva,
                        'error' => $e->getMessage(),
                        'exception' => get_class($e),
                    ]
                );
            }

            $this->registrarAuditoriaError(
                $request,
                'logo.actualizacion_error',
                [
                    'error_code' => 'LOGO_UPDATE_ERROR',
                    'logo_nuevo' => $imagenNueva,
                    'base_datos_actualizada' => $baseDatosActualizada,
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible actualizar el logo.',
                'error_code' => 'LOGO_UPDATE_ERROR',
            ], 500);
        }
    }

    /**
     * Eliminar el logo de la empresa.
     */
    public function destroy(Request $request)
    {
        $contexto = $this->obtenerEmpresa($request);

        if ($contexto['response']) {
            return $contexto['response'];
        }

        $usuario = $contexto['usuario'];
        $empresa = $contexto['empresa'];

        $datosAntes = $empresa->toArray();
        $logoAnterior = $empresa->logo;

        $baseDatosActualizada = false;

        try {
            /*
             * Actualizar la empresa aunque no exista archivo físico.
             *
             * Eloquent utiliza parámetros enlazados.
             */
            $empresa->update([
                'logo' => null,
            ]);

            $baseDatosActualizada = true;

            $empresa->refresh();

            /*
             * El archivo físico se elimina después de actualizar
             * correctamente la BD.
             */
            if ($logoAnterior) {
                try {
                    $eliminado = Storage::disk('public')->delete(
                        $logoAnterior
                    );

                    if (!$eliminado) {
                        Log::warning(
                            'El archivo físico del logo no pudo ser eliminado.',
                            [
                                'empresa_id' => $empresa->id,
                                'logo' => $logoAnterior,
                            ]
                        );
                    }
                } catch (Throwable $e) {
                    /*
                     * La BD ya está correctamente actualizada.
                     * Un fallo del almacenamiento no debe convertir
                     * una eliminación lógica exitosa en error 500.
                     */
                    Log::warning(
                        'No fue posible eliminar archivo físico del logo.',
                        [
                            'empresa_id' => $empresa->id,
                            'logo' => $logoAnterior,
                            'error' => $e->getMessage(),
                            'exception' => get_class($e),
                        ]
                    );
                }
            }

            $this->registrarAuditoria(
                $request,
                'logo.eliminado',
                (int) $empresa->id,
                [
                    'logo' => $logoAnterior,
                    'logo_url' => $datosAntes['logo_url']
                        ?? null,
                    'tiene_logo' => !empty($logoAnterior),
                ],
                [
                    'logo' => null,
                    'logo_url' => $empresa->logo_url,
                    'tiene_logo' => false,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Logo eliminado correctamente.',
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Error al eliminar logo de empresa.',
                [
                    'usuario_id' => $usuario->id,
                    'empresa_id' => $empresa->id,
                    'logo_anterior' => $logoAnterior,
                    'base_datos_actualizada' => $baseDatosActualizada,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'logo.eliminacion_error',
                [
                    'error_code' => 'LOGO_DELETE_ERROR',
                    'logo_anterior' => $logoAnterior,
                    'base_datos_actualizada' => $baseDatosActualizada,
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible eliminar el logo.',
                'error_code' => 'LOGO_DELETE_ERROR',
            ], 500);
        }
    }
}
