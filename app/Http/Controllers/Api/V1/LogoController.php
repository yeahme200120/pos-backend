<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Throwable;

class LogoController extends Controller
{
    protected AuditoriaService $auditoria;

    public function __construct(AuditoriaService $auditoria)
    {
        $this->auditoria = $auditoria;
    }

    /**
     * Registrar auditoría sin permitir que un fallo del servicio
     * de auditoría afecte la operación principal.
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

            $this->auditoria->registrar(
                $request,
                $accion,
                'empresas',
                $registroId,
                $datosAntes,
                $datosDespues,
                $usuario?->empresa_id,
                $usuario?->id
            );
        } catch (Throwable $e) {
            Log::warning('No fue posible registrar auditoría del logo', [
                'accion' => $accion,
                'registro_id' => $registroId,
                'usuario_id' => $request->user()?->id,
                'empresa_id' => $request->user()?->empresa_id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtener la URL del logo de la empresa.
     */
    public function show(Request $request)
    {
        $usuario = $request->user();

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresa = $usuario->empresa;

        if (!$empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

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
    }

    /**
     * Subir y procesar el logo de la empresa.
     */
    public function upload(Request $request)
    {
        $usuario = $request->user();

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresa = $usuario->empresa;

        if (!$empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        $validated = $request->validate([
            'logo' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,png,jpg,gif,svg',
                'max:2048',
            ],
        ]);

        $datosAntes = $empresa->toArray();

        $file = $validated['logo'];

        $imagenNueva = null;

        try {
            $extension = strtolower(
                $file->getClientOriginalExtension()
            );

            /*
             * SVG no puede procesarse correctamente con el driver GD
             * de Intervention Image.
             *
             * En ese caso se almacena directamente.
             */
            if ($extension === 'svg') {
                $imagenNueva = $file->store(
                    'logos/' . $empresa->id,
                    'public'
                );
            } else {
                /*
                 * Procesamiento de imágenes rasterizadas.
                 */
                $manager = new ImageManager(
                    new Driver()
                );

                $image = $manager->read($file);

                /*
                 * Redimensionar a máximo 300x200 manteniendo
                 * proporción.
                 */
                $image->scale(
                    width: 300,
                    height: 200
                );

                $imagenNueva = 'logos/'
                    . $empresa->id
                    . '/'
                    . uniqid('', true)
                    . '.'
                    . $extension;

                Storage::disk('public')->put(
                    $imagenNueva,
                    (string) $image->encode()
                );
            }

            /*
             * Actualizar primero la base de datos.
             */
            $logoAnterior = $empresa->logo;

            $empresa->update([
                'logo' => $imagenNueva,
            ]);

            $empresa->refresh();

            /*
             * Eliminar el logo anterior únicamente después
             * de actualizar correctamente la empresa.
             */
            if (
                $logoAnterior
                && $logoAnterior !== $imagenNueva
            ) {
                try {
                    Storage::disk('public')->delete(
                        $logoAnterior
                    );
                } catch (Throwable $e) {
                    Log::warning(
                        'No fue posible eliminar el logo anterior',
                        [
                            'empresa_id' => $empresa->id,
                            'logo_anterior' => $logoAnterior,
                            'error' => $e->getMessage(),
                        ]
                    );
                }
            }

            /*
             * La auditoría nunca debe invalidar una operación
             * ya confirmada.
             */
            $this->registrarAuditoria(
                $request,
                'logo.actualizado',
                (int) $empresa->id,
                $datosAntes,
                $empresa->toArray()
            );

            return response()->json([
                'success' => true,
                'message' => 'Logo actualizado correctamente.',
                'logo_url' => $empresa->logo_url,
            ]);
        } catch (Throwable $e) {
            /*
             * Si el archivo nuevo fue creado pero la operación
             * falló, eliminarlo para evitar archivos huérfanos.
             */
            if ($imagenNueva) {
                try {
                    Storage::disk('public')->delete(
                        $imagenNueva
                    );
                } catch (Throwable $deleteException) {
                    Log::warning(
                        'No fue posible eliminar logo temporal',
                        [
                            'empresa_id' => $empresa->id,
                            'logo' => $imagenNueva,
                            'error' => $deleteException->getMessage(),
                        ]
                    );
                }
            }

            Log::error('Error al actualizar logo de empresa', [
                'usuario_id' => $usuario->id,
                'empresa_id' => $empresa->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible actualizar el logo.',
            ], 500);
        }
    }

    /**
     * Eliminar el logo de la empresa.
     */
    public function destroy(Request $request)
    {
        $usuario = $request->user();

        if (!$usuario) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresa = $usuario->empresa;

        if (!$empresa) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        $datosAntes = $empresa->toArray();
        $logoAnterior = $empresa->logo;

        try {
            /*
             * Actualizar la empresa aunque no exista archivo físico.
             */
            $empresa->update([
                'logo' => null,
            ]);

            $empresa->refresh();

            /*
             * Eliminar archivo físico después de actualizar BD.
             */
            if ($logoAnterior) {
                try {
                    Storage::disk('public')->delete(
                        $logoAnterior
                    );
                } catch (Throwable $e) {
                    Log::warning(
                        'No fue posible eliminar archivo físico del logo',
                        [
                            'empresa_id' => $empresa->id,
                            'logo' => $logoAnterior,
                            'error' => $e->getMessage(),
                        ]
                    );
                }
            }

            $this->registrarAuditoria(
                $request,
                'logo.eliminado',
                (int) $empresa->id,
                $datosAntes,
                $empresa->toArray()
            );

            return response()->json([
                'success' => true,
                'message' => 'Logo eliminado correctamente.',
            ]);
        } catch (Throwable $e) {
            Log::error('Error al eliminar logo de empresa', [
                'usuario_id' => $usuario->id,
                'empresa_id' => $empresa->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible eliminar el logo.',
            ], 500);
        }
    }
}