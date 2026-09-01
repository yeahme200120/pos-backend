<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

            $this->auditoria->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosDespues,
                $usuario?->empresa_id,
                $usuario?->id
            );
        } catch (Throwable $e) {
            Log::warning('No fue posible registrar auditoría', [
                'accion' => $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'usuario_id' => $request->user()?->id,
                'empresa_id' => $request->user()?->empresa_id,
                'error' => $e->getMessage(),
            ]);
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
     * Listar productos.
     */
    public function index(Request $request)
    {
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

        try {
            $usuario = $request->user();
            $empresaId = $this->obtenerEmpresaId($request);

            if (!$empresaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asociada.',
                ], 403);
            }

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

                $query->where(function ($q) use ($search) {
                    $q->where(
                        'codigo',
                        'like',
                        "%{$search}%"
                    )
                        ->orWhere(
                            'nombre',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'descripcion',
                            'like',
                            "%{$search}%"
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
                    $validated['categoria_id']
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

            $perPage = $validated['per_page'] ?? 20;

            $productos = $query
                ->orderBy('nombre')
                ->paginate($perPage);

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

            return response()->json([
                'success' => true,
                'data' => [
                    'productos' => $productos->items(),
                    'categorias' => $categorias,
                    'unidades' => $unidades,
                    'pagination' => [
                        'current_page' => $productos->currentPage(),
                        'last_page' => $productos->lastPage(),
                        'per_page' => $productos->perPage(),
                        'total' => $productos->total(),
                        'from' => $productos->firstItem(),
                        'to' => $productos->lastItem(),
                    ],
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Error al listar productos', [
                'usuario_id' => $request->user()?->id,
                'empresa_id' => $request->user()?->empresa_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener los productos.',
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
        try {
            $empresaId = $this->obtenerEmpresaId($request);

            if (!$empresaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asociada.',
                ], 403);
            }

            $producto = Producto::query()
                ->where('empresa_id', $empresaId)
                ->with([
                    'categoria',
                    'unidadMedida',
                ])
                ->find($id);

            if (!$producto) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $producto,
            ]);
        } catch (Throwable $e) {
            Log::error('Error al consultar producto', [
                'producto_id' => $id,
                'usuario_id' => $request->user()?->id,
                'empresa_id' => $request->user()?->empresa_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener el producto.',
            ], 500);
        }
    }

    /**
     * Crear producto.
     */
    public function store(Request $request)
    {
        $usuario = $request->user();
        $empresaId = $this->obtenerEmpresaId($request);

        if (!$empresaId) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        $validated = $request->validate([
            'codigo' => [
                'required',
                'string',
                'max:100',
                Rule::unique('productos', 'codigo')
                    ->where(
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
                'nullable',
                'integer',
                'min:1',
                Rule::exists('categorias', 'id')
                    ->where(
                        fn ($query) =>
                        $query->where(
                            'empresa_id',
                            $empresaId
                        )
                    ),
            ],

            'unidad_medida_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('unidades_medida', 'id')
                    ->where(
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

            'imagen' => [
                'nullable',
                'file',
                'mimes:jpeg,png,jpg,gif,svg',
                'max:2048',
            ],
        ]);

        $validated['codigo'] = trim(
            $validated['codigo']
        );

        $validated['nombre'] = trim(
            $validated['nombre']
        );

        if (isset($validated['descripcion'])) {
            $validated['descripcion'] = trim(
                $validated['descripcion']
            );
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
                'empresa_id' => $empresaId,
                'codigo' => $validated['codigo'],
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'precio' => $validated['precio'],
                'costo' => $validated['costo'] ?? null,
                'impuesto' => $validated['impuesto'] ?? null,
                'stock' => $validated['stock'] ?? 0,
                'stock_minimo' => $validated['stock_minimo'] ?? 0,
                'categoria_id' => $validated['categoria_id'] ?? null,
                'unidad_medida_id' => $validated['unidad_medida_id'] ?? null,
                'activo' => $validated['activo'] ?? true,
                'imagen' => $imagenPath,
            ];

            $producto = DB::transaction(
                function () use ($datos) {
                    return Producto::create($datos);
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
                'message' => 'Producto creado correctamente.',
                'data' => $producto,
            ], 201);
        } catch (Throwable $e) {
            if ($imagenPath) {
                try {
                    Storage::disk('public')->delete(
                        $imagenPath
                    );
                } catch (Throwable $deleteException) {
                    Log::warning(
                        'No fue posible eliminar imagen de producto',
                        [
                            'imagen' => $imagenPath,
                            'error' => $deleteException->getMessage(),
                        ]
                    );
                }
            }

            Log::error('Error al crear producto', [
                'usuario_id' => $usuario->id,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible crear el producto.',
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
        $usuario = $request->user();
        $empresaId = $this->obtenerEmpresaId($request);

        if (!$empresaId) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        $producto = Producto::query()
            ->where('empresa_id', $empresaId)
            ->find($id);

        if (!$producto) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        $validated = $request->validate([
            'codigo' => [
                'required',
                'string',
                'max:100',
                Rule::unique('productos', 'codigo')
                    ->where(
                        fn ($query) =>
                        $query->where(
                            'empresa_id',
                            $empresaId
                        )
                    )
                    ->ignore($producto->id),
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
                'nullable',
                'integer',
                'min:1',
                Rule::exists('categorias', 'id')
                    ->where(
                        fn ($query) =>
                        $query->where(
                            'empresa_id',
                            $empresaId
                        )
                    ),
            ],

            'unidad_medida_id' => [
                'nullable',
                'integer',
                'min:1',
                Rule::exists('unidades_medida', 'id')
                    ->where(
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

            'imagen' => [
                'nullable',
                'file',
                'mimes:jpeg,png,jpg,gif,svg',
                'max:2048',
            ],
        ]);

        $validated['codigo'] = trim(
            $validated['codigo']
        );

        $validated['nombre'] = trim(
            $validated['nombre']
        );

        if (isset($validated['descripcion'])) {
            $validated['descripcion'] = trim(
                $validated['descripcion']
            );
        }

        $datosAntes = $producto->toArray();
        $imagenAnterior = $producto->imagen;
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
                'codigo' => $validated['codigo'],
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'] ?? null,
                'precio' => $validated['precio'],
                'costo' => $validated['costo'] ?? null,
                'impuesto' => $validated['impuesto'] ?? null,
                'stock_minimo' => $validated['stock_minimo'] ?? 0,
                'categoria_id' => $validated['categoria_id'] ?? null,
                'unidad_medida_id' => $validated['unidad_medida_id'] ?? null,
            ];

            if (array_key_exists('activo', $validated)) {
                $datosActualizar['activo'] =
                    $validated['activo'];
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

            if (
                $imagenNueva !== null
                && $imagenAnterior
                && $imagenAnterior !== $imagenNueva
            ) {
                try {
                    Storage::disk('public')->delete(
                        $imagenAnterior
                    );
                } catch (Throwable $e) {
                    Log::warning(
                        'No fue posible eliminar imagen anterior',
                        [
                            'producto_id' => $producto->id,
                            'imagen_anterior' => $imagenAnterior,
                            'error' => $e->getMessage(),
                        ]
                    );
                }
            }

            $producto->refresh();

            $producto->load([
                'categoria',
                'unidadMedida',
            ]);

            $datosDespues = $producto->toArray();

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
                'message' => 'Producto actualizado correctamente.',
                'data' => $producto,
            ]);
        } catch (Throwable $e) {
            if ($imagenNueva !== null) {
                try {
                    Storage::disk('public')->delete(
                        $imagenNueva
                    );
                } catch (Throwable $deleteException) {
                    Log::warning(
                        'No fue posible eliminar nueva imagen',
                        [
                            'producto_id' => $id,
                            'imagen' => $imagenNueva,
                            'error' => $deleteException->getMessage(),
                        ]
                    );
                }
            }

            Log::error('Error al actualizar producto', [
                'producto_id' => $id,
                'usuario_id' => $usuario->id,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible actualizar el producto.',
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
        $usuario = $request->user();
        $empresaId = $this->obtenerEmpresaId($request);

        if (!$empresaId) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        $producto = Producto::query()
            ->where('empresa_id', $empresaId)
            ->find($id);

        if (!$producto) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        try {
            $datosAntes = $producto->toArray();

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
                    'deleted_at' => $producto->deleted_at
                        ? $producto->deleted_at
                            ->toDateTimeString()
                        : null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Producto eliminado correctamente.',
            ]);
        } catch (Throwable $e) {
            Log::error('Error al eliminar producto', [
                'producto_id' => $id,
                'usuario_id' => $usuario->id,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible eliminar el producto.',
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
        $usuario = $request->user();
        $empresaId = $this->obtenerEmpresaId($request);

        if (!$empresaId) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

        $producto = Producto::withTrashed()
            ->where('empresa_id', $empresaId)
            ->find($id);

        if (!$producto) {
            return response()->json([
                'success' => false,
                'message' => 'Producto no encontrado.',
            ], 404);
        }

        if (!$producto->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'El producto no está eliminado.',
            ], 422);
        }

        try {
            $datosAntes = $producto->toArray();

            DB::transaction(
                function () use ($producto) {
                    $producto->restore();
                }
            );

            $producto->refresh();

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
                'message' => 'Producto restaurado correctamente.',
                'data' => $producto,
            ]);
        } catch (Throwable $e) {
            Log::error('Error al restaurar producto', [
                'producto_id' => $id,
                'usuario_id' => $usuario->id,
                'empresa_id' => $empresaId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible restaurar el producto.',
            ], 500);
        }
    }

    /**
     * Listar productos con stock bajo.
     */
    public function stockBajo(Request $request)
    {
        try {
            $empresaId = $this->obtenerEmpresaId($request);

            if (!$empresaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asociada.',
                ], 403);
            }

            $productos = Producto::query()
                ->where('empresa_id', $empresaId)
                ->whereColumn(
                    'stock',
                    '<=',
                    'stock_minimo'
                )
                ->where('stock', '>', 0)
                ->with([
                    'categoria',
                    'unidadMedida',
                ])
                ->orderBy('nombre')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $productos,
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Error al obtener productos con stock bajo',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener los productos con stock bajo.',
            ], 500);
        }
    }

    /**
     * Listar productos agotados.
     */
    public function agotados(Request $request)
    {
        try {
            $empresaId = $this->obtenerEmpresaId($request);

            if (!$empresaId) {
                return response()->json([
                    'success' => false,
                    'message' => 'El usuario no tiene una empresa asociada.',
                ], 403);
            }

            $productos = Producto::query()
                ->where('empresa_id', $empresaId)
                ->where('stock', 0)
                ->with([
                    'categoria',
                    'unidadMedida',
                ])
                ->orderBy('nombre')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $productos,
            ]);
        } catch (Throwable $e) {
            Log::error(
                'Error al obtener productos agotados',
                [
                    'usuario_id' => $request->user()?->id,
                    'empresa_id' => $request->user()?->empresa_id,
                    'error' => $e->getMessage(),
                ]
            );

            return response()->json([
                'success' => false,
                'message' => 'No fue posible obtener los productos agotados.',
            ], 500);
        }
    }

    /**
     * Ajustar stock de un producto.
     */
    public function ajustarStock(
        Request $request,
        int $id
    ) {
        $usuario = $request->user();
        $empresaId = $this->obtenerEmpresaId($request);

        if (!$empresaId) {
            return response()->json([
                'success' => false,
                'message' => 'El usuario no tiene una empresa asociada.',
            ], 403);
        }

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
            array_key_exists('motivo', $validated)
            && $validated['motivo'] !== null
        ) {
            $validated['motivo'] = trim(
                $validated['motivo']
            );
        }

        try {
            $resultado = DB::transaction(
                function () use (
                    $empresaId,
                    $id,
                    $validated
                ) {
                    $producto = Producto::query()
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

                    $stockAnterior = (int) (
                        $producto->stock ?? 0
                    );

                    $cantidad = (int) (
                        $validated['cantidad']
                    );

                    $stockNuevo =
                        $stockAnterior + $cantidad;

                    if ($stockNuevo < 0) {
                        throw new \RuntimeException(
                            'STOCK_INSUFICIENTE'
                        );
                    }

                    $producto->stock = $stockNuevo;
                    $producto->save();

                    return [
                        'producto' => $producto,
                        'stock_anterior' => $stockAnterior,
                        'cantidad' => $cantidad,
                        'stock_nuevo' => $stockNuevo,
                    ];
                }
            );

            $producto = $resultado['producto'];

            $this->registrarAuditoria(
                $request,
                'ajustar_stock',
                'productos',
                (int) $producto->id,
                [
                    'stock' => $resultado['stock_anterior'],
                ],
                [
                    'stock' => $resultado['stock_nuevo'],
                    'cantidad_ajuste' => $resultado['cantidad'],
                    'motivo' => $validated['motivo'] ?? null,
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Stock ajustado correctamente.',
                'data' => [
                    'producto_id' => $producto->id,
                    'stock_anterior' =>
                        $resultado['stock_anterior'],
                    'cantidad_ajuste' =>
                        $resultado['cantidad'],
                    'stock_nuevo' =>
                        $resultado['stock_nuevo'],
                    'motivo' =>
                        $validated['motivo'] ?? null,
                ],
            ]);
        } catch (Throwable $e) {
            if (
                $e->getMessage()
                === 'PRODUCTO_NO_ENCONTRADO'
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'Producto no encontrado.',
                ], 404);
            }

            if (
                $e->getMessage()
                === 'STOCK_INSUFICIENTE'
            ) {
                return response()->json([
                    'success' => false,
                    'message' => 'El ajuste produciría un stock negativo.',
                ], 422);
            }

            Log::error('Error al ajustar stock', [
                'producto_id' => $id,
                'usuario_id' => $usuario->id,
                'empresa_id' => $empresaId,
                'cantidad' =>
                    $validated['cantidad'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible ajustar el stock.',
            ], 500);
        }
    }
}