<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Services\AuditoriaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class ClienteController extends Controller
{
    public function __construct(
        private readonly AuditoriaService $auditoriaService
    ) {}

    /**
     * Listar clientes con filtros.
     *
     * - Superadmin: puede consultar todos los clientes.
     * - Superadmin: puede filtrar por empresa_id.
     * - Usuario normal: solamente clientes de su empresa.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        try {
            $validated = $request->validate([
                'search' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
                'tipo' => [
                    'nullable',
                    'in:particular,empresa',
                ],
                'activo' => [
                    'nullable',
                    'boolean',
                ],
                'per_page' => [
                    'nullable',
                    'integer',
                    'min:1',
                    'max:100',
                ],
                'empresa_id' => [
                    'nullable',
                    'integer',
                    'exists:empresas,id',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaValidacion(
                $request,
                'clientes.consultados_validacion_rechazada',
                'clientes',
                null,
                [
                    'errores' => $e->errors(),
                ]
            );

            throw $e;
        }

        try {
            $esSuperadmin = $user->rol === 'superadmin';

            $query = Cliente::query();

            /*
             * Aislamiento por empresa.
             *
             * El superadmin puede consultar todas las empresas
             * o filtrar explícitamente por una empresa.
             */
            if (!$esSuperadmin) {
                $empresaId = (int) $user->empresa_id;

                if ($empresaId <= 0) {
                    $this->registrarAuditoriaValidacion(
                        $request,
                        'clientes.consultados_empresa_invalida',
                        'clientes',
                        null,
                        [
                            'empresa_id' => $empresaId,
                        ]
                    );

                    return response()->json([
                        'message' => 'El usuario no tiene una empresa asociada.',
                    ], 422);
                }

                $query->where('empresa_id', $empresaId);
            } elseif (
                array_key_exists('empresa_id', $validated)
                && $validated['empresa_id'] !== null
            ) {
                $query->where(
                    'empresa_id',
                    (int) $validated['empresa_id']
                );
            }

            /*
             * Búsqueda.
             *
             * Los valores son enviados como bindings por Eloquent.
             * No se concatena SQL directamente.
             */
            if (
                array_key_exists('search', $validated)
                && $validated['search'] !== null
                && trim($validated['search']) !== ''
            ) {
                $search = trim($validated['search']);
                $like = '%' . $search . '%';

                $query->where(function ($q) use ($like) {
                    $q->where('nombre', 'like', $like)
                        ->orWhere('email', 'like', $like)
                        ->orWhere('telefono', 'like', $like)
                        ->orWhere('rfc', 'like', $like);
                });
            }

            if (
                array_key_exists('tipo', $validated)
                && $validated['tipo'] !== null
                && $validated['tipo'] !== ''
            ) {
                $query->where(
                    'tipo',
                    $validated['tipo']
                );
            }

            if (
                array_key_exists('activo', $validated)
                && $validated['activo'] !== null
            ) {
                $query->where(
                    'activo',
                    (bool) $validated['activo']
                );
            }

            $perPage = (int) ($validated['per_page'] ?? 20);

            $clientes = $query
                ->orderBy('nombre', 'asc')
                ->paginate($perPage)
                ->appends($request->query());

            $empresaAuditada = $esSuperadmin
                ? (
                    array_key_exists('empresa_id', $validated)
                    && $validated['empresa_id'] !== null
                        ? (int) $validated['empresa_id']
                        : null
                )
                : (int) $user->empresa_id;

            Log::info('Clientes obtenidos.', [
                'total' => $clientes->total(),
                'usuario_id' => (int) $user->id,
                'usuario_rol' => $user->rol,
                'empresa_id' => $empresaAuditada ?? 'todas',
            ]);

            $this->registrarAuditoria(
                $request,
                'clientes.consultados',
                'clientes',
                null,
                null,
                [
                    'usuario_rol' => $user->rol,
                    'empresa_id' => $empresaAuditada,
                    'todas_las_empresas' => $esSuperadmin
                        && $empresaAuditada === null,
                    'search' => $validated['search'] ?? null,
                    'tipo' => $validated['tipo'] ?? null,
                    'activo' => $validated['activo'] ?? null,
                    'pagina' => $clientes->currentPage(),
                    'por_pagina' => $clientes->perPage(),
                    'total' => $clientes->total(),
                ],
                $empresaAuditada,
                (int) $user->id
            );

            return response()->json([
                'data' => $clientes->items(),
                'current_page' => $clientes->currentPage(),
                'last_page' => $clientes->lastPage(),
                'per_page' => $clientes->perPage(),
                'total' => $clientes->total(),
            ]);
        } catch (QueryException $e) {
            Log::error('Error de base de datos al listar clientes.', [
                'usuario_id' => $user->id,
                'empresa_id' => $user->empresa_id,
                'error' => $e->getMessage(),
                'codigo' => $e->getCode(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'clientes.consultados_error_bd',
                'clientes',
                null,
                [
                    'error' => $e->getMessage(),
                    'codigo' => $e->getCode(),
                ],
                $user->rol === 'superadmin'
                    ? null
                    : (int) $user->empresa_id,
                (int) $user->id
            );

            return response()->json([
                'message' => 'No fue posible consultar los clientes en este momento.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error interno al listar clientes.', [
                'usuario_id' => $user->id,
                'empresa_id' => $user->empresa_id,
                'error' => $e->getMessage(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'clientes.consultados_error',
                'clientes',
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $user->rol === 'superadmin'
                    ? null
                    : (int) $user->empresa_id,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Error interno al cargar clientes.',
            ], 500);
        }
    }

    /**
     * Obtener un cliente específico.
     */
    public function show($id, Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        try {
            $esSuperadmin = $user->rol === 'superadmin';

            $query = Cliente::query();

            if (!$esSuperadmin) {
                $empresaId = (int) $user->empresa_id;

                if ($empresaId <= 0) {
                    return response()->json([
                        'message' => 'El usuario no tiene una empresa asociada.',
                    ], 422);
                }

                $query->where('empresa_id', $empresaId);
            }

            $cliente = $query->find($id);

            if (!$cliente) {
                $this->registrarAuditoria(
                    $request,
                    'cliente.consultado_no_encontrado',
                    'clientes',
                    is_numeric($id) ? (int) $id : null,
                    null,
                    [
                        'cliente_id' => is_numeric($id)
                            ? (int) $id
                            : null,
                    ],
                    $esSuperadmin
                        ? null
                        : (int) $user->empresa_id,
                    (int) $user->id
                );

                return response()->json([
                    'message' => 'Cliente no encontrado.',
                ], 404);
            }

            $this->registrarAuditoria(
                $request,
                'cliente.consultado',
                'clientes',
                (int) $cliente->id,
                null,
                $cliente->toArray(),
                (int) $cliente->empresa_id,
                (int) $user->id
            );

            return response()->json($cliente);
        } catch (QueryException $e) {
            Log::error('Error de base de datos al consultar cliente.', [
                'usuario_id' => $user->id,
                'cliente_id' => $id,
                'error' => $e->getMessage(),
                'codigo' => $e->getCode(),
            ]);

            return response()->json([
                'message' => 'No fue posible consultar el cliente en este momento.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error interno al consultar cliente.', [
                'usuario_id' => $user->id,
                'cliente_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Error interno al consultar cliente.',
            ], 500);
        }
    }

    /**
     * Crear un nuevo cliente.
     *
     * - Superadmin: puede especificar empresa_id.
     * - Usuario normal: empresa del usuario autenticado.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        try {
            $validated = $request->validate([
                'nombre' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'email' => [
                    'nullable',
                    'email',
                    'max:255',
                ],
                'telefono' => [
                    'nullable',
                    'string',
                    'max:20',
                ],
                'direccion' => [
                    'nullable',
                    'string',
                ],
                'rfc' => [
                    'nullable',
                    'string',
                    'max:13',
                ],
                'tipo' => [
                    'nullable',
                    'in:particular,empresa',
                ],
                'limite_credito' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],
                'notas' => [
                    'nullable',
                    'string',
                ],
                'activo' => [
                    'nullable',
                    'boolean',
                ],
                'empresa_id' => [
                    'nullable',
                    'integer',
                    'exists:empresas,id',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaValidacion(
                $request,
                'cliente.creado_validacion_rechazada',
                'clientes',
                null,
                [
                    'errores' => $e->errors(),
                ]
            );

            throw $e;
        }

        try {
            $esSuperadmin = $user->rol === 'superadmin';

            /*
             * Determinar empresa afectada.
             */
            if ($esSuperadmin) {
                $empresaId = array_key_exists('empresa_id', $validated)
                    && $validated['empresa_id'] !== null
                    ? (int) $validated['empresa_id']
                    : (int) $user->empresa_id;

                if ($empresaId <= 0) {
                    $this->registrarAuditoriaValidacion(
                        $request,
                        'cliente.creado_empresa_invalida',
                        'clientes',
                        null,
                        [
                            'empresa_id' => $empresaId,
                        ]
                    );

                    return response()->json([
                        'message' => 'El superadmin debe tener una empresa asociada o especificar empresa_id.',
                    ], 422);
                }
            } else {
                $empresaId = (int) $user->empresa_id;

                if ($empresaId <= 0) {
                    $this->registrarAuditoriaValidacion(
                        $request,
                        'cliente.creado_empresa_invalida',
                        'clientes',
                        null,
                        [
                            'empresa_id' => $empresaId,
                        ]
                    );

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
                'activo' => array_key_exists('activo', $validated)
                    ? (bool) $validated['activo']
                    : true,
            ];

            $cliente = Cliente::create($data);

            $this->registrarAuditoria(
                $request,
                'cliente.creado',
                'clientes',
                (int) $cliente->id,
                null,
                $cliente->toArray(),
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Cliente creado correctamente.',
                'cliente' => $cliente,
            ], 201);
        } catch (QueryException $e) {
            Log::error('Error de base de datos al crear cliente.', [
                'usuario_id' => $user->id,
                'empresa_id' => $empresaId ?? null,
                'error' => $e->getMessage(),
                'codigo' => $e->getCode(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'cliente.creado_error_bd',
                'clientes',
                null,
                [
                    'error' => $e->getMessage(),
                    'codigo' => $e->getCode(),
                ],
                $empresaId ?? null,
                (int) $user->id
            );

            return response()->json([
                'message' => 'No fue posible guardar el cliente en este momento.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error interno al crear cliente.', [
                'usuario_id' => $user->id,
                'empresa_id' => $empresaId ?? null,
                'error' => $e->getMessage(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'cliente.creado_error',
                'clientes',
                null,
                [
                    'error' => $e->getMessage(),
                ],
                $empresaId ?? null,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Error interno al crear cliente.',
            ], 500);
        }
    }

    /**
     * Actualizar un cliente.
     *
     * - Superadmin: puede actualizar cualquier cliente.
     * - Usuario normal: solamente si pertenece a su empresa.
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        try {
            $validated = $request->validate([
                'nombre' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'email' => [
                    'nullable',
                    'email',
                    'max:255',
                ],
                'telefono' => [
                    'nullable',
                    'string',
                    'max:20',
                ],
                'direccion' => [
                    'nullable',
                    'string',
                ],
                'rfc' => [
                    'nullable',
                    'string',
                    'max:13',
                ],
                'tipo' => [
                    'nullable',
                    'in:particular,empresa',
                ],
                'limite_credito' => [
                    'nullable',
                    'numeric',
                    'min:0',
                ],
                'notas' => [
                    'nullable',
                    'string',
                ],
                'activo' => [
                    'nullable',
                    'boolean',
                ],
            ]);
        } catch (ValidationException $e) {
            $this->registrarAuditoriaValidacion(
                $request,
                'cliente.actualizado_validacion_rechazada',
                'clientes',
                is_numeric($id) ? (int) $id : null,
                [
                    'errores' => $e->errors(),
                ]
            );

            throw $e;
        }

        try {
            $esSuperadmin = $user->rol === 'superadmin';

            $query = Cliente::query();

            if (!$esSuperadmin) {
                $empresaId = (int) $user->empresa_id;

                if ($empresaId <= 0) {
                    return response()->json([
                        'message' => 'El usuario no tiene una empresa asociada.',
                    ], 422);
                }

                $query->where('empresa_id', $empresaId);
            }

            $cliente = $query->find($id);

            if (!$cliente) {
                $this->registrarAuditoria(
                    $request,
                    'cliente.actualizado_no_encontrado',
                    'clientes',
                    is_numeric($id) ? (int) $id : null,
                    null,
                    [
                        'cliente_id' => is_numeric($id)
                            ? (int) $id
                            : null,
                    ],
                    $esSuperadmin
                        ? null
                        : (int) $user->empresa_id,
                    (int) $user->id
                );

                return response()->json([
                    'message' => 'Cliente no encontrado.',
                ], 404);
            }

            $empresaId = (int) $cliente->empresa_id;
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

            /*
             * No permitimos modificar empresa_id desde update.
             *
             * La empresa del cliente permanece asociada al registro.
             */
            $cliente->update($data);
            $cliente->refresh();

            $this->registrarAuditoria(
                $request,
                'cliente.actualizado',
                'clientes',
                (int) $cliente->id,
                $datosAntes,
                $cliente->toArray(),
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Cliente actualizado correctamente.',
                'cliente' => $cliente,
            ]);
        } catch (QueryException $e) {
            Log::error('Error de base de datos al actualizar cliente.', [
                'usuario_id' => $user->id,
                'cliente_id' => $id,
                'empresa_id' => $user->empresa_id,
                'error' => $e->getMessage(),
                'codigo' => $e->getCode(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'cliente.actualizado_error_bd',
                'clientes',
                is_numeric($id) ? (int) $id : null,
                [
                    'error' => $e->getMessage(),
                    'codigo' => $e->getCode(),
                ],
                $user->rol === 'superadmin'
                    ? null
                    : (int) $user->empresa_id,
                (int) $user->id
            );

            return response()->json([
                'message' => 'No fue posible actualizar el cliente en este momento.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error interno al actualizar cliente.', [
                'usuario_id' => $user->id,
                'cliente_id' => $id,
                'error' => $e->getMessage(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'cliente.actualizado_error',
                'clientes',
                is_numeric($id) ? (int) $id : null,
                [
                    'error' => $e->getMessage(),
                ],
                $user->rol === 'superadmin'
                    ? null
                    : (int) $user->empresa_id,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Error interno al actualizar cliente.',
            ], 500);
        }
    }

    /**
     * Eliminar un cliente (soft delete).
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        try {
            $esSuperadmin = $user->rol === 'superadmin';

            $query = Cliente::query();

            if (!$esSuperadmin) {
                $empresaId = (int) $user->empresa_id;

                if ($empresaId <= 0) {
                    return response()->json([
                        'message' => 'El usuario no tiene una empresa asociada.',
                    ], 422);
                }

                $query->where('empresa_id', $empresaId);
            }

            $cliente = $query->find($id);

            if (!$cliente) {
                $this->registrarAuditoria(
                    $request,
                    'cliente.eliminado_no_encontrado',
                    'clientes',
                    is_numeric($id) ? (int) $id : null,
                    null,
                    [
                        'cliente_id' => is_numeric($id)
                            ? (int) $id
                            : null,
                    ],
                    $esSuperadmin
                        ? null
                        : (int) $user->empresa_id,
                    (int) $user->id
                );

                return response()->json([
                    'message' => 'Cliente no encontrado.',
                ], 404);
            }

            $empresaId = (int) $cliente->empresa_id;
            $datosAntes = $cliente->toArray();

            $cliente->delete();

            $this->registrarAuditoria(
                $request,
                'cliente.eliminado',
                'clientes',
                (int) $cliente->id,
                $datosAntes,
                null,
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Cliente eliminado correctamente.',
            ]);
        } catch (QueryException $e) {
            Log::error('Error de base de datos al eliminar cliente.', [
                'usuario_id' => $user->id,
                'cliente_id' => $id,
                'error' => $e->getMessage(),
                'codigo' => $e->getCode(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'cliente.eliminado_error_bd',
                'clientes',
                is_numeric($id) ? (int) $id : null,
                [
                    'error' => $e->getMessage(),
                    'codigo' => $e->getCode(),
                ],
                $user->rol === 'superadmin'
                    ? null
                    : (int) $user->empresa_id,
                (int) $user->id
            );

            return response()->json([
                'message' => 'No fue posible eliminar el cliente en este momento.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error interno al eliminar cliente.', [
                'usuario_id' => $user->id,
                'cliente_id' => $id,
                'error' => $e->getMessage(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'cliente.eliminado_error',
                'clientes',
                is_numeric($id) ? (int) $id : null,
                [
                    'error' => $e->getMessage(),
                ],
                $user->rol === 'superadmin'
                    ? null
                    : (int) $user->empresa_id,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Error interno al eliminar cliente.',
            ], 500);
        }
    }

    /**
     * Restaurar un cliente eliminado.
     */
    public function restore($id, Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
            ], 401);
        }

        try {
            $esSuperadmin = $user->rol === 'superadmin';

            $query = Cliente::withTrashed();

            if (!$esSuperadmin) {
                $empresaId = (int) $user->empresa_id;

                if ($empresaId <= 0) {
                    return response()->json([
                        'message' => 'El usuario no tiene una empresa asociada.',
                    ], 422);
                }

                $query->where('empresa_id', $empresaId);
            }

            $cliente = $query->find($id);

            if (!$cliente) {
                $this->registrarAuditoria(
                    $request,
                    'cliente.restaurado_no_encontrado',
                    'clientes',
                    is_numeric($id) ? (int) $id : null,
                    null,
                    [
                        'cliente_id' => is_numeric($id)
                            ? (int) $id
                            : null,
                    ],
                    $esSuperadmin
                        ? null
                        : (int) $user->empresa_id,
                    (int) $user->id
                );

                return response()->json([
                    'message' => 'Cliente eliminado no encontrado.',
                ], 404);
            }

            /*
             * Si ya está activo, no se considera una restauración válida.
             */
            if ($cliente->deleted_at === null) {
                return response()->json([
                    'message' => 'El cliente no está eliminado.',
                ], 422);
            }

            $empresaId = (int) $cliente->empresa_id;
            $datosAntes = $cliente->toArray();

            $cliente->restore();
            $cliente->refresh();

            $this->registrarAuditoria(
                $request,
                'cliente.restaurado',
                'clientes',
                (int) $cliente->id,
                $datosAntes,
                $cliente->toArray(),
                $empresaId,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Cliente restaurado correctamente.',
            ]);
        } catch (QueryException $e) {
            Log::error('Error de base de datos al restaurar cliente.', [
                'usuario_id' => $user->id,
                'cliente_id' => $id,
                'error' => $e->getMessage(),
                'codigo' => $e->getCode(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'cliente.restaurado_error_bd',
                'clientes',
                is_numeric($id) ? (int) $id : null,
                [
                    'error' => $e->getMessage(),
                    'codigo' => $e->getCode(),
                ],
                $user->rol === 'superadmin'
                    ? null
                    : (int) $user->empresa_id,
                (int) $user->id
            );

            return response()->json([
                'message' => 'No fue posible restaurar el cliente en este momento.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error interno al restaurar cliente.', [
                'usuario_id' => $user->id,
                'cliente_id' => $id,
                'error' => $e->getMessage(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'cliente.restaurado_error',
                'clientes',
                is_numeric($id) ? (int) $id : null,
                [
                    'error' => $e->getMessage(),
                ],
                $user->rol === 'superadmin'
                    ? null
                    : (int) $user->empresa_id,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Error interno al restaurar cliente.',
            ], 500);
        }
    }

    /**
     * Obtener historial de compras de un cliente.
     */
    public function historial($id, Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Usuario no autenticado.',
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
            $this->registrarAuditoriaValidacion(
                $request,
                'cliente.historial.consultado_validacion_rechazada',
                'clientes',
                is_numeric($id) ? (int) $id : null,
                [
                    'errores' => $e->errors(),
                ]
            );

            throw $e;
        }

        try {
            $esSuperadmin = $user->rol === 'superadmin';

            $query = Cliente::query();

            if (!$esSuperadmin) {
                $empresaId = (int) $user->empresa_id;

                if ($empresaId <= 0) {
                    return response()->json([
                        'message' => 'El usuario no tiene una empresa asociada.',
                    ], 422);
                }

                $query->where('empresa_id', $empresaId);
            }

            $cliente = $query->find($id);

            if (!$cliente) {
                $this->registrarAuditoria(
                    $request,
                    'cliente.historial_no_encontrado',
                    'clientes',
                    is_numeric($id) ? (int) $id : null,
                    null,
                    [
                        'cliente_id' => is_numeric($id)
                            ? (int) $id
                            : null,
                    ],
                    $esSuperadmin
                        ? null
                        : (int) $user->empresa_id,
                    (int) $user->id
                );

                return response()->json([
                    'message' => 'Cliente no encontrado.',
                ], 404);
            }

            $empresaId = (int) $cliente->empresa_id;
            $perPage = (int) ($validated['per_page'] ?? 20);

            /*
             * La relación del cliente mantiene la empresa del
             * cliente como origen del historial.
             *
             * Los modelos relacionados se cargan mediante Eloquent,
             * utilizando consultas parametrizadas internamente.
             */
            $ventas = $cliente->ventas()
                ->with([
                    'usuario',
                    'detalles.producto',
                ])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage)
                ->appends($request->query());

            $this->registrarAuditoria(
                $request,
                'cliente.historial.consultado',
                'clientes',
                (int) $cliente->id,
                null,
                [
                    'cliente_id' => (int) $cliente->id,
                    'empresa_id' => $empresaId,
                    'total' => $ventas->total(),
                    'pagina' => $ventas->currentPage(),
                    'por_pagina' => $ventas->perPage(),
                ],
                $empresaId,
                (int) $user->id
            );

            return response()->json($ventas);
        } catch (QueryException $e) {
            Log::error('Error de base de datos al consultar historial de cliente.', [
                'usuario_id' => $user->id,
                'cliente_id' => $id,
                'error' => $e->getMessage(),
                'codigo' => $e->getCode(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'cliente.historial_error_bd',
                'clientes',
                is_numeric($id) ? (int) $id : null,
                [
                    'error' => $e->getMessage(),
                    'codigo' => $e->getCode(),
                ],
                $user->rol === 'superadmin'
                    ? null
                    : (int) $user->empresa_id,
                (int) $user->id
            );

            return response()->json([
                'message' => 'No fue posible consultar el historial del cliente en este momento.',
            ], 500);
        } catch (Throwable $e) {
            Log::error('Error interno al consultar historial de cliente.', [
                'usuario_id' => $user->id,
                'cliente_id' => $id,
                'error' => $e->getMessage(),
            ]);

            $this->registrarAuditoriaError(
                $request,
                'cliente.historial_error',
                'clientes',
                is_numeric($id) ? (int) $id : null,
                [
                    'error' => $e->getMessage(),
                ],
                $user->rol === 'superadmin'
                    ? null
                    : (int) $user->empresa_id,
                (int) $user->id
            );

            return response()->json([
                'message' => 'Error interno al consultar historial del cliente.',
            ], 500);
        }
    }

    /**
     * Registrar auditoría de forma segura.
     *
     * Un fallo del sistema de auditoría no debe romper
     * una operación que ya fue completada.
     *
     * $empresaId:
     * empresa realmente afectada por la operación.
     *
     * $usuarioId:
     * actor autenticado que realizó la acción.
     */
    private function registrarAuditoria(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues,
        ?int $empresaId = null,
        ?int $usuarioId = null
    ): void {
        try {
            $this->auditoriaService->registrar(
                $request,
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosDespues,
                $empresaId,
                $usuarioId
            );
        } catch (Throwable $e) {
            Log::warning('No se pudo registrar auditoría.', [
                'accion' => $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'empresa_id' => $empresaId,
                'usuario_id' => $usuarioId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Registrar validaciones rechazadas.
     */
    private function registrarAuditoriaValidacion(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        ?array $datos
    ): void {
        $user = $request->user();

        $empresaId = $user
            ? (
                $user->rol === 'superadmin'
                    ? null
                    : (int) $user->empresa_id
            )
            : null;

        $usuarioId = $user
            ? (int) $user->id
            : null;

        $this->registrarAuditoria(
            $request,
            $accion,
            $tabla,
            $registroId,
            null,
            $datos,
            $empresaId,
            $usuarioId
        );
    }

    /**
     * Registrar errores internos.
     *
     * Nunca lanza una excepción propia para evitar
     * encadenar un segundo error durante el manejo
     * del error original.
     */
    private function registrarAuditoriaError(
        Request $request,
        string $accion,
        string $tabla,
        ?int $registroId,
        array $datos,
        ?int $empresaId,
        ?int $usuarioId
    ): void {
        $this->registrarAuditoria(
            $request,
            $accion,
            $tabla,
            $registroId,
            null,
            $datos,
            $empresaId,
            $usuarioId
        );
    }
}
