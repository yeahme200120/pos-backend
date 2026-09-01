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
use App\Services\AuditoriaService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class CatalogController extends Controller
{
    /**
     * Obtener todos los catálogos.
     *
     * Sin "desde":
     * sincronización completa.
     *
     * Con "desde":
     * solamente registros modificados después
     * de la fecha indicada.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' =>
                    'Usuario no autenticado.',
            ], 401);
        }

        $request->validate([
            'desde' => [
                'nullable',
                'date',
            ],
        ]);

        $empresaId =
            (int) $user->empresa_id;

        if ($empresaId <= 0) {
            return response()->json([
                'message' =>
                    'El usuario no tiene una empresa asociada.',
            ], 422);
        }

        try {
            $fechaSync =
                $request->input('desde');

            /*
             * =====================================================
             * EMPRESA
             * =====================================================
             *
             * Se limita la información devuelta a datos propios
             * de configuración del cliente.
             */
            $empresa = Empresa::query()
                ->whereKey($empresaId)
                ->first([
                    'id',
                    'nombre',
                    'logo',
                    'colores',
                    'direccion',
                    'telefono',
                    'rfc',
                    'activo',
                    'updated_at',
                ]);

            if (!$empresa) {
                return response()->json([
                    'message' =>
                        'Empresa no encontrada.',
                ], 404);
            }

            /*
             * =====================================================
             * CATÁLOGOS
             * =====================================================
             */
            $response = [
                'empresa' =>
                    $empresa,

                'productos' =>
                    $this->getCatalog(
                        Producto::class,
                        $empresaId,
                        $fechaSync
                    ),

                'clientes' =>
                    $this->getCatalog(
                        Cliente::class,
                        $empresaId,
                        $fechaSync
                    ),

                'impuestos' =>
                    $this->getCatalog(
                        Impuesto::class,
                        $empresaId,
                        $fechaSync
                    ),

                'formas_pago' =>
                    $this->getCatalog(
                        FormaPago::class,
                        $empresaId,
                        $fechaSync
                    ),

                'unidades_medida' =>
                    $this->getCatalog(
                        UnidadMedida::class,
                        $empresaId,
                        $fechaSync
                    ),

                'categorias' =>
                    $this->getCatalog(
                        Categoria::class,
                        $empresaId,
                        $fechaSync
                    ),

                'promociones' =>
                    $this->getCatalog(
                        Promocion::class,
                        $empresaId,
                        $fechaSync
                    ),

                'cupones' =>
                    $this->getCatalog(
                        Cupon::class,
                        $empresaId,
                        $fechaSync
                    ),

                /*
                 * Las versiones se devuelven SIEMPRE.
                 */
                'versiones' =>
                    $this->getVersions(
                        $empresaId
                    ),

                /*
                 * Tombstones.
                 */
                'tombstones' =>
                    $this->buildTombstones(
                        $empresaId,
                        $fechaSync
                    ),
            ];

            /*
             * =====================================================
             * COMPATIBILIDAD CON FORMATO ANTERIOR
             * =====================================================
             */
            $tombstones =
                $response['tombstones'];

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

            app(AuditoriaService::class)->registrar(
                $request,
                'catalogo.sincronizado',
                'catalogos',
                null,
                null,
                [
                    'desde' =>
                        $fechaSync,

                    'productos' =>
                        $response['productos']->count(),

                    'clientes' =>
                        $response['clientes']->count(),

                    'impuestos' =>
                        $response['impuestos']->count(),

                    'formas_pago' =>
                        $response['formas_pago']->count(),

                    'unidades_medida' =>
                        $response['unidades_medida']->count(),

                    'categorias' =>
                        $response['categorias']->count(),

                    'promociones' =>
                        $response['promociones']->count(),

                    'cupones' =>
                        $response['cupones']->count(),
                ],
                $empresaId,
                $user->id
            );

            return response()->json(
                $response
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al sincronizar catálogo: ' .
                $e->getMessage()
            );

            return response()->json([
                'message' =>
                    'Error al cargar el catálogo.',
            ], 500);
        }
    }

    /**
     * Obtener catálogo completo o incremental.
     */
    private function getCatalog(
        string $modelClass,
        int $empresaId,
        ?string $fechaSync
    ) {
        /** @var Model $model */
        $query = $modelClass::query()
            ->where(
                'empresa_id',
                $empresaId
            );

        /*
         * Eloquent excluye automáticamente los soft deleted
         * cuando el modelo usa SoftDeletes.
         */
        if (
            $fechaSync !== null &&
            trim($fechaSync) !== ''
        ) {
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
            'empresa' =>
                Empresa::query()
                    ->whereKey(
                        $empresaId
                    )
                    ->max('updated_at'),

            'productos' =>
                Producto::query()
                    ->where(
                        'empresa_id',
                        $empresaId
                    )
                    ->max('updated_at'),

            'clientes' =>
                Cliente::query()
                    ->where(
                        'empresa_id',
                        $empresaId
                    )
                    ->max('updated_at'),

            'impuestos' =>
                Impuesto::query()
                    ->where(
                        'empresa_id',
                        $empresaId
                    )
                    ->max('updated_at'),

            'formas_pago' =>
                FormaPago::query()
                    ->where(
                        'empresa_id',
                        $empresaId
                    )
                    ->max('updated_at'),

            'unidades_medida' =>
                UnidadMedida::query()
                    ->where(
                        'empresa_id',
                        $empresaId
                    )
                    ->max('updated_at'),

            'categorias' =>
                Categoria::query()
                    ->where(
                        'empresa_id',
                        $empresaId
                    )
                    ->max('updated_at'),

            'promociones' =>
                Promocion::query()
                    ->where(
                        'empresa_id',
                        $empresaId
                    )
                    ->max('updated_at'),

            'cupones' =>
                Cupon::query()
                    ->where(
                        'empresa_id',
                        $empresaId
                    )
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
            'productos' =>
                Producto::class,

            'clientes' =>
                Cliente::class,

            'impuestos' =>
                Impuesto::class,

            'formas_pago' =>
                FormaPago::class,

            'unidades_medida' =>
                UnidadMedida::class,

            'categorias' =>
                Categoria::class,

            'promociones' =>
                Promocion::class,

            'cupones' =>
                Cupon::class,
        ];

        $tombstones = [];

        foreach (
            $models as
            $key => $modelClass
        ) {
            $usesSoftDeletes =
                in_array(
                    \Illuminate\Database\Eloquent\SoftDeletes::class,
                    class_uses_recursive(
                        $modelClass
                    ),
                    true
                );

            if (!$usesSoftDeletes) {
                $tombstones[$key] = [];

                continue;
            }

            $query = $modelClass
                ::withTrashed()
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->whereNotNull(
                    'deleted_at'
                );

            if (
                $fechaSync !== null &&
                trim($fechaSync) !== ''
            ) {
                $query->where(
                    'deleted_at',
                    '>',
                    $fechaSync
                );
            }

            $tombstones[$key] =
                $query
                    ->orderBy('id')
                    ->get([
                        'id',
                        'deleted_at',
                    ])
                    ->map(
                        function ($item) {
                            return [
                                'id' =>
                                    (int) $item->id,

                                'deleted_at' =>
                                    $item->deleted_at
                                        ?->toIso8601String(),
                            ];
                        }
                    )
                    ->values()
                    ->all();
        }

        return $tombstones;
    }

    /**
     * Obtener solamente productos.
     */
    public function productos(
        Request $request
    ) {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' =>
                    'Usuario no autenticado.',
            ], 401);
        }

        $request->validate([
            'per_page' => [
                'nullable',
                'integer',
                'min:1',
                'max:100',
            ],
        ]);

        $empresaId =
            (int) $user->empresa_id;

        if ($empresaId <= 0) {
            return response()->json([
                'message' =>
                    'El usuario no tiene una empresa asociada.',
            ], 422);
        }

        try {
            $perPage = (int) $request->input(
                'per_page',
                50
            );

            $productos = Producto::query()
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->orderBy('id')
                ->paginate($perPage)
                ->appends(
                    $request->query()
                );

            app(AuditoriaService::class)->registrar(
                $request,
                'productos.consultados',
                'productos',
                null,
                null,
                [
                    'pagina' =>
                        $productos->currentPage(),

                    'total' =>
                        $productos->total(),

                    'per_page' =>
                        $productos->perPage(),
                ],
                $empresaId,
                $user->id
            );

            return response()->json(
                $productos
            );
        } catch (\Throwable $e) {
            Log::error(
                '❌ Error al listar productos: ' .
                $e->getMessage()
            );

            return response()->json([
                'message' =>
                    'Error al cargar productos.',
            ], 500);
        }
    }
}