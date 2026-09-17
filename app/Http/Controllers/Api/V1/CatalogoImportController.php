<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CatalogoImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CatalogoImportController extends Controller
{
    public function __construct(
        private readonly CatalogoImportService $importService
    ) {}

    /**
     * Importar catálogo desde base64.
     *
     * Body:
     *   - usuario_id (int, requerido)
     *   - tipo_catalogo (string, requerido)
     *   - archivo_base64 (string, requerido)
     *   - nombre_archivo (string, opcional)
     */
    public function importarExcel(Request $request): JsonResponse
    {
        $auth = $this->ensureSuperAdmin($request);

        if ($auth) {
            return $auth;
        }

        $data = $request->validate([
            'usuario_id' => ['required', 'integer', 'exists:users,id'],
            'tipo_catalogo' => ['required', 'string', 'in:productos,clientes,categorias,impuestos,formas_pago,unidades_medida,promociones,cupones'],
            'archivo_base64' => ['required', 'string'],
            'nombre_archivo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $resultado = $this->importService->importarDesdeBase64(
                $request,
                (int) $data['usuario_id'],
                $data['tipo_catalogo'],
                $data['archivo_base64'],
                $data['nombre_archivo'] ?? 'importacion.xlsx'
            );

            return response()->json([
                'success' => true,
                'message' => 'Importación completada.',
                'data' => $resultado,
            ]);
        } catch (Throwable $e) {
            Log::error('Error importando catálogo.', [
                'usuario_id' => $data['usuario_id'],
                'tipo_catalogo' => $data['tipo_catalogo'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => 'IMPORT_ERROR',
            ], 422);
        }
    }

    private function ensureSuperAdmin(Request $request): ?JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No autenticado.',
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        if (!$user->isSuperAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes autorización.',
                'error' => 'SUPERADMIN_REQUIRED',
            ], 403);
        }

        return null;
    }
}