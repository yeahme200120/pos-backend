<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Producto;
use App\Models\Categoria;
use App\Models\UnidadMedida;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ProductoController extends Controller
{
    /**
     * Listar productos con filtros y paginación.
     */
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $query = Producto::where('empresa_id', $empresaId)
            ->with(['categoria', 'unidadMedida']);

        // Filtros
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('nombre', 'like', "%{$request->search}%")
                  ->orWhere('codigo', 'like', "%{$request->search}%")
                  ->orWhere('descripcion', 'like', "%{$request->search}%");
            });
        }

        if ($request->categoria_id) {
            $query->where('categoria_id', $request->categoria_id);
        }

        if ($request->activo !== null) {
            $query->where('activo', $request->activo);
        }

        if ($request->stock_minimo) {
            $query->where('stock', '<=', 'stock_minimo');
        }

        $productos = $query->orderBy('nombre', 'asc')->paginate(20);

        // Obtener categorías y unidades para selectores
        $categorias = Categoria::where('empresa_id', $empresaId)->get();
        $unidades = UnidadMedida::where('empresa_id', $empresaId)->get();

        return response()->json([
            'data' => $productos->items(),
            'categorias' => $categorias,
            'unidades' => $unidades,
            'current_page' => $productos->currentPage(),
            'last_page' => $productos->lastPage(),
            'per_page' => $productos->perPage(),
            'total' => $productos->total(),
        ]);
    }

    /**
     * Obtener un producto específico.
     */
    public function show($id, Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $producto = Producto::where('empresa_id', $empresaId)
            ->with(['categoria', 'unidadMedida'])
            ->findOrFail($id);

        return response()->json($producto);
    }

    /**
     * Crear un nuevo producto.
     */
    public function store(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $validated = $request->validate([
            'codigo' => 'required|string|unique:productos,codigo',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'categoria_id' => 'nullable|exists:categorias,id',
            'unidad_medida_id' => 'nullable|exists:unidades_medida,id',
            'precio' => 'required|numeric|min:0',
            'costo' => 'nullable|numeric|min:0',
            'impuesto' => 'nullable|numeric|min:0|max:100',
            'stock' => 'nullable|integer|min:0',
            'stock_minimo' => 'nullable|integer|min:0',
            'activo' => 'nullable|boolean',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $data = [
            'empresa_id' => $empresaId,
            'codigo' => $validated['codigo'],
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'categoria_id' => $validated['categoria_id'] ?? null,
            'unidad_medida_id' => $validated['unidad_medida_id'] ?? null,
            'precio' => $validated['precio'],
            'costo' => $validated['costo'] ?? 0,
            'impuesto' => $validated['impuesto'] ?? 0,
            'stock' => $validated['stock'] ?? 0,
            'stock_minimo' => $validated['stock_minimo'] ?? 0,
            'activo' => $validated['activo'] ?? true,
        ];

        // Guardar imagen si existe
        if ($request->hasFile('imagen')) {
            $path = $request->file('imagen')->store('productos', 'public');
            $data['imagen'] = $path;
        }

        $producto = Producto::create($data);

        return response()->json([
            'message' => 'Producto creado correctamente',
            'producto' => $producto->load(['categoria', 'unidadMedida'])
        ], 201);
    }

    /**
     * Actualizar un producto.
     */
    public function update(Request $request, $id)
    {
        $empresaId = $request->user()->empresa_id;

        $producto = Producto::where('empresa_id', $empresaId)->findOrFail($id);

        $validated = $request->validate([
            'codigo' => ['required', 'string', Rule::unique('productos')->ignore($producto->id)],
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'categoria_id' => 'nullable|exists:categorias,id',
            'unidad_medida_id' => 'nullable|exists:unidades_medida,id',
            'precio' => 'required|numeric|min:0',
            'costo' => 'nullable|numeric|min:0',
            'impuesto' => 'nullable|numeric|min:0|max:100',
            'stock' => 'nullable|integer|min:0',
            'stock_minimo' => 'nullable|integer|min:0',
            'activo' => 'nullable|boolean',
            'imagen' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
        ]);

        $data = [
            'codigo' => $validated['codigo'],
            'nombre' => $validated['nombre'],
            'descripcion' => $validated['descripcion'] ?? null,
            'categoria_id' => $validated['categoria_id'] ?? null,
            'unidad_medida_id' => $validated['unidad_medida_id'] ?? null,
            'precio' => $validated['precio'],
            'costo' => $validated['costo'] ?? 0,
            'impuesto' => $validated['impuesto'] ?? 0,
            'stock' => $validated['stock'] ?? 0,
            'stock_minimo' => $validated['stock_minimo'] ?? 0,
            'activo' => $validated['activo'] ?? true,
        ];

        // Guardar imagen si existe
        if ($request->hasFile('imagen')) {
            // Eliminar imagen anterior
            if ($producto->imagen) {
                Storage::disk('public')->delete($producto->imagen);
            }
            $path = $request->file('imagen')->store('productos', 'public');
            $data['imagen'] = $path;
        }

        $producto->update($data);

        return response()->json([
            'message' => 'Producto actualizado correctamente',
            'producto' => $producto->load(['categoria', 'unidadMedida'])
        ]);
    }

    /**
     * Eliminar un producto (soft delete).
     */
    public function destroy($id, Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $producto = Producto::where('empresa_id', $empresaId)->findOrFail($id);
        $producto->delete();

        return response()->json(['message' => 'Producto eliminado correctamente']);
    }

    /**
     * Restaurar un producto eliminado.
     */
    public function restore($id, Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $producto = Producto::where('empresa_id', $empresaId)
            ->withTrashed()
            ->findOrFail($id);

        $producto->restore();

        return response()->json(['message' => 'Producto restaurado correctamente']);
    }

    /**
     * Obtener productos con stock bajo.
     */
    public function stockBajo(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $productos = Producto::where('empresa_id', $empresaId)
            ->whereColumn('stock', '<=', 'stock_minimo')
            ->where('stock', '>', 0)
            ->orderBy('stock', 'asc')
            ->get();

        return response()->json($productos);
    }

    /**
     * Obtener productos agotados (stock = 0).
     */
    public function agotados(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $productos = Producto::where('empresa_id', $empresaId)
            ->where('stock', 0)
            ->orderBy('nombre', 'asc')
            ->get();

        return response()->json($productos);
    }

    /**
     * Ajustar stock manualmente.
     */
    public function ajustarStock(Request $request, $id)
    {
        $empresaId = $request->user()->empresa_id;

        $producto = Producto::where('empresa_id', $empresaId)->findOrFail($id);

        $request->validate([
            'cantidad' => 'required|integer|not_in:0',
            'motivo' => 'nullable|string|max:255',
        ]);

        $producto->stock += $request->cantidad;
        $producto->save();

        // Registrar en log (puedes crear un modelo MovimientoStock)
        // Log::info("Ajuste de stock", ['producto' => $producto->id, 'cantidad' => $request->cantidad]);

        return response()->json([
            'message' => 'Stock ajustado correctamente',
            'producto' => $producto
        ]);
    }
}