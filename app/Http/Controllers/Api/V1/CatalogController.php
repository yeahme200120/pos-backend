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
                'message' => 'Usuario no autenticado.',
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        try {
            $validated = $request->validate([
                'desde' => [
                    'nullable',
                    'date',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarErrorAuditoria(
                $request,
                'catalogo.validacion_fallida',
                'catalogos',
                null,
                null,
                [
                    'errores' => $e->errors(),
                    'query' => $request->query(),
                ],
                (int) ($user->empresa_id ?? 0),
                (int) $user->id
            );

            return response()->json([
                'message' => 'Los parámetros enviados para sincronizar el catálogo no son válidos.',
                'error' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        }

        $empresaId = (int) $user->empresa_id;

        if ($empresaId <= 0) {
            $this->registrarErrorAuditoria(
                $request,
                'catalogo.empresa_invalida',
                'catalogos',
                null,
                null,
                [
                    'motivo' => 'El usuario no tiene empresa asociada.',
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
                'error' => 'EMPRESA_NO_ASOCIADA',
            ], 422);
        }

        try {
            $fechaSync = $validated['desde'] ?? null;

            /*
             * La empresa siempre se obtiene por el empresa_id
             * del usuario autenticado.
             *
             * No se acepta empresa_id enviado por el cliente.
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
                $this->registrarErrorAuditoria(
                    $request,
                    'catalogo.empresa_no_encontrada',
                    'empresas',
                    $empresaId,
                    null,
                    [
                        'empresa_id' => $empresaId,
                    ],
                    $empresaId,
                    (int) $user->id
                );

                return response()->json([
                    'message' => 'Empresa no encontrada.',
                    'error' => 'EMPRESA_NO_ENCONTRADA',
                    'empresa_id' => $empresaId,
                ], 404);
            }

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

                /*
                 * Formas de pago: catálogo GLOBAL.
                 *
                 * No se filtra por empresa: se devuelven todas
                 * las formas de pago registradas en el sistema.
                 */
                'formas_pago' => $this->getFormasPagoGlobales($fechaSync),

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

                'versiones' => $this->getVersions($empresaId),

                'tombstones' => $this->buildTombstones(
                    $empresaId,
                    $fechaSync
                ),
            ];

            /*
             * Se conservan las respuestas individuales de eliminados
             * por compatibilidad con los clientes actuales.
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

            /*
             * Auditoría de sincronización exitosa.
             *
             * El empresa_id se toma directamente de la sesión
             * autenticada y no del request.
             */
            $this->registrarAuditoria(
                $request,
                'catalogo.sincronizado',
                'catalogos',
                null,
                null,
                [
                    'desde' => $fechaSync,
                    'productos' => $response['productos']->count(),
                    'clientes' => $response['clientes']->count(),
                    'impuestos' => $response['impuestos']->count(),
                    'formas_pago' => $response['formas_pago']->count(),
                    'unidades_medida' => $response['unidades_medida']->count(),
                    'categorias' => $response['categorias']->count(),
                    'promociones' => $response['promociones']->count(),
                    'cupones' => $response['cupones']->count(),

                    'productos_eliminados' =>
                        count($response['productos_eliminados']),

                    'clientes_eliminados' =>
                        count($response['clientes_eliminados']),

                    'impuestos_eliminados' =>
                        count($response['impuestos_eliminados']),

                    'formas_pago_eliminadas' =>
                        count($response['formas_pago_eliminadas']),

                    'unidades_medida_eliminadas' =>
                        count($response['unidades_medida_eliminadas']),

                    'categorias_eliminadas' =>
                        count($response['categorias_eliminadas']),

                    'promociones_eliminadas' =>
                        count($response['promociones_eliminadas']),

                    'cupones_eliminados' =>
                        count($response['cupones_eliminados']),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error(
                'Error al sincronizar catálogo.',
                [
                    'controller' => self::class,
                    'method' => __FUNCTION__,
                    'empresa_id' => $empresaId,
                    'usuario_id' => (int) $user->id,
                    'desde' => $request->input('desde'),
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            $this->registrarErrorAuditoria(
                $request,
                'catalogo.error_interno',
                'catalogos',
                null,
                null,
                [
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'No fue posible sincronizar el catálogo.',
                'error' => 'CATALOGO_SYNC_ERROR',
            ], 500);
        }
    }

    /**
     * Obtener registros de un catálogo filtrados por empresa
     * y opcionalmente por fecha de sincronización.
     *
     * @param class-string<Model> $modelClass
     */
    private function getCatalog(
        string $modelClass,
        int $empresaId,
        ?string $fechaSync
    ) {
        /*
         * Eloquent utiliza consultas parametrizadas internamente.
         *
         * No se construyen consultas SQL concatenando valores
         * provenientes del request.
         */
        $query = $modelClass::query()
            ->where('empresa_id', $empresaId);

        if ($fechaSync !== null && trim($fechaSync) !== '') {
            $query->where('updated_at', '>', $fechaSync);
        }

        return $query
            ->orderBy('id')
            ->get();
    }

    /**
     * Obtener formas de pago globales.
     *
     * A diferencia de getCatalog, este método NO filtra por
     * empresa.
     *
     * Las formas de pago son un catálogo compartido entre
     * todas las empresas del sistema.
     *
     * @param string|null $fechaSync
     *        Si tiene valor, solamente registros modificados
     *        después de esa fecha.
     */
    private function getFormasPagoGlobales(?string $fechaSync)
    {
        $query = FormaPago::query();

        /*
         * Sin filtro por empresa: catálogo global.
         */

        if ($fechaSync !== null && trim($fechaSync) !== '') {
            $query->where('updated_at', '>', $fechaSync);
        }

        return $query
            ->orderBy('id')
            ->get();
    }

    /**
     * Obtener las versiones de los catálogos.
     *
     * Todas las consultas están aisladas por empresa.
     */
    private function getVersions(int $empresaId): array
    {
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
     * Construir tombstones para registros eliminados.
     *
     * Solamente se consultan modelos que realmente implementan
     * SoftDeletes.
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
            try {
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
                    ->whereNotNull('deleted_at');

                /*
                 * FormaPago es un catálogo global: NO se filtra
                 * por empresa.
                 *
                 * Todos los demás catálogos SÍ se filtran.
                 */
                if ($modelClass !== FormaPago::class) {
                    $query->where('empresa_id', $empresaId);
                }

                if ($fechaSync !== null && trim($fechaSync) !== '') {
                    $query->where('deleted_at', '>', $fechaSync);
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
                            'deleted_at' => $item->deleted_at
                                ?->toIso8601String(),
                        ];
                    })
                    ->values()
                    ->all();
            } catch (\Throwable $e) {
                /*
                 * Un error en un catálogo concreto no debe quedar
                 * oculto ni generar una respuesta inconsistente.
                 */
                Log::error(
                    'Error al construir tombstones del catálogo.',
                    [
                        'controller' => self::class,
                        'catalogo' => $key,
                        'model' => $modelClass,
                        'empresa_id' => $empresaId,
                        'exception' => get_class($e),
                        'message' => $e->getMessage(),
                    ]
                );

                throw $e;
            }
        }

        return $tombstones;
    }

    /**
     * Listar productos de la empresa autenticada.
     */
    public function productos(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
                'error' => 'UNAUTHENTICATED',
            ], 401);
        }

        try {
            $validated = $request->validate([
                'per_page' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:100',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarErrorAuditoria(
                $request,
                'productos.validacion_fallida',
                'productos',
                null,
                null,
                [
                    'errores' => $e->errors(),
                ],
                (int) ($user->empresa_id ?? 0),
                (int) $user->id
            );

            return response()->json([
                'message' => 'El parámetro per_page no es válido.',
                'error' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        }

        $empresaId = (int) $user->empresa_id;

        if ($empresaId <= 0) {
            $this->registrarErrorAuditoria(
                $request,
                'productos.empresa_invalida',
                'productos',
                null,
                null,
                [
                    'motivo' => 'El usuario no tiene empresa asociada.',
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asociada.',
                'error' => 'EMPRESA_NO_ASOCIADA',
            ], 422);
        }

        try {
            $perPage = (int) ($validated['per_page'] ?? 50);

            /*
             * La consulta siempre queda restringida a la empresa
             * del usuario autenticado.
             */
            $productos = Producto::query()
                ->where('empresa_id', $empresaId)
                ->orderBy('id')
                ->paginate($perPage)
                ->appends($request->query());

            $this->registrarAuditoria(
                $request,
                'productos.consultados',
                'productos',
                null,
                null,
                [
                    'pagina' => $productos->currentPage(),
                    'total' => $productos->total(),
                    'per_page' => $productos->perPage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json($productos);
        } catch (\Throwable $e) {
            Log::error(
                'Error al listar productos.',
                [
                    'controller' => self::class,
                    'method' => __FUNCTION__,
                    'empresa_id' => $empresaId,
                    'usuario_id' => (int) $user->id,
                    'per_page' => $request->input('per_page'),
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]
            );

            $this->registrarErrorAuditoria(
                $request,
                'productos.error_interno',
                'productos',
                null,
                null,
                [
                    'empresa_id' => $empresaId,
                    'error' => $e->getMessage(),
                    'exception' => get_class($e),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'No fue posible cargar los productos.',
                'error' => 'PRODUCTOS_QUERY_ERROR',
            ], 500);
        }
    }

    /**
     * Registrar una auditoría sin permitir que un problema
     * del sistema de auditoría rompa la operación principal.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        string $entidad,
        $entidadId,
        $folio,
        array $datos,
        int $empresaId,
        int $usuarioId
    ): void {
        try {
            app(AuditoriaService::class)->registrar(
                $request,
                $accion,
                $entidad,
                $entidadId,
                $folio,
                $datos,
                $empresaId,
                $usuarioId
            );
        } catch (\Throwable $e) {
            /*
             * La auditoría es importante, pero nunca debe provocar
             * que una consulta de catálogo falle.
             */
            Log::warning(
                'No fue posible registrar la auditoría del catálogo.',
                [
                    'controller' => self::class,
                    'accion' => $accion,
                    'entidad' => $entidad,
                    'entidad_id' => $entidadId,
                    'folio' => $folio,
                    'empresa_id' => $empresaId,
                    'usuario_id' => $usuarioId,
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Registrar errores de auditoría de forma segura.
     *
     * El método auxiliar evita duplicar manejo de excepciones
     * en cada endpoint.
     */
    private function registrarErrorAuditoria(
        Request $request,
        string $accion,
        string $entidad,
        $entidadId,
        $folio,
        array $datos,
        int $empresaId,
        int $usuarioId
    ): void {
        /*
         * No lanzamos excepciones desde aquí.
         *
         * Un error al auditar un error no debe reemplazar
         * la respuesta real que corresponde al cliente.
         */
        $this->registrarAuditoria(
            $request,
            $accion,
            $entidad,
            $entidadId,
            $folio,
            $datos,
            $empresaId,
            $usuarioId
        );
    }
}