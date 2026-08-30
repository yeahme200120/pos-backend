<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Cliente;
use App\Models\Impuesto;
use App\Models\FormaPago;
use App\Models\UnidadMedida;
use App\Models\Categoria;
use App\Models\Promocion;
use App\Models\Cupon;

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

        if (!$fechaSync) {
            $response = [
                'productos' => Producto::where('empresa_id', $empresaId)->get(),
                'clientes' => Cliente::where('empresa_id', $empresaId)->get(),
                'impuestos' => Impuesto::where('empresa_id', $empresaId)->get(),
                'formas_pago' => FormaPago::where('empresa_id', $empresaId)->get(),
                'unidades_medida' => UnidadMedida::where('empresa_id', $empresaId)->get(),
                'categorias' => Categoria::where('empresa_id', $empresaId)->get(),
                'promociones' => Promocion::where('empresa_id', $empresaId)->get(),
                'cupones' => Cupon::where('empresa_id', $empresaId)->get(),
                'versiones' => [
                    'productos' => Producto::where('empresa_id', $empresaId)->max('updated_at'),
                    'clientes' => Cliente::where('empresa_id', $empresaId)->max('updated_at'),
                    'impuestos' => Impuesto::where('empresa_id', $empresaId)->max('updated_at'),
                    'formas_pago' => FormaPago::where('empresa_id', $empresaId)->max('updated_at'),
                    'unidades_medida' => UnidadMedida::where('empresa_id', $empresaId)->max('updated_at'),
                    'categorias' => Categoria::where('empresa_id', $empresaId)->max('updated_at'),
                    'promociones' => Promocion::where('empresa_id', $empresaId)->max('updated_at'),
                    'cupones' => Cupon::where('empresa_id', $empresaId)->max('updated_at'),
                ],
                'tombstones' => $this->buildTombstones($empresaId, $fechaSync),
            ];
        } else {
            $response = [
                'productos' => Producto::where('empresa_id', $empresaId)->where('updated_at', '>', $fechaSync)->get(),
                'clientes' => Cliente::where('empresa_id', $empresaId)->where('updated_at', '>', $fechaSync)->get(),
                'impuestos' => Impuesto::where('empresa_id', $empresaId)->where('updated_at', '>', $fechaSync)->get(),
                'formas_pago' => FormaPago::where('empresa_id', $empresaId)->where('updated_at', '>', $fechaSync)->get(),
                'unidades_medida' => UnidadMedida::where('empresa_id', $empresaId)->where('updated_at', '>', $fechaSync)->get(),
                'categorias' => Categoria::where('empresa_id', $empresaId)->where('updated_at', '>', $fechaSync)->get(),
                'promociones' => Promocion::where('empresa_id', $empresaId)->where('updated_at', '>', $fechaSync)->get(),
                'cupones' => Cupon::where('empresa_id', $empresaId)->where('updated_at', '>', $fechaSync)->get(),
                'tombstones' => $this->buildTombstones($empresaId, $fechaSync),
                'productos_eliminados' => $this->buildTombstones($empresaId, $fechaSync)['productos'],
                'clientes_eliminados' => $this->buildTombstones($empresaId, $fechaSync)['clientes'],
                'unidades_medida_eliminadas' => $this->buildTombstones($empresaId, $fechaSync)['unidades_medida'],
                'categorias_eliminadas' => $this->buildTombstones($empresaId, $fechaSync)['categorias'],
                'promociones_eliminadas' => $this->buildTombstones($empresaId, $fechaSync)['promociones'],
                'cupones_eliminados' => $this->buildTombstones($empresaId, $fechaSync)['cupones'],
            ];
        }

        return response()->json($response);
    }

    private function buildTombstones(int $empresaId, ?string $fechaSync): array
    {
        $models = [
            'productos' => Producto::class,
            'clientes' => Cliente::class,
            'impuestos' => Impuesto::class,
            'formas_pago' => FormaPago::class,
            'unidades_medida' => UnidadMedida::class,
            'categorias' => Categoria::class,
            'promociones' => Promocion::class,
            'cupones' => Cupon::class,
        ];

        $tombstones = [];

        foreach ($models as $key => $modelClass) {
            if (!in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                $tombstones[$key] = [];
                continue;
            }

            $query = $modelClass::withTrashed()->where('empresa_id', $empresaId);

            if ($fechaSync) {
                $query->where('deleted_at', '>', $fechaSync);
            }

            $tombstones[$key] = $query->get(['id', 'deleted_at'])->map(function ($item) {
                return [
                    'id' => $item->id,
                    'deleted_at' => $item->deleted_at,
                ];
            })->values()->all();
        }

        return $tombstones;
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
