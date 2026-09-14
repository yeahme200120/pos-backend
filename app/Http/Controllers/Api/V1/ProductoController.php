<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Services\AuditoriaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ProductoController extends Controller
{
    protected AuditoriaService $auditoria;

    public function __construct(AuditoriaService $auditoria)
    {
        $this->auditoria = $auditoria;
    }

    /**
     * Registrar auditoría sin afectar la operación principal.
     *
     * La auditoría recibe siempre el usuario y empresa reales
     * obtenidos de la sesión autenticada.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues
    ): void {
        try {
            $usuario = $request->user();

            $datosAuditoria = $datosDespues ?? [];

            /*
             * Estos valores siempre deben venir del servidor.
             * No se permite que datos construidos externamente
             * los sobrescriban.
             */
            $datosAuditoria['empresa_id'] =
                $usuario?->empresa_id;

            $datosAuditoria['usuario_id'] =
                $usuario?->id;

            $this->auditoria->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosAuditoria,
                $usuario?->empresa_id,
                $usuario?->id
            );
        } catch (Throwable $e) {
            /*
             * Un fallo de auditoría nunca debe provocar rollback
             * ni convertir una operación exitosa en un error.
             */
            Log::warning(
                'No fue posible registrar auditoría de producto.',
                [
                    'accion' => $accion,
                    'tabla' => $tabla,
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
     * Registrar errores en auditoría sin afectar la operación.
     */
    private function registrarAuditoriaError(
        Request $request,
        string $accion,
        string $tabla = 'productos',
        ?int $registroId = null,
        array $datos = []
    ): void {
        try {
            $usuario = $request->user();

            $datos['empresa_id'] =
                $usuario?->empresa_id;

            $datos['usuario_id'] =
                $usuario?->id;

            $this->auditoria->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                null,
                $datos,
                $usuario?->empresa_id,
                $usuario?->id
            );
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible registrar auditoría del error de producto.',
                [
                    'accion' => $accion,
                    'tabla' => $tabla,
                    'registro_id' => $registroId,
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );
        }
    }

    /**
     * Obtener empresa del usuario autenticado.
     */
    private function obtenerEmpresaId(Request $request): ?int
    {
        $empresaId = $request->user()?->empresa_id;

        return $empresaId
            ? (int) $empresaId
            : null;
    }

    /**
     * Validar autenticación y empresa.
     */
    private function validarContexto(Request $request): ?\Illuminate\Http\JsonResponse
    {
        $usuario = $request->user();

        if (!$usuario) {
            $this->registrarAuditoriaError(
                $request,
                'producto.autenticacion_fallida',
                'productos',
                null,
                [
                    'error_code' => 'PRODUCTO_UNAUTHENTICATED',
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'Usuario no autenticado.',
                'error_code' => 'PRODUCTO_UNAUTHENTICATED',
            ], 401);
        }

        if (!$usuario->empresa_id) {
            $this->registrarAuditoriaError(
                $request,
                'producto.empresa_no_asociada',
                'productos',
                null,
                [
                    'error_code' => 'PRODUCTO_EMPRESA_NO_ASOCIADA',
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
                'error_code' => 'PRODUCTO_EMPRESA_NO_ASOCIADA',
            ], 403);
        }

        return null;
    }

    /**
     * Obtener mensaje de error de base de datos sin exponer
     * información interna al frontend.
     */
    private function responderErrorBaseDatos(
        Request $request,
        string $operacion,
        ?int $productoId = null
    ) {
        $codigo = 'PRODUCTO_' . strtoupper($operacion) . '_DB_ERROR';

        Log::error(
            'Error de base de datos en ProductoController.',
            [
                'operacion' => $operacion,
                'producto_id' => $productoId,
                'usuario_id' => $request->user()?->id,
                'empresa_id' => $request->user()?->empresa_id,
            ]
        );

        $this->registrarAuditoriaError(
            $request,
            'producto.' . $operacion . '.error_bd',
            'productos',
            $productoId,
            [
                'error_code' => $codigo,
            ]
        );

        return response()->json([
            'success' => false,
            'message' => 'No fue posible realizar la operación debido a un error de base de datos.',
            'error_code' => $codigo,
        ], 500);
    }

    /**
     * Listar productos.
     */
    public function index(Request $request)
    {
        $contexto = $this->validarContexto($request);

        if ($contexto) {
            return $contexto;
        }

        try {
            $validated = $request->validate([
                'search' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:255',
                ],

                'categoria_id' => [
                    'sometimes',
                    'nullable',
                    'integer',
                    'min:1',
                ],

                'activo' => [
                    'sometimes',
                    'boolean',
                ],

                'stock_minimo' => [
                    'sometimes',
                    'boolean',
                ],

                'per_page' => [
                    'sometimes',
                    'integer',
                    'min:1',
                    'max:100',
                ],
            ]);

            $empresaId = $this->obtenerEmpresaId($request);

            $query = Producto::query()
                ->where('empresa_id', $empresaId)
                ->with([
                    'categoria',
                    'unidadMedida',
                ]);

            if (
                array_key_exists('search', $validated)
                && filled($validated['search'])
            ) {
                $search = trim(
                    (string) $validated['search']
                );

                /*
                 * Los valores son enviados mediante bindings
                 * de Eloquent. No existe concatenación SQL directa.
                 */
                $searchLike = '%' . $search . '%';

                $query->where(function ($q) use ($searchLike) {
                    $q->where(
                        'codigo',
                        'like',
                        $searchLike
                    )
                        ->orWhere(
                            'nombre',
                            'like',
                            $searchLike
                        )
                        ->orWhere(
                            'descripcion',
                            'like',
                            $searchLike
                        );
                });
            }

            if (
                array_key_exists(
                    'categoria_id',
                    $validated
                )
                && $validated['categoria_id'] !== null
            ) {
                $query->where(
                    'categoria_id',
                    (int) $validated['categoria_id']
                );
            }

            if (array_key_exists('activo', $validated)) {
                $query->where(
                    'activo',
                    (bool) $validated['activo']
                );
            }

            if (
                array_key_exists(
                    'stock_minimo',
                    $validated
                )
                && $validated['stock_minimo']
            ) {
                $query->whereColumn(
                    'stock',
                    '<=',
                    'stock_minimo'
                );
            }

            $perPage = (int) (
                $validated['per_page'] ?? 20
            );

            $productos = $query
                ->orderBy('nombre')
                ->paginate($perPage);

            /*
             * Estas consultas ya están aisladas por empresa.
             */
            $categorias = Categoria::query()
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->orderBy('nombre')
                ->get();

            $unidades = UnidadMedida::query()
                ->where('empresa_id', $empresaId)
                ->where('activo', true)
                ->orderBy('nombre')
                ->get();

            $data = [
                'productos' => $productos->items(),
                'categorias' => $categorias,
                'unidades' => $unidades,
                'pagination' => [
                    'current_page' =>
                        $productos->currentPage(),

                    'last_page' =>
                        $productos->lastPage(),

                    'per_page' =>
                        $productos->perPage(),

                    'total' =>
                        $productos->total(),

                    'from' =>
                        $productos->firstItem(),

                    'to' =>
                        $productos->lastItem(),
                ],
            ];

            $this->registrarAuditoria(
                $request,
                'consultar_productos',
                'productos',
                null,
                null,
                [
                    'filtros' => [
                        'search' =>
                            $validated['search'] ?? null,

                        'categoria_id' =>
                            $validated['categoria_id'] ?? null,

                        'activo' =>
                            $validated['activo'] ?? null,

                        'stock_minimo' =>
                            $validated['stock_minimo'] ?? null,

                        'per_page' => $perPage,
                    ],

                    'pagina' =>
                        $productos->currentPage(),

                    'resultados' =>
                        $productos->total(),
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaError(
                $request,
                'consultar_productos.validacion_fallida',
                'productos',
                null,
                [
                    'errores' => $e->errors(),
                ]
            );

            throw $e;
        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos al listar productos.',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]
            );

            return $this->responderErrorBaseDatos(
                $request,
                'listar'
            );
        } catch (Throwable $e) {
            Log::error(
                'Error al listar productos.',
                [
                    'usuario_id' =>
                        $request->user()?->id,

                    'empresa_id' =>
                        $request->user()?->empresa_id,

                    'error' =>
                        $e->getMessage(),

                    'exception' =>
                        get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'consultar_productos.error',
                'productos',
                null,
                [
                    'error_code' =>
                        'PRODUCTO_LISTAR_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener los productos.',
                'error_code' => 'PRODUCTO_LISTAR_ERROR',
            ], 500);
        }
    }

    /**
     * Mostrar un producto.
     */
    public function show(
        int $id,
        Request $request
    ) {
        $contexto = $this->validarContexto($request);

        if ($contexto) {
            return $contexto;
        }

        try {
            $empresaId = $this->obtenerEmpresaId($request);

            $producto = Producto::query()
                ->where('empresa_id', $empresaId)
                ->with([
                    'categoria',
                    'unidadMedida',
                ])
                ->find($id);

            if (!$producto) {
                $this->registrarAuditoriaError(
                    $request,
                    'producto.consulta_no_encontrado',
                    'productos',
                    $id,
                    [
                        'error_code' =>
                            'PRODUCTO_NO_ENCONTRADO',
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado.',
                    'error_code' => 'PRODUCTO_NO_ENCONTRADO',
                ], 404);
            }

            $this->registrarAuditoria(
                $request,
                'consultar_producto',
                'productos',
                (int) $producto->id,
                null,
                [
                    'producto_id' =>
                        (int) $producto->id,

                    'codigo' =>
                        $producto->codigo,

                    'nombre' =>
                        $producto->nombre,
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $producto,
            ]);
        } catch (QueryException $e) {
            Log::error(
                'Error de base de datos al consultar producto.',
                [
                    'producto_id' => $id,
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]
            );

            return $this->responderErrorBaseDatos(
                $request,
                'consultar',
                $id
            );
        } catch (Throwable $e) {
            Log::error(
                'Error al consultar producto.',
                [
                    'producto_id' =>
                        $id,

                    'usuario_id' =>
                        $request->user()?->id,

                    'empresa_id' =>
                        $request->user()?->empresa_id,

                    'error' =>
                        $e->getMessage(),

                    'exception' =>
                        get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'producto.consulta.error',
                'productos',
                $id,
                [
                    'error_code' =>
                        'PRODUCTO_CONSULTAR_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener el producto.',
                'error_code' => 'PRODUCTO_CONSULTAR_ERROR',
            ], 500);
        }
    }

    /**
     * Crear producto.
     */
    public function store(Request $request)
    {
        $contexto = $this->validarContexto($request);

        if ($contexto) {
            return $contexto;
        }

        $usuario = $request->user();
        $empresaId = $this->obtenerEmpresaId($request);

        try {
            $validated = $request->validate([
                'codigo' => [
                    'required',
                    'string',
                    'max:100',

                    Rule::unique(
                        'productos',
                        'codigo'
                    )->where(
                        fn ($query) =>
                        $query->where(
                            'empresa_id',
                            $empresaId
                        )
                    ),
                ],

                'nombre' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'descripcion' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],

                'precio' => [
                    'required',
                    'numeric',
                    'min:0',
                ],

                'costo' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'impuesto' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'stock' => [
                    'nullable',
                    'integer',
                    'min:0',
                ],

                'stock_minimo' => [
                    'nullable',
                    'integer',
                    'min:0',
                ],

                'categoria_id' => [
                    'required',
                    'integer',
                    'min:1',

                    Rule::exists(
                        'categorias',
                        'id'
                    )->where(
                        fn ($query) =>
                        $query
                            ->where(
                                'empresa_id',
                                $empresaId
                            )
                            ->where(
                                'activo',
                                true
                            )
                    ),
                ],

                'unidad_medida_id' => [
                    'nullable',
                    'integer',
                    'min:1',

                    Rule::exists(
                        'unidades_medida',
                        'id'
                    )->where(
                        fn ($query) =>
                        $query->where(
                            'empresa_id',
                            $empresaId
                        )
                    ),
                ],

                'activo' => [
                    'sometimes',
                    'boolean',
                ],

                'is_inventariable' => [
                    'sometimes',
                    'boolean',
                ],

                'imagen' => [
                    'nullable',
                    'file',
                    'mimes:jpeg,png,jpg,gif,svg',
                    'max:2048',
                ],
            ], [
                'categoria_id.required' =>
                    'Debes seleccionar una categoría.',

                'categoria_id.integer' =>
                    'La categoría seleccionada no es válida.',

                'categoria_id.min' =>
                    'La categoría seleccionada no es válida.',

                'categoria_id.exists' =>
                    'La categoría seleccionada no existe o está inactiva.',
            ]);

            $validated['codigo'] =
                trim($validated['codigo']);

            $validated['nombre'] =
                trim($validated['nombre']);

            if (isset($validated['descripcion'])) {
                $validated['descripcion'] =
                    trim($validated['descripcion']);
            }

            $imagenPath = null;

            try {
                if ($request->hasFile('imagen')) {
                    $imagenPath = $request
                        ->file('imagen')
                        ->store(
                            'productos',
                            'public'
                        );
                }

                $datos = [
                    'empresa_id' =>
                        $empresaId,

                    'codigo' =>
                        $validated['codigo'],

                    'nombre' =>
                        $validated['nombre'],

                    'descripcion' =>
                        $validated['descripcion'] ?? null,

                    'precio' =>
                        $validated['precio'],

                    'costo' =>
                        $validated['costo'] ?? 0,

                    'impuesto' =>
                        $validated['impuesto'] ?? 0,

                    'stock' =>
                        $validated['stock'] ?? 0,

                    'stock_minimo' =>
                        $validated['stock_minimo'] ?? 0,

                    'categoria_id' =>
                        $validated['categoria_id'],

                    'unidad_medida_id' =>
                        $validated['unidad_medida_id'] ?? null,

                    'activo' =>
                        $validated['activo'] ?? true,

                    /*
                     * false debe conservarse como false.
                     */
                    'is_inventariable' =>
                        $validated['is_inventariable'] ?? true,

                    'imagen' =>
                        $imagenPath,
                ];

                $producto = DB::transaction(
                    function () use ($datos) {
                        return Producto::create(
                            $datos
                        );
                    }
                );

                $producto->load([
                    'categoria',
                    'unidadMedida',
                ]);

                $this->registrarAuditoria(
                    $request,
                    'crear_producto',
                    'productos',
                    (int) $producto->id,
                    null,
                    $producto->toArray()
                );

                return response()->json([
                    'success' => true,
                    'message' =>
                        'Producto creado correctamente.',
                    'data' => $producto,
                ], 201);
            } catch (QueryException $e) {
                if ($imagenPath) {
                    $this->eliminarArchivoSeguro(
                        $imagenPath,
                        'imagen_producto_creacion',
                        $productoId = null
                    );
                }

                Log::error(
                    'Error de base de datos al crear producto.',
                    [
                        'usuario_id' =>
                            $usuario->id,

                        'empresa_id' =>
                            $empresaId,

                        'error' =>
                            $e->getMessage(),

                        'code' =>
                            $e->getCode(),
                    ]
                );

                return $this->responderErrorBaseDatos(
                    $request,
                    'crear'
                );
            } catch (Throwable $e) {
                if ($imagenPath) {
                    $this->eliminarArchivoSeguro(
                        $imagenPath,
                        'imagen_producto_creacion',
                        null
                    );
                }

                Log::error(
                    'Error al crear producto.',
                    [
                        'usuario_id' =>
                            $usuario->id,

                        'empresa_id' =>
                            $empresaId,

                        'error' =>
                            $e->getMessage(),

                        'exception' =>
                            get_class($e),
                    ]
                );

                $this->registrarAuditoriaError(
                    $request,
                    'crear_producto.error',
                    'productos',
                    null,
                    [
                        'error_code' =>
                            'PRODUCTO_CREAR_ERROR',
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'No fue posible crear el producto.',
                    'error_code' =>
                        'PRODUCTO_CREAR_ERROR',
                ], 500);
            }
        } catch (ValidationException $e) {
            $this->registrarAuditoriaError(
                $request,
                'crear_producto.validacion_fallida',
                'productos',
                null,
                [
                    'errores' =>
                        $e->errors(),
                ]
            );

            throw $e;
        } catch (Throwable $e) {
            Log::error(
                'Error inesperado durante validación de producto.',
                [
                    'usuario_id' =>
                        $usuario->id,

                    'empresa_id' =>
                        $empresaId,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible validar los datos del producto.',
                'error_code' =>
                    'PRODUCTO_VALIDACION_ERROR',
            ], 500);
        }
    }

    /**
     * Actualizar producto.
     *
     * El stock no se modifica aquí.
     */
    public function update(
        Request $request,
        int $id
    ) {
        $contexto = $this->validarContexto($request);

        if ($contexto) {
            return $contexto;
        }

        $usuario = $request->user();
        $empresaId = $this->obtenerEmpresaId($request);

        try {
            $producto = Producto::query()
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->find($id);

            if (!$producto) {
                $this->registrarAuditoriaError(
                    $request,
                    'actualizar_producto.no_encontrado',
                    'productos',
                    $id,
                    [
                        'error_code' =>
                            'PRODUCTO_NO_ENCONTRADO',
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Producto no encontrado.',
                    'error_code' =>
                        'PRODUCTO_NO_ENCONTRADO',
                ], 404);
            }

            $validated = $request->validate([
                'codigo' => [
                    'required',
                    'string',
                    'max:100',

                    Rule::unique(
                        'productos',
                        'codigo'
                    )
                        ->where(
                            fn ($query) =>
                            $query->where(
                                'empresa_id',
                                $empresaId
                            )
                        )
                        ->ignore(
                            $producto->id
                        ),
                ],

                'nombre' => [
                    'required',
                    'string',
                    'max:255',
                ],

                'descripcion' => [
                    'nullable',
                    'string',
                    'max:5000',
                ],

                'precio' => [
                    'required',
                    'numeric',
                    'min:0',
                ],

                'costo' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'impuesto' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],

                'stock_minimo' => [
                    'nullable',
                    'integer',
                    'min:0',
                ],

                'categoria_id' => [
                    'required',
                    'integer',
                    'min:1',

                    Rule::exists(
                        'categorias',
                        'id'
                    )->where(
                        fn ($query) =>
                        $query
                            ->where(
                                'empresa_id',
                                $empresaId
                            )
                            ->where(
                                'activo',
                                true
                            )
                    ),
                ],

                'unidad_medida_id' => [
                    'nullable',
                    'integer',
                    'min:1',

                    Rule::exists(
                        'unidades_medida',
                        'id'
                    )->where(
                        fn ($query) =>
                        $query->where(
                            'empresa_id',
                            $empresaId
                        )
                    ),
                ],

                'activo' => [
                    'sometimes',
                    'boolean',
                ],

                'is_inventariable' => [
                    'sometimes',
                    'boolean',
                ],

                'imagen' => [
                    'nullable',
                    'file',
                    'mimes:jpeg,png,jpg,gif,svg',
                    'max:2048',
                ],
            ], [
                'categoria_id.required' =>
                    'Debes seleccionar una categoría.',

                'categoria_id.integer' =>
                    'La categoría seleccionada no es válida.',

                'categoria_id.min' =>
                    'La categoría seleccionada no es válida.',

                'categoria_id.exists' =>
                    'La categoría seleccionada no existe o está inactiva.',
            ]);

            $validated['codigo'] =
                trim($validated['codigo']);

            $validated['nombre'] =
                trim($validated['nombre']);

            if (isset($validated['descripcion'])) {
                $validated['descripcion'] =
                    trim($validated['descripcion']);
            }

            $datosAntes =
                $producto->toArray();

            $imagenAnterior =
                $producto->imagen;

            $imagenNueva = null;

            try {
                if ($request->hasFile('imagen')) {
                    $imagenNueva = $request
                        ->file('imagen')
                        ->store(
                            'productos',
                            'public'
                        );
                }

                $datosActualizar = [
                    'codigo' =>
                        $validated['codigo'],

                    'nombre' =>
                        $validated['nombre'],

                    'descripcion' =>
                        $validated['descripcion'] ?? null,

                    'precio' =>
                        $validated['precio'],

                    'costo' =>
                        $validated['costo'] ?? 0,

                    'impuesto' =>
                        $validated['impuesto'] ?? 0,

                    'stock_minimo' =>
                        $validated['stock_minimo'] ?? 0,

                    'categoria_id' =>
                        $validated['categoria_id'],

                    'unidad_medida_id' =>
                        $validated['unidad_medida_id'] ?? null,
                ];

                if (
                    array_key_exists(
                        'activo',
                        $validated
                    )
                ) {
                    $datosActualizar['activo'] =
                        (bool) $validated['activo'];
                }

                /*
                 * IMPORTANTE:
                 *
                 * array_key_exists() permite distinguir:
                 *
                 * false = actualizar a 0
                 * true  = actualizar a 1
                 * ausente = no modificar
                 */
                if (
                    array_key_exists(
                        'is_inventariable',
                        $validated
                    )
                ) {
                    $datosActualizar[
                        'is_inventariable'
                    ] =
                        (bool) $validated[
                            'is_inventariable'
                        ];
                }

                if ($imagenNueva !== null) {
                    $datosActualizar['imagen'] =
                        $imagenNueva;
                }

                DB::transaction(
                    function () use (
                        $producto,
                        $datosActualizar
                    ) {
                        $producto->update(
                            $datosActualizar
                        );
                    }
                );

                /*
                 * La imagen anterior se elimina únicamente
                 * después de confirmar la actualización de BD.
                 */
                if (
                    $imagenNueva !== null
                    && $imagenAnterior
                    && $imagenAnterior !== $imagenNueva
                ) {
                    $this->eliminarArchivoSeguro(
                        $imagenAnterior,
                        'imagen_producto_anterior',
                        (int) $producto->id
                    );
                }

                $producto->refresh();

                $producto->load([
                    'categoria',
                    'unidadMedida',
                ]);

                Log::info(
                    'Producto actualizado correctamente.',
                    [
                        'producto_id' =>
                            $producto->id,

                        'empresa_id' =>
                            $empresaId,

                        'codigo' =>
                            $producto->codigo,

                        'is_inventariable' =>
                            (bool) $producto->is_inventariable,

                        'is_inventariable_db' =>
                            $producto->getRawOriginal(
                                'is_inventariable'
                            ),
                    ]
                );

                $datosDespues =
                    $producto->toArray();

                $this->registrarAuditoria(
                    $request,
                    'actualizar_producto',
                    'productos',
                    (int) $producto->id,
                    $datosAntes,
                    $datosDespues
                );

                return response()->json([
                    'success' => true,
                    'message' =>
                        'Producto actualizado correctamente.',
                    'data' => $producto,
                ]);
            } catch (QueryException $e) {
                if ($imagenNueva !== null) {
                    $this->eliminarArchivoSeguro(
                        $imagenNueva,
                        'imagen_producto_nueva_error_bd',
                        (int) $producto->id
                    );
                }

                Log::error(
                    'Error de base de datos al actualizar producto.',
                    [
                        'producto_id' =>
                            $id,

                        'usuario_id' =>
                            $usuario->id,

                        'empresa_id' =>
                            $empresaId,

                        'error' =>
                            $e->getMessage(),

                        'code' =>
                            $e->getCode(),
                    ]
                );

                return $this->responderErrorBaseDatos(
                    $request,
                    'actualizar',
                    $id
                );
            } catch (Throwable $e) {
                if ($imagenNueva !== null) {
                    $this->eliminarArchivoSeguro(
                        $imagenNueva,
                        'imagen_producto_nueva_error',
                        (int) $producto->id
                    );
                }

                Log::error(
                    'Error al actualizar producto.',
                    [
                        'producto_id' =>
                            $id,

                        'usuario_id' =>
                            $usuario->id,

                        'empresa_id' =>
                            $empresaId,

                        'error' =>
                            $e->getMessage(),

                        'exception' =>
                            get_class($e),
                    ]
                );

                $this->registrarAuditoriaError(
                    $request,
                    'actualizar_producto.error',
                    'productos',
                    $id,
                    [
                        'error_code' =>
                            'PRODUCTO_ACTUALIZAR_ERROR',
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'No fue posible actualizar el producto.',
                    'error_code' =>
                        'PRODUCTO_ACTUALIZAR_ERROR',
                ], 500);
            }
        } catch (ValidationException $e) {
            $this->registrarAuditoriaError(
                $request,
                'actualizar_producto.validacion_fallida',
                'productos',
                $id,
                [
                    'errores' =>
                        $e->errors(),
                ]
            );

            throw $e;
        } catch (QueryException $e) {
            return $this->responderErrorBaseDatos(
                $request,
                'buscar_actualizar',
                $id
            );
        } catch (Throwable $e) {
            Log::error(
                'Error al preparar actualización de producto.',
                [
                    'producto_id' =>
                        $id,

                    'usuario_id' =>
                        $usuario->id,

                    'empresa_id' =>
                        $empresaId,

                    'error' =>
                        $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible preparar la actualización del producto.',
                'error_code' =>
                    'PRODUCTO_ACTUALIZAR_PREPARACION_ERROR',
            ], 500);
        }
    }

    /**
     * Eliminar producto mediante SoftDeletes.
     */
    public function destroy(
        int $id,
        Request $request
    ) {
        $contexto = $this->validarContexto($request);

        if ($contexto) {
            return $contexto;
        }

        $usuario = $request->user();
        $empresaId = $this->obtenerEmpresaId($request);

        try {
            $producto = Producto::query()
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->find($id);

            if (!$producto) {
                $this->registrarAuditoriaError(
                    $request,
                    'eliminar_producto.no_encontrado',
                    'productos',
                    $id,
                    [
                        'error_code' =>
                            'PRODUCTO_NO_ENCONTRADO',
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Producto no encontrado.',
                    'error_code' =>
                        'PRODUCTO_NO_ENCONTRADO',
                ], 404);
            }

            $datosAntes =
                $producto->toArray();

            DB::transaction(
                function () use ($producto) {
                    $producto->delete();
                }
            );

            $producto->refresh();

            $this->registrarAuditoria(
                $request,
                'eliminar_producto',
                'productos',
                (int) $producto->id,
                $datosAntes,
                [
                    'deleted_at' =>
                        $producto->deleted_at
                            ? $producto->deleted_at
                                ->toDateTimeString()
                            : null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Producto eliminado correctamente.',
            ]);
        } catch (QueryException $e) {
            return $this->responderErrorBaseDatos(
                $request,
                'eliminar',
                $id
            );
        } catch (Throwable $e) {
            Log::error(
                'Error al eliminar producto.',
                [
                    'producto_id' =>
                        $id,

                    'usuario_id' =>
                        $usuario->id,

                    'empresa_id' =>
                        $empresaId,

                    'error' =>
                        $e->getMessage(),

                    'exception' =>
                        get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'eliminar_producto.error',
                'productos',
                $id,
                [
                    'error_code' =>
                        'PRODUCTO_ELIMINAR_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible eliminar el producto.',
                'error_code' =>
                    'PRODUCTO_ELIMINAR_ERROR',
            ], 500);
        }
    }

    /**
     * Restaurar producto eliminado.
     */
    public function restore(
        int $id,
        Request $request
    ) {
        $contexto = $this->validarContexto($request);

        if ($contexto) {
            return $contexto;
        }

        $usuario = $request->user();
        $empresaId = $this->obtenerEmpresaId($request);

        try {
            $producto = Producto::withTrashed()
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->find($id);

            if (!$producto) {
                $this->registrarAuditoriaError(
                    $request,
                    'restaurar_producto.no_encontrado',
                    'productos',
                    $id,
                    [
                        'error_code' =>
                            'PRODUCTO_NO_ENCONTRADO',
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Producto no encontrado.',
                    'error_code' =>
                        'PRODUCTO_NO_ENCONTRADO',
                ], 404);
            }

            if (!$producto->trashed()) {
                $this->registrarAuditoriaError(
                    $request,
                    'restaurar_producto.no_eliminado',
                    'productos',
                    $id,
                    [
                        'error_code' =>
                            'PRODUCTO_NO_ELIMINADO',
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'El producto no está eliminado.',
                    'error_code' =>
                        'PRODUCTO_NO_ELIMINADO',
                ], 422);
            }

            $datosAntes =
                $producto->toArray();

            DB::transaction(
                function () use ($producto) {
                    $producto->restore();
                }
            );

            $producto->refresh();

            $producto->load([
                'categoria',
                'unidadMedida',
            ]);

            $this->registrarAuditoria(
                $request,
                'restaurar_producto',
                'productos',
                (int) $producto->id,
                $datosAntes,
                $producto->toArray()
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Producto restaurado correctamente.',
                'data' => $producto,
            ]);
        } catch (QueryException $e) {
            return $this->responderErrorBaseDatos(
                $request,
                'restaurar',
                $id
            );
        } catch (Throwable $e) {
            Log::error(
                'Error al restaurar producto.',
                [
                    'producto_id' =>
                        $id,

                    'usuario_id' =>
                        $usuario->id,

                    'empresa_id' =>
                        $empresaId,

                    'error' =>
                        $e->getMessage(),

                    'exception' =>
                        get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'restaurar_producto.error',
                'productos',
                $id,
                [
                    'error_code' =>
                        'PRODUCTO_RESTAURAR_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible restaurar el producto.',
                'error_code' =>
                    'PRODUCTO_RESTAURAR_ERROR',
            ], 500);
        }
    }

    /**
     * Listar productos con stock bajo.
     */
    public function stockBajo(Request $request)
    {
        $contexto = $this->validarContexto($request);

        if ($contexto) {
            return $contexto;
        }

        try {
            $empresaId =
                $this->obtenerEmpresaId($request);

            $productos = Producto::query()
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->whereColumn(
                    'stock',
                    '<=',
                    'stock_minimo'
                )
                ->where(
                    'stock',
                    '>',
                    0
                )
                ->with([
                    'categoria',
                    'unidadMedida',
                ])
                ->orderBy('nombre')
                ->get();

            $this->registrarAuditoria(
                $request,
                'consultar_stock_bajo',
                'productos',
                null,
                null,
                [
                    'cantidad_resultados' =>
                        $productos->count(),
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $productos,
            ]);
        } catch (QueryException $e) {
            return $this->responderErrorBaseDatos(
                $request,
                'stock_bajo'
            );
        } catch (Throwable $e) {
            Log::error(
                'Error al obtener productos con stock bajo.',
                [
                    'usuario_id' =>
                        $request->user()?->id,

                    'empresa_id' =>
                        $request->user()?->empresa_id,

                    'error' =>
                        $e->getMessage(),

                    'exception' =>
                        get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'consultar_stock_bajo.error',
                'productos',
                null,
                [
                    'error_code' =>
                        'PRODUCTO_STOCK_BAJO_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible obtener los productos con stock bajo.',
                'error_code' =>
                    'PRODUCTO_STOCK_BAJO_ERROR',
            ], 500);
        }
    }

    /**
     * Listar productos agotados.
     */
    public function agotados(Request $request)
    {
        $contexto = $this->validarContexto($request);

        if ($contexto) {
            return $contexto;
        }

        try {
            $empresaId =
                $this->obtenerEmpresaId($request);

            $productos = Producto::query()
                ->where(
                    'empresa_id',
                    $empresaId
                )
                ->where(
                    'stock',
                    0
                )
                ->with([
                    'categoria',
                    'unidadMedida',
                ])
                ->orderBy('nombre')
                ->get();

            $this->registrarAuditoria(
                $request,
                'consultar_productos_agotados',
                'productos',
                null,
                null,
                [
                    'cantidad_resultados' =>
                        $productos->count(),
                ]
            );

            return response()->json([
                'success' => true,
                'data' => $productos,
            ]);
        } catch (QueryException $e) {
            return $this->responderErrorBaseDatos(
                $request,
                'agotados'
            );
        } catch (Throwable $e) {
            Log::error(
                'Error al obtener productos agotados.',
                [
                    'usuario_id' =>
                        $request->user()?->id,

                    'empresa_id' =>
                        $request->user()?->empresa_id,

                    'error' =>
                        $e->getMessage(),

                    'exception' =>
                        get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'consultar_productos_agotados.error',
                'productos',
                null,
                [
                    'error_code' =>
                        'PRODUCTO_AGOTADOS_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible obtener los productos agotados.',
                'error_code' =>
                    'PRODUCTO_AGOTADOS_ERROR',
            ], 500);
        }
    }

    /**
     * Ajustar stock de un producto.
     *
     * Se utiliza bloqueo pesimista para evitar condiciones
     * de carrera cuando dos operaciones modifican el mismo stock.
     */
    public function ajustarStock(
        Request $request,
        int $id
    ) {
        $contexto = $this->validarContexto($request);

        if ($contexto) {
            return $contexto;
        }

        $usuario = $request->user();
        $empresaId =
            $this->obtenerEmpresaId($request);

        try {
            $validated = $request->validate([
                'cantidad' => [
                    'required',
                    'integer',
                    'not_in:0',
                ],

                'motivo' => [
                    'sometimes',
                    'nullable',
                    'string',
                    'max:500',
                ],
            ]);

            if (
                array_key_exists(
                    'motivo',
                    $validated
                )
                && $validated['motivo'] !== null
            ) {
                $validated['motivo'] =
                    trim(
                        $validated['motivo']
                    );
            }

            $resultado = DB::transaction(
                function () use (
                    $empresaId,
                    $id,
                    $validated
                ) {
                    /*
                     * La consulta queda completamente aislada
                     * por empresa y bloqueada durante la transacción.
                     */
                    $producto =
                        Producto::query()
                            ->where(
                                'empresa_id',
                                $empresaId
                            )
                            ->whereKey($id)
                            ->lockForUpdate()
                            ->first();

                    if (!$producto) {
                        throw new \RuntimeException(
                            'PRODUCTO_NO_ENCONTRADO'
                        );
                    }

                    $stockAnterior =
                        (int) (
                            $producto->stock ?? 0
                        );

                    $cantidad =
                        (int) (
                            $validated['cantidad']
                        );

                    $stockNuevo =
                        $stockAnterior +
                        $cantidad;

                    if ($stockNuevo < 0) {
                        throw new \RuntimeException(
                            'STOCK_INSUFICIENTE'
                        );
                    }

                    $producto->stock =
                        $stockNuevo;

                    $producto->save();

                    return [
                        'producto' =>
                            $producto,

                        'stock_anterior' =>
                            $stockAnterior,

                        'cantidad' =>
                            $cantidad,

                        'stock_nuevo' =>
                            $stockNuevo,
                    ];
                }
            );

            $producto =
                $resultado['producto'];

            $this->registrarAuditoria(
                $request,
                'ajustar_stock',
                'productos',
                (int) $producto->id,
                [
                    'stock' =>
                        $resultado[
                            'stock_anterior'
                        ],
                ],
                [
                    'stock' =>
                        $resultado[
                            'stock_nuevo'
                        ],

                    'cantidad_ajuste' =>
                        $resultado['cantidad'],

                    'motivo' =>
                        $validated['motivo']
                            ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' =>
                    'Stock ajustado correctamente.',
                'data' => [
                    'producto_id' =>
                        $producto->id,

                    'stock_anterior' =>
                        $resultado[
                            'stock_anterior'
                        ],

                    'cantidad_ajuste' =>
                        $resultado['cantidad'],

                    'stock_nuevo' =>
                        $resultado[
                            'stock_nuevo'
                        ],

                    'motivo' =>
                        $validated['motivo']
                            ?? null,
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaError(
                $request,
                'ajustar_stock.validacion_fallida',
                'productos',
                $id,
                [
                    'errores' =>
                        $e->errors(),
                ]
            );

            throw $e;
        } catch (QueryException $e) {
            return $this->responderErrorBaseDatos(
                $request,
                'ajustar_stock',
                $id
            );
        } catch (Throwable $e) {
            /*
             * Errores de negocio controlados.
             */
            if (
                $e->getMessage()
                === 'PRODUCTO_NO_ENCONTRADO'
            ) {
                $this->registrarAuditoriaError(
                    $request,
                    'ajustar_stock.producto_no_encontrado',
                    'productos',
                    $id,
                    [
                        'error_code' =>
                            'PRODUCTO_NO_ENCONTRADO',
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'Producto no encontrado.',
                    'error_code' =>
                        'PRODUCTO_NO_ENCONTRADO',
                ], 404);
            }

            if (
                $e->getMessage()
                === 'STOCK_INSUFICIENTE'
            ) {
                $this->registrarAuditoriaError(
                    $request,
                    'ajustar_stock.stock_insuficiente',
                    'productos',
                    $id,
                    [
                        'error_code' =>
                            'STOCK_INSUFICIENTE',

                        'cantidad' =>
                            $validated['cantidad']
                                ?? null,
                    ]
                );

                return response()->json([
                    'success' => false,
                    'message' =>
                        'El ajuste produciría un stock negativo.',
                    'error_code' =>
                        'STOCK_INSUFICIENTE',
                ], 422);
            }

            Log::error(
                'Error al ajustar stock.',
                [
                    'producto_id' =>
                        $id,

                    'usuario_id' =>
                        $usuario->id,

                    'empresa_id' =>
                        $empresaId,

                    'cantidad' =>
                        $validated['cantidad']
                            ?? null,

                    'motivo' =>
                        $validated['motivo']
                            ?? null,

                    'error' =>
                        $e->getMessage(),

                    'exception' =>
                        get_class($e),
                ]
            );

            $this->registrarAuditoriaError(
                $request,
                'ajustar_stock.error',
                'productos',
                $id,
                [
                    'error_code' =>
                        'PRODUCTO_AJUSTAR_STOCK_ERROR',
                ]
            );

            return response()->json([
                'success' => false,
                'message' =>
                    'No fue posible ajustar el stock.',
                'error_code' =>
                    'PRODUCTO_AJUSTAR_STOCK_ERROR',
            ], 500);
        }
    }

    /**
     * Eliminar archivo de Storage de forma segura.
     *
     * Un fallo al eliminar un archivo físico nunca debe
     * invalidar una operación que ya fue confirmada en BD.
     */
    private function eliminarArchivoSeguro(
        string $path,
        string $operacion,
        ?int $productoId = null
    ): void {
        try {
            if (
                $path === ''
                || !Storage::disk('public')->exists($path)
            ) {
                return;
            }

            Storage::disk('public')->delete($path);
        } catch (Throwable $e) {
            Log::warning(
                'No fue posible eliminar archivo de producto.',
                [
                    'operacion' =>
                        $operacion,

                    'producto_id' =>
                        $productoId,

                    'archivo' =>
                        $path,

                    'error' =>
                        $e->getMessage(),

                    'exception' =>
                        get_class($e),
                ]
            );
        }
    }
}
