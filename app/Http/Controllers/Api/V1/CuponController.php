<?php

// app/Http/Controllers/Api/V1/CuponController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cupon;
use App\Services\AuditoriaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CuponController extends Controller
{
    /**
     * Listar cupones
     */
    public function index(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado',
                'error' => 'AUTH_REQUIRED',
            ], 401);
        }

        $empresaId = $user->empresa_id;

        if (!$empresaId) {
            $this->auditarError(
                $request,
                'cupones.consulta_error',
                null,
                null,
                [
                    'motivo' => 'usuario_sin_empresa',
                ],
                null,
                $user->id
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asignada',
                'error' => 'EMPRESA_NO_ASIGNADA',
            ], 422);
        }

        try {
            $query = Cupon::query()
                ->where('empresa_id', $empresaId);

            /*
             * El agrupamiento de nombre/codigo es importante.
             * Sin este grupo, el OR podía ignorar empresa_id.
             */
            if ($request->filled('search')) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($q) use ($search) {
                    $q->where('nombre', 'LIKE', '%' . $search . '%')
                        ->orWhere('codigo', 'LIKE', '%' . $search . '%');
                });
            }

            if ($request->has('activo') && $request->input('activo') !== null) {
                $query->where('activo', $request->boolean('activo'));
            }

            if ($request->boolean('disponible')) {
                $query->disponibles();
            }

            $perPage = (int) ($request->input('per_page', 20));

            if ($perPage < 1) {
                $perPage = 20;
            }

            if ($perPage > 100) {
                $perPage = 100;
            }

            $cupones = $query
                ->orderByDesc('created_at')
                ->paginate($perPage)
                ->appends($request->query());

            $this->auditar(
                $request,
                'cupones.consultados',
                'cupones',
                null,
                null,
                [
                    'empresa_id' => $empresaId,
                    'search' => $request->input('search'),
                    'activo' => $request->input('activo'),
                    'disponible' => $request->boolean('disponible'),
                    'pagina' => $cupones->currentPage(),
                    'per_page' => $cupones->perPage(),
                    'total' => $cupones->total(),
                ],
                $empresaId,
                $user->id
            );

            return response()->json($cupones);

        } catch (QueryException $e) {
            Log::error('Error de base de datos listando cupones', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            $this->auditarError(
                $request,
                'cupones.consulta_error',
                'cupones',
                null,
                [
                    'motivo' => 'error_base_datos',
                ],
                $empresaId,
                $user->id
            );

            return response()->json([
                'message' => 'No fue posible consultar los cupones',
                'error' => 'DATABASE_ERROR',
            ], 500);

        } catch (Throwable $e) {
            Log::error('Error inesperado listando cupones', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->auditarError(
                $request,
                'cupones.consulta_error',
                'cupones',
                null,
                [
                    'motivo' => 'error_interno',
                ],
                $empresaId,
                $user->id
            );

            return response()->json([
                'message' => 'No fue posible consultar los cupones',
                'error' => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    /**
     * Crear cupón
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado',
                'error' => 'AUTH_REQUIRED',
            ], 401);
        }

        $empresaId = $user->empresa_id;

        if (!$empresaId) {
            $this->auditarError(
                $request,
                'cupon.creacion.rechazada',
                'cupones',
                null,
                [
                    'motivo' => 'usuario_sin_empresa',
                ],
                null,
                $user->id
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asignada',
                'error' => 'EMPRESA_NO_ASIGNADA',
            ], 422);
        }

        try {
            $validated = $request->validate([
                'codigo' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('cupones', 'codigo')
                        ->where(fn ($query) => $query->where('empresa_id', $empresaId)),
                ],
                'nombre' => 'required|string|max:255',
                'tipo' => 'required|in:porcentaje,monto_fijo',
                'valor' => 'required|numeric|min:0.01',
                'monto_minimo' => 'nullable|numeric|min:0',
                'uso_maximo' => 'nullable|integer|min:1',
                'uso_por_usuario' => 'nullable|integer|min:1',
                'fecha_inicio' => 'nullable|date',
                'fecha_fin' => 'nullable|date|after:fecha_inicio',
                'activo' => 'nullable|boolean',
            ]);

        } catch (ValidationException $e) {
            $this->auditarError(
                $request,
                'cupon.creacion.rechazada',
                'cupones',
                null,
                [
                    'motivo' => 'validacion',
                    'errores' => $e->errors(),
                ],
                $empresaId,
                $user->id
            );

            throw $e;
        }

        DB::beginTransaction();

        try {
            $codigo = strtoupper(trim($validated['codigo']));

            $cupon = Cupon::create([
                'empresa_id' => $empresaId,
                'codigo' => $codigo,
                'nombre' => $validated['nombre'],
                'tipo' => $validated['tipo'],
                'valor' => $validated['valor'],
                'monto_minimo' => $validated['monto_minimo'] ?? 0,
                'uso_maximo' => $validated['uso_maximo'] ?? null,
                'uso_por_usuario' => $validated['uso_por_usuario'] ?? null,
                'fecha_inicio' => $validated['fecha_inicio'] ?? null,
                'fecha_fin' => $validated['fecha_fin'] ?? null,
                'activo' => $validated['activo'] ?? true,
            ]);

            DB::commit();

            $this->auditar(
                $request,
                'cupon.creado',
                'cupones',
                (int) $cupon->id,
                null,
                $cupon->toArray(),
                $empresaId,
                $user->id
            );

            return response()->json([
                'message' => 'Cupón creado correctamente',
                'data' => $cupon,
            ], 201);

        } catch (QueryException $e) {
            DB::rollBack();

            Log::error('Error de base de datos creando cupón', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            $this->auditarError(
                $request,
                'cupon.creacion.error',
                'cupones',
                null,
                [
                    'motivo' => 'error_base_datos',
                    'codigo' => $request->input('codigo')
                        ? strtoupper(trim((string) $request->input('codigo')))
                        : null,
                ],
                $empresaId,
                $user->id
            );

            return response()->json([
                'message' => 'No fue posible crear el cupón',
                'error' => 'DATABASE_ERROR',
            ], 500);

        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Error inesperado creando cupón', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->auditarError(
                $request,
                'cupon.creacion.error',
                'cupones',
                null,
                [
                    'motivo' => 'error_interno',
                ],
                $empresaId,
                $user->id
            );

            return response()->json([
                'message' => 'No fue posible crear el cupón',
                'error' => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    /**
     * Actualizar cupón
     */
    public function update(Request $request, $id)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado',
                'error' => 'AUTH_REQUIRED',
            ], 401);
        }

        $empresaId = $user->empresa_id;

        if (!$empresaId) {
            $this->auditarError(
                $request,
                'cupon.actualizacion.rechazada',
                'cupones',
                is_numeric($id) ? (int) $id : null,
                [
                    'motivo' => 'usuario_sin_empresa',
                ],
                null,
                $user->id
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asignada',
                'error' => 'EMPRESA_NO_ASIGNADA',
            ], 422);
        }

        try {
            $cupon = Cupon::query()
                ->where('empresa_id', $empresaId)
                ->whereKey($id)
                ->first();

            if (!$cupon) {
                $this->auditarError(
                    $request,
                    'cupon.actualizacion.rechazada',
                    'cupones',
                    is_numeric($id) ? (int) $id : null,
                    [
                        'motivo' => 'cupon_no_encontrado',
                    ],
                    $empresaId,
                    $user->id
                );

                return response()->json([
                    'message' => 'Cupón no encontrado',
                    'error' => 'CUPON_NOT_FOUND',
                ], 404);
            }

            $datosAntes = $cupon->toArray();

            try {
                $validated = $request->validate([
                    'codigo' => [
                        'required',
                        'string',
                        'max:50',
                        Rule::unique('cupones', 'codigo')
                            ->ignore($cupon->id)
                            ->where(fn ($query) => $query->where('empresa_id', $empresaId)),
                    ],
                    'nombre' => 'required|string|max:255',
                    'tipo' => 'required|in:porcentaje,monto_fijo',
                    'valor' => 'required|numeric|min:0.01',
                    'monto_minimo' => 'nullable|numeric|min:0',
                    'uso_maximo' => 'nullable|integer|min:1',
                    'uso_por_usuario' => 'nullable|integer|min:1',
                    'fecha_inicio' => 'nullable|date',
                    'fecha_fin' => 'nullable|date|after:fecha_inicio',
                    'activo' => 'nullable|boolean',
                ]);

            } catch (ValidationException $e) {
                $this->auditarError(
                    $request,
                    'cupon.actualizacion.rechazada',
                    'cupones',
                    (int) $cupon->id,
                    [
                        'motivo' => 'validacion',
                        'errores' => $e->errors(),
                    ],
                    $empresaId,
                    $user->id
                );

                throw $e;
            }

            DB::beginTransaction();

            try {
                $cupon->update([
                    'codigo' => strtoupper(trim($validated['codigo'])),
                    'nombre' => $validated['nombre'],
                    'tipo' => $validated['tipo'],
                    'valor' => $validated['valor'],
                    'monto_minimo' => $validated['monto_minimo'] ?? 0,
                    'uso_maximo' => $validated['uso_maximo'] ?? null,
                    'uso_por_usuario' => $validated['uso_por_usuario'] ?? null,
                    'fecha_inicio' => $validated['fecha_inicio'] ?? null,
                    'fecha_fin' => $validated['fecha_fin'] ?? null,
                    'activo' => $validated['activo'] ?? true,
                ]);

                $cupon->refresh();

                DB::commit();

                $this->auditar(
                    $request,
                    'cupon.actualizado',
                    'cupones',
                    (int) $cupon->id,
                    $datosAntes,
                    $cupon->toArray(),
                    $empresaId,
                    $user->id
                );

                return response()->json([
                    'message' => 'Cupón actualizado correctamente',
                    'data' => $cupon,
                ]);

            } catch (QueryException $e) {
                DB::rollBack();

                Log::error('Error de base de datos actualizando cupón', [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'cupon_id' => $cupon->id,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);

                $this->auditarError(
                    $request,
                    'cupon.actualizacion.error',
                    'cupones',
                    (int) $cupon->id,
                    [
                        'motivo' => 'error_base_datos',
                    ],
                    $empresaId,
                    $user->id
                );

                return response()->json([
                    'message' => 'No fue posible actualizar el cupón',
                    'error' => 'DATABASE_ERROR',
                ], 500);

            } catch (Throwable $e) {
                DB::rollBack();

                Log::error('Error inesperado actualizando cupón', [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'cupon_id' => $cupon->id,
                    'error' => $e->getMessage(),
                ]);

                $this->auditarError(
                    $request,
                    'cupon.actualizacion.error',
                    'cupones',
                    (int) $cupon->id,
                    [
                        'motivo' => 'error_interno',
                    ],
                    $empresaId,
                    $user->id
                );

                return response()->json([
                    'message' => 'No fue posible actualizar el cupón',
                    'error' => 'INTERNAL_ERROR',
                ], 500);
            }

        } catch (QueryException $e) {
            Log::error('Error de base de datos buscando cupón para actualizar', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'cupon_id' => $id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'message' => 'No fue posible consultar el cupón',
                'error' => 'DATABASE_ERROR',
            ], 500);

        } catch (Throwable $e) {
            Log::error('Error inesperado actualizando cupón', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'cupon_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No fue posible actualizar el cupón',
                'error' => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    /**
     * Eliminar cupón
     */
    public function destroy($id, Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado',
                'error' => 'AUTH_REQUIRED',
            ], 401);
        }

        $empresaId = $user->empresa_id;

        if (!$empresaId) {
            $this->auditarError(
                $request,
                'cupon.eliminacion.rechazada',
                'cupones',
                is_numeric($id) ? (int) $id : null,
                [
                    'motivo' => 'usuario_sin_empresa',
                ],
                null,
                $user->id
            );

            return response()->json([
                'message' => 'El usuario no tiene una empresa asignada',
                'error' => 'EMPRESA_NO_ASIGNADA',
            ], 422);
        }

        try {
            $cupon = Cupon::query()
                ->where('empresa_id', $empresaId)
                ->whereKey($id)
                ->first();

            if (!$cupon) {
                $this->auditarError(
                    $request,
                    'cupon.eliminacion.rechazada',
                    'cupones',
                    is_numeric($id) ? (int) $id : null,
                    [
                        'motivo' => 'cupon_no_encontrado',
                    ],
                    $empresaId,
                    $user->id
                );

                return response()->json([
                    'message' => 'Cupón no encontrado',
                    'error' => 'CUPON_NOT_FOUND',
                ], 404);
            }

            $datosAntes = $cupon->toArray();

            DB::beginTransaction();

            try {
                $cupon->delete();

                DB::commit();

                $this->auditar(
                    $request,
                    'cupon.eliminado',
                    'cupones',
                    (int) $cupon->id,
                    $datosAntes,
                    null,
                    $empresaId,
                    $user->id
                );

                return response()->json([
                    'message' => 'Cupón eliminado correctamente',
                ]);

            } catch (QueryException $e) {
                DB::rollBack();

                Log::error('Error de base de datos eliminando cupón', [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'cupon_id' => $cupon->id,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                ]);

                $this->auditarError(
                    $request,
                    'cupon.eliminacion.error',
                    'cupones',
                    (int) $cupon->id,
                    [
                        'motivo' => 'error_base_datos',
                    ],
                    $empresaId,
                    $user->id
                );

                return response()->json([
                    'message' => 'No fue posible eliminar el cupón',
                    'error' => 'DATABASE_ERROR',
                ], 500);

            } catch (Throwable $e) {
                DB::rollBack();

                Log::error('Error inesperado eliminando cupón', [
                    'empresa_id' => $empresaId,
                    'usuario_id' => $user->id,
                    'cupon_id' => $cupon->id,
                    'error' => $e->getMessage(),
                ]);

                $this->auditarError(
                    $request,
                    'cupon.eliminacion.error',
                    'cupones',
                    (int) $cupon->id,
                    [
                        'motivo' => 'error_interno',
                    ],
                    $empresaId,
                    $user->id
                );

                return response()->json([
                    'message' => 'No fue posible eliminar el cupón',
                    'error' => 'INTERNAL_ERROR',
                ], 500);
            }

        } catch (QueryException $e) {
            Log::error('Error de base de datos buscando cupón para eliminar', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'cupon_id' => $id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            return response()->json([
                'message' => 'No fue posible consultar el cupón',
                'error' => 'DATABASE_ERROR',
            ], 500);

        } catch (Throwable $e) {
            Log::error('Error inesperado eliminando cupón', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'cupon_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'No fue posible eliminar el cupón',
                'error' => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    /**
     * Validar cupón
     */
    public function validar(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'No autenticado',
                'error' => 'AUTH_REQUIRED',
            ], 401);
        }

        $empresaId = $user->empresa_id;

        if (!$empresaId) {
            $this->auditarError(
                $request,
                'cupon.validacion.fallida',
                'cupones',
                null,
                [
                    'motivo' => 'usuario_sin_empresa',
                ],
                null,
                $user->id
            );

            return response()->json([
                'valido' => false,
                'message' => 'El usuario no tiene una empresa asignada',
                'error' => 'EMPRESA_NO_ASIGNADA',
            ], 422);
        }

        try {
            $validated = $request->validate([
                'codigo' => 'required|string',
                'subtotal' => 'required|numeric|min:0',
            ]);

        } catch (ValidationException $e) {
            $this->auditarError(
                $request,
                'cupon.validacion.fallida',
                'cupones',
                null,
                [
                    'motivo' => 'validacion',
                    'errores' => $e->errors(),
                ],
                $empresaId,
                $user->id
            );

            throw $e;
        }

        try {
            $codigo = strtoupper(trim($validated['codigo']));
            $subtotal = $validated['subtotal'];

            $cupon = Cupon::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', $codigo)
                ->first();

            if (!$cupon) {
                $this->auditar(
                    $request,
                    'cupon.validacion.fallida',
                    'cupones',
                    null,
                    null,
                    [
                        'codigo' => $codigo,
                        'subtotal' => $subtotal,
                        'motivo' => 'cupon_no_encontrado',
                    ],
                    $empresaId,
                    $user->id
                );

                return response()->json([
                    'valido' => false,
                    'message' => 'Cupón no encontrado',
                ], 404);
            }

            if (!$cupon->estaActivo()) {
                $this->auditar(
                    $request,
                    'cupon.validacion.fallida',
                    'cupones',
                    (int) $cupon->id,
                    null,
                    [
                        'codigo' => $cupon->codigo,
                        'subtotal' => $subtotal,
                        'motivo' => 'cupon_inactivo_o_expirado',
                    ],
                    $empresaId,
                    $user->id
                );

                return response()->json([
                    'valido' => false,
                    'message' => 'El cupón no está activo o ha expirado',
                ], 422);
            }

            $descuento = $cupon->getDescuento($subtotal);

            if ($descuento == 0) {
                $this->auditar(
                    $request,
                    'cupon.validacion.fallida',
                    'cupones',
                    (int) $cupon->id,
                    null,
                    [
                        'codigo' => $cupon->codigo,
                        'subtotal' => $subtotal,
                        'motivo' => 'cupon_no_aplica',
                    ],
                    $empresaId,
                    $user->id
                );

                return response()->json([
                    'valido' => false,
                    'message' => 'El cupón no aplica para este monto',
                ], 422);
            }

            $this->auditar(
                $request,
                'cupon.validado',
                'cupones',
                (int) $cupon->id,
                null,
                [
                    'codigo' => $cupon->codigo,
                    'subtotal' => $subtotal,
                    'descuento' => $descuento,
                    'valido' => true,
                ],
                $empresaId,
                $user->id
            );

            return response()->json([
                'valido' => true,
                'data' => $cupon,
                'descuento' => $descuento,
                'message' => 'Cupón válido',
            ]);

        } catch (QueryException $e) {
            Log::error('Error de base de datos validando cupón', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);

            $this->auditarError(
                $request,
                'cupon.validacion.error',
                'cupones',
                null,
                [
                    'motivo' => 'error_base_datos',
                ],
                $empresaId,
                $user->id
            );

            return response()->json([
                'valido' => false,
                'message' => 'No fue posible validar el cupón',
                'error' => 'DATABASE_ERROR',
            ], 500);

        } catch (Throwable $e) {
            Log::error('Error inesperado validando cupón', [
                'empresa_id' => $empresaId,
                'usuario_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            $this->auditarError(
                $request,
                'cupon.validacion.error',
                'cupones',
                null,
                [
                    'motivo' => 'error_interno',
                ],
                $empresaId,
                $user->id
            );

            return response()->json([
                'valido' => false,
                'message' => 'No fue posible validar el cupón',
                'error' => 'INTERNAL_ERROR',
            ], 500);
        }
    }

    /**
     * Registrar auditoría sin permitir que una falla
     * del sistema de auditoría rompa la operación principal.
     */
    private function auditar(
        Request $request,
        string $accion,
        ?string $tabla,
        ?int $registroId,
        ?array $datosAntes,
        ?array $datosDespues,
        ?int $empresaId,
        ?int $usuarioId
    ): void {
        try {
            app(AuditoriaService::class)->registrar(
                $accion,
                $tabla,
                $registroId,
                $datosAntes,
                $datosDespues,
                $request,
                $empresaId,
                $usuarioId
            );
        } catch (Throwable $e) {
            Log::warning('No fue posible registrar auditoría de cupón', [
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
     * Registrar auditoría de errores/rechazos.
     */
    private function auditarError(
        Request $request,
        string $accion,
        ?string $tabla,
        ?int $registroId,
        ?array $datos,
        ?int $empresaId,
        ?int $usuarioId
    ): void {
        $this->auditar(
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
