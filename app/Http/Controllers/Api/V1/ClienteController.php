<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cliente;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    /**
     * Listar clientes con filtros.
     */
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $query = Cliente::where('empresa_id', $empresaId);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('nombre', 'like', "%{$request->search}%")
                  ->orWhere('email', 'like', "%{$request->search}%")
                  ->orWhere('telefono', 'like', "%{$request->search}%")
                  ->orWhere('rfc', 'like', "%{$request->search}%");
            });
        }

        if ($request->tipo) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->activo !== null) {
            $query->where('activo', $request->activo);
        }

        $clientes = $query->orderBy('nombre', 'asc')->paginate(20);

        return response()->json([
            'data' => $clientes->items(),
            'current_page' => $clientes->currentPage(),
            'last_page' => $clientes->lastPage(),
            'per_page' => $clientes->perPage(),
            'total' => $clientes->total(),
        ]);
    }

    /**
     * Obtener un cliente específico.
     */
    public function show($id, Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $cliente = Cliente::where('empresa_id', $empresaId)->findOrFail($id);

        return response()->json($cliente);
    }

    /**
     * Crear un nuevo cliente.
     */
    public function store(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string',
            'rfc' => 'nullable|string|max:13',
            'tipo' => 'nullable|in:particular,empresa',
            'limite_credito' => 'nullable|numeric|min:0',
            'notas' => 'nullable|string',
            'activo' => 'nullable|boolean',
        ]);

        $data = [
            'empresa_id' => $empresaId,
            'nombre' => $validated['nombre'],
            'email' => $validated['email'] ?? null,
            'telefono' => $validated['telefono'] ?? null,
            'direccion' => $validated['direccion'] ?? null,
            'rfc' => $validated['rfc'] ?? null,
            'tipo' => $validated['tipo'] ?? 'particular',
            'limite_credito' => $validated['limite_credito'] ?? 0,
            'notas' => $validated['notas'] ?? null,
            'activo' => $validated['activo'] ?? true,
        ];

        $cliente = Cliente::create($data);

        return response()->json([
            'message' => 'Cliente creado correctamente',
            'cliente' => $cliente
        ], 201);
    }

    /**
     * Actualizar un cliente.
     */
    public function update(Request $request, $id)
    {
        $empresaId = $request->user()->empresa_id;

        $cliente = Cliente::where('empresa_id', $empresaId)->findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string',
            'rfc' => 'nullable|string|max:13',
            'tipo' => 'nullable|in:particular,empresa',
            'limite_credito' => 'nullable|numeric|min:0',
            'notas' => 'nullable|string',
            'activo' => 'nullable|boolean',
        ]);

        $cliente->update($validated);

        return response()->json([
            'message' => 'Cliente actualizado correctamente',
            'cliente' => $cliente
        ]);
    }

    /**
     * Eliminar un cliente (soft delete).
     */
    public function destroy($id, Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $cliente = Cliente::where('empresa_id', $empresaId)->findOrFail($id);
        $cliente->delete();

        return response()->json(['message' => 'Cliente eliminado correctamente']);
    }

    /**
     * Restaurar un cliente eliminado.
     */
    public function restore($id, Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $cliente = Cliente::where('empresa_id', $empresaId)
            ->withTrashed()
            ->findOrFail($id);

        $cliente->restore();

        return response()->json(['message' => 'Cliente restaurado correctamente']);
    }

    /**
     * Obtener historial de compras de un cliente.
     */
    public function historial($id, Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $cliente = Cliente::where('empresa_id', $empresaId)->findOrFail($id);

        $ventas = $cliente->ventas()
            ->with(['usuario', 'detalles.producto'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($ventas);
    }
}