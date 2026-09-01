<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ClienteController extends Controller
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * Listar clientes con filtros.
     * - Superadmin: ve todos los clientes (puede filtrar por empresa_id)
     * - Usuario normal: solo ve los de su empresa
     */
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:255',
            'tipo' => 'nullable|in:particular,empresa',
            'activo' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
            'empresa_id' => 'nullable|integer|exists:empresas,id',
        ]);

        try {
            $user = $request->user();
            $esSuperadmin = $user->rol === 'superadmin';

            $query = Cliente::query();

            // Si NO es superadmin, filtrar por su empresa
            if (!$esSuperadmin) {
                $empresaId = (int) $user->empresa_id;
                if ($empresaId <= 0) {
                    return response()->json([
                        'message' => 'El usuario no tiene una empresa asociada.',
                    ], 422);
                }
                $query->where('empresa_id', $empresaId);
            } else {
                // Si es superadmin y se envía empresa_id, filtrar por esa empresa
                if (!empty($validated['empresa_id'])) {
                    $query->where('empresa_id', (int) $validated['empresa_id']);
                }
                // Si no se envía, no se filtra (ver todos)
            }

            // Filtros comunes
            if (!empty($validated['search'])) {
                $search = trim($validated['search']);
                $like = '%' . $search . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('nombre', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('telefono', 'like', $like)
                        ->orWhere('rfc', 'like', $like);
                });
            }

            if (!empty($validated['tipo'])) {
                $query->where('tipo', $validated['tipo']);
            }

            // Corregir filtro activo: solo si el valor no es null
            if (array_key_exists('activo', $validated) && !is_null($validated['activo'])) {
                $query->where('activo', (bool) $validated['activo']);
            }

            $perPage = (int) ($validated['per_page'] ?? 20);
            $clientes = $query->orderBy('nombre', 'asc')->paginate($perPage);

            // Log para depuración
            Log::info('📋 Clientes obtenidos', [
                'total' => $clientes->total(),
                'usuario_rol' => $user->rol,
                'empresa_id' => $esSuperadmin ? ($validated['empresa_id'] ?? 'todas') : $user->empresa_id,
            ]);

            $this->registrarAuditoria(
                $request,
                'clientes.consultados',
                'clientes',
                null,
                null,
                [
                    'usuario_rol' => $user->rol,
                    'empresa_id' => $validated['empresa_id'] ?? ($esSuperadmin ? 'todas' : $user->empresa_id),
                    'search' => $validated['search'] ?? null,
                    'tipo' => $validated['tipo'] ?? null,
                    'activo' => $validated['activo'] ?? null,
                    'pagina' => $clientes->currentPage(),
                    'por_pagina' => $clientes->perPage(),
                    'total' => $clientes->total(),
                ]
            );

            return response()->json([
                'data' => $clientes->items(),
                'current_page' => $clientes->currentPage(),
                'last_page' => $clientes->lastPage(),
                'per_page' => $clientes->perPage(),
                'total' => $clientes->total(),
            ]);
        } catch (Throwable $e) {
            Log::error('❌ Error al listar clientes.', [
                'usuario_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'message' => 'Error al cargar clientes.',
            ], 500);
        }
    }

    /**
     * Obtener un cliente específico.
     */
    public function show($id, Request $request)
    {
        try {
            $user = $request->user();
            $esSuperadmin = $user->rol === 'superadmin';

            $query = Cliente::query();

            if (!$esSuperadmin) {
                $empresaId = (int) $user->empresa_id;
                $query->where('empresa_id', $empresaId);
            }

            $cliente = $query->findOrFail($id);

            $this->registrarAuditoria(
                $request,
                'cliente.consultado',
                'clientes',
                (int) $cliente->id,
                null,
                $cliente->toArray()
            );

            return response()->json($cliente);
        } catch (Throwable $e) {
            Log::error('Error al consultar cliente.', [
                'usuario_id' => $request->user()?->id,
                'cliente_id' => $id,
                'error' => $e->getMessage(),
            ]);

            if ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                throw $e;
            }

            return response()->json([
                'message' => 'Error al consultar cliente.',
            ], 500);
        }
    }

    /**
     * Crear un nuevo cliente.
     * - Superadmin: puede especificar empresa_id (opcional). Si no, usa la suya.
     * - Usuario normal: se asigna automáticamente su empresa.
     */
    public function store(Request $request)
    {
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
            'empresa_id' => 'nullable|integer|exists:empresas,id', // opcional
        ]);

        try {
            $user = $request->user();
            $esSuperadmin = $user->rol === 'superadmin';

            // Determinar empresa_id
            if ($esSuperadmin) {
                // Superadmin: usa el que envía o el suyo propio
                $empresaId = $validated['empresa_id'] ?? $user->empresa_id;
                if (!$empresaId) {
                    return response()->json([
                        'message' => 'El superadmin debe tener una empresa asociada o especificar empresa_id.',
                    ], 422);
                }
            } else {
                $empresaId = (int) $user->empresa_id;
                if ($empresaId <= 0) {
                    return response()->json([
                        'message' => 'El usuario no tiene una empresa asociada.',
                    ], 422);
                }
            }

            $data = [
                'empresa_id' => $empresaId,
                'nombre' => trim($validated['nombre']),
                'email' => $validated['email'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'direccion' => $validated['direccion'] ?? null,
                'rfc' => $validated['rfc'] ?? null,
                'tipo' => $validated['tipo'] ?? 'particular',
                'limite_credito' => $validated['limite_credito'] ?? 0,
                'notas' => $validated['notas'] ?? null,
                'activo' => array_key_exists('activo', $validated) ? (bool) $validated['activo'] : true,
            ];

            $cliente = Cliente::create($data);

            $this->registrarAuditoria(
                $request,
                'cliente.creado',
                'clientes',
                (int) $cliente->id,
                null,
                $cliente->toArray()
            );

            return response()->json([
                'message' => 'Cliente creado correctamente.',
                'cliente' => $cliente,
            ], 201);
        } catch (Throwable $e) {
            Log::error('Error al crear cliente.', [
                'usuario_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al crear cliente.',
            ], 500);
        }
    }

    /**
     * Actualizar un cliente.
     * - Superadmin: puede actualizar cualquier cliente.
     * - Usuario normal: solo si pertenece a su empresa.
     */
    public function update(Request $request, $id)
    {
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

        try {
            $user = $request->user();
            $esSuperadmin = $user->rol === 'superadmin';

            $query = Cliente::query();
            if (!$esSuperadmin) {
                $empresaId = (int) $user->empresa_id;
                $query->where('empresa_id', $empresaId);
            }

            $cliente = $query->findOrFail($id);
            $datosAntes = $cliente->toArray();

            $data = [
                'nombre' => trim($validated['nombre']),
                'email' => $validated['email'] ?? null,
                'telefono' => $validated['telefono'] ?? null,
                'direccion' => $validated['direccion'] ?? null,
                'rfc' => $validated['rfc'] ?? null,
                'tipo' => $validated['tipo'] ?? 'particular',
                'limite_credito' => $validated['limite_credito'] ?? 0,
                'notas' => $validated['notas'] ?? null,
            ];

            if (array_key_exists('activo', $validated)) {
                $data['activo'] = (bool) $validated['activo'];
            }

            $cliente->update($data);
            $cliente->refresh();

            $this->registrarAuditoria(
                $request,
                'cliente.actualizado',
                'clientes',
                (int) $cliente->id,
                $datosAntes,
                $cliente->toArray()
            );

            return response()->json([
                'message' => 'Cliente actualizado correctamente.',
                'cliente' => $cliente,
            ]);
        } catch (Throwable $e) {
            Log::error('Error al actualizar cliente.', [
                'usuario_id' => $request->user()?->id,
                'cliente_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al actualizar cliente.',
            ], 500);
        }
    }

    /**
     * Eliminar un cliente (soft delete).
     */
    public function destroy($id, Request $request)
    {
        try {
            $user = $request->user();
            $esSuperadmin = $user->rol === 'superadmin';

            $query = Cliente::query();
            if (!$esSuperadmin) {
                $empresaId = (int) $user->empresa_id;
                $query->where('empresa_id', $empresaId);
            }

            $cliente = $query->findOrFail($id);
            $datosAntes = $cliente->toArray();

            $cliente->delete();

            $this->registrarAuditoria(
                $request,
                'cliente.eliminado',
                'clientes',
                (int) $cliente->id,
                $datosAntes,
                null
            );

            return response()->json([
                'message' => 'Cliente eliminado correctamente.',
            ]);
        } catch (Throwable $e) {
            Log::error('Error al eliminar cliente.', [
                'usuario_id' => $request->user()?->id,
                'cliente_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al eliminar cliente.',
            ], 500);
        }
    }

    /**
     * Restaurar un cliente eliminado.
     */
    public function restore($id, Request $request)
    {
        try {
            $user = $request->user();
            $esSuperadmin = $user->rol === 'superadmin';

            $query = Cliente::withTrashed();
            if (!$esSuperadmin) {
                $empresaId = (int) $user->empresa_id;
                $query->where('empresa_id', $empresaId);
            }

            $cliente = $query->findOrFail($id);
            $datosAntes = $cliente->toArray();

            $cliente->restore();
            $cliente->refresh();

            $this->registrarAuditoria(
                $request,
                'cliente.restaurado',
                'clientes',
                (int) $cliente->id,
                $datosAntes,
                $cliente->toArray()
            );

            return response()->json([
                'message' => 'Cliente restaurado correctamente.',
            ]);
        } catch (Throwable $e) {
            Log::error('Error al restaurar cliente.', [
                'usuario_id' => $request->user()?->id,
                'cliente_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al restaurar cliente.',
            ], 500);
        }
    }

    /**
     * Obtener historial de compras de un cliente.
     */
    public function historial($id, Request $request)
    {
        $validated = $request->validate([
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        try {
            $user = $request->user();
            $esSuperadmin = $user->rol === 'superadmin';

            $query = Cliente::query();
            if (!$esSuperadmin) {
                $empresaId = (int) $user->empresa_id;
                $query->where('empresa_id', $empresaId);
            }

            $cliente = $query->findOrFail($id);

            $perPage = (int) ($validated['per_page'] ?? 20);
            $ventas = $cliente->ventas()
                ->with(['usuario', 'detalles.producto'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $this->registrarAuditoria(
                $request,
                'cliente.historial.consultado',
                'clientes',
                (int) $cliente->id,
                null,
                [
                    'cliente_id' => (int) $cliente->id,
                    'total' => $ventas->total(),
                    'pagina' => $ventas->currentPage(),
                    'por_pagina' => $ventas->perPage(),
                ]
            );

            return response()->json($ventas);
        } catch (Throwable $e) {
            Log::error('Error al consultar historial de cliente.', [
                'usuario_id' => $request->user()?->id,
                'cliente_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error al consultar historial del cliente.',
            ], 500);
        }
    }

    /**
     * Registrar auditoría de forma segura.
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
            $this->auditoriaService->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosDespues
            );
        } catch (Throwable $e) {
            Log::warning('No se pudo registrar auditoría.', [
                'accion' => $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
