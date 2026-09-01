<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\Cupon;
use App\Models\Empresa;
use App\Models\FormaPago;
use App\Models\Impuesto;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    /**
     * Obtener todos los catálogos.
     *
     * Si no se envía "desde":
     * devuelve una sincronización completa.
     *
     * Si se envía "desde":
     * devuelve solamente registros modificados desde esa fecha.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;

        if ($empresaId <= 0) {
            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 422);
        }

        $fechaSync = $request->input('desde');

        /*
         * ========================================================
         * EMPRESA
         * ========================================================
         */

        $empresa = Empresa::query()
            ->whereKey($empresaId)
            ->first();

        /*
         * ========================================================
         * CATÁLOGOS
         * ========================================================
         */

        $response = [
            'empresa' => $empresa,
            'productos' => $this->getCatalog(
                Producto::class,
                $empresaId,
                $fechaSync
            ),
            'clientes' => $this->getCatalog(
                Cliente::class,
                $empresaId,
                $fechaSync
            ),
            'impuestos' => $this->getCatalog(
                Impuesto::class,
                $empresaId,
                $fechaSync
            ),
            'formas_pago' => $this->getCatalog(
                FormaPago::class,
                $empresaId,
                $fechaSync
            ),
            'unidades_medida' => $this->getCatalog(
                UnidadMedida::class,
                $empresaId,
                $fechaSync
            ),
            'categorias' => $this->getCatalog(
                Categoria::class,
                $empresaId,
                $fechaSync
            ),
            'promociones' => $this->getCatalog(
                Promocion::class,
                $empresaId,
                $fechaSync
            ),
            'cupones' => $this->getCatalog(
                Cupon::class,
                $empresaId,
                $fechaSync
            ),

            /*
             * Las versiones se devuelven SIEMPRE.
             * Esto permite que Flutter actualice catalog_sync.
             */
            'versiones' => $this->getVersions(
                $empresaId
            ),

            /*
             * Tombstones.
             */
            'tombstones' => $this->buildTombstones(
                $empresaId,
                $fechaSync
            ),
        ];

        /*
         * ========================================================
         * COMPATIBILIDAD CON EL FORMATO ANTERIOR
         * ========================================================
         */

        $tombstones = $response['tombstones'];

        $response['productos_eliminados'] =
            $tombstones['productos'] ?? [];

        $response['clientes_eliminados'] =
            $tombstones['clientes'] ?? [];

        $response['impuestos_eliminados'] =
            $tombstones['impuestos'] ?? [];

        $response['formas_pago_eliminadas'] =
            $tombstones['formas_pago'] ?? [];

        $response['unidades_medida_eliminadas'] =
            $tombstones['unidades_medida'] ?? [];

        $response['categorias_eliminadas'] =
            $tombstones['categorias'] ?? [];

        $response['promociones_eliminadas'] =
            $tombstones['promociones'] ?? [];

        $response['cupones_eliminados'] =
            $tombstones['cupones'] ?? [];

        return response()->json($response);
    }

    /**
     * Obtener catálogo completo o incremental.
     */
    private function getCatalog(
        string $modelClass,
        int $empresaId,
        ?string $fechaSync
    ) {
        $query = $modelClass::query()
            ->where('empresa_id', $empresaId);

        if ($fechaSync) {
            $query->where(
                'updated_at',
                '>',
                $fechaSync
            );
        }

        return $query
            ->orderBy('id')
            ->get();
    }

    /**
     * Obtener versión de todos los catálogos.
     */
    private function getVersions(
        int $empresaId
    ): array {
        return [
            'empresa' => Empresa::query()
                ->whereKey($empresaId)
                ->max('updated_at'),

            'productos' => Producto::query()
                ->where('empresa_id', $empresaId)
                ->max('updated_at'),

            'clientes' => Cliente::query()
                ->where('empresa_id', $empresaId)
                ->max('updated_at'),

            'impuestos' => Impuesto::query()
                ->where('empresa_id', $empresaId)
                ->max('updated_at'),

            'formas_pago' => FormaPago::query()
                ->where('empresa_id', $empresaId)
                ->max('updated_at'),

            'unidades_medida' => UnidadMedida::query()
                ->where('empresa_id', $empresaId)
                ->max('updated_at'),

            'categorias' => Categoria::query()
                ->where('empresa_id', $empresaId)
                ->max('updated_at'),

            'promociones' => Promocion::query()
                ->where('empresa_id', $empresaId)
                ->max('updated_at'),

            'cupones' => Cupon::query()
                ->where('empresa_id', $empresaId)
                ->max('updated_at'),
        ];
    }

    /**
     * Construir registros eliminados.
     */
    private function buildTombstones(
        int $empresaId,
        ?string $fechaSync
    ): array {
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
            $usesSoftDeletes = in_array(
                \Illuminate\Database\Eloquent\SoftDeletes::class,
                class_uses_recursive($modelClass),
                true
            );

            if (!$usesSoftDeletes) {
                $tombstones[$key] = [];
                continue;
            }

            $query = $modelClass::withTrashed()
                ->where('empresa_id', $empresaId)
                ->whereNotNull('deleted_at');

            if ($fechaSync) {
                $query->where(
                    'deleted_at',
                    '>',
                    $fechaSync
                );
            }

            $tombstones[$key] = $query
                ->orderBy('id')
                ->get([
                    'id',
                    'deleted_at',
                ])
                ->map(function ($item) {
                    return [
                        'id' => (int) $item->id,
                        'deleted_at' =>
                            $item->deleted_at?->toIso8601String(),
                    ];
                })
                ->values()
                ->all();
        }

        return $tombstones;
    }

    /**
     * Obtener solo productos.
     */
    public function productos(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        $empresaId = (int) $user->empresa_id;

        return response()->json(
            Producto::where(
                'empresa_id',
                $empresaId
            )->paginate(50)
        );
    }
}