<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Cliente;
use App\Models\Impuesto;
use App\Models\FormaPago;
use App\Models\UnidadMedida;

class CatalogController extends Controller
{
    /**
     * Obtener todos los catálogos en una sola respuesta.
     */
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        $fechaSync = $request->input('desde', null);

        $response = [];

        // Si no hay fecha de sincronización, devolver todo
        if (!$fechaSync) {
            $response = [
                'productos' => Producto::where('empresa_id', $empresaId)->get(),
                'clientes' => Cliente::where('empresa_id', $empresaId)->get(),
                'impuestos' => Impuesto::where('empresa_id', $empresaId)->get(),
                'formas_pago' => FormaPago::where('empresa_id', $empresaId)->get(),
                'unidades_medida' => UnidadMedida::where('empresa_id', $empresaId)->get(),
                'versiones' => [
                    'productos' => Producto::where('empresa_id', $empresaId)->max('updated_at'),
                    'clientes' => Cliente::where('empresa_id', $empresaId)->max('updated_at'),
                    'impuestos' => Impuesto::where('empresa_id', $empresaId)->max('updated_at'),
                    'formas_pago' => FormaPago::where('empresa_id', $empresaId)->max('updated_at'),
                    'unidades_medida' => UnidadMedida::where('empresa_id', $empresaId)->max('updated_at'),
                ]
            ];
        } else {
            // Solo devolver cambios desde la fecha indicada
            $response = [
                'productos' => Producto::where('empresa_id', $empresaId)
                    ->where('updated_at', '>', $fechaSync)
                    ->get(),
                'clientes' => Cliente::where('empresa_id', $empresaId)
                    ->where('updated_at', '>', $fechaSync)
                    ->get(),
                'impuestos' => Impuesto::where('empresa_id', $empresaId)
                    ->where('updated_at', '>', $fechaSync)
                    ->get(),
                'formas_pago' => FormaPago::where('empresa_id', $empresaId)
                    ->where('updated_at', '>', $fechaSync)
                    ->get(),
                // Incluir también los eliminados (soft delete)
                'productos_eliminados' => Producto::where('empresa_id', $empresaId)
                    ->where('deleted_at', '>', $fechaSync)
                    ->withTrashed()
                    ->get(['id', 'deleted_at']),
                'clientes_eliminados' => Cliente::where('empresa_id', $empresaId)
                    ->where('deleted_at', '>', $fechaSync)
                    ->withTrashed()
                    ->get(['id', 'deleted_at']),
                'unidades_medida_eliminadas' => UnidadMedida::where('empresa_id', $empresaId)
                    ->where('deleted_at', '>', $fechaSync)
                    ->withTrashed()
                    ->get(['id', 'deleted_at']),
            ];
        }

        return response()->json($response);
    }
    /**
     * Obtener solo los productos (con paginación si se requiere).
     */
    public function productos(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        return response()->json(
            Producto::where('empresa_id', $empresaId)->paginate(50)
        );
    }
}
